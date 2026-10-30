<?php

namespace App\Http\Controllers;

use App\Models\Casa;
use App\Models\Empresa;
use App\Models\Poliza;
use App\Models\PolizaAnotacion;
use App\Models\PolizaArchivo;
use App\Services\EstadoCuentaCuotas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Transparencia contable del condominio (sólo lectura):
 *  - pólizas con sus movimientos, comprobantes y anotaciones
 *  - estado de resultados (reutiliza ReporteController@getMovimientos)
 *  - listado de deudores de cuotas
 *
 * Las anotaciones las hacen condóminos y administración; la administración
 * responde y las marca como resueltas. Todo queda con usuario, fecha, hora y
 * (para condóminos) la casa desde la que se anotó.
 */
class TransparenciaController extends Controller
{
    public function index(Request $request)
    {
        if (!Gate::allows('ver-transparencia')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para ver la transparencia contable');
        }

        $ids = auth()->user()->idsCondominios();
        $condominios = Empresa::whereIn('id', $ids)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']);

        $seleccionado = (int) ($request->input('empresa_id') ?: session('empresa_transparencia') ?: ($condominios->first()->id ?? 0));
        if (!in_array($seleccionado, $ids, true)) {
            $seleccionado = (int) ($condominios->first()->id ?? 0);
        }
        session(['empresa_transparencia' => $seleccionado]);

        return Inertia::render('Transparencia/Index', [
            'condominios' => $condominios,
            'empresaSeleccionada' => $seleccionado ?: null,
            'tiposAnotacion' => PolizaAnotacion::TIPOS,
            'casaPropia' => auth()->user()->condomino?->casa?->only(['id', 'identificador']),
            'tab' => $request->input('tab', 'polizas'),
        ]);
    }

    /**
     * Listado de pólizas (JSON, paginado) con totales y contador de anotaciones.
     */
    public function polizas(Request $request)
    {
        $empresaId = $this->empresaAutorizada($request->input('empresa_id'));

        $query = Poliza::where('polizas.id_empresa', $empresaId)
            ->with(['persona:id_persona,Nombre,Paterno,Materno,razon_social,tipo_persona'])
            ->withCount([
                'anotaciones as anotaciones_total' => fn ($q) => $q->whereNull('id_padre'),
                'anotaciones as anotaciones_abiertas' => fn ($q) => $q->whereNull('id_padre')->where('resuelta', false),
                'archivos',
            ])
            ->addSelect(['total' => DB::table('movimientos_poliza')
                ->selectRaw('COALESCE(SUM(monto), 0)')
                ->whereColumn('movimientos_poliza.id_poliza', 'polizas.id')]);

        if ($request->filled('folio')) {
            $query->where('folio', 'LIKE', '%' . $request->folio . '%');
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_poliza', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_poliza', '<=', $request->fecha_hasta);
        }
        if ($request->filled('tipo_poliza')) {
            $query->where('tipo_poliza', $request->tipo_poliza);
        }
        if ($request->filled('concepto')) {
            $query->where(fn ($q) => $q->where('referencia', 'LIKE', '%' . $request->concepto . '%')
                ->orWhere('nota', 'LIKE', '%' . $request->concepto . '%'));
        }
        // Un solo filtro para la columna agrupada "Concepto / proveedor" (también busca el folio).
        if ($request->filled('texto')) {
            $buscar = '%' . $request->texto . '%';
            $query->where(fn ($q) => $q->where('folio', 'LIKE', $buscar)
                ->orWhere('referencia', 'LIKE', $buscar)
                ->orWhere('nota', 'LIKE', $buscar)
                ->orWhereHas('persona', fn ($p) => $p->where(DB::raw("CONCAT_WS(' ', Nombre, Paterno, Materno, razon_social)"), 'LIKE', $buscar)));
        }
        if ($request->filled('persona')) {
            $query->whereHas('persona', fn ($q) => $q->where(DB::raw("CONCAT_WS(' ', Nombre, Paterno, Materno, razon_social)"), 'LIKE', '%' . $request->persona . '%'));
        }
        if ($request->filled('estatus')) {
            $query->where('estatus', $request->estatus);
        }
        if ($request->filled('anotaciones')) {
            match ($request->anotaciones) {
                'abiertas' => $query->whereHas('anotaciones', fn ($q) => $q->whereNull('id_padre')->where('resuelta', false)),
                'con' => $query->whereHas('anotaciones', fn ($q) => $q->whereNull('id_padre')),
                'sin' => $query->whereDoesntHave('anotaciones'),
                default => null,
            };
        }

        $polizas = $query->orderByDesc('fecha_poliza')->orderByDesc('id')->paginate(20)->withQueryString();

        $polizas->through(fn ($p) => [
            'id' => $p->id,
            'folio' => $p->folio,
            'fecha_poliza' => $p->fecha_poliza?->format('Y-m-d'),
            'tipo_poliza' => $p->tipo_poliza,
            'categoria' => $p->categoria,
            'estatus' => $p->estatus,
            'estatus_texto' => $p->estatus_texto,
            'referencia' => $p->referencia,
            'nota' => $p->nota,
            'persona' => $p->persona?->nombre_completo,
            'total' => (float) $p->total,
            'archivos_count' => $p->archivos_count,
            'anotaciones_total' => $p->anotaciones_total,
            'anotaciones_abiertas' => $p->anotaciones_abiertas,
        ]);

        return response()->json([
            'polizas' => $polizas,
            'pendientes' => PolizaAnotacion::abiertas()
                ->whereHas('poliza', fn ($q) => $q->where('id_empresa', $empresaId))
                ->count(),
        ]);
    }

    /**
     * Detalle de una póliza: movimientos, comprobantes y anotaciones.
     */
    public function poliza(Poliza $poliza)
    {
        $this->empresaAutorizada($poliza->id_empresa);

        $poliza->load([
            'persona',
            'usuarioCreador:id_usuario,nombre_usuario',
            'movimientos.cuenta:id_cuenta,codigo_cuenta,nombre_cuenta',
            'movimientos.cuentaFondeadora:id_cuenta,codigo_cuenta,nombre_cuenta',
            'archivos:id,id_poliza,nombre_original,tipo_archivo,created_at',
            'abonos',
        ]);

        return response()->json([
            'poliza' => [
                'id' => $poliza->id,
                'folio' => $poliza->folio,
                'fecha_poliza' => $poliza->fecha_poliza?->format('Y-m-d'),
                'tipo_poliza' => $poliza->tipo_poliza,
                'categoria' => $poliza->categoria,
                'estatus_texto' => $poliza->estatus_texto,
                'referencia' => $poliza->referencia,
                'nota' => $poliza->nota,
                'persona' => $poliza->persona?->nombre_completo,
                'capturo' => $poliza->usuarioCreador?->nombre_usuario,
                'created_at' => $poliza->created_at,
                'total' => (float) $poliza->movimientos->sum('monto'),
                'abonado' => (float) $poliza->abonos->sum('monto_abonado'),
                'movimientos' => $poliza->movimientos->map(fn ($m) => [
                    'cuenta' => $m->cuenta ? "{$m->cuenta->codigo_cuenta} {$m->cuenta->nombre_cuenta}" : null,
                    'fondeadora' => $m->cuentaFondeadora?->nombre_cuenta,
                    'monto' => (float) $m->monto,
                    'iva' => (float) ($m->monto_iva ?: $m->iva_dieciseis),
                ]),
                'archivos' => $poliza->archivos->map(fn ($a) => [
                    'id' => $a->id,
                    'nombre' => $a->nombre_original,
                    'tipo' => $a->tipo_archivo,
                    'url' => route('transparencia.archivo', $a->id),
                ]),
            ],
            'anotaciones' => $this->hilo($poliza),
        ]);
    }

    /** Comprobante de la póliza, validando que sea del condominio del usuario. */
    public function archivo(PolizaArchivo $archivo)
    {
        $this->empresaAutorizada(Poliza::whereKey($archivo->id_poliza)->value('id_empresa'));

        abort_unless(Storage::disk('public')->exists($archivo->ruta), 404, 'Archivo no encontrado');

        return Storage::disk('public')->response($archivo->ruta, $archivo->nombre_original);
    }

    // ======================================================
    // 💬 ANOTACIONES
    // ======================================================

    public function anotar(Request $request, Poliza $poliza)
    {
        abort_unless(Gate::allows('anotar-polizas'), 403, 'No puedes hacer anotaciones');
        $this->empresaAutorizada($poliza->id_empresa);

        $data = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(PolizaAnotacion::TIPOS))],
            'texto' => ['required', 'string', 'max:2000'],
        ], ['texto.required' => 'Escribe la anotación.']);

        $casa = auth()->user()->condomino?->casa;

        $poliza->anotaciones()->create($data + [
            'id_usuario' => auth()->id(),
            'id_casa' => $casa?->id,
            'casa_identificador' => $casa?->identificador,
        ]);

        return response()->json(['anotaciones' => $this->hilo($poliza)]);
    }

    /** Respuesta a una anotación (administración o el mismo condómino). */
    public function responder(Request $request, PolizaAnotacion $anotacion)
    {
        abort_unless(Gate::allows('anotar-polizas'), 403, 'No puedes responder anotaciones');
        abort_if($anotacion->id_padre !== null, 422, 'Sólo se responde a la anotación original.');
        $poliza = $anotacion->poliza;
        $this->empresaAutorizada($poliza->id_empresa);

        $data = $request->validate(['texto' => ['required', 'string', 'max:2000']], ['texto.required' => 'Escribe la respuesta.']);
        $casa = auth()->user()->condomino?->casa;

        $poliza->anotaciones()->create([
            'id_padre' => $anotacion->id,
            'id_usuario' => auth()->id(),
            'id_casa' => $casa?->id,
            'casa_identificador' => $casa?->identificador,
            'tipo' => 'RESPUESTA',
            'texto' => $data['texto'],
        ]);

        return response()->json(['anotaciones' => $this->hilo($poliza)]);
    }

    /** Marca una anotación como resuelta (o la reabre). Sólo administración. */
    public function resolver(PolizaAnotacion $anotacion)
    {
        abort_unless(Gate::allows('resolver-anotaciones'), 403, 'Sólo la administración resuelve anotaciones');
        abort_if($anotacion->id_padre !== null, 422);
        $this->empresaAutorizada($anotacion->poliza->id_empresa);

        $resuelta = !$anotacion->resuelta;
        $anotacion->update([
            'resuelta' => $resuelta,
            'id_usuario_resolvio' => $resuelta ? auth()->id() : null,
            'fecha_resolucion' => $resuelta ? now() : null,
        ]);

        return response()->json(['anotaciones' => $this->hilo($anotacion->poliza)]);
    }

    // ======================================================
    // 💰 DEUDORES
    // ======================================================

    /**
     * Casas con saldo pendiente. Los condóminos ven casa, saldo y cargos
     * vencidos; el personal ve además responsable y teléfono.
     */
    public function deudores(Request $request, EstadoCuentaCuotas $estadoCuenta)
    {
        $empresaId = $this->empresaAutorizada($request->input('empresa_id'));
        $esPersonal = !auth()->user()->esCondomino();

        $resumen = $estadoCuenta->resumenCondominio($empresaId);
        $casas = Casa::where('id_empresa', $empresaId)
            ->where('activo', true)
            ->with('responsable')
            ->orderBy('identificador')
            ->get();

        $deudores = $casas
            ->map(function ($casa) use ($resumen, $esPersonal) {
                $r = $resumen->get($casa->id);
                return array_merge([
                    'id_casa' => $casa->id,
                    'identificador' => $casa->identificador,
                    'direccion' => $casa->direccion_corta,
                    'cuota' => $casa->cuota_mantenimiento !== null ? (float) $casa->cuota_mantenimiento : null,
                ], $r, $esPersonal ? [
                    'responsable' => $casa->responsable?->nombre_completo,
                    'telefono' => $casa->responsable?->telefono,
                ] : []);
            })
            // Deudor = tiene cargos vencidos sin pagar (no cuenta el mes en curso aún no vencido).
            ->filter(fn ($d) => $d['saldo_vencido'] > 0.009)
            ->sortByDesc('saldo_vencido')
            ->values();

        return response()->json([
            'deudores' => $deudores,
            'totales' => [
                'casas' => $casas->count(),
                'deudoras' => $deudores->count(),
                'adeudo' => round($deudores->sum('saldo_vencido'), 2),
                'tres_o_mas' => $deudores->where('cargos_vencidos', '>=', 3)->count(),
            ],
        ]);
    }

    // ======================================================
    // 🔒 HELPERS
    // ======================================================

    private function empresaAutorizada($empresaId): int
    {
        abort_unless(Gate::allows('ver-transparencia'), 403);
        abort_unless($empresaId && auth()->user()->puedeVerCondominio($empresaId), 403, 'No tienes acceso a ese condominio.');

        return (int) $empresaId;
    }

    private function hilo(Poliza $poliza)
    {
        return PolizaAnotacion::raiz()
            ->where('id_poliza', $poliza->id)
            ->with([
                'usuario:id_usuario,nombre_usuario,nombre_completo,tipo_usuario',
                'resolvio:id_usuario,nombre_usuario',
                'respuestas.usuario:id_usuario,nombre_usuario,nombre_completo,tipo_usuario',
            ])
            ->latest()
            ->get();
    }
}
