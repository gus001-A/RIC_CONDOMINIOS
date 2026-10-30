<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Incidencia;
use App\Models\Insumo;
use App\Models\InsumoMovimiento;
use App\Models\Poliza;
use App\Services\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Inventario de insumos y materiales del condominio: catálogo, compras,
 * consumos y ajustes, con existencias al día y kárdex por insumo.
 */
class InventarioController extends Controller
{
    public function __construct(private Inventario $inventario)
    {
    }

    public function index()
    {
        if (!Gate::allows('ver-inventario')) {
            return redirect()->route('dashboard')->with('error', 'No tienes acceso al inventario');
        }

        return Inertia::render('Inventario/Index', [
            'condominios' => Empresa::whereIn('id', auth()->user()->idsCondominios())->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'categorias' => Insumo::CATEGORIAS,
            'unidades' => Insumo::UNIDADES,
            'tiposMovimiento' => InsumoMovimiento::TIPOS,
        ]);
    }

    /** Existencias al día (la vista lo consulta periódicamente). */
    public function lista(Request $request)
    {
        $empresaId = $this->empresa($request->input('id_empresa'));

        $insumos = Insumo::where('id_empresa', $empresaId)
            ->when(!$request->boolean('inactivos'), fn ($q) => $q->where('activo', true))
            ->with(['modifico:id_usuario,nombre_usuario'])
            ->addSelect(['ultimo_movimiento' => InsumoMovimiento::selectRaw('MAX(created_at)')->whereColumn('id_insumo', 'insumos.id')])
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'insumos' => $insumos,
            'totales' => [
                'insumos' => $insumos->where('activo', true)->count(),
                'bajo_minimo' => $insumos->where('activo', true)->where('bajo_minimo', true)->count(),
                'agotados' => $insumos->where('activo', true)->filter(fn ($i) => $i->existencia <= 0)->count(),
                'valor' => round($insumos->sum(fn ($i) => max(0, $i->existencia) * $i->costo_promedio), 2),
            ],
        ]);
    }

    public function guardar(Request $request, ?Insumo $insumo = null)
    {
        abort_unless(Gate::allows('gestionar-inventario'), 403);
        if ($insumo?->exists) {
            $this->empresa($insumo->id_empresa);
        }

        $empresaId = $insumo?->exists ? $insumo->id_empresa : $this->empresa($request->input('id_empresa'));
        $data = $request->validate([
            'clave' => ['nullable', 'string', 'max:30', Rule::unique('insumos', 'clave')->where('id_empresa', $empresaId)->ignore($insumo?->id)],
            'nombre' => ['required', 'string', 'max:150'],
            'categoria' => ['required', Rule::in(array_keys(Insumo::CATEGORIAS))],
            'unidad' => ['required', Rule::in(array_keys(Insumo::UNIDADES))],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'ubicacion' => ['nullable', 'string', 'max:100'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'activo' => ['boolean'],
        ], [
            'nombre.required' => 'Captura el nombre del insumo.',
            'clave.unique' => 'Ya existe un insumo con esa clave en el condominio.',
        ]);

        $data['id_usuario_modifico'] = auth()->id();
        if ($insumo?->exists) {
            $insumo->update($data);
        } else {
            $insumo = Insumo::create($data + ['id_empresa' => $empresaId, 'id_usuario_registro' => auth()->id()]);
        }

        return response()->json(['insumo' => $insumo->fresh()]);
    }

    /** Entrada (compra), salida (uso) o ajuste por conteo físico. */
    public function movimiento(Request $request, Insumo $insumo)
    {
        $this->empresa($insumo->id_empresa);
        abort_unless($insumo->activo, 422, 'El insumo está inactivo.');

        $tipo = strtoupper((string) $request->input('tipo'));
        abort_unless(match ($tipo) {
            'SALIDA' => Gate::allows('usar-inventario'),
            'ENTRADA', 'AJUSTE' => Gate::allows('gestionar-inventario'),
            default => false,
        }, 403, 'No puedes registrar ese movimiento.');

        $data = $request->validate([
            'cantidad' => ['required', 'numeric', $tipo === 'AJUSTE' ? 'min:0' : 'gt:0'],
            'costo_unitario' => [$tipo === 'ENTRADA' ? 'required' : 'nullable', 'numeric', 'min:0'],
            'proveedor' => ['nullable', 'string', 'max:150'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'motivo' => [$tipo === 'ENTRADA' ? 'nullable' : 'required', 'string', 'max:255'],
            'id_poliza' => ['nullable', 'integer', Rule::exists('polizas', 'id')->where('id_empresa', $insumo->id_empresa)->where('tipo_poliza', 'EGRESO')],
            'id_incidencia' => ['nullable', 'integer', Rule::exists('incidencias', 'id')->where('id_empresa', $insumo->id_empresa)],
        ], [
            'cantidad.gt' => 'La cantidad debe ser mayor a cero.',
            'costo_unitario.required' => 'Captura el costo unitario de la compra.',
            'motivo.required' => $tipo === 'AJUSTE' ? 'Explica el motivo del ajuste.' : '¿En qué se usó?',
            'id_poliza.exists' => 'La póliza debe ser de egreso y del mismo condominio.',
        ]);

        $extra = collect($data)->only(['proveedor', 'referencia', 'motivo', 'id_poliza', 'id_incidencia'])->all();
        $movimiento = match ($tipo) {
            'ENTRADA' => $this->inventario->entrada($insumo, (float) $data['cantidad'], (float) $data['costo_unitario'], $extra),
            'SALIDA' => $this->inventario->salida($insumo, (float) $data['cantidad'], $extra),
            'AJUSTE' => $this->inventario->ajuste($insumo, (float) $data['cantidad'], $extra),
        };

        return response()->json([
            'insumo' => $insumo->fresh(),
            'sin_cambio' => $movimiento === null,
        ]);
    }

    /** Kárdex de un insumo. */
    public function kardex(Insumo $insumo)
    {
        $this->empresa($insumo->id_empresa);

        return response()->json([
            'insumo' => $insumo,
            'movimientos' => $insumo->movimientos()
                ->with(['usuario:id_usuario,nombre_usuario', 'poliza:id,folio', 'incidencia:id,folio,titulo'])
                ->latest()->latest('id')->limit(300)->get(),
        ]);
    }

    /** Apoyos para los formularios: pólizas de egreso e incidencias abiertas. */
    public function referencias(Request $request)
    {
        $empresaId = $this->empresa($request->input('id_empresa'));

        return response()->json([
            'polizas' => Gate::allows('gestionar-inventario')
                ? Poliza::where('id_empresa', $empresaId)->where('tipo_poliza', 'EGRESO')
                    ->orderByDesc('fecha_poliza')->limit(50)->get(['id', 'folio', 'fecha_poliza', 'referencia'])
                    ->map(fn ($p) => ['id' => $p->id, 'etiqueta' => trim("{$p->folio} · " . $p->fecha_poliza?->format('d/m/Y') . ($p->referencia ? " · {$p->referencia}" : ''))])
                : [],
            'incidencias' => Incidencia::where('id_empresa', $empresaId)->whereIn('estatus', ['PENDIENTE', 'EN PROCESO', 'ATENDIDA'])
                ->latest()->limit(50)->get(['id', 'folio', 'titulo'])
                ->map(fn ($i) => ['id' => $i->id, 'etiqueta' => "{$i->folio} · {$i->titulo}"]),
        ]);
    }

    private function empresa($empresaId): int
    {
        abort_unless(Gate::allows('ver-inventario'), 403);
        abort_unless($empresaId && auth()->user()->puedeVerCondominio($empresaId), 403, 'No tienes acceso a ese condominio.');

        return (int) $empresaId;
    }
}
