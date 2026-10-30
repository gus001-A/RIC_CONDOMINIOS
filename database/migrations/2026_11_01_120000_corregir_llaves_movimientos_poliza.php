<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * movimientos_poliza.id_cuenta e id_caja_fondo guardan ids de la tabla `cuentas`
 * (la cuenta del movimiento y la cuenta fondeadora), pero la migración original
 * las ligaba a `cuentas_contables` y `cajas_fondo`, que ya no se usan.
 *
 * En MySQL de WAMP esas tablas nacieron como MyISAM (sin llaves foráneas), así que
 * nunca estorbó. SQLite sí las aplica: aquí se apuntan a `cuentas`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sólo si la tabla trae las llaves viejas (base creada con las migraciones);
        // una copia de MySQL (db:sqlite-desde-mysql) no las tiene.
        $viejas = collect(DB::getDriverName() === 'sqlite' ? Schema::getForeignKeys('movimientos_poliza') : [])
            ->pluck('foreign_table')->intersect(['cuentas_contables', 'cajas_fondo']);
        if ($viejas->isEmpty()) {
            return;
        }

        Schema::table('movimientos_poliza', function (Blueprint $table) {
            $table->dropForeign(['id_cuenta']);
            $table->dropForeign(['id_caja_fondo']);
        });
        Schema::table('movimientos_poliza', function (Blueprint $table) {
            $table->foreign('id_cuenta')->references('id_cuenta')->on('cuentas')->cascadeOnDelete();
            $table->foreign('id_caja_fondo')->references('id_cuenta')->on('cuentas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('movimientos_poliza', function (Blueprint $table) {
            $table->dropForeign(['id_cuenta']);
            $table->dropForeign(['id_caja_fondo']);
        });
        Schema::table('movimientos_poliza', function (Blueprint $table) {
            $table->foreign('id_cuenta')->references('id')->on('cuentas_contables')->cascadeOnDelete();
            $table->foreign('id_caja_fondo')->references('id')->on('cajas_fondo')->nullOnDelete();
        });
    }
};
