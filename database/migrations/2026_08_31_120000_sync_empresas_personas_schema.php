<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sincroniza el esquema local de `empresas` y `personas` con lo que espera el
 * codigo (modelos + controladores). En produccion estas columnas ya existen
 * (se agregaron a mano sin migracion), por eso cada ALTER va protegido: si la
 * columna ya existe no hace nada.
 *
 * Sintoma que arregla localmente:
 *   SQLSTATE[42S22] Unknown column 'tipo_persona' in ... (empresas)
 *   SQLSTATE[42S22] Unknown column 'empleado' in ...     (personas)
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- personas.nombre -> Nombre (respetando mayuscula, como en prod) ---
        if ($this->hasExactColumn('personas', 'nombre') && ! $this->hasExactColumn('personas', 'Nombre')) {
            DB::getDriverName() === 'mysql'
                ? DB::statement("ALTER TABLE `personas` CHANGE `nombre` `Nombre` VARCHAR(200) NULL")
                : Schema::table('personas', fn ($t) => $t->renameColumn('nombre', 'Nombre'));
        }

        Schema::table('empresas', function ($table) {
            $add = function (string $name, callable $def) use ($table) {
                if (! Schema::hasColumn('empresas', $name)) {
                    $def($table);
                }
            };

            $add('regimen',        fn ($t) => $t->string('regimen', 50)->nullable());
            $add('tipo_persona',   fn ($t) => $t->string('tipo_persona', 10)->default('FISICA'));
            $add('clave',          fn ($t) => $t->string('clave', 20)->nullable());

            $add('calle',           fn ($t) => $t->string('calle', 100)->nullable());
            $add('numero_exterior', fn ($t) => $t->string('numero_exterior', 20)->nullable());
            $add('numero_interior', fn ($t) => $t->string('numero_interior', 20)->nullable());
            $add('colonia',         fn ($t) => $t->string('colonia', 100)->nullable());
            $add('ciudad',          fn ($t) => $t->string('ciudad', 100)->nullable());
            $add('municipio',       fn ($t) => $t->string('municipio', 100)->nullable());
            $add('estado',          fn ($t) => $t->string('estado', 100)->nullable());
            $add('codigo_postal',   fn ($t) => $t->string('codigo_postal', 10)->nullable());

            $add('correo',            fn ($t) => $t->string('correo', 100)->nullable());
            $add('telefono_personal', fn ($t) => $t->string('telefono_personal', 20)->nullable());
            $add('telefono_trabajo',  fn ($t) => $t->string('telefono_trabajo', 20)->nullable());
            $add('extension',         fn ($t) => $t->string('extension', 10)->nullable());

            $add('representante_nombre',           fn ($t) => $t->string('representante_nombre', 100)->nullable());
            $add('representante_apellido_paterno', fn ($t) => $t->string('representante_apellido_paterno', 50)->nullable());
            $add('representante_apellido_materno', fn ($t) => $t->string('representante_apellido_materno', 50)->nullable());
            $add('representante_rfc',              fn ($t) => $t->string('representante_rfc', 13)->nullable());
            $add('representante_curp',             fn ($t) => $t->string('representante_curp', 18)->nullable());
        });

        Schema::table('personas', function ($table) {
            $add = function (string $name, callable $def) use ($table) {
                if (! Schema::hasColumn('personas', $name)) {
                    $def($table);
                }
            };

            $add('empleado', fn ($t) => $t->boolean('empleado')->default(false));

            $add('Paterno',          fn ($t) => $t->string('Paterno', 100)->nullable());
            $add('Materno',          fn ($t) => $t->string('Materno', 100)->nullable());
            $add('Fecha_nacimiento', fn ($t) => $t->date('Fecha_nacimiento')->nullable());
            $add('sexo',             fn ($t) => $t->string('sexo', 20)->nullable());

            $add('telefono_particular', fn ($t) => $t->string('telefono_particular', 20)->nullable());
            $add('telefono_trabajo',    fn ($t) => $t->string('telefono_trabajo', 20)->nullable());
            $add('extension_trabajo',   fn ($t) => $t->string('extension_trabajo', 10)->nullable());

            $add('calle',           fn ($t) => $t->string('calle', 150)->nullable());
            $add('numero_exterior', fn ($t) => $t->string('numero_exterior', 20)->nullable());
            $add('numero_interior', fn ($t) => $t->string('numero_interior', 20)->nullable());
            $add('colonia',         fn ($t) => $t->string('colonia', 100)->nullable());
            $add('ciudad',          fn ($t) => $t->string('ciudad', 100)->nullable());
            $add('municipio',       fn ($t) => $t->string('municipio', 100)->nullable());
            $add('estado',          fn ($t) => $t->string('estado', 100)->nullable());
            $add('codigo_postal',   fn ($t) => $t->string('codigo_postal', 10)->nullable());

            $add('representante_nombre',            fn ($t) => $t->string('representante_nombre', 100)->nullable());
            $add('representante_paterno',           fn ($t) => $t->string('representante_paterno', 100)->nullable());
            $add('representante_materno',           fn ($t) => $t->string('representante_materno', 100)->nullable());
            $add('representante_fecha_nacimiento',  fn ($t) => $t->date('representante_fecha_nacimiento')->nullable());
            $add('representante_sexo',              fn ($t) => $t->string('representante_sexo', 20)->nullable());
            $add('representante_email',             fn ($t) => $t->string('representante_email', 100)->nullable());
            $add('representante_telefono_particular', fn ($t) => $t->string('representante_telefono_particular', 20)->nullable());
            $add('representante_telefono_trabajo',    fn ($t) => $t->string('representante_telefono_trabajo', 20)->nullable());
            $add('representante_extension_trabajo',   fn ($t) => $t->string('representante_extension_trabajo', 10)->nullable());

            $add('notas', fn ($t) => $t->text('notas')->nullable());
        });
    }

    public function down(): void
    {
        // Migracion de sincronizacion: no revierte (las columnas podrian tener
        // datos reales en produccion). Es intencionalmente no-op.
    }

    /**
     * Comprueba si una columna existe con EXACTAMENTE esa mayuscula/minuscula.
     * (Schema::hasColumn es case-insensitive y aqui necesitamos distinguir
     *  `nombre` de `Nombre`.)
     */
    private function hasExactColumn(string $table, string $column): bool
    {
        // SQLite (y otros): la lista de columnas del esquema respeta mayúsculas.
        if (DB::getDriverName() !== 'mysql') {
            return in_array($column, Schema::getColumnListing($table), true);
        }

        $rows = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        foreach ($rows as $row) {
            if ($row->COLUMN_NAME === $column) {
                return true;
            }
        }

        return false;
    }
};
