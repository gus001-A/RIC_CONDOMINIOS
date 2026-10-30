<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transparencia contable de los condominios:
 *  - poliza_anotaciones: dudas / inconformidades de condóminos (o del personal)
 *    sobre una póliza, con respuesta de la administración.
 *  - cuota_cargos / cuota_pagos: estado de cuenta de las cuotas de cada casa,
 *    de donde sale el listado de deudores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poliza_anotaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_poliza');
            // Respuestas cuelgan de la anotación original.
            $table->unsignedBigInteger('id_padre')->nullable();
            $table->unsignedBigInteger('id_usuario');
            // Casa del condómino que anota (snapshot del identificador por si la casa cambia).
            $table->unsignedBigInteger('id_casa')->nullable();
            $table->string('casa_identificador', 30)->nullable();
            $table->string('tipo', 20)->default('DUDA')->comment('DUDA, INCONFORMIDAD, COMENTARIO, RESPUESTA');
            $table->text('texto');
            $table->boolean('resuelta')->default(false);
            $table->unsignedBigInteger('id_usuario_resolvio')->nullable();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->timestamps();

            $table->index(['id_poliza', 'id_padre']);
            $table->foreign('id_poliza')->references('id')->on('polizas')->cascadeOnDelete();
            $table->foreign('id_padre')->references('id')->on('poliza_anotaciones')->cascadeOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
            $table->foreign('id_casa')->references('id')->on('casas')->nullOnDelete();
            $table->foreign('id_usuario_resolvio')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('cuota_cargos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_casa');
            $table->date('periodo')->comment('Primer día del mes que se cobra');
            $table->string('concepto', 100)->default('CUOTA DE MANTENIMIENTO');
            $table->decimal('monto', 12, 2);
            $table->date('fecha_vencimiento')->nullable();
            $table->boolean('cancelado')->default(false);
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedBigInteger('id_usuario_modifico')->nullable();
            $table->timestamps();

            $table->unique(['id_casa', 'periodo', 'concepto'], 'cuota_cargos_casa_periodo_unique');
            $table->index(['id_empresa', 'periodo']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_casa')->references('id')->on('casas')->cascadeOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuarios')->nullOnDelete();
            $table->foreign('id_usuario_modifico')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('cuota_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_casa');
            $table->date('fecha_pago');
            $table->decimal('monto', 12, 2);
            $table->string('forma_pago', 20)->default('TRANSFERENCIA');
            $table->string('referencia', 100)->nullable();
            // Póliza de ingreso de RIC donde quedó registrado el dinero (opcional).
            $table->unsignedBigInteger('id_poliza')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('cancelado')->default(false);
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedBigInteger('id_usuario_modifico')->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'fecha_pago']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_casa')->references('id')->on('casas')->cascadeOnDelete();
            $table->foreign('id_poliza')->references('id')->on('polizas')->nullOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuarios')->nullOnDelete();
            $table->foreign('id_usuario_modifico')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuota_pagos');
        Schema::dropIfExists('cuota_cargos');
        Schema::dropIfExists('poliza_anotaciones');
    }
};
