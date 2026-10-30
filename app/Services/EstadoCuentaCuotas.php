<?php

namespace App\Services;

use App\Models\Casa;
use App\Models\CuotaCargo;
use App\Models\CuotaPago;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Estado de cuenta de cuotas por casa.
 *
 * Los pagos se aplican a los cargos más antiguos primero (FIFO); así se sabe
 * desde qué periodo debe la casa y cuántos cargos tiene vencidos.
 */
class EstadoCuentaCuotas
{
    /**
     * Resumen de todas las casas de un condominio:
     *   [id_casa => ['cargos','pagos','saldo','cargos_vencidos','adeudo_desde','ultimo_pago']]
     */
    public function resumenCondominio(int $empresaId): Collection
    {
        $cargos = CuotaCargo::vigentes()
            ->where('id_empresa', $empresaId)
            ->orderBy('periodo')
            ->orderBy('id')
            ->get(['id', 'id_casa', 'periodo', 'monto', 'fecha_vencimiento'])
            ->groupBy('id_casa');

        $pagos = CuotaPago::vigentes()
            ->where('id_empresa', $empresaId)
            ->get(['id_casa', 'monto', 'fecha_pago'])
            ->groupBy('id_casa');

        $casas = Casa::where('id_empresa', $empresaId)->pluck('id');

        return $casas->mapWithKeys(fn ($idCasa) => [
            $idCasa => $this->calcular($cargos->get($idCasa, collect()), $pagos->get($idCasa, collect())),
        ]);
    }

    /**
     * Estado de cuenta detallado de una casa: movimientos en orden cronológico
     * con saldo corrido, más el resumen.
     */
    public function detalleCasa(Casa $casa): array
    {
        $cargos = CuotaCargo::where('id_casa', $casa->id)
            ->with(['registro:id_usuario,nombre_usuario', 'modifico:id_usuario,nombre_usuario'])
            ->orderBy('periodo')->orderBy('id')->get();
        $pagos = CuotaPago::where('id_casa', $casa->id)
            ->with(['registro:id_usuario,nombre_usuario', 'modifico:id_usuario,nombre_usuario', 'poliza:id,folio'])
            ->orderBy('fecha_pago')->orderBy('id')->get();

        $movimientos = collect()
            ->concat($cargos->map(fn ($c) => [
                'tipo' => 'CARGO',
                'id' => $c->id,
                'fecha' => $c->periodo->format('Y-m-d'),
                'concepto' => $c->concepto . ' ' . $this->nombrePeriodo($c->periodo),
                'cargo' => (float) $c->monto,
                'abono' => 0.0,
                'cancelado' => $c->cancelado,
                'motivo_cancelacion' => $c->motivo_cancelacion,
                'registro' => $c->registro?->nombre_usuario,
                'created_at' => $c->created_at,
                'modifico' => $c->modifico?->nombre_usuario,
                'updated_at' => $c->updated_at,
            ]))
            ->concat($pagos->map(fn ($p) => [
                'tipo' => 'PAGO',
                'id' => $p->id,
                'fecha' => $p->fecha_pago->format('Y-m-d'),
                'concepto' => 'PAGO ' . $p->forma_pago
                    . ($p->referencia ? ' · REF. ' . $p->referencia : '')
                    . ($p->poliza ? ' · PÓLIZA ' . $p->poliza->folio : ''),
                'cargo' => 0.0,
                'abono' => (float) $p->monto,
                'cancelado' => $p->cancelado,
                'motivo_cancelacion' => $p->motivo_cancelacion,
                'registro' => $p->registro?->nombre_usuario,
                'created_at' => $p->created_at,
                'modifico' => $p->modifico?->nombre_usuario,
                'updated_at' => $p->updated_at,
            ]))
            ->sortBy([['fecha', 'asc'], ['tipo', 'asc']])
            ->values();

        $saldo = 0.0;
        $movimientos = $movimientos->map(function ($m) use (&$saldo) {
            if (!$m['cancelado']) {
                $saldo += $m['cargo'] - $m['abono'];
            }
            return $m + ['saldo' => round($saldo, 2)];
        });

        return [
            'movimientos' => $movimientos,
            'resumen' => $this->calcular($cargos->where('cancelado', false), $pagos->where('cancelado', false)),
        ];
    }

    private function calcular(Collection $cargos, Collection $pagos): array
    {
        $totalCargos = round((float) $cargos->sum('monto'), 2);
        $totalPagos = round((float) $pagos->sum('monto'), 2);

        // FIFO: el dinero pagado cubre los cargos más viejos.
        $disponible = $totalPagos;
        $adeudoDesde = null;
        $vencidos = 0;
        $saldoVencido = 0.0;
        $hoy = Carbon::today();

        foreach ($cargos->sortBy('periodo') as $cargo) {
            $monto = (float) $cargo->monto;
            if ($disponible >= $monto - 0.009) {
                $disponible -= $monto;
                continue;
            }
            $pendiente = $monto - $disponible;
            $disponible = 0;
            $adeudoDesde ??= $cargo->periodo;
            $vence = $cargo->fecha_vencimiento ?? $cargo->periodo->copy()->endOfMonth();
            if ($vence->lt($hoy)) {
                $vencidos++;
                $saldoVencido += $pendiente;
            }
        }

        return [
            'cargos' => $totalCargos,
            'pagos' => $totalPagos,
            'saldo' => round($totalCargos - $totalPagos, 2),
            // Sólo lo que ya pasó su fecha de vencimiento: eso es ser deudor.
            'saldo_vencido' => round($saldoVencido, 2),
            'cargos_vencidos' => $vencidos,
            'adeudo_desde' => $adeudoDesde?->format('Y-m-d'),
            'ultimo_pago' => optional($pagos->sortByDesc('fecha_pago')->first())->fecha_pago?->format('Y-m-d'),
        ];
    }

    /**
     * Antigüedad de saldos de cada casa al día de hoy (mismo criterio FIFO):
     *   [id_casa => ['por_vencer','d1_30','d31_60','d61_90','mas_90','total']]
     */
    public function antiguedadCondominio(int $empresaId): Collection
    {
        $cargos = CuotaCargo::vigentes()->where('id_empresa', $empresaId)
            ->orderBy('periodo')->orderBy('id')
            ->get(['id', 'id_casa', 'periodo', 'monto', 'fecha_vencimiento'])
            ->groupBy('id_casa');
        $pagos = CuotaPago::vigentes()->where('id_empresa', $empresaId)
            ->selectRaw('id_casa, SUM(monto) as total')->groupBy('id_casa')->pluck('total', 'id_casa');
        $hoy = Carbon::today();

        return $cargos->map(function (Collection $deCasa, $idCasa) use ($pagos, $hoy) {
            $disponible = (float) ($pagos[$idCasa] ?? 0);
            $b = ['por_vencer' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'mas_90' => 0.0];
            foreach ($deCasa as $cargo) {
                $monto = (float) $cargo->monto;
                if ($disponible >= $monto - 0.009) {
                    $disponible -= $monto;
                    continue;
                }
                $pendiente = $monto - $disponible;
                $disponible = 0;
                $vence = $cargo->fecha_vencimiento ?? $cargo->periodo->copy()->endOfMonth();
                $dias = $vence->lt($hoy) ? (int) $vence->diffInDays($hoy) : 0;
                $clave = match (true) {
                    $dias === 0 => 'por_vencer',
                    $dias <= 30 => 'd1_30',
                    $dias <= 60 => 'd31_60',
                    $dias <= 90 => 'd61_90',
                    default => 'mas_90',
                };
                $b[$clave] += $pendiente;
            }
            $b = array_map(fn ($v) => round($v, 2), $b);

            return $b + ['total' => round(array_sum($b), 2)];
        });
    }

    public function nombrePeriodo(Carbon $periodo): string
    {
        $meses = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

        return $meses[$periodo->month - 1] . ' ' . $periodo->year;
    }
}
