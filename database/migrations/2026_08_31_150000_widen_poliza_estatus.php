<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `polizas.estatus` estaba como enum('PENDIENTE','ABONADO','LIQUIDADO') pero el
 * flujo de trabajo del sistema usa además CAPTURADO, REVISADO, AUTORIZADO,
 * RECHAZADO y CERRADO. Al insertar esos valores MySQL los truncaba a '' y
 * rompía revisar / autorizar / cerrar póliza.
 *
 * Se cambia a VARCHAR(20) para admitir todo el ciclo sin volver a chocar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('polizas', 'estatus')) {
            return;
        }

        DB::getDriverName() === 'mysql'
            ? DB::statement("ALTER TABLE `polizas` MODIFY `estatus` VARCHAR(20) NOT NULL DEFAULT 'CAPTURADO'")
            : Schema::table('polizas', fn ($t) => $t->string('estatus', 20)->default('CAPTURADO')->change());

        // Pólizas que quedaron con estatus vacío por el truncado anterior.
        DB::statement("UPDATE `polizas` SET `estatus` = 'CAPTURADO' WHERE `estatus` = '' OR `estatus` IS NULL");
    }

    public function down(): void
    {
        // No se revierte a enum para no perder datos válidos.
    }
};
