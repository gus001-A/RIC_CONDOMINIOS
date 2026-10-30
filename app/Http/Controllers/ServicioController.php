<?php

namespace App\Http\Controllers;

use App\Models\Casa;
use App\Models\DocumentoGenerado;
use App\Models\Empresa;
use App\Models\PlantillaDocumento;
use App\Models\SolicitudCotizacion;
use App\Models\SolicitudSeguimiento;
use App\Models\SolicitudServicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Servicios particulares y asesoría inmobiliaria.
 *
 * La casa pide apoyo (reparación dentro de su casa, rentarla, venderla…) y la
 * administración la asesora, le consigue cotizaciones de proveedores y le
 * genera contratos. Todo es PRIVADO entre la casa y la administración: ni
 * vecinos ni vigilancia / mantenimiento lo ven.
 */
class ServicioController extends Controller
{
    public function index(Request $request)
    {
        $usuario = auth()->user();
        $esAdmin = Gate::allows('atender-servicios');
        $casa = $this->miCasa();

        if (!$esAdmin && !$casa) {
            return redirect()->route('dashboard')->with('error', 'No tienes acceso a los servicios');
        }

        $query = SolicitudServicio::query()
            ->with(['casa:id,identificador', 'condominio:id,nombre_empresa', 'solicita:id_usuario,nombre_usuario', 'atiende:id_usuario,nombre_usuario'])
            ->withCount(['cotizaciones', 'seguimientos']);

        $esAdmin
            ? $query->whereIn('id_empresa', $usuario->idsCondominios())
            : $query->where('id_casa', $casa->id);

        foreach (['tipo', 'estatus', 'id_empresa'] as $campo) {
            if ($request->filled($campo)) {
                $query->where($campo, $request->input($campo));
            }
        }
        if ($request->filled('folio')) {
            $query->where('folio', 'LIKE', '%' . $request->folio . '%');
        }
        if ($request->filled('casa')) {
            $query->whereHas('casa', fn ($q) => $q->where('identificador', 'LIKE', '%' . $request->casa . '%'));
        }
        if ($request->filled('buscar')) {
            // La columna agrupada "Solicitud" busca también por folio y casa.
            $buscar = '%' . $request->buscar . '%';
            $query->where(fn ($q) => $q->where('titulo', 'LIKE', $buscar)
                ->orWhere('folio', 'LIKE', $buscar)
                ->orWhere('descripcion', 'LIKE', $buscar)
                ->orWhereHas('casa', fn ($c) => $c->where('identificador', 'LIKE', $buscar)));
        }
        if (!$request->filled('estatus') && !$request->boolean('ver_cerradas')) {
            $query->whereNotIn('estatus', ['CONCLUIDA', 'CANCELADA']);
        }

        return Inertia::render('Servicios/Index', [
            'solicitudes' => $query->latest()->paginate(15)->withQueryString(),
            'esAdministracion' => $esAdmin,
            'condominios' => $esAdmin ? Empresa::whereIn('id', $usuario->idsCondominios())->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']) : [],
            'miCasa' => $casa?->only(['id', 'identificador']),
            'catalogos' => $this->catalogos(),
            'filtros' => $request->only(['tipo', 'estatus', 'id_empresa', 'folio', 'casa', 'buscar', 'ver_cerradas']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Gate::allows('solicitar-servicios'), 403, 'Sólo los condóminos solicitan servicios.');
        $casa = $this->miCasa();
        abort_unless($casa, 403, 'Tu usuario no está ligado a una casa.');

        $data = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(SolicitudServicio::TIPOS))],
            'categoria' => ['nullable', 'required_if:tipo,MANTENIMIENTO', Rule::in(array_keys(SolicitudServicio::CATEGORIAS))],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'max:3000'],
            'precio_deseado' => ['nullable', 'numeric', 'min:0'],
            'fecha_disponible' => ['nullable', 'date'],
            'adjunto' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'categoria.required_if' => '¿Qué tipo de reparación necesitas?',
            'titulo.required' => 'Escribe un título breve.',
            'descripcion.required' => 'Describe lo que necesitas.',
        ]);

        $solicitud = DB::transaction(function () use ($request, $data, $casa) {
            // array_merge (no `+`): la categoría sólo aplica a mantenimiento y debe sobrescribirse.
            $solicitud = SolicitudServicio::create(array_merge(collect($data)->except('adjunto')->all(), [
                'id_empresa' => $casa->id_empresa,
                'id_casa' => $casa->id,
                'folio' => SolicitudServicio::siguienteFolio($casa->id_empresa),
                'categoria' => $data['tipo'] === 'MANTENIMIENTO' ? $data['categoria'] : null,
                'id_usuario_solicita' => auth()->id(),
            ]));
            $this->registrarSeguimiento($solicitud, $request, 'SOLICITUD CREADA', 'NUEVA', null);

            return $solicitud;
        });

        return redirect()->route('servicios.show', $solicitud->id)
            ->with('success', "Solicitud {$solicitud->folio} enviada. La administración te responderá aquí.");
    }

    public function show(SolicitudServicio $solicitud)
    {
        $this->autorizar($solicitud);
        $esAdmin = Gate::allows('atender-servicios');

        $solicitud->load([
            'casa:id,identificador,id_empresa', 'condominio:id,nombre_empresa',
            'solicita:id_usuario,nombre_usuario,nombre_completo', 'atiende:id_usuario,nombre_usuario',
            'seguimientos.usuario:id_usuario,nombre_usuario',
            'cotizaciones.registro:id_usuario,nombre_usuario', 'cotizaciones.respuesta:id_usuario,nombre_usuario',
            'documentos:id,id_solicitud,titulo,created_at,updated_at,id_usuario_registro',
            'documentos.registro:id_usuario,nombre_usuario',
        ]);

        return Inertia::render('Servicios/Show', [
            'solicitud' => $solicitud,
            'esAdministracion' => $esAdmin,
            'catalogos' => $this->catalogos(),
            'plantillas' => $esAdmin
                ? PlantillaDocumento::disponiblesPara(auth()->user())->where('activo', true)
                    ->where(fn ($q) => $q->whereNull('id_empresa')->orWhere('id_empresa', $solicitud->id_empresa))
                    ->orderBy('nombre')->get(['id', 'nombre', 'tipo', 'id_empresa'])
                : [],
        ]);
    }

    /** Comentario y/o cambio de estatus, con adjunto opcional. */
    public function seguimiento(Request $request, SolicitudServicio $solicitud)
    {
        $this->autorizar($solicitud);
        $esAdmin = Gate::allows('atender-servicios');

        // Administración: cualquier estatus; la casa sólo puede cancelar mientras no se haya hecho el trabajo.
        $permitidos = $esAdmin
            ? array_keys(SolicitudServicio::ESTATUS)
            : (in_array($solicitud->estatus, ['NUEVA', 'EN_REVISION', 'COTIZADA'], true) ? ['CANCELADA'] : []);

        $data = $request->validate([
            'estatus_nuevo' => ['nullable', Rule::in($permitidos)],
            'comentario' => ['nullable', 'string', 'max:3000', 'required_without_all:adjunto,estatus_nuevo'],
            'adjunto' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx'],
        ], [
            'estatus_nuevo.in' => 'No puedes cambiar la solicitud a ese estatus.',
            'comentario.required_without_all' => 'Escribe un comentario o adjunta un archivo.',
        ]);

        abort_if($solicitud->estaCerrada() && !$esAdmin, 422, 'La solicitud está cerrada.');
        if (($data['estatus_nuevo'] ?? null) === 'CANCELADA' && !trim($data['comentario'] ?? '')) {
            return back()->withErrors(['comentario' => 'Indica el motivo de la cancelación.']);
        }

        DB::transaction(function () use ($request, $solicitud, $data, $esAdmin) {
            $nuevo = $data['estatus_nuevo'] ?? null;
            if ($nuevo === $solicitud->estatus) {
                $nuevo = null;
            }
            $this->registrarSeguimiento($solicitud, $request, $data['comentario'] ?? null, $nuevo, $nuevo ? $solicitud->estatus : null);

            $cambios = [];
            if ($nuevo) {
                $cambios['estatus'] = $nuevo;
                $cambios['fecha_cierre'] = in_array($nuevo, ['CONCLUIDA', 'CANCELADA'], true) ? now() : null;
            }
            // Quien de la administración responde primero queda como responsable.
            if ($esAdmin && !$solicitud->id_usuario_atiende) {
                $cambios['id_usuario_atiende'] = auth()->id();
                if (!$nuevo && $solicitud->estatus === 'NUEVA') {
                    $cambios['estatus'] = 'EN_REVISION';
                }
            }
            $cambios ? $solicitud->update($cambios) : $solicitud->touch();
        });

        return back()->with('success', 'Seguimiento agregado.');
    }

    /** La administración agrega la cotización de un proveedor. */
    public function cotizar(Request $request, SolicitudServicio $solicitud)
    {
        abort_unless(Gate::allows('atender-servicios'), 403);
        $this->autorizar($solicitud);
        abort_if($solicitud->estaCerrada(), 422, 'La solicitud está cerrada.');

        $data = $request->validate([
            'proveedor' => ['required', 'string', 'max:150'],
            'contacto_proveedor' => ['nullable', 'string', 'max:150'],
            'concepto' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'proveedor.required' => 'Captura el proveedor.',
            'concepto.required' => 'Describe qué incluye la cotización.',
            'monto.required' => 'Captura el monto.',
        ]);

        DB::transaction(function () use ($request, $solicitud, $data) {
            $archivo = $request->file('archivo');
            $solicitud->cotizaciones()->create(collect($data)->except('archivo')->all() + [
                'archivo_ruta' => $archivo?->store("servicios/{$solicitud->id}", 'local'),
                'archivo_nombre' => $archivo?->getClientOriginalName(),
                'id_usuario_registro' => auth()->id(),
            ]);
            $solicitud->seguimientos()->create([
                'id_usuario' => auth()->id(),
                'de_administracion' => true,
                'estatus_anterior' => in_array($solicitud->estatus, ['NUEVA', 'EN_REVISION'], true) ? $solicitud->estatus : null,
                'estatus_nuevo' => in_array($solicitud->estatus, ['NUEVA', 'EN_REVISION'], true) ? 'COTIZADA' : null,
                'comentario' => "COTIZACIÓN DE {$data['proveedor']}: $" . number_format($data['monto'], 2),
            ]);
            $solicitud->update([
                'estatus' => in_array($solicitud->estatus, ['NUEVA', 'EN_REVISION'], true) ? 'COTIZADA' : $solicitud->estatus,
                'id_usuario_atiende' => $solicitud->id_usuario_atiende ?? auth()->id(),
            ]);
        });

        return back()->with('success', 'Cotización enviada a la casa.');
    }

    /** La casa acepta o rechaza una cotización. Aceptar una rechaza las demás. */
    public function responderCotizacion(Request $request, SolicitudCotizacion $cotizacion)
    {
        $solicitud = SolicitudServicio::findOrFail($cotizacion->id_solicitud);
        $casa = $this->miCasa();
        abort_unless($casa && $casa->id === $solicitud->id_casa, 403, 'Sólo la casa que solicitó responde la cotización.');
        abort_unless($cotizacion->estatus === 'PROPUESTA' && !$solicitud->estaCerrada(), 422, 'Esta cotización ya fue respondida.');

        // Se acepta 'aceptar' o 'ACEPTAR'.
        $request->merge(['accion' => strtolower((string) $request->input('accion'))]);
        $data = $request->validate(['accion' => ['required', Rule::in(['aceptar', 'rechazar'])]]);
        $acepta = $data['accion'] === 'aceptar';

        DB::transaction(function () use ($solicitud, $cotizacion, $acepta) {
            $respuesta = ['id_usuario_respuesta' => auth()->id(), 'fecha_respuesta' => now()];
            $cotizacion->update(['estatus' => $acepta ? 'ACEPTADA' : 'RECHAZADA'] + $respuesta);

            if ($acepta) {
                $solicitud->cotizaciones()->where('id', '!=', $cotizacion->id)->where('estatus', 'PROPUESTA')
                    ->update(['estatus' => 'RECHAZADA'] + $respuesta);
            }
            $solicitud->seguimientos()->create([
                'id_usuario' => auth()->id(),
                'estatus_anterior' => $acepta ? $solicitud->estatus : null,
                'estatus_nuevo' => $acepta ? 'EN_PROCESO' : null,
                'comentario' => ($acepta ? 'ACEPTÉ' : 'RECHACÉ') . " LA COTIZACIÓN DE {$cotizacion->proveedor} ($" . number_format($cotizacion->monto, 2) . ')',
            ]);
            $acepta ? $solicitud->update(['estatus' => 'EN_PROCESO']) : $solicitud->touch();
        });

        return back()->with('success', $acepta ? 'Cotización aceptada. La administración coordinará el servicio.' : 'Cotización rechazada.');
    }

    /** Adjuntos privados (seguimientos y cotizaciones). */
    public function adjunto(string $tipo, int $id)
    {
        [$ruta, $nombre, $idSolicitud] = match ($tipo) {
            'seguimiento' => (fn ($s) => [$s->adjunto_ruta, $s->adjunto_nombre, $s->id_solicitud])(SolicitudSeguimiento::findOrFail($id)),
            'cotizacion' => (fn ($c) => [$c->archivo_ruta, $c->archivo_nombre, $c->id_solicitud])(SolicitudCotizacion::findOrFail($id)),
            default => abort(404),
        };
        $this->autorizar(SolicitudServicio::findOrFail($idSolicitud));
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->response($ruta, $nombre);
    }

    // ======================================================
    // 🔒 HELPERS
    // ======================================================

    private function miCasa(): ?Casa
    {
        $usuario = auth()->user();
        $condomino = $usuario->esCondomino() ? $usuario->condomino : null;

        return ($condomino && $condomino->activo) ? $condomino->casa : null;
    }

    /** Sólo la casa dueña de la solicitud o la administración de su condominio. */
    private function autorizar(SolicitudServicio $solicitud): void
    {
        $casa = $this->miCasa();
        if ($casa && $casa->id === $solicitud->id_casa) {
            return;
        }
        abort_unless(
            Gate::allows('atender-servicios') && auth()->user()->puedeVerCondominio($solicitud->id_empresa),
            403,
            'Esta solicitud es privada entre la casa y la administración.'
        );
    }

    private function registrarSeguimiento(SolicitudServicio $solicitud, Request $request, ?string $comentario, ?string $nuevo, ?string $anterior): void
    {
        $archivo = $request->file('adjunto');
        $solicitud->seguimientos()->create([
            'id_usuario' => auth()->id(),
            'de_administracion' => Gate::allows('atender-servicios'),
            'estatus_anterior' => $anterior,
            'estatus_nuevo' => $nuevo,
            'comentario' => $comentario ? trim($comentario) : null,
            'adjunto_ruta' => $archivo?->store("servicios/{$solicitud->id}", 'local'),
            'adjunto_nombre' => $archivo?->getClientOriginalName(),
            'adjunto_mime' => $archivo?->getMimeType(),
        ]);
    }

    private function catalogos(): array
    {
        return [
            'tipos' => SolicitudServicio::TIPOS,
            'categorias' => SolicitudServicio::CATEGORIAS,
            'estatus' => SolicitudServicio::ESTATUS,
        ];
    }
}
