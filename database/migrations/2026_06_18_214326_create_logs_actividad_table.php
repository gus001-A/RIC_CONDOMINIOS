<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_actividad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->onDelete('cascade');
            $table->string('accion', 50);
            $table->string('tabla_afectada', 50);
            $table->integer('id_registro_afectado')->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('ip_origen', 45)->nullable();
            $table->timestamp('fecha_hora')->useCurrent();
            $table->timestamps();

            // ✅ Índices
            $table->index('id_usuario');
            $table->index('accion');
            $table->index('tabla_afectada');
            $table->index('fecha_hora');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_actividad');
    }
};