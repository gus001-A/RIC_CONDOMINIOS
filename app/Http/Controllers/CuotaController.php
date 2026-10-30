<?php

namespace App\Http\Controllers;

use App\Models\Casa;
use App\Models\CuotaCargo;
use App\Models\CuotaPago;
use App\Models\Empresa;
use App\Models\Poliza;
use App\Services\EstadoCuentaCuotas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Cuotas de mantenimiento: cargos mensuales por casa y pagos recibidos.
 * De aquí sale el estado de cuenta de cada casa y el listado de deudores.
 */
class CuotaController extends Controller
{
    public function index(Request $request, EstadoCuentaCuotas $estadoCuenta)
    {
        if (!Gate::allows('gestionar-cuotas')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para administrar cuotas');
        }

        $ids = auth()->user()->idsCondominios();
        $condominios = Empresa::whereIn('id', $ids)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']);

        $empresaId = (int) ($request->input('empresa_id') ?: session('empresa_transparencia') ?: ($condominios->first()->id ?? 0));
        if (!in_array($empresaId, $ids, true)) {
            $empresaId = (int) ($condominios->first()->id ?? 0);
        }
        session(['empresa_transparencia' => $empresaId]);

        $casas = collect();
        $totales = ['casas' => 0, 'cargos' => 0, 'pagos' => 0, 'saldo' => 0, 'deudoras' => 0];
        $ultimoPeriodo = null;

        if ($empresaId) {
            $resumen = $estadoCuenta->resumenCondominio($empresaId);
            $casas = Casa::where('id_empresa', $empresaId)
                ->with('responsable')
                ->orderByDesc('activo')
                ->orderBy('identificador')
                ->get()
                ->map(fn ($c) => array_merge([
                    'id' => $c->id,
                    'identificador' => $c->identificador,
                    'activo' => $c->activo,
                    'cuota' => $c->cuota_mantenimiento !== null ? (float) $c->cuota_mantenimiento : null,
                    'responsable' => $c->responsable?->nombre_completo,
                    'telefono' => $c->responsable?->telefono,
                ], $resumen->get($c->id)));

            $totales = [
                'casas' => $casas->count(),
                'cargos' => round($casas->sum('cargos'), 2),
                'pagos' => round($casas->sum('pagos'), 2),
                'saldo' => round($casas->sum('saldo'), 2),
                'deudoras' => $casas->where('saldo_vencido', '>', 0.009)->count(),
            ];

            $ultimoPeriodo = CuotaCargo::where('id_empresa', $empresaId)->vigentes()->max('periodo');
        }

        return Inertia::render('Cuotas/Index', [
            'condominios' => $condominios,
            'empresaSeleccionada' => $empresaId ?: null,
            'casas' => $casas->values(),
            'totales' => $totales,
            'ultimoPeriodo' => $ultimoPeriodo ? Carbon::parse($ultimoPeriodo)->format('Y-m') : null,
            'formasPago' => CuotaPago::FORMAS_PAGO,
        ]);
    }

    /**
     * Genera el cargo del periodo a cada casa activa con cuota. Si la casa ya
     * tiene ese cargo no lo duplica, así que se puede volver a correr sin riesgo.
     */
    public function generarCargos(Request $request)
    {
        abort_unless(Gate::allows('gestionar-cuotas'), 403);

        $data = $request->validate([
            'empresa_id' => ['required', 'integer'],
            'periodo' => ['required', 'date_format:Y-m'],
            'dia_vencimiento' => ['required', 'integer', 'between:1,28'],
        ]);
        $this->autorizarCondominio($data['empresa_id']);

        $periodo = Carbon::createFromFormat('Y-m', $data['periodo'])->startOfMonth();
        $vence = $periodo->copy()->day($data['dia_vencimiento']);

        $creados = 0;
        $omitidas = 0;
        DB::transaction(function () use ($data, $periodo, $vence, &$creados, &$omitidas) {
            $casas = Casa::where('id_empresa', $data['empresa_id'])->where('activo', true)->get();
            foreach ($casas as $casa) {
                if (!$casa->cuota_mantenimiento || $casa->cuota_mantenimiento <= 0) {
                    $omitidas++;
                    continue;
                }
                $cargo = CuotaCargo::firstOrCreate(
                    ['id_casa' => $casa->id, 'periodo' => $periodo->toDateString(), 'concepto' => 'Cuota de mantenimiento'],
                    [
                        'id_empresa' => $casa->id_empresa,
                        'monto' => $casa->cuota_mantenimiento,
                        'fecha_vencimiento' => $vence->toDateString(),
                        'id_usuario_registro' => auth()->id(),
                        'id_usuario_modifico' => auth()->id(),
                    ]
                );
                $cargo->wasRecentlyCreated ? $creados++ : $omitidas++;
            }
        });

        $mes = app(EstadoCuentaCuotas::class)->nombrePeriodo($periodo);
        $mensaje = "Cargos de {$mes}: {$creados} generado(s)";
        if ($omitidas) {
            $mensaje .= ", {$omitidas} casa(s) omitida(s) (ya tenían el cargo o no tienen cuota)";
        }

        return back()->with('success', $mensaje . '.');
    }

    public function storePago(Request $request, Casa $casa)
    {
        abort_unless(Gate::allows('gestionar-cuotas'), 403);
        $this->autorizarCondominio($casa->id_empresa);

        $data = $request->validate([
            'fecha_pago' => ['required', 'date', 'before_or_equal:today'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'forma_pago' => ['required', Rule::in(array_keys(CuotaPago::FORMAS_PAGO))],
            'referencia' => ['nullable', 'string', 'max:100'],
            'id_poliza' => [
                'nullable', 'integer',
                Rule::exists('polizas', 'id')->where('id_empresa', $casa->id_empresa)->where('tipo_poliza', 'INGRESO'),
            ],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [
            'monto.min' => 'El monto debe ser mayor a cero.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'id_poliza.exists' => 'La póliza debe ser de ingreso y del mismo condominio.',
        ]);

        $casa->pagosCuota()->create($data + [
            'id_empresa' => $casa->id_empresa,
            'id_usuario_registro' => auth()->id(),
            'id_usuario_modifico' => auth()->id(),
        ]);

        return back()->with('success', "Pago registrado a la casa {$casa->identificador}.");
    }

    /** Cancela un pago o un cargo (no se borran: queda la trazabilidad). */
    public function cancelar(Request $request, string $tipo, int $id)
    {
        abort_unless(Gate::allows('gestionar-cuotas'), 403);

        $modelo = match ($tipo) {
            'pago' => CuotaPago::findOrFail($id),
            'cargo' => CuotaCargo::findOrFail($id),
            default => abort(404),
        };
        $this->autorizarCondominio($modelo->id_empresa);

        $data = $request->validate(
            ['motivo' => ['required', 'string', 'max:255']],
            ['motivo.required' => 'Indica el motivo de la cancelación.']
        );

        $modelo->update([
            'cancelado' => true,
            'motivo_cancelacion' => $data['motivo'],
            'id_usuario_modifico' => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Estado de cuenta de una casa (JSON). Lo ve la administración del
     * condominio y el condómino de esa casa.
     */
    public function estadoCuenta(Casa $casa, EstadoCuentaCuotas $estadoCuenta)
    {
        $condomino = auth()->user()->condomino;
        $esSuCasa = auth()->user()->esCondomino() && $condomino?->activo && $condomino->id_casa === $casa->id;

        if (!$esSuCasa) {
            abort_unless(Gate::allows('ver-transparencia') && !auth()->user()->esCondomino(), 403);
            $this->autorizarCondominio($casa->id_empresa);
        }

        return response()->json($estadoCuenta->detalleCasa($casa) + [
            'casa' => $casa->only(['id', 'identificador', 'cuota_mantenimiento']),
        ]);
    }

    /** Pólizas de ingreso del condominio para ligar un pago (buscador). */
    public function polizasIngreso(Request $request)
    {
        abort_unless(Gate::allows('gestionar-cuotas'), 403);
        $empresaId = (int) $request->input('empresa_id');
        $this->autorizarCondominio($empresaId);

        return Poliza::where('id_empresa', $empresaId)
            ->where('tipo_poliza', 'INGRESO')
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

    private function autorizarCondominio($empresaId): void
    {
        abort_unless(auth()->user()->puedeVerCondominio($empresaId), 403, 'No tienes acceso a ese condominio.');
    }
}
