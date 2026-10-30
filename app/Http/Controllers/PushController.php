<?php

namespace App\Http\Controllers;

use App\Models\PushSuscripcion;
use App\Services\NotificacionesPush;
use Illuminate\Http\Request;

/**
 * Suscripciones push de cada celular / navegador del usuario.
 * El navegador genera la suscripción (endpoint + llaves) y aquí sólo se guarda.
 */
class PushController extends Controller
{
    public function suscribir(Request $request)
    {
        abort_unless(NotificacionesPush::configurado(), 503, 'Las notificaciones no están configuradas en el servidor.');

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        // Un mismo celular puede cambiar de usuario: la suscripción queda con el último que entró.
        PushSuscripcion::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'id_usuario' => auth()->id(),
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'dispositivo' => mb_substr((string) $request->userAgent(), 0, 255),
            ],
        );

        // Para quitarla al cerrar sesión (ver AppServiceProvider): un celular compartido no
        // debe seguir recibiendo avisos del usuario anterior.
        $request->session()->put('push_endpoint_hash', hash('sha256', $data['endpoint']));

        return response()->json(['ok' => true, 'dispositivos' => PushSuscripcion::where('id_usuario', auth()->id())->count()]);
    }

    public function desuscribir(Request $request)
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        PushSuscripcion::where('endpoint_hash', hash('sha256', $request->endpoint))
            ->where('id_usuario', auth()->id())
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Aviso de prueba a los celulares del propio usuario. */
    public function prueba(NotificacionesPush $push)
    {
        $entregadas = $push->enviarAhora([auth()->id()], [
            'titulo' => '🔔 Notificaciones activadas',
            'cuerpo' => 'Así te avisaremos de visitas, alertas y mensajes, aunque la app esté cerrada.',
            'url' => route('dashboard', [], false),
            'tag' => 'prueba-' . auth()->id(),
        ]);

        return response()->json(['entregadas' => $entregadas]);
    }
}
