<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega las columnas del flujo de revisión/autorización/rechazo/liquidación
 * de pólizas que el código (MovimientoController, Poliza model) ya usa pero
 * que faltan en algunas instalaciones (mismo patrón que
 * 2026_08_28_120000_add_cierre_columns_to_polizas_table).
 *
 * Sin estas columnas, revisarPoliza/autorizarPoliza/rechazarPoliza/
 * storeNomina/abonos fallan con:
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'id_usuario_revisor'
 * (o el nombre de la columna que falte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polizas', function (Blueprint $table) {
            if (!Schema::hasColumn('polizas', 'id_usuario_revisor')) {
                $table->unsignedBigInteger('id_usuario_revisor')->nullable()->after('id_usuario_autorizador');
            }
            if (!Schema::hasColumn('polizas', 'fecha_revision')) {
                $table->timestamp('fecha_revision')->nullable()->after('fecha_autorizacion');
            }
            if (!Schema::hasColumn('polizas', 'comentario_revision')) {
                $table->string('comentario_revision', 500)->nullable();
            }
            if (!Schema::hasColumn('polizas', 'comentario_autorizacion')) {
                $table->string('comentario_autorizacion', 500)->nullable();
            }
            if (!Schema::hasColumn('polizas', 'estatus_anterior')) {
                $table->string('estatus_anterior', 30)->nullable();
            }
            if (!Schema::hasColumn('polizas', 'motivo_rechazo')) {
                $table->string('motivo_rechazo', 500)->nullable();
            }
            if (!Schema::hasColumn('polizas', 'fecha_liquidacion')) {
                $table->timestamp('fecha_liquidacion')->nullable();
            }
            if (!Schema::hasColumn('polizas', 'fecha_abono')) {
                $table->timestamp('fecha_abono')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('polizas', function (Blueprint $table) {
            foreach ([
                'id_usuario_revisor', 'fecha_revision', 'comentario_revision',
                'comentario_autorizacion', 'estatus_anterior', 'motivo_rechazo',
                'fecha_liquidacion', 'fecha_abono',
            ] as $columna) {
                if (Schema::hasColumn('polizas', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
