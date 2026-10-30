<?php

namespace App\Http\Controllers;

use App\Services\NotificacionesPush;
use App\Models\AlertaArchivo;
use App\Models\AlertaSeguridad;
use App\Models\Casa;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Alertas vecinales de seguridad. Se difunden a todos los usuarios del
 * condominio (el layout las consulta con el "pulso"). Pueden ser anónimas para
 * los vecinos, pero siempre guardan quién las emitió: la administración lo ve
 * para detectar mal uso o falsas alarmas.
 */
class AlertaController extends Controller
{
    public function index()
    {
        if (!Gate::allows('ver-alertas')) {
            return redirect()->route('dashboard')->with('error', 'No tienes acceso a las alertas');
        }

        $ids = auth()->user()->idsCondominios();

        return Inertia::render('Seguridad/Alertas', [
            'tipos' => AlertaSeguridad::TIPOS,
            'estatus' => AlertaSeguridad::ESTATUS,
            'condominios' => Empresa::whereIn('id', $ids)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'centros' => Casa::centros($ids),
        ]);
    }

    public function lista(Request $request)
    {
        abort_unless(Gate::allows('ver-alertas'), 403);
        $usuario = auth()->user();
        $rastrear = Gate::allows('rastrear-alertas');

        $query = AlertaSeguridad::whereIn('id_empresa', $usuario->idsCondominios())
            ->with(['usuario:id_usuario,nombre_usuario,tipo_usuario', 'casa:id,identificador', 'cierre:id_usuario,nombre_usuario', 'archivos']);

        foreach (['estatus', 'tipo', 'id_empresa'] as $campo) {
            if ($request->filled($campo)) {
                $query->where($campo, $request->input($campo));
            }
        }
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }

        $alertas = $query->orderByRaw("estatus = 'ACTIVA' DESC")->latest()->limit(200)->get();

        $respuesta = ['alertas' => $alertas->map(fn ($a) => $a->paraUsuario($usuario, $rastrear))];

        // Trazabilidad para la administración: quién emite falsas alarmas.
        if ($rastrear) {
            $respuesta['falsas_alarmas'] = AlertaSeguridad::whereIn('alertas_seguridad.id_empresa', $usuario->idsCondominios())
                ->join('usuarios', 'usuarios.id_usuario', '=', 'alertas_seguridad.id_usuario')
                ->leftJoin('casas', 'casas.id', '=', 'alertas_seguridad.id_casa')
                ->groupBy('alertas_seguridad.id_usuario', 'usuarios.nombre_usuario', 'casas.identificador')
                ->selectRaw("usuarios.nombre_usuario as usuario, casas.identificador as casa,
                    COUNT(*) as total,
                    SUM(alertas_seguridad.estatus = 'FALSA_ALARMA') as falsas,
                    SUM(alertas_seguridad.anonima = 1) as anonimas")
                ->havingRaw("SUM(alertas_seguridad.estatus = 'FALSA_ALARMA') > 0")
                ->orderByDesc('falsas')
                ->get();
        }

        return response()->json($respuesta);
    }

    public function store(Request $request)
    {
        abort_unless(Gate::allows('emitir-alertas'), 403, 'No puedes emitir alertas');
        $usuario = auth()->user();
        $casa = $usuario->esCondomino() && $usuario->condomino?->activo ? $usuario->condomino->casa : null;

        $data = $request->validate([
            'id_empresa' => [$casa ? 'nullable' : 'required', 'integer'],
            'tipo' => ['required', Rule::in(array_keys(AlertaSeguridad::TIPOS))],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
            'anonima' => ['boolean'],
            'archivos' => ['nullable', 'array', 'max:3'],
            'archivos.*' => ['file', 'max:51200', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm,video/3gpp'],
        ], [
            'id_empresa.required' => 'Selecciona el condominio.',
            'archivos.max' => 'Máximo 3 fotos o videos.',
            'archivos.*.max' => 'Cada archivo puede pesar hasta 50 MB.',
            'archivos.*.mimetypes' => 'Sólo fotos (JPG, PNG, WEBP) o videos (MP4, MOV, WEBM).',
        ]);

        $empresaId = $casa ? $casa->id_empresa : (int) $data['id_empresa'];
        abort_unless($usuario->puedeVerCondominio($empresaId), 403, 'No tienes acceso a ese condominio.');

        $alerta = DB::transaction(function () use ($request, $data, $usuario, $casa, $empresaId) {
            $alerta = AlertaSeguridad::create([
                'id_empresa' => $empresaId,
                'tipo' => $data['tipo'],
                'descripcion' => $data['descripcion'] ?? null,
                'ubicacion' => $data['ubicacion'] ?? null,
                'latitud' => $data['latitud'] ?? null,
                'longitud' => $data['longitud'] ?? null,
                'anonima' => $request->boolean('anonima'),
                'id_usuario' => $usuario->id_usuario,
                'id_casa' => $casa?->id,
            ]);

            foreach ($request->file('archivos', []) as $archivo) {
                $alerta->archivos()->create([
                    'ruta' => $archivo->store("alertas/{$alerta->id}", 'local'),
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime' => $archivo->getMimeType(),
                ]);
            }

            return $alerta;
        });

        // Todo el condominio recibe la alerta en el celular aunque tenga la app cerrada.
        app(NotificacionesPush::class)->alerta($alerta);

        return response()->json(['id' => $alerta->id]);
    }

    public function cerrar(Request $request, AlertaSeguridad $alerta)
    {
        abort_unless(Gate::allows('cerrar-alertas') && auth()->user()->puedeVerCondominio($alerta->id_empresa), 403);
        abort_unless($alerta->estatus === 'ACTIVA', 422, 'La alerta ya está cerrada.');

        $data = $request->validate([
            'estatus' => ['required', Rule::in(['ATENDIDA', 'FALSA_ALARMA'])],
            'comentario' => ['required', 'string', 'max:500'],
        ], ['comentario.required' => 'Explica cómo se atendió o por qué es falsa alarma.']);

        $alerta->update([
            'estatus' => $data['estatus'],
            'comentario_cierre' => $data['comentario'],
            'id_usuario_cierre' => auth()->id(),
            'fecha_cierre' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function archivo(AlertaArchivo $archivo)
    {
        $alerta = $archivo->alerta;
        abort_unless(Gate::allows('ver-alertas') && auth()->user()->puedeVerCondominio($alerta->id_empresa), 403);
        abort_unless(Storage::disk('local')->exists($archivo->ruta), 404);

        return Storage::disk('local')->response($archivo->ruta, $archivo->nombre_original);
    }
}
