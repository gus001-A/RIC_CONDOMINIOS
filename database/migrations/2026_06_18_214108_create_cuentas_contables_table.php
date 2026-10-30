<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_contables', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_cuenta', 50);
            $table->string('descripcion', 200);
            $table->string('naturaleza', 200)->default('DEUDORA');
            $table->boolean('es_cuenta_resultados')->default(false);
            $table->foreignId('id_cuenta_madre')->nullable()->constrained('cuentas_contables')->onDelete('set null');
            $table->boolean('en_uso')->default(true);
            $table->integer('nivel')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_contables');
    }
};