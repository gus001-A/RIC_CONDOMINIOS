<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonos_poliza', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_poliza')->constrained('polizas', 'id')->onDelete('cascade');
            $table->decimal('monto_abonado', 15, 2);
            $table->timestamp('fecha_abono')->useCurrent();
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->onDelete('cascade');
            $table->string('comprobante_ruta', 255)->nullable();
            $table->text('nota')->nullable();
            $table->timestamps();

            // ✅ Índices
            $table->index('id_poliza');
            $table->index('id_usuario');
            $table->index('fecha_abono');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos_poliza');
    }
};