<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polizas', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->timestamp('fecha_poliza')->useCurrent();
            $table->date('fecha_vencimiento')->nullable();
            $table->enum('tipo_poliza', ['INGRESO', 'EGRESO', 'TRASPASO'])->default('EGRESO');
            $table->enum('categoria', ['FISCAL', 'NO_FISCAL'])->default('NO_FISCAL');
            $table->enum('estatus', ['PENDIENTE', 'ABONADO', 'LIQUIDADO'])->default('PENDIENTE');
            $table->boolean('es_por_pagar')->default(false);
            $table->string('referencia', 100)->nullable();
            $table->text('nota')->nullable();
            
            $table->foreignId('id_persona')->nullable()->constrained('personas', 'id_persona')->onDelete('set null');
            $table->foreignId('id_usuario_creador')->constrained('usuarios', 'id_usuario')->onDelete('cascade');
            $table->foreignId('id_usuario_autorizador')->nullable()->constrained('usuarios', 'id_usuario')->onDelete('set null');
            
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_autorizacion')->nullable();
            $table->timestamps();

            // ✅ Índices para búsquedas
            $table->index('tipo_poliza');
            $table->index('categoria');
            $table->index('estatus');
            $table->index('fecha_poliza');
            $table->index('fecha_vencimiento');
            $table->index('id_persona');
            $table->index('id_usuario_creador');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polizas');
    }
};