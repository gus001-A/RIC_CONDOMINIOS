<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas_usuarios_marcadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_empresa_usuario')->constrained('empresas_usuarios', 'id')->onDelete('cascade');
            $table->foreignId('id_marcador')->constrained('marcadores', 'id')->onDelete('cascade');
            $table->boolean('ver_todos')->default(false);
            $table->timestamps();

            // ✅ Índices con nombres cortos
            $table->unique(['id_empresa_usuario', 'id_marcador'], 'eum_empresa_marcador_unique');
            $table->index(['id_empresa_usuario', 'id_marcador'], 'eum_empresa_marcador_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas_usuarios_marcadores');
    }
};