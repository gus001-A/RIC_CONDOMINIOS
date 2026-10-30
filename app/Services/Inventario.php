<?php

namespace App\Services;

use App\Models\Insumo;
use App\Models\InsumoMovimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único lugar donde cambia la existencia de un insumo. Cada cambio deja su
 * renglón en el kárdex (insumo_movimientos) con la existencia resultante.
 *
 * El insumo se bloquea (lockForUpdate) durante el movimiento para que dos
 * salidas simultáneas no dejen el almacén en negativo.
 */
class Inventario
{
    /** Compra: suma existencia y recalcula el costo promedio ponderado. */
    public function entrada(Insumo $insumo, float $cantidad, float $costoUnitario, array $datos = []): InsumoMovimiento
    {
        return DB::transaction(function () use ($insumo, $cantidad, $costoUnitario, $datos) {
            $insumo = Insumo::whereKey($insumo->id)->lockForUpdate()->firstOrFail();

            $existenciaAntes = max(0, $insumo->existencia);
            $nueva = $existenciaAntes + $cantidad;
            $insumo->costo_promedio = $nueva > 0
                ? (($existenciaAntes * $insumo->costo_promedio) + ($cantidad * $costoUnitario)) / $nueva
                : $costoUnitario;

            return $this->registrar($insumo, 'ENTRADA', $cantidad, $datos + ['costo_unitario' => $costoUnitario]);
        });
    }

    /** Uso del material (mantenimiento, incidencia…). No permite dejar existencia negativa. */
    public function salida(Insumo $insumo, float $cantidad, array $datos = []): InsumoMovimiento
    {
        return DB::transaction(function () use ($insumo, $cantidad, $datos) {
            $insumo = Insumo::whereKey($insumo->id)->lockForUpdate()->firstOrFail();

            if ($cantidad > $insumo->existencia + 0.0005) {
                throw ValidationException::withMessages([
                    'cantidad' => sprintf('Sólo hay %s %s(s) de %s.', $this->numero($insumo->existencia), mb_strtolower($insumo->unidad), $insumo->nombre),
                ]);
            }

            return $this->registrar($insumo, 'SALIDA', -$cantidad, $datos + ['costo_unitario' => $insumo->costo_promedio]);
        });
    }

    /** Conteo físico: deja la existencia en lo que realmente hay. */
    public function ajuste(Insumo $insumo, float $existenciaReal, array $datos = []): ?InsumoMovimiento
    {
        return DB::transaction(function () use ($insumo, $existenciaReal, $datos) {
            $insumo = Insumo::whereKey($insumo->id)->lockForUpdate()->firstOrFail();
            $diferencia = round($existenciaReal - $insumo->existencia, 3);
            if (abs($diferencia) < 0.0005) {
                return null;
            }

            return $this->registrar($insumo, 'AJUSTE', $diferencia, $datos + ['costo_unitario' => $insumo->costo_promedio]);
        });
    }

    private function registrar(Insumo $insumo, string $tipo, float $cantidad, array $datos): InsumoMovimiento
    {
        $insumo->existencia = round($insumo->existencia + $cantidad, 3);
        $insumo->id_usuario_modifico = auth()->id();
        $insumo->save();

        return $insumo->movimientos()->create([
            'id_empresa' => $insumo->id_empresa,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'existencia_resultante' => $insumo->existencia,
            'id_usuario' => auth()->id(),
        ] + array_intersect_key($datos, array_flip(['costo_unitario', 'proveedor', 'referencia', 'motivo', 'id_poliza', 'id_incidencia'])));
    }

    private function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ','), '0'), '.');
    }
}
