<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_empresa')->constrained('empresas', 'id')->onDelete('cascade');
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->onDelete('cascade');
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->timestamps();

            // ✅ Índices con nombres cortos
            $table->unique(['id_empresa', 'id_usuario'], 'eu_empresa_usuario_unique');
            $table->index(['id_empresa', 'id_usuario'], 'eu_empresa_usuario_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas_usuarios');
    }
};