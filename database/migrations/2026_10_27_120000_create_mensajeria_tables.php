<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajería interna: conversaciones entre una casa (sus condóminos) y la
 * administración de su condominio. La puede iniciar cualquiera de los dos lados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_casa');
            $table->string('asunto', 150);
            $table->string('categoria', 20)->default('GENERAL');
            $table->string('estatus', 20)->default('ABIERTA')->comment('ABIERTA, CERRADA');
            $table->unsignedBigInteger('id_usuario_inicio');
            $table->boolean('iniciada_por_administracion')->default(false);
            $table->unsignedBigInteger('id_usuario_cierre')->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'estatus', 'ultimo_mensaje_at']);
            $table->index(['id_casa', 'ultimo_mensaje_at']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_casa')->references('id')->on('casas')->cascadeOnDelete();
            $table->foreign('id_usuario_inicio')->references('id_usuario')->on('usuarios');
            $table->foreign('id_usuario_cierre')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_conversacion');
            $table->unsignedBigInteger('id_usuario');
            $table->boolean('de_administracion')->default(false);
            $table->text('texto')->nullable();
            $table->string('adjunto_ruta', 255)->nullable();
            $table->string('adjunto_nombre', 255)->nullable();
            $table->string('adjunto_mime', 100)->nullable();
            $table->timestamps();

            $table->index(['id_conversacion', 'created_at']);
            $table->foreign('id_conversacion')->references('id')->on('conversaciones')->cascadeOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
        });

        // Hasta dónde leyó cada usuario cada conversación (para los no leídos).
        Schema::create('conversacion_lecturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_conversacion');
            $table->unsignedBigInteger('id_usuario');
            $table->timestamp('leido_hasta');
            $table->timestamps();

            $table->unique(['id_conversacion', 'id_usuario']);
            $table->foreign('id_conversacion')->references('id')->on('conversaciones')->cascadeOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversacion_lecturas');
        Schema::dropIfExists('mensajes');
        Schema::dropIfExists('conversaciones');
    }
};
