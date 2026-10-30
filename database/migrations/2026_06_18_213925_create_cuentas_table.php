<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas', function (Blueprint $table) {
            $table->id('id_cuenta');
            $table->foreignId('id_empresa')->constrained('empresas', 'id')->onDelete('cascade');
            $table->string('codigo_cuenta', 50);
            $table->string('nombre_cuenta', 150);
            $table->text('descripcion')->nullable();
            $table->enum('naturaleza', ['DEUDORA', 'ACREEDORA'])->default('DEUDORA');
            $table->enum('tipo_cuenta', ['FONDEADORA', 'RESULTADO', 'ORDEN'])->default('RESULTADO');
            $table->foreignId('id_cuenta_madre')->nullable()->constrained('cuentas', 'id_cuenta')->onDelete('set null');
            $table->boolean('en_uso')->default(true);
            $table->boolean('es_cuenta_resultados')->default(false);
            $table->decimal('saldo_inicial', 15, 2)->default(0);
            $table->integer('nivel')->default(1);
            $table->timestamps();

            // ✅ Índices con nombres cortos
            $table->unique(['id_empresa', 'codigo_cuenta'], 'cuentas_empresa_codigo_unique');
            $table->index(['id_empresa', 'tipo_cuenta'], 'cuentas_empresa_tipo_index');
            $table->index('id_cuenta_madre');
            $table->index('naturaleza');
            $table->index('tipo_cuenta');
            $table->index('en_uso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas');
    }
};