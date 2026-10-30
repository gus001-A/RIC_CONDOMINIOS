<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El modelo MovimientoPoliza (fillable + casts) y todo el flujo de pólizas
 * (store, update, abonos, nómina, liquidación, traspasos) usan estas 4 columnas,
 * pero la migración original `create_movimientos_poliza_table` nunca las creó.
 *
 * Efecto: al ACTUALIZAR una póliza, `MovimientoPoliza::update()` intentaba
 *   update movimientos_poliza set monto_iva_cero = 0, monto_iva_dieciseis = 0, iva_dieciseis = 0 ...
 * y MySQL respondía:
 *   SQLSTATE[42S22]: Unknown column 'monto_iva_cero' in 'field list'
 * La transacción hacía rollback -> la póliza no se modificaba, pero como el
 * controlador devolvía redirect()->back() (302), axios lo seguía y el frontend
 * mostraba "actualizado correctamente" sin que nada cambiara.
 *
 * Se añaden con guardas para que sea idempotente (no-op si ya existen).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('movimientos_poliza')) {
            return;
        }

        Schema::table('movimientos_poliza', function (Blueprint $table) {
            if (!Schema::hasColumn('movimientos_poliza', 'monto_traspaso')) {
                $table->decimal('monto_traspaso', 15, 2)->nullable()->after('monto_iva');
            }
            if (!Schema::hasColumn('movimientos_poliza', 'monto_iva_cero')) {
                $table->decimal('monto_iva_cero', 15, 2)->default(0)->after('monto_iva');
            }
            if (!Schema::hasColumn('movimientos_poliza', 'monto_iva_dieciseis')) {
                $table->decimal('monto_iva_dieciseis', 15, 2)->default(0)->after('monto_iva');
            }
            if (!Schema::hasColumn('movimientos_poliza', 'iva_dieciseis')) {
                $table->decimal('iva_dieciseis', 15, 2)->default(0)->after('monto_iva');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('movimientos_poliza')) {
            return;
        }

        Schema::table('movimientos_poliza', function (Blueprint $table) {
            foreach (['monto_traspaso', 'monto_iva_cero', 'monto_iva_dieciseis', 'iva_dieciseis'] as $col) {
                if (Schema::hasColumn('movimientos_poliza', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
