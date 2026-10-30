<?php

namespace App\Http\Controllers;

use App\Models\Casa;
use App\Models\Empresa;
use App\Models\Incidencia;
use App\Models\IncidenciaGasto;
use App\Models\Insumo;
use App\Models\InsumoMovimiento;
use App\Services\Inventario;
use Illuminate\Validation\ValidationException;
use App\Models\Poliza;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Incidencias y sugerencias de zonas comunes.
 *
 * Las reporta un condómino (o el personal), se canalizan a mantenimiento /
 * administración y avanzan PENDIENTE → EN PROCESO → ATENDIDA → SUPERVISADA.
 * Cada avance o comentario queda como seguimiento con fotos y gastos, visible
 * para todos los usuarios del condominio.
 */
class IncidenciaController extends Controller
{
    public function index(Request $request)
    {
        if (!Gate::allows('ver-incidencias')) {
            return redirect()->route('dashboard')->with('error', 'No tienes acceso a las incidencias');
        }

        $usuario = auth()->user();
        $ids = $usuario->idsCondominios();

        $query = Incidencia::visiblesPara($usuario)
            ->with(['condominio:id,nombre_empresa', 'casa:id,identificador', 'reporta:id_usuario,nombre_usuario', 'asignado:id_usuario,nombre_usuario'])
            ->withCount('fotos')
            ->withSum(['gastos as total_gastos' => fn ($q) => $q->where('cancelado', false)], 'monto')
            ->addSelect(['ultimo_movimiento' => DB::table('incidencia_seguimientos')
                ->selectRaw('MAX(created_at)')
                ->whereColumn('id_incidencia', 'incidencias.id')]);

        foreach (['id_empresa', 'estatus', 'categoria', 'prioridad', 'tipo', 'id_usuario_asignado'] as $campo) {
            if ($request->filled($campo)) {
                $query->where($campo, $request->input($campo));
            }
        }
        if ($request->filled('folio')) {
            $query->where('folio', 'LIKE', '%' . $request->folio . '%');
        }
        if ($request->filled('buscar')) {
            $buscar = '%' . $request->buscar . '%';
            // La columna agrupada "Incidencia" también busca por folio.
            $query->where(fn ($q) => $q->where('titulo', 'LIKE', $buscar)
                ->orWhere('folio', 'LIKE', $buscar)
                ->orWhere('descripcion', 'LIKE', $buscar)
                ->orWhere('ubicacion', 'LIKE', $buscar));
        }
        if ($request->boolean('mias')) {
            $query->where(fn ($q) => $q->where('id_usuario_asignado', $usuario->id_usuario)
                ->orWhere('id_usuario_reporta', $usuario->id_usuario));
        }
        // Por defecto se ocultan las cerradas (supervisadas y canceladas).
        if (!$request->filled('estatus') && !$request->boolean('ver_cerradas')) {
            $query->whereNotIn('estatus', ['SUPERVISADA', 'CANCELADA']);
        }

        $incidencias = $query
            ->orderByRaw("FIELD(prioridad, 'URGENTE', 'ALTA', 'MEDIA', 'BAJA')")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $porEstatus = Incidencia::visiblesPara($usuario)
            ->selectRaw('estatus, COUNT(*) as n')
            ->groupBy('estatus')
            ->pluck('n', 'estatus');

        return Inertia::render('Incidencias/Index', [
            'incidencias' => $incidencias,
            'porEstatus' => $porEstatus,
            'condominios' => Empresa::whereIn('id', $ids)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'personalMantenimiento' => $this->personalMantenimiento($ids),
            'centros' => Casa::centros($ids),
            'catalogos' => $this->catalogos(),
            'filtros' => $request->only(['id_empresa', 'estatus', 'categoria', 'prioridad', 'tipo', 'id_usuario_asignado', 'folio', 'buscar', 'mias', 'ver_cerradas']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Gate::allows('reportar-incidencias'), 403, 'No puedes reportar incidencias');
        $usuario = auth()->user();
        $esPersonal = !$usuario->esCondomino();
        $casa = $usuario->condomino?->activo ? $usuario->condomino->casa : null;

        $data = $request->validate([
            'id_empresa' => [$esPersonal ? 'required' : 'nullable', 'integer'],
            'tipo' => ['required', Rule::in(array_keys(Incidencia::TIPOS))],
            'categoria' => ['required', Rule::in(array_keys(Incidencia::CATEGORIAS))],
            'prioridad' => ['nullable', Rule::in(array_keys(Incidencia::PRIORIDADES))],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'max:3000'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
            'fotos' => ['nullable', 'array', 'max:5'],
            'fotos.*' => ['image', 'max:8192'],
        ], [
            'titulo.required' => 'Escribe un título breve.',
            'descripcion.required' => 'Describe el problema o la sugerencia.',
            'id_empresa.required' => 'Selecciona el condominio.',
            'fotos.max' => 'Máximo 5 fotos por reporte.',
            'fotos.*.image' => 'Las fotos deben ser imágenes.',
            'fotos.*.max' => 'Cada foto puede pesar hasta 8 MB.',
        ]);

        // El condómino reporta en su condominio; el personal elige uno de los suyos.
        $empresaId = $esPersonal ? (int) $data['id_empresa'] : $casa?->id_empresa;
        abort_unless($empresaId && $usuario->puedeVerCondominio($empresaId), 403, 'No tienes acceso a ese condominio.');

        $incidencia = DB::transaction(function () use ($request, $data, $usuario, $casa, $empresaId, $esPersonal) {
            $incidencia = Incidencia::create([
                'id_empresa' => $empresaId,
                'folio' => Incidencia::siguienteFolio($empresaId),
                'tipo' => $data['tipo'],
                'categoria' => $data['categoria'],
                // La prioridad la decide el personal; los condóminos reportan en MEDIA.
                'prioridad' => $esPersonal ? ($data['prioridad'] ?? 'MEDIA') : 'MEDIA',
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'],
                'ubicacion' => $data['ubicacion'] ?? null,
                'latitud' => $data['latitud'] ?? null,
                'longitud' => $data['longitud'] ?? null,
                'id_usuario_reporta' => $usuario->id_usuario,
                'id_casa' => $casa?->id,
            ]);

            $seguimiento = $incidencia->seguimientos()->create([
                'id_usuario' => $usuario->id_usuario,
                'estatus_nuevo' => 'PENDIENTE',
                'comentario' => 'Reporte creado',
            ]);
            $this->guardarFotos($request, $incidencia, $seguimiento->id);

            return $incidencia;
        });

        return redirect()->route('incidencias.show', $incidencia->id)
            ->with('success', "Reporte {$incidencia->folio} registrado. Lo verá la administración y el equipo de mantenimiento.");
    }

    public function show(Incidencia $incidencia)
    {
        $this->autorizar($incidencia);
        $usuario = auth()->user();

        $incidencia->load([
            'condominio:id,nombre_empresa',
            'casa:id,identificador',
            'reporta:id_usuario,nombre_usuario,nombre_completo,tipo_usuario',
            'asignado:id_usuario,nombre_usuario,nombre_completo',
            'seguimientos.usuario:id_usuario,nombre_usuario,tipo_usuario',
            'seguimientos.fotos',
            'seguimientos.gastos.poliza:id,folio',
            'seguimientos.gastos.modifico:id_usuario,nombre_usuario',
        ]);

        $puedeAtender = Gate::allows('atender-incidencias');
        $puedeSupervisar = Gate::allows('supervisar-incidencias');

        return Inertia::render('Incidencias/Show', [
            'incidencia' => $incidencia,
            'totalGastos' => (float) $incidencia->gastos()->where('cancelado', false)->sum('monto'),
            'transiciones' => $incidencia->transicionesPara($usuario, $puedeAtender, $puedeSupervisar),
            'puedeAtender' => $puedeAtender,
            'puedeSupervisar' => $puedeSupervisar,
            // Los gastos y montos los ve todo el condominio (transparencia), pero
            // sólo la administración liga pólizas o cancela gastos.
            'personalMantenimiento' => $puedeSupervisar ? $this->personalMantenimiento([$incidencia->id_empresa]) : [],
            'catalogos' => $this->catalogos(),
            // Materiales del almacén usados en la reparación (salidas del inventario).
            'materiales' => InsumoMovimiento::where('id_incidencia', $incidencia->id)
                ->with(['insumo:id,nombre,unidad', 'usuario:id_usuario,nombre_usuario'])
                ->oldest()->get(),
            'insumosDisponibles' => $puedeAtender && Gate::allows('usar-inventario')
                ? Insumo::where('id_empresa', $incidencia->id_empresa)->where('activo', true)->where('existencia', '>', 0)
                    ->orderBy('nombre')->get(['id', 'nombre', 'unidad', 'existencia'])
                : [],
        ]);
    }

    /**
     * Comentario y/o cambio de estatus, con fotos y (para mantenimiento /
     * administración) gastos de la reparación.
     */
    public function seguimiento(Request $request, Incidencia $incidencia)
    {
        $this->autorizar($incidencia);
        $usuario = auth()->user();
        $puedeAtender = Gate::allows('atender-incidencias');
        $permitidas = $incidencia->transicionesPara($usuario, $puedeAtender, Gate::allows('supervisar-incidencias'));
        $cerrada = in_array($incidencia->estatus, ['SUPERVISADA', 'CANCELADA'], true);

        $data = $request->validate([
            'estatus_nuevo' => ['nullable', Rule::in($permitidas)],
            'comentario' => ['nullable', 'string', 'max:3000'],
            'fotos' => ['nullable', 'array', 'max:5'],
            'fotos.*' => ['image', 'max:8192'],
            'gastos' => ['nullable', 'array', 'max:10'],
            'gastos.*.concepto' => ['required', 'string', 'max:150'],
            'gastos.*.monto' => ['required', 'numeric', 'min:0.01'],
            'gastos.*.proveedor' => ['nullable', 'string', 'max:150'],
            'insumos' => ['nullable', 'array', 'max:20'],
            'insumos.*.id_insumo' => ['required', 'integer', Rule::exists('insumos', 'id')->where('id_empresa', $incidencia->id_empresa)->where('activo', true)],
            'insumos.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ], [
            'insumos.*.id_insumo.required' => 'Elige el material usado.',
            'insumos.*.cantidad.gt' => 'La cantidad de material debe ser mayor a cero.',
            'estatus_nuevo.in' => 'No puedes mover la incidencia a ese estatus.',
            'gastos.*.concepto.required' => 'Cada gasto necesita concepto.',
            'gastos.*.monto.required' => 'Cada gasto necesita monto.',
            'gastos.*.monto.min' => 'El monto del gasto debe ser mayor a cero.',
            'fotos.max' => 'Máximo 5 fotos por seguimiento.',
            'fotos.*.image' => 'Las fotos deben ser imágenes.',
        ]);

        $nuevo = $data['estatus_nuevo'] ?? null;
        $comentario = trim($data['comentario'] ?? '');
        $hayGastos = !empty($data['gastos']);
        $hayInsumos = !empty($data['insumos']);

        abort_if($hayGastos && !$puedeAtender, 403, 'Sólo mantenimiento y administración registran gastos.');
        abort_if($hayInsumos && !($puedeAtender && Gate::allows('usar-inventario')), 403, 'No puedes registrar material del almacén.');
        abort_if($cerrada && !$nuevo, 422, 'La incidencia está cerrada; ya no recibe comentarios.');

        // Atender, cancelar o regresar a proceso requiere explicar qué pasó.
        if (in_array($nuevo, ['ATENDIDA', 'CANCELADA'], true)
            || ($nuevo === 'EN PROCESO' && $incidencia->estatus === 'ATENDIDA')
            || ($nuevo === 'PENDIENTE' && $incidencia->estatus === 'CANCELADA')) {
            $request->validate(['comentario' => ['required']], ['comentario.required' => 'Agrega un comentario que explique este cambio.']);
        }
        if (!$nuevo && $comentario === '' && !$request->hasFile('fotos') && !$hayGastos && !$hayInsumos) {
            return back()->withErrors(['comentario' => 'Escribe un comentario o agrega fotos, gastos o material.']);
        }

        DB::transaction(function () use ($request, $incidencia, $usuario, $nuevo, $comentario, $data) {
            $seguimiento = $incidencia->seguimientos()->create([
                'id_usuario' => $usuario->id_usuario,
                'estatus_anterior' => $nuevo ? $incidencia->estatus : null,
                'estatus_nuevo' => $nuevo,
                'comentario' => $comentario !== '' ? $comentario : null,
            ]);

            $this->guardarFotos($request, $incidencia, $seguimiento->id);

            foreach ($data['gastos'] ?? [] as $gasto) {
                $seguimiento->gastos()->create([
                    'id_incidencia' => $incidencia->id,
                    'concepto' => $gasto['concepto'],
                    'monto' => $gasto['monto'],
                    'proveedor' => $gasto['proveedor'] ?? null,
                    'id_usuario' => $usuario->id_usuario,
                ]);
            }

            // Material del almacén: sale del inventario ligado a esta incidencia.
            // Si no alcanza la existencia, se revierte todo el seguimiento.
            foreach ($data['insumos'] ?? [] as $i => $uso) {
                try {
                    app(Inventario::class)->salida(Insumo::findOrFail($uso['id_insumo']), (float) $uso['cantidad'], [
                        'motivo' => "INCIDENCIA {$incidencia->folio}: {$incidencia->titulo}",
                        'id_incidencia' => $incidencia->id,
                    ]);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(["insumos.{$i}.cantidad" => $e->errors()['cantidad'] ?? ['No hay suficiente material.']]);
                }
            }

            if ($nuevo) {
                $cambios = ['estatus' => $nuevo];
                match ($nuevo) {
                    'EN PROCESO' => $cambios += [
                        'fecha_inicio' => $incidencia->fecha_inicio ?? now(),
                        // Quien la toma queda asignado si nadie lo estaba.
                        'id_usuario_asignado' => $incidencia->id_usuario_asignado
                            ?? ($usuario->esMantenimiento() ? $usuario->id_usuario : null),
                    ],
                    'ATENDIDA' => $cambios['fecha_atencion'] = now(),
                    'SUPERVISADA' => $cambios['fecha_supervision'] = now(),
                    'CANCELADA' => $cambios['fecha_cancelacion'] = now(),
                    'PENDIENTE' => $cambios['fecha_cancelacion'] = null,
                };
                $incidencia->update($cambios);
            } else {
                $incidencia->touch();
            }
        });

        $mensaje = $nuevo
            ? "Incidencia {$incidencia->folio}: " . Incidencia::ESTATUS[$nuevo]
            : 'Seguimiento agregado.';

        return back()->with('success', $mensaje);
    }

    /** Asigna la incidencia a alguien de mantenimiento (o la libera). */
    public function asignar(Request $request, Incidencia $incidencia)
    {
        abort_unless(Gate::allows('supervisar-incidencias'), 403);
        $this->autorizar($incidencia);

        $permitidos = collect($this->personalMantenimiento([$incidencia->id_empresa]))->pluck('id')->all();
        $data = $request->validate([
            'id_usuario_asignado' => ['nullable', 'integer', Rule::in($permitidos)],
            'prioridad' => ['required', Rule::in(array_keys(Incidencia::PRIORIDADES))],
        ], ['id_usuario_asignado.in' => 'Esa persona no es de mantenimiento de este condominio.']);

        DB::transaction(function () use ($incidencia, $data) {
            $cambios = [];
            if ((int) $incidencia->id_usuario_asignado !== (int) ($data['id_usuario_asignado'] ?? 0)) {
                $nombre = $data['id_usuario_asignado']
                    ? Usuario::whereKey($data['id_usuario_asignado'])->value('nombre_usuario')
                    : null;
                $cambios[] = $nombre ? "ASIGNADA A {$nombre}" : 'SIN ASIGNAR';
            }
            if ($incidencia->prioridad !== $data['prioridad']) {
                $cambios[] = 'PRIORIDAD ' . $data['prioridad'];
            }
            if (!$cambios) {
                return;
            }

            $incidencia->update([
                'id_usuario_asignado' => $data['id_usuario_asignado'] ?? null,
                'prioridad' => $data['prioridad'],
            ]);
            $incidencia->seguimientos()->create([
                'id_usuario' => auth()->id(),
                'comentario' => implode(' · ', $cambios),
            ]);
        });

        return back()->with('success', 'Asignación actualizada.');
    }

    /** La administración liga un gasto a su póliza de egreso o lo cancela. */
    public function gasto(Request $request, IncidenciaGasto $gasto)
    {
        abort_unless(Gate::allows('supervisar-incidencias'), 403);
        $incidencia = Incidencia::findOrFail($gasto->id_incidencia);
        $this->autorizar($incidencia);

        // Se acepta 'ligar' o 'LIGAR'.
        $request->merge(['accion' => strtolower((string) $request->input('accion'))]);
        $data = $request->validate([
            'accion' => ['required', Rule::in(['ligar', 'cancelar'])],
            'id_poliza' => [
                'nullable', 'integer',
                Rule::exists('polizas', 'id')->where('id_empresa', $incidencia->id_empresa)->where('tipo_poliza', 'EGRESO'),
            ],
            'motivo' => ['required_if:accion,cancelar', 'nullable', 'string', 'max:255'],
        ], [
            'id_poliza.exists' => 'La póliza debe ser de egreso y del mismo condominio.',
            'motivo.required_if' => 'Indica el motivo de la cancelación.',
        ]);

        $data['accion'] === 'ligar'
            ? $gasto->update(['id_poliza' => $data['id_poliza'] ?? null, 'id_usuario_modifico' => auth()->id()])
            : $gasto->update(['cancelado' => true, 'motivo_cancelacion' => $data['motivo'], 'id_usuario_modifico' => auth()->id()]);

        return back()->with('success', $data['accion'] === 'ligar' ? 'Gasto ligado a la póliza.' : 'Gasto cancelado.');
    }

    /** Buscador de pólizas de egreso del condominio (para ligar gastos). */
    public function polizasEgreso(Request $request, Incidencia $incidencia)
    {
        abort_unless(Gate::allows('supervisar-incidencias'), 403);
        $this->autorizar($incidencia);

        return Poliza::where('id_empresa', $incidencia->id_empresa)
            ->where('tipo_poliza', 'EGRESO')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('folio', 'LIKE', '%' . $request->q . '%')
                ->orWhere('referencia', 'LIKE', '%' . $request->q . '%')))
            ->orderByDesc('fecha_poliza')
            ->limit(20)
            ->get(['id', 'folio', 'fecha_poliza', 'referencia'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'etiqueta' => trim("{$p->folio} · " . $p->fecha_poliza?->format('d/m/Y') . ($p->referencia ? " · {$p->referencia}" : '')),
            ]);
    }

    // ======================================================
    // 🔒 HELPERS
    // ======================================================

    private function autorizar(Incidencia $incidencia): void
    {
        abort_unless(Gate::allows('ver-incidencias'), 403);
        abort_unless(auth()->user()->puedeVerCondominio($incidencia->id_empresa), 403, 'No tienes acceso a esta incidencia.');
    }

    private function guardarFotos(Request $request, Incidencia $incidencia, int $idSeguimiento): void
    {
        foreach ($request->file('fotos', []) as $foto) {
            $incidencia->fotos()->create([
                'id_seguimiento' => $idSeguimiento,
                'ruta' => $foto->store("incidencias/{$incidencia->id}", 'public'),
                'nombre_original' => $foto->getClientOriginalName(),
                'id_usuario' => auth()->id(),
            ]);
        }
    }

    /** Usuarios de mantenimiento activos asignados a esos condominios. */
    private function personalMantenimiento(array $empresaIds): array
    {
        return Usuario::where('tipo_usuario', 'MANTENIMIENTO')
            ->where('activo', true)
            ->whereHas('empresas', fn ($q) => $q->whereIn('empresas.id', $empresaIds))
            ->orderBy('nombre_completo')
            ->get(['id_usuario', 'nombre_usuario', 'nombre_completo'])
            ->map(fn ($u) => ['id' => $u->id_usuario, 'nombre' => "{$u->nombre_completo} ({$u->nombre_usuario})"])
            ->all();
    }

    private function catalogos(): array
    {
        return [
            'tipos' => Incidencia::TIPOS,
            'categorias' => Incidencia::CATEGORIAS,
            'prioridades' => Incidencia::PRIORIDADES,
            'estatus' => Incidencia::ESTATUS,
        ];
    }
}
