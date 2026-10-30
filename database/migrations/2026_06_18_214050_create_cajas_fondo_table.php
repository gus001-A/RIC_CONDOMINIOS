<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas_fondo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_caja', 100);
            $table->decimal('saldo_actual', 15, 2)->default(0);
            $table->boolean('es_fondeadora')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas_fondo');
    }
};