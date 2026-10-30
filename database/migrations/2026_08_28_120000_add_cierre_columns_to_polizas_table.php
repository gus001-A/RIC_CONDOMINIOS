<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega las columnas necesarias para la funcionalidad de "cerrar / reabrir póliza".
 *
 * MovimientoController@cerrarPoliza y MovimientoController@reabrirPoliza
 * (y los métodos Poliza::cerrar() / Poliza::reabrir()) escriben en estas
 * columnas. Si no existen en la base de datos se produce:
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'motivo_cierre'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polizas', function (Blueprint $table) {
            if (!Schema::hasColumn('polizas', 'fecha_cierre')) {
                $table->timestamp('fecha_cierre')->nullable()->after('fecha_autorizacion');
            }
            if (!Schema::hasColumn('polizas', 'id_usuario_cierre')) {
                $table->unsignedBigInteger('id_usuario_cierre')->nullable()->after('fecha_cierre');
            }
            if (!Schema::hasColumn('polizas', 'motivo_cierre')) {
                $table->string('motivo_cierre', 500)->nullable()->after('id_usuario_cierre');
            }
        });

        // FK opcional hacia usuarios (id_usuario). Se agrega sólo si no existe.
        Schema::table('polizas', function (Blueprint $table) {
            $table->foreign('id_usuario_cierre', 'fk_polizas_id_usuario_cierre')
                ->references('id_usuario')->on('usuarios')
                ->onUpdate('cascade')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('polizas', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_polizas_id_usuario_cierre');
            } catch (\Throwable $e) {
                // la FK puede no existir
            }
            $table->dropColumn(['fecha_cierre', 'id_usuario_cierre', 'motivo_cierre']);
        });
    }
};
