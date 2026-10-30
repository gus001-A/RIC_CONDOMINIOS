<?php

namespace App\Http\Controllers;

use App\Models\AlertaSeguridad;
use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\Visita;
use Illuminate\Support\Facades\Gate;

/**
 * "Pulso" global (sin websockets): el layout lo consulta cada pocos segundos
 * en cualquier pantalla. Trae lo que debe avisarse al momento:
 *  - alertas vecinales activas del condominio
 *  - visitas en caseta esperando la autorización de la casa
 *  - mensajes sin leer (contador y los más recientes para el aviso)
 */
class PulsoController extends Controller
{
    public function __invoke()
    {
        $usuario = auth()->user();
        $respuesta = ['alertas' => [], 'visitas_esperando' => [], 'mensajes' => ['no_leidos' => 0, 'recientes' => []]];

        if (Gate::allows('ver-alertas')) {
            $verEmisor = Gate::allows('rastrear-alertas');
            $respuesta['alertas'] = AlertaSeguridad::whereIn('id_empresa', $usuario->idsCondominios())
                ->where('estatus', 'ACTIVA')
                ->where('created_at', '>=', now()->subDay())
                ->with(['usuario:id_usuario,nombre_usuario,tipo_usuario', 'casa:id,identificador', 'archivos'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($a) => $a->paraUsuario($usuario, $verEmisor));
        }

        $condomino = $usuario->esCondomino() ? $usuario->condomino : null;
        if ($condomino && $condomino->activo) {
            $respuesta['visitas_esperando'] = Visita::where('id_casa', $condomino->id_casa)
                ->where('estatus', 'ESPERANDO')
                ->with('casa:id,identificador')
                ->latest('fecha_llegada')
                ->get()
                ->map(fn ($v) => $v->aArreglo());
        }

        if (Gate::allows('usar-mensajes')) {
            $sinLeer = Conversacion::visiblesPara($usuario)->sinLeer($usuario->id_usuario)->pluck('id');

            // Los mensajes SIN LEER de los últimos minutos (posteriores a lo que el
            // usuario ya leyó de esa conversación), para avisar una sola vez cada uno.
            $recientes = $sinLeer->isEmpty() ? collect() : Mensaje::whereIn('id_conversacion', $sinLeer)
                ->where('id_usuario', '!=', $usuario->id_usuario)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->whereRaw("mensajes.created_at > COALESCE((
                    SELECT l.leido_hasta FROM conversacion_lecturas l
                    WHERE l.id_conversacion = mensajes.id_conversacion AND l.id_usuario = ?
                ), '1970-01-01')", [$usuario->id_usuario])
                ->with(['usuario:id_usuario,nombre_usuario', 'conversacion:id,asunto,id_casa', 'conversacion.casa:id,identificador'])
                ->latest('id')
                ->limit(5)
                ->get();

            $respuesta['mensajes'] = [
                'no_leidos' => $sinLeer->count(),
                'recientes' => $recientes->map(fn ($m) => [
                    'id' => $m->id,
                    'id_conversacion' => $m->id_conversacion,
                    'asunto' => $m->conversacion?->asunto,
                    'autor' => $m->de_administracion ? 'Administración' : 'Casa ' . $m->conversacion?->casa?->identificador,
                    'usuario' => $m->usuario?->nombre_usuario,
                    'texto' => $m->texto ? mb_strimwidth($m->texto, 0, 120, '…') : '📎 ' . $m->adjunto_nombre,
                    'created_at' => $m->created_at,
                ])->values(),
            ];
        }

        return response()->json($respuesta);
    }
}
