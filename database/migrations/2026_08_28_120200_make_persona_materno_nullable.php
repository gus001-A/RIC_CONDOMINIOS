<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `personas.Materno` estaba como VARCHAR NOT NULL, pero toda la aplicación
 * trata el apellido materno como OPCIONAL:
 *  - el formulario (Create/Edit) no lo marca como requerido
 *  - PersonaController::validatePersona() lo valida como `nullable`
 *  - el mutator Persona::setMaternoAttribute() guarda NULL cuando viene vacío
 *
 * Resultado: crear/editar una persona sin apellido materno lanzaba
 *   SQLSTATE[23000]: Column 'Materno' cannot be null
 *
 * Se hace NULL-able para que coincida con el comportamiento de la app.
 * (Los demás campos opcionales de personas ya son NULL-able.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('personas', 'Materno')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `personas` MODIFY `Materno` VARCHAR(255) NULL DEFAULT NULL");
        } else {
            Schema::table('personas', fn ($t) => $t->string('Materno', 255)->nullable()->default(null)->change());
        }

        // Normalizar cadenas vacías a NULL para consistencia.
        DB::statement("UPDATE `personas` SET `Materno` = NULL WHERE `Materno` = ''");
    }

    public function down(): void
    {
        if (!Schema::hasColumn('personas', 'Materno')) {
            return;
        }

        // Requiere que no existan NULL para poder volver a NOT NULL.
        DB::statement("UPDATE `personas` SET `Materno` = '' WHERE `Materno` IS NULL");
        DB::statement("ALTER TABLE `personas` MODIFY `Materno` VARCHAR(255) NOT NULL");
    }
};
