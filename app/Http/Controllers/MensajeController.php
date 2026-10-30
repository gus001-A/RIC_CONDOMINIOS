<?php

namespace App\Http\Controllers;

use App\Services\NotificacionesPush;
use App\Models\Casa;
use App\Models\Conversacion;
use App\Models\Empresa;
use App\Models\Mensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Mensajería interna entre las casas (condóminos) y la administración.
 *
 * Una conversación siempre es de una casa: todos los condóminos con acceso de
 * esa casa la ven, y del otro lado la ve toda la administración del condominio.
 * Sin websockets: la vista consulta cada pocos segundos si hay mensajes nuevos.
 */
class MensajeController extends Controller
{
    public function index(Request $request)
    {
        if (!Gate::allows('usar-mensajes')) {
            return redirect()->route('dashboard')->with('error', 'No tienes acceso a la mensajería');
        }

        $usuario = auth()->user();
        $esAdmin = Gate::allows('atender-mensajes');

        return Inertia::render('Mensajes/Index', [
            'categorias' => Conversacion::CATEGORIAS,
            'esAdministracion' => $esAdmin,
            'condominios' => $esAdmin
                ? Empresa::whereIn('id', $usuario->idsCondominios())->orderBy('nombre_empresa')->get(['id', 'nombre_empresa'])
                : [],
            // Casas a las que la administración puede escribir.
            'casas' => $esAdmin
                ? Casa::whereIn('id_empresa', $usuario->idsCondominios())->where('activo', true)
                    ->with('responsable')->orderBy('identificador')->get()
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'id_empresa' => $c->id_empresa,
                        'etiqueta' => $c->identificador . ($c->responsable ? ' · ' . $c->responsable->nombre_completo : ''),
                    ])
                : [],
            'miCasa' => $usuario->condomino?->casa?->only(['id', 'identificador']),
            'abrir' => $request->integer('conversacion') ?: null,
        ]);
    }

    /** Bandeja (JSON) con filtros y no leídos por conversación. */
    public function conversaciones(Request $request)
    {
        $this->autorizar();
        $usuario = auth()->user();

        $query = Conversacion::visiblesPara($usuario)
            ->select('conversaciones.*')
            ->conNoLeidos($usuario->id_usuario)
            ->with(['casa:id,identificador', 'ultimoMensaje.usuario:id_usuario,nombre_usuario']);

        if ($request->filled('estatus')) {
            $query->where('estatus', $request->estatus);
        }
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }
        if ($request->filled('id_empresa')) {
            $query->where('id_empresa', $request->id_empresa);
        }
        if ($request->filled('buscar')) {
            $buscar = '%' . $request->buscar . '%';
            $query->where(fn ($q) => $q->where('asunto', 'LIKE', $buscar)
                ->orWhereHas('casa', fn ($c) => $c->where('identificador', 'LIKE', $buscar))
                ->orWhereHas('mensajes', fn ($m) => $m->where('texto', 'LIKE', $buscar)));
        }
        if ($request->boolean('sin_leer')) {
            $query->sinLeer($usuario->id_usuario);
        }

        $conversaciones = $query->orderByRaw("estatus = 'CERRADA'")
            ->orderByDesc('ultimo_mensaje_at')
            ->limit(200)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'asunto' => $c->asunto,
                'categoria' => $c->categoria,
                'estatus' => $c->estatus,
                'casa' => $c->casa?->identificador,
                'no_leidos' => (int) $c->no_leidos,
                'ultimo_mensaje_at' => $c->ultimo_mensaje_at,
                'ultimo_texto' => $c->ultimoMensaje
                    ? ($c->ultimoMensaje->texto ?: '📎 ' . $c->ultimoMensaje->adjunto_nombre)
                    : null,
                'ultimo_autor' => $c->ultimoMensaje?->de_administracion ? 'Administración' : $c->ultimoMensaje?->usuario?->nombre_usuario,
            ]);

        return response()->json([
            'conversaciones' => $conversaciones,
            'no_leidos' => $conversaciones->where('no_leidos', '>', 0)->count(),
        ]);
    }

    /** Mensajes de una conversación; al abrirla se marca como leída. */
    /**
     * Conversación completa, o sólo lo nuevo para el sondeo del chat:
     *   ?desde=ID  → mensajes con id > ID (respuesta ligera, cada 2-3 s)
     *   ?leer=1    → marca como leída (sólo si el usuario la tiene a la vista)
     * Siempre trae el estado: estatus, quién está escribiendo y hasta dónde
     * leyó el otro lado (para las palomitas de "visto").
     */
    public function show(Request $request, Conversacion $conversacion)
    {
        $this->autorizar($conversacion);

        if ($request->boolean('leer', true)) {
            $conversacion->marcarLeida(auth()->id());
        }

        if ($request->filled('desde')) {
            return response()->json([
                'nuevos' => $conversacion->mensajes()
                    ->where('id', '>', $request->integer('desde'))
                    ->with('usuario:id_usuario,nombre_usuario,nombre_completo')
                    ->get(),
            ] + $this->estado($conversacion));
        }

        return response()->json(['conversacion' => $this->completa($conversacion)] + $this->estado($conversacion));
    }

    /** Aviso de "escribiendo…" (dura unos segundos; el chat lo renueva mientras se teclea). */
    public function escribiendo(Conversacion $conversacion)
    {
        $this->autorizar($conversacion);
        Cache::put($this->llaveEscribiendo($conversacion, Gate::allows('atender-mensajes')), auth()->user()->nombre_usuario, now()->addSeconds(6));

        return response()->noContent();
    }

    public function store(Request $request)
    {
        $this->autorizar();
        $usuario = auth()->user();
        $esAdmin = Gate::allows('atender-mensajes');

        $data = $request->validate([
            'asunto' => ['required', 'string', 'max:150'],
            'categoria' => ['required', Rule::in(array_keys(Conversacion::CATEGORIAS))],
            'texto' => ['required', 'string', 'max:5000'],
            'adjunto' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
            'id_casa' => [$esAdmin ? 'required' : 'nullable', 'integer', 'exists:casas,id'],
        ], [
            'asunto.required' => 'Escribe el asunto.',
            'texto.required' => 'Escribe el mensaje.',
            'id_casa.required' => 'Selecciona la casa a la que escribes.',
            'adjunto.max' => 'El archivo no puede pesar más de 10 MB.',
        ]);

        if ($esAdmin) {
            $casa = Casa::findOrFail($data['id_casa']);
            abort_unless($usuario->puedeVerCondominio($casa->id_empresa), 403, 'No tienes acceso a esa casa.');
        } else {
            $casa = $usuario->condomino?->activo ? $usuario->condomino->casa : null;
            abort_unless($casa, 403, 'Tu usuario no está ligado a una casa.');
        }

        $conversacion = DB::transaction(function () use ($request, $data, $casa, $usuario, $esAdmin) {
            $conversacion = Conversacion::create([
                'id_empresa' => $casa->id_empresa,
                'id_casa' => $casa->id,
                'asunto' => $data['asunto'],
                'categoria' => $data['categoria'],
                'id_usuario_inicio' => $usuario->id_usuario,
                'iniciada_por_administracion' => $esAdmin,
            ]);
            $this->agregarMensaje($conversacion, $request, $data['texto'], $esAdmin);

            return $conversacion;
        });

        return response()->json(['id' => $conversacion->id]);
    }

    public function responder(Request $request, Conversacion $conversacion)
    {
        $this->autorizar($conversacion);
        $esAdmin = Gate::allows('atender-mensajes');

        abort_if($conversacion->estatus === 'CERRADA' && !$esAdmin, 422, 'La conversación está cerrada. Inicia una nueva si necesitas algo más.');

        $data = $request->validate([
            'texto' => ['nullable', 'string', 'max:5000', 'required_without:adjunto'],
            'adjunto' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ], [
            'texto.required_without' => 'Escribe un mensaje o adjunta un archivo.',
            'adjunto.max' => 'El archivo no puede pesar más de 10 MB.',
        ]);

        $mensaje = DB::transaction(function () use ($request, $conversacion, $data, $esAdmin) {
            // Si la administración escribe en una conversación cerrada, se reabre.
            if ($conversacion->estatus === 'CERRADA') {
                $conversacion->update(['estatus' => 'ABIERTA', 'id_usuario_cierre' => null, 'fecha_cierre' => null]);
            }
            return $this->agregarMensaje($conversacion, $request, $data['texto'] ?? null, $esAdmin);
        });

        // Ya envió: deja de "escribir".
        Cache::forget($this->llaveEscribiendo($conversacion, $esAdmin));

        return response()->json([
            'mensaje' => $mensaje->load('usuario:id_usuario,nombre_usuario,nombre_completo'),
        ] + $this->estado($conversacion->fresh()));
    }

    /** Cerrar / reabrir. Cierra cualquiera de los dos lados; reabre la administración. */
    public function estatus(Conversacion $conversacion)
    {
        $this->autorizar($conversacion);

        if ($conversacion->estatus === 'ABIERTA') {
            $conversacion->update([
                'estatus' => 'CERRADA',
                'id_usuario_cierre' => auth()->id(),
                'fecha_cierre' => now(),
            ]);
        } else {
            abort_unless(Gate::allows('atender-mensajes'), 403, 'Sólo la administración reabre conversaciones.');
            $conversacion->update(['estatus' => 'ABIERTA', 'id_usuario_cierre' => null, 'fecha_cierre' => null]);
        }

        $conversacion = $conversacion->fresh();

        return response()->json(['conversacion' => $this->completa($conversacion)] + $this->estado($conversacion));
    }

    public function adjunto(Mensaje $mensaje)
    {
        $this->autorizar($mensaje->conversacion);
        abort_unless($mensaje->adjunto_ruta && Storage::disk('local')->exists($mensaje->adjunto_ruta), 404);

        return Storage::disk('local')->response($mensaje->adjunto_ruta, $mensaje->adjunto_nombre);
    }

    // ======================================================
    // 🔒 HELPERS
    // ======================================================

    private function autorizar(?Conversacion $conversacion = null): void
    {
        abort_unless(Gate::allows('usar-mensajes'), 403);

        if ($conversacion) {
            abort_unless(
                Conversacion::visiblesPara(auth()->user())->whereKey($conversacion->id)->exists(),
                403,
                'No tienes acceso a esta conversación.'
            );
        }
    }

    private function completa(Conversacion $conversacion): Conversacion
    {
        return $conversacion->load([
            'casa:id,identificador,id_empresa',
            'condominio:id,nombre_empresa',
            'inicio:id_usuario,nombre_usuario',
            'cierre:id_usuario,nombre_usuario',
            'mensajes.usuario:id_usuario,nombre_usuario,nombre_completo',
        ]);
    }

    /**
     * Estado "vivo" de la conversación para el chat:
     *  - estatus y cierre (por si el otro lado la cerró / reabrió)
     *  - escribiendo: nombre de quien del otro lado está tecleando
     *  - visto_hasta: lo último que leyó el otro lado (palomitas azules)
     */
    private function estado(Conversacion $conversacion): array
    {
        $soyAdmin = Gate::allows('atender-mensajes');

        // "El otro lado": para la administración, los condóminos de la casa; para la casa, la administración.
        $lecturas = DB::table('conversacion_lecturas as l')
            ->join('usuarios as u', 'u.id_usuario', '=', 'l.id_usuario')
            ->where('l.id_conversacion', $conversacion->id)
            ->where('l.id_usuario', '!=', auth()->id());
        $soyAdmin
            ? $lecturas->where('u.tipo_usuario', 'CONDOMINO')
            : $lecturas->where('u.tipo_usuario', '!=', 'CONDOMINO');

        $visto = $lecturas->max('l.leido_hasta');

        return [
            'estatus' => $conversacion->estatus,
            'escribiendo' => Cache::get($this->llaveEscribiendo($conversacion, !$soyAdmin)),
            // ISO con zona horaria, igual que created_at de los mensajes, para compararlos en el navegador.
            'visto_hasta' => $visto ? \Illuminate\Support\Carbon::parse($visto)->toIso8601String() : null,
        ];
    }

    private function llaveEscribiendo(Conversacion $conversacion, bool $deAdministracion): string
    {
        return "chat:{$conversacion->id}:escribe:" . ($deAdministracion ? 'admin' : 'casa');
    }

    private function agregarMensaje(Conversacion $conversacion, Request $request, ?string $texto, bool $esAdmin): Mensaje
    {
        $datos = [
            'id_usuario' => auth()->id(),
            'de_administracion' => $esAdmin,
            'texto' => $texto,
        ];

        // Los adjuntos van al disco privado: sólo se descargan por la ruta con permisos.
        if ($request->hasFile('adjunto')) {
            $archivo = $request->file('adjunto');
            $datos['adjunto_ruta'] = $archivo->store("mensajes/{$conversacion->id}", 'local');
            $datos['adjunto_nombre'] = $archivo->getClientOriginalName();
            $datos['adjunto_mime'] = $archivo->getMimeType();
        }

        $mensaje = $conversacion->mensajes()->create($datos);
        $conversacion->update(['ultimo_mensaje_at' => $mensaje->created_at]);
        // Quien escribe ya leyó todo hasta su propio mensaje.
        $conversacion->marcarLeida(auth()->id());
        // La otra parte recibe el aviso en el celular (se envía al terminar la petición).
        app(NotificacionesPush::class)->mensaje($mensaje, $conversacion);

        return $mensaje;
    }
}
