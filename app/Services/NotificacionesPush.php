<?php

namespace App\Services;

use App\Models\AlertaSeguridad;
use App\Models\Condomino;
use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\PushSuscripcion;
use App\Models\Usuario;
use App\Models\Visita;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Notificaciones push: llegan al celular aunque la app esté cerrada.
 *
 * Se mandan DESPUÉS de responder la petición (afterResponse), así quien
 * registra una visita o envía un mensaje no espera a los servicios de
 * notificaciones de Google / Apple / Mozilla.
 *
 * El "tag" es el mismo que usa el aviso dentro de la app (usePulso.js):
 * si llegan los dos, el celular muestra uno solo.
 */
class NotificacionesPush
{
    private const STAFF_ALERTAS = ['VIGILANCIA', 'MANTENIMIENTO', 'LECTOR', 'CAPTURISTA', 'ADMINISTRADOR', 'AUDITOR', 'SUPERUSUARIO', 'CONDOMINO'];
    private const STAFF_MENSAJES = ['ADMINISTRADOR', 'AUDITOR', 'SUPERUSUARIO'];

    public static function configurado(): bool
    {
        return (bool) config('services.webpush.public_key') && (bool) config('services.webpush.private_key');
    }

    // ============================================================
    // Eventos
    // ============================================================

    /** Llegó alguien a caseta y la casa debe autorizar. */
    public function visitaEnCaseta(Visita $visita): void
    {
        $this->enviar($this->condominosDeCasa($visita->id_casa), [
            'titulo' => 'Visita en caseta',
            'cuerpo' => $visita->nombre_visitante . ($visita->placas ? ' · ' . $visita->placas : '') . ' quiere entrar a tu casa. Toca para autorizar.',
            'url' => route('seguridad.mis-visitas', [], false),
            'tag' => "visita-{$visita->id}",
            'urgente' => true,
        ]);
    }

    /** La casa autorizó o rechazó: avisar a quien la registró en caseta. */
    public function visitaRespondida(Visita $visita): void
    {
        $autorizo = $visita->estatus === 'AUTORIZADA';
        $this->enviar([$visita->id_usuario_registro], [
            'titulo' => $autorizo ? '✅ Visita autorizada' : '⛔ Visita rechazada',
            'cuerpo' => 'Casa ' . ($visita->casa?->identificador ?? '') . ': ' . $visita->nombre_visitante
                . ($visita->comentario_respuesta ? ' · ' . $visita->comentario_respuesta : ''),
            'url' => route('seguridad.caseta', [], false),
            'tag' => "visita-resp-{$visita->id}",
        ]);
    }

    /** Alerta vecinal: a todo el condominio menos a quien la emitió. */
    public function alerta(AlertaSeguridad $alerta): void
    {
        $destino = $this->usuariosDelCondominio($alerta->id_empresa, self::STAFF_ALERTAS);
        $tipo = AlertaSeguridad::TIPOS[$alerta->tipo]['label'] ?? $alerta->tipo;

        $this->enviar(array_diff($destino, [$alerta->id_usuario]), [
            'titulo' => "🚨 Alerta: {$tipo}",
            'cuerpo' => $alerta->ubicacion ?: ($alerta->descripcion ?: 'Revisa la alerta en RIC Condominios'),
            'url' => route('seguridad.alertas', [], false),
            'tag' => "alerta-{$alerta->id}",
            'urgente' => true,
        ]);
    }

    /** Mensaje nuevo: a la otra parte de la conversación. */
    public function mensaje(Mensaje $mensaje, Conversacion $conversacion): void
    {
        $destino = $mensaje->de_administracion
            ? $this->condominosDeCasa($conversacion->id_casa)
            : $this->usuariosDelCondominio($conversacion->id_empresa, self::STAFF_MENSAJES);
        $autor = $mensaje->de_administracion ? 'Administración' : 'Casa ' . ($conversacion->casa?->identificador ?? '');
        $texto = $mensaje->texto ? mb_strimwidth($mensaje->texto, 0, 140, '…') : '📎 ' . $mensaje->adjunto_nombre;

        $this->enviar(array_diff($destino, [$mensaje->id_usuario]), [
            'titulo' => "💬 {$autor}",
            'cuerpo' => "{$conversacion->asunto}: {$texto}",
            'url' => route('mensajes.index', ['conversacion' => $conversacion->id], false),
            'tag' => "conv-{$conversacion->id}",
        ]);
    }

    // ============================================================
    // Destinatarios
    // ============================================================

    private function condominosDeCasa(?int $idCasa): array
    {
        if (!$idCasa) {
            return [];
        }

        return Condomino::where('id_casa', $idCasa)->where('activo', true)->whereNotNull('id_usuario')
            ->pluck('id_usuario')->map(fn ($id) => (int) $id)->all();
    }

    private function usuariosDelCondominio(int $empresaId, array $tipos): array
    {
        return Usuario::where('activo', true)
            ->whereIn('tipo_usuario', $tipos)
            ->where(fn ($q) => $q->where('tipo_usuario', 'SUPERUSUARIO')
                ->orWhereHas('empresas', fn ($e) => $e->where('empresas.id', $empresaId)))
            ->pluck('id_usuario')->map(fn ($id) => (int) $id)->all();
    }

    // ============================================================
    // Envío
    // ============================================================

    /** Programa el envío para después de responder al usuario. */
    private function enviar(array $idsUsuarios, array $aviso): void
    {
        $idsUsuarios = array_values(array_unique(array_filter($idsUsuarios)));
        if (!$idsUsuarios || !self::configurado()) {
            return;
        }
        dispatch(fn () => app(self::class)->enviarAhora($idsUsuarios, $aviso))->afterResponse();
    }

    /** Envía a todos los celulares de esos usuarios; borra los que ya no existen. Regresa cuántos se entregaron. */
    public function enviarAhora(array $idsUsuarios, array $aviso): int
    {
        $suscripciones = PushSuscripcion::whereIn('id_usuario', $idsUsuarios)->get();
        if ($suscripciones->isEmpty()) {
            return 0;
        }

        try {
            return $this->entregar($suscripciones, $aviso);
        } catch (\RuntimeException $e) {
            // En Windows, PHP necesita la variable OPENSSL_CONF apuntando a su openssl.cnf
            // (p. ej. C:\wamp64\bin\php\php8.4.15\extras\ssl\openssl.cnf) para cifrar los avisos.
            Log::error('No se pudieron cifrar las notificaciones push: ' . $e->getMessage()
                . (PHP_OS_FAMILY === 'Windows' && !getenv('OPENSSL_CONF') ? ' · Falta la variable de entorno OPENSSL_CONF.' : ''));

            return 0;
        }
    }

    private function entregar($suscripciones, array $aviso): int
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ], [
            // Una visita o alerta vieja ya no sirve: caduca en 10 min; un mensaje, en 1 h.
            'TTL' => ($aviso['urgente'] ?? false) ? 600 : 3600,
            'urgency' => ($aviso['urgente'] ?? false) ? 'high' : 'normal',
        ], 8);

        $carga = json_encode([
            'titulo' => $aviso['titulo'],
            'cuerpo' => $aviso['cuerpo'],
            'url' => $aviso['url'],
            'tag' => $aviso['tag'],
            'urgente' => $aviso['urgente'] ?? false,
        ], JSON_UNESCAPED_UNICODE);

        $porEndpoint = [];
        foreach ($suscripciones as $s) {
            $porEndpoint[$s->endpoint] = $s;
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $s->endpoint,
                'publicKey' => $s->p256dh,
                'authToken' => $s->auth,
                'contentEncoding' => 'aes128gcm',
            ]), $carga);
        }

        $entregadas = 0;
        foreach ($webPush->flush() as $reporte) {
            $s = $porEndpoint[$reporte->getEndpoint()] ?? null;
            if ($reporte->isSuccess()) {
                $entregadas++;
                $s?->update(['ultimo_envio' => now()]);
            } elseif ($reporte->isSubscriptionExpired()) {
                $s?->delete(); // el usuario desinstaló la app o quitó el permiso
            } else {
                Log::warning('Push no entregado: ' . $reporte->getReason());
            }
        }

        return $entregadas;
    }
}
