<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios particulares y asesoría inmobiliaria (privados entre la casa y la
 * administración):
 *  - solicitudes_servicio: mantenimiento dentro de la casa, renta, venta…
 *  - solicitud_seguimientos: conversación y cambios de estatus de la solicitud.
 *  - solicitud_cotizaciones: propuestas de proveedores que la casa acepta o rechaza.
 * Generador de documentos:
 *  - plantillas_documento: contratos / convenios con variables {{...}}.
 *  - documentos_generados: el documento ya llenado con los datos de la casa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_servicio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_casa');
            $table->string('folio', 20);
            $table->string('tipo', 30)->comment('MANTENIMIENTO, RENTA, VENTA, ASESORIA');
            $table->string('categoria', 30)->nullable()->comment('Sólo mantenimiento: PLOMERIA, ELECTRICIDAD…');
            $table->string('titulo', 150);
            $table->text('descripcion');
            $table->decimal('precio_deseado', 14, 2)->nullable()->comment('Renta / venta');
            $table->date('fecha_disponible')->nullable()->comment('Renta / venta / visita técnica');
            $table->string('estatus', 20)->default('NUEVA');
            $table->unsignedBigInteger('id_usuario_solicita');
            $table->unsignedBigInteger('id_usuario_atiende')->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();

            $table->unique(['id_empresa', 'folio']);
            $table->index(['id_casa', 'estatus']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_casa')->references('id')->on('casas')->cascadeOnDelete();
            $table->foreign('id_usuario_solicita')->references('id_usuario')->on('usuarios');
            $table->foreign('id_usuario_atiende')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('solicitud_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_solicitud');
            $table->unsignedBigInteger('id_usuario');
            $table->boolean('de_administracion')->default(false);
            $table->string('estatus_anterior', 20)->nullable();
            $table->string('estatus_nuevo', 20)->nullable();
            $table->text('comentario')->nullable();
            $table->string('adjunto_ruta', 255)->nullable();
            $table->string('adjunto_nombre', 255)->nullable();
            $table->string('adjunto_mime', 100)->nullable();
            $table->timestamps();

            $table->foreign('id_solicitud')->references('id')->on('solicitudes_servicio')->cascadeOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
        });

        Schema::create('solicitud_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_solicitud');
            $table->string('proveedor', 150);
            $table->string('contacto_proveedor', 150)->nullable();
            $table->string('concepto', 255);
            $table->decimal('monto', 12, 2);
            $table->text('notas')->nullable();
            $table->string('archivo_ruta', 255)->nullable();
            $table->string('archivo_nombre', 255)->nullable();
            $table->string('estatus', 20)->default('PROPUESTA')->comment('PROPUESTA, ACEPTADA, RECHAZADA');
            $table->unsignedBigInteger('id_usuario_registro');
            $table->unsignedBigInteger('id_usuario_respuesta')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->timestamps();

            $table->foreign('id_solicitud')->references('id')->on('solicitudes_servicio')->cascadeOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuarios');
            $table->foreign('id_usuario_respuesta')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('plantillas_documento', function (Blueprint $table) {
            $table->id();
            // NULL = plantilla general disponible para todos los condominios.
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre', 150);
            $table->string('tipo', 30)->default('OTRO');
            $table->text('descripcion')->nullable();
            $table->longText('contenido');
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedBigInteger('id_usuario_modifico')->nullable();
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuarios')->nullOnDelete();
            $table->foreign('id_usuario_modifico')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('documentos_generados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_casa');
            $table->unsignedBigInteger('id_plantilla')->nullable();
            $table->unsignedBigInteger('id_solicitud')->nullable();
            $table->string('titulo', 200);
            $table->longText('contenido');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedBigInteger('id_usuario_modifico')->nullable();
            $table->timestamps();

            $table->index(['id_casa']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_casa')->references('id')->on('casas')->cascadeOnDelete();
            $table->foreign('id_plantilla')->references('id')->on('plantillas_documento')->nullOnDelete();
            $table->foreign('id_solicitud')->references('id')->on('solicitudes_servicio')->nullOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuarios')->nullOnDelete();
            $table->foreign('id_usuario_modifico')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_generados');
        Schema::dropIfExists('plantillas_documento');
        Schema::dropIfExists('solicitud_cotizaciones');
        Schema::dropIfExists('solicitud_seguimientos');
        Schema::dropIfExists('solicitudes_servicio');
    }
};
