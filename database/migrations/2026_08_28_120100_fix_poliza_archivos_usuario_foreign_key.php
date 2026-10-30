<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repara la llave foránea de poliza_archivos.id_usuario_subio.
 *
 * En producción la tabla se creó con:
 *   CONSTRAINT fk_poliza_archivos_id_usuario_subio
 *   FOREIGN KEY (id_usuario_subio) REFERENCES users (id)
 *
 * pero la autenticación del sistema usa la tabla `usuarios` (PK id_usuario),
 * no `users`. Por eso al subir un archivo falla con:
 *   SQLSTATE[23000]: Integrity constraint violation: 1452 ...
 *   a foreign key constraint fails (... REFERENCES `users` (`id`))
 *
 * Esta migración deja la tabla creada (si no existe) y apuntando la FK a
 * usuarios(id_usuario).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Crear la tabla si no existe (mismas columnas que usa el controlador).
        if (!Schema::hasTable('poliza_archivos')) {
            Schema::create('poliza_archivos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_poliza');
                $table->string('nombre_original');
                $table->string('nombre_guardado');
                $table->string('ruta');
                $table->string('tipo_archivo', 20)->default('other');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('tamano')->default(0);
                $table->unsignedBigInteger('id_usuario_subio')->nullable();
                $table->string('descripcion')->nullable();
                $table->timestamps();

                $table->index('id_poliza');
                $table->index('id_usuario_subio');

                $table->foreign('id_poliza')
                    ->references('id')->on('polizas')
                    ->onUpdate('cascade')->onDelete('cascade');
            });
        }

        // SQLite: no admite ALTER de llaves foráneas; basta con que la columna permita NULL.
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('poliza_archivos', fn (Blueprint $t) => $t->unsignedBigInteger('id_usuario_subio')->nullable()->change());

            return;
        }

        // 2) Eliminar cualquier FK previa sobre id_usuario_subio (nombre conocido
        //    y también búsqueda dinámica por si el nombre difiere).
        $this->dropForeignKeysOn('poliza_archivos', 'id_usuario_subio');

        // 3) Asegurar que la columna permita NULL (para onDelete set null).
        DB::statement('ALTER TABLE `poliza_archivos` MODIFY `id_usuario_subio` BIGINT UNSIGNED NULL');

        // 4) Limpiar valores huérfanos que no existan en usuarios.
        DB::statement("
            UPDATE `poliza_archivos` pa
            LEFT JOIN `usuarios` u ON u.`id_usuario` = pa.`id_usuario_subio`
            SET pa.`id_usuario_subio` = NULL
            WHERE pa.`id_usuario_subio` IS NOT NULL AND u.`id_usuario` IS NULL
        ");

        // 5) Crear la FK correcta hacia usuarios(id_usuario).
        DB::statement("
            ALTER TABLE `poliza_archivos`
            ADD CONSTRAINT `fk_poliza_archivos_id_usuario_subio`
            FOREIGN KEY (`id_usuario_subio`) REFERENCES `usuarios` (`id_usuario`)
            ON UPDATE CASCADE ON DELETE SET NULL
        ");
    }

    public function down(): void
    {
        $this->dropForeignKeysOn('poliza_archivos', 'id_usuario_subio');
    }

    /**
     * Elimina todas las FK definidas sobre una columna concreta.
     */
    private function dropForeignKeysOn(string $table, string $column): void
    {
        $constraints = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table, $column]);

        foreach ($constraints as $c) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$c->CONSTRAINT_NAME}`");
        }
    }
};
