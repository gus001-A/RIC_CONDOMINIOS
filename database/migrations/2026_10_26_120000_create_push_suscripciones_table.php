<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Celulares / navegadores donde cada usuario activó las notificaciones push
 * (visitas, alertas y mensajes aunque la app esté cerrada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_suscripciones', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('id_usuario')->index();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique(); // el endpoint es muy largo para un índice único
            $table->string('p256dh', 255);
            $table->string('auth', 255);
            $table->string('dispositivo', 255)->nullable();
            $table->timestamp('ultimo_envio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_suscripciones');
    }
};
