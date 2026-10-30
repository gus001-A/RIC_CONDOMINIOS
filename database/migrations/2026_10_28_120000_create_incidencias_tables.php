<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incidencias y sugerencias de zonas comunes (desperfectos, fugas, luminarias…).
 * Flujo: PENDIENTE → EN PROCESO → ATENDIDA → SUPERVISADA (o CANCELADA).
 * Cada paso queda en `incidencia_seguimientos` con su comentario, fotos y gastos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('folio', 20);
            $table->string('tipo', 20)->default('INCIDENCIA')->comment('INCIDENCIA, SUGERENCIA');
            $table->string('categoria', 30);
            $table->string('prioridad', 20)->default('MEDIA');
            $table->string('titulo', 150);
            $table->text('descripcion');
            $table->string('ubicacion', 255)->nullable()->comment('Referencia: parque, caseta, calle…');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('estatus', 20)->default('PENDIENTE');
            $table->unsignedBigInteger('id_usuario_reporta');
            $table->unsignedBigInteger('id_casa')->nullable();
            $table->unsignedBigInteger('id_usuario_asignado')->nullable();
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_atencion')->nullable();
            $table->timestamp('fecha_supervision')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->timestamps();

            $table->unique(['id_empresa', 'folio']);
            $table->index(['id_empresa', 'estatus']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_usuario_reporta')->references('id_usuario')->on('usuarios');
            $table->foreign('id_casa')->references('id')->on('casas')->nullOnDelete();
            $table->foreign('id_usuario_asignado')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('incidencia_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_incidencia');
            $table->unsignedBigInteger('id_usuario');
            $table->string('estatus_anterior', 20)->nullable();
            $table->string('estatus_nuevo', 20)->nullable()->comment('NULL = sólo comentario');
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->foreign('id_incidencia')->references('id')->on('incidencias')->cascadeOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
        });

        Schema::create('incidencia_fotos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_incidencia');
            $table->unsignedBigInteger('id_seguimiento')->nullable();
            $table->string('ruta', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->unsignedBigInteger('id_usuario');
            $table->timestamps();

            $table->foreign('id_incidencia')->references('id')->on('incidencias')->cascadeOnDelete();
            $table->foreign('id_seguimiento')->references('id')->on('incidencia_seguimientos')->nullOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
        });

        Schema::create('incidencia_gastos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_incidencia');
            $table->unsignedBigInteger('id_seguimiento')->nullable();
            $table->string('concepto', 150);
            $table->decimal('monto', 12, 2);
            $table->string('proveedor', 150)->nullable();
            // Póliza de egreso de RIC donde se pagó (opcional, la liga la administración).
            $table->unsignedBigInteger('id_poliza')->nullable();
            $table->boolean('cancelado')->default(false);
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_usuario_modifico')->nullable();
            $table->timestamps();

            $table->foreign('id_incidencia')->references('id')->on('incidencias')->cascadeOnDelete();
            $table->foreign('id_seguimiento')->references('id')->on('incidencia_seguimientos')->nullOnDelete();
            $table->foreign('id_poliza')->references('id')->on('polizas')->nullOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
            $table->foreign('id_usuario_modifico')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencia_gastos');
        Schema::dropIfExists('incidencia_fotos');
        Schema::dropIfExists('incidencia_seguimientos');
        Schema::dropIfExists('incidencias');
    }
};
