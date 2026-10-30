<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_poliza', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_poliza')->constrained('polizas', 'id')->onDelete('cascade');
            $table->foreignId('id_cuenta')->constrained('cuentas_contables', 'id')->onDelete('cascade');
            $table->foreignId('id_caja_fondo')->nullable()->constrained('cajas_fondo', 'id')->onDelete('set null');
            $table->foreignId('id_tipo_iva')->nullable()->constrained('tipos_iva', 'id')->onDelete('set null');
            $table->decimal('monto', 15, 2);
            $table->decimal('monto_base', 15, 2)->default(0);
            $table->decimal('monto_iva', 15, 2)->default(0);
            $table->timestamps();

            // ✅ Índices
            $table->index('id_poliza');
            $table->index('id_cuenta');
            $table->index('id_caja_fondo');
            $table->index('id_tipo_iva');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_poliza');
    }
};