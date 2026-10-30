<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguridad del condominio:
 *  - visitas: bitácora de caseta (personas y vehículos). Vigilancia registra la
 *    llegada, la casa autoriza o rechaza, vigilancia marca entrada y salida.
 *    El condómino también puede pre-autorizar visitas esperadas.
 *  - alertas_seguridad: botón de alerta vecinal (intrusos, agresión, incendio…)
 *    que se difunde a todo el condominio; puede ser anónima para los vecinos
 *    pero siempre guarda quién la emitió (trazabilidad para la administración).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_casa');
            $table->string('tipo_visita', 20)->default('VISITA');
            $table->string('nombre_visitante', 150);
            $table->string('identificacion', 100)->nullable()->comment('Tipo y número de identificación');
            $table->string('placas', 20)->nullable();
            $table->string('vehiculo', 100)->nullable()->comment('Marca, modelo, color');
            $table->unsignedSmallInteger('num_personas')->default(1);
            $table->string('motivo', 255)->nullable();
            $table->string('foto', 255)->nullable();
            $table->string('estatus', 20)->default('ESPERANDO')
                ->comment('PREAUTORIZADA, ESPERANDO, AUTORIZADA, RECHAZADA, DENTRO, SALIO, CANCELADA');
            $table->date('fecha_esperada')->nullable()->comment('Sólo pre-autorizadas');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->timestamp('fecha_llegada')->nullable();
            $table->unsignedBigInteger('id_usuario_respuesta')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->string('comentario_respuesta', 255)->nullable();
            $table->unsignedBigInteger('id_usuario_entrada')->nullable();
            $table->timestamp('fecha_entrada')->nullable();
            $table->unsignedBigInteger('id_usuario_salida')->nullable();
            $table->timestamp('fecha_salida')->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'estatus']);
            $table->index(['id_casa', 'estatus']);
            $table->index('placas');
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_casa')->references('id')->on('casas')->cascadeOnDelete();
            foreach (['id_usuario_registro', 'id_usuario_respuesta', 'id_usuario_entrada', 'id_usuario_salida'] as $col) {
                $table->foreign($col)->references('id_usuario')->on('usuarios')->nullOnDelete();
            }
        });

        Schema::create('alertas_seguridad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('tipo', 30);
            $table->text('descripcion')->nullable();
            $table->string('ubicacion', 255)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->boolean('anonima')->default(false);
            // Siempre se guarda quién la emitió, aunque sea anónima para los vecinos.
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_casa')->nullable();
            $table->string('estatus', 20)->default('ACTIVA')->comment('ACTIVA, ATENDIDA, FALSA_ALARMA');
            $table->unsignedBigInteger('id_usuario_cierre')->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->string('comentario_cierre', 500)->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'estatus', 'created_at']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
            $table->foreign('id_casa')->references('id')->on('casas')->nullOnDelete();
            $table->foreign('id_usuario_cierre')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('alerta_archivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_alerta');
            $table->string('ruta', 255);
            $table->string('nombre_original', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->timestamps();

            $table->foreign('id_alerta')->references('id')->on('alertas_seguridad')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_archivos');
        Schema::dropIfExists('alertas_seguridad');
        Schema::dropIfExists('visitas');
    }
};
