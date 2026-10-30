<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Casas / propiedades de cada condominio.
 *
 * El condominio ES la empresa de RIC (`empresas`): así cada condominio lleva
 * su propia contabilidad (pólizas, cuentas, estado de resultados) sin tocar
 * el motor contable. Una casa pertenece a un condominio y tiene uno o más
 * condóminos (tabla `condominos`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');

            // Identificación dentro del condominio
            $table->string('identificador', 30)->comment('Clave interna única en el condominio, ej. M3-L12');
            $table->string('tipo_propiedad', 20)->default('CASA');
            $table->string('manzana', 20)->nullable();
            $table->string('lote', 20)->nullable();
            $table->string('calle', 100)->nullable();
            $table->string('numero_exterior', 20)->nullable();
            $table->string('numero_interior', 20)->nullable();
            $table->text('referencias')->nullable();

            // Ubicación GPS (para emergencias, vigilancia y entregas)
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();

            $table->string('estatus_ocupacion', 20)->default('HABITADA');
            $table->decimal('cuota_mantenimiento', 12, 2)->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);

            // Trazabilidad usuario / fecha / hora
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedBigInteger('id_usuario_modifico')->nullable();
            $table->timestamps();

            $table->unique(['id_empresa', 'identificador'], 'casas_empresa_identificador_unique');
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuarios')->nullOnDelete();
            $table->foreign('id_usuario_modifico')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('condominos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_casa');
            // Acceso al sistema / app (usuario tipo CONDOMINO). Opcional.
            $table->unsignedBigInteger('id_usuario')->nullable();

            $table->string('tipo', 20)->default('PROPIETARIO')->comment('PROPIETARIO, INQUILINO, FAMILIAR');
            $table->boolean('es_responsable')->default(false);

            $table->string('nombre', 100);
            $table->string('apellido_paterno', 60)->nullable();
            $table->string('apellido_materno', 60)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('telefono_alterno', 20)->nullable();
            $table->string('correo', 100)->nullable();

            $table->string('emergencia_nombre', 150)->nullable();
            $table->string('emergencia_telefono', 20)->nullable();
            $table->string('emergencia_parentesco', 50)->nullable();

            $table->string('foto', 255)->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);

            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedBigInteger('id_usuario_modifico')->nullable();
            $table->timestamps();

            $table->foreign('id_casa')->references('id')->on('casas')->cascadeOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->nullOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuarios')->nullOnDelete();
            $table->foreign('id_usuario_modifico')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('casa_fotos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_casa');
            $table->string('tipo', 20)->default('FACHADA')->comment('FACHADA, RESIDENTE, VEHICULO, OTRO');
            $table->string('ruta', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->unsignedBigInteger('id_usuario_subio')->nullable();
            $table->timestamps();

            $table->foreign('id_casa')->references('id')->on('casas')->cascadeOnDelete();
            $table->foreign('id_usuario_subio')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casa_fotos');
        Schema::dropIfExists('condominos');
        Schema::dropIfExists('casas');
    }
};
