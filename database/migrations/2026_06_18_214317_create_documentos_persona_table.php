<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_persona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_persona')->constrained('personas', 'id_persona')->onDelete('cascade');
            $table->string('titulo', 100);
            $table->string('tipo_documento', 50);
            $table->string('ruta_archivo', 255);
            $table->timestamp('fecha_subida')->useCurrent();
            $table->boolean('finalizado')->default(false);
            $table->foreignId('id_usuario_subio')->constrained('usuarios', 'id_usuario')->onDelete('cascade');
            $table->timestamps();

            // ✅ Índices
            $table->index('id_persona');
            $table->index('tipo_documento');
            $table->index('finalizado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_persona');
    }
};