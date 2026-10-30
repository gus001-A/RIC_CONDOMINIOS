<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `personas.Paterno` estaba como VARCHAR NOT NULL, pero a pedido del negocio
 * el apellido paterno ya NO es obligatorio:
 *  - el formulario (Create/Edit) no lo marca como requerido
 *  - PersonaController::validatePersona() lo valida como `nullable`
 *
 * Resultado: guardar una persona sin apellido paterno lanzaba
 *   SQLSTATE[23000]: Column 'Paterno' cannot be null
 *
 * Se hace NULL-able igual que `Materno`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('personas', 'Paterno')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `personas` MODIFY `Paterno` VARCHAR(255) NULL DEFAULT NULL");
        } else {
            Schema::table('personas', fn ($t) => $t->string('Paterno', 255)->nullable()->default(null)->change());
        }
        DB::statement("UPDATE `personas` SET `Paterno` = NULL WHERE `Paterno` = ''");
    }

    public function down(): void
    {
        if (!Schema::hasColumn('personas', 'Paterno')) {
            return;
        }

        DB::statement("UPDATE `personas` SET `Paterno` = '' WHERE `Paterno` IS NULL");
        DB::statement("ALTER TABLE `personas` MODIFY `Paterno` VARCHAR(255) NOT NULL");
    }
};
