<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario de insumos y materiales del condominio.
 *  - insumos: catálogo con existencia y costo promedio al día.
 *  - insumo_movimientos: kárdex. Entradas (compras), salidas (uso, p. ej. en
 *    una incidencia) y ajustes por conteo físico. La existencia de `insumos`
 *    siempre es el resultado de estos movimientos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('clave', 30)->nullable();
            $table->string('nombre', 150);
            $table->string('categoria', 30)->default('OTRO');
            $table->string('unidad', 20)->default('PIEZA');
            $table->decimal('existencia', 12, 3)->default(0);
            $table->decimal('stock_minimo', 12, 3)->default(0);
            $table->decimal('costo_promedio', 12, 4)->default(0);
            $table->string('ubicacion', 100)->nullable()->comment('Bodega, caseta, cuarto de máquinas…');
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedBigInteger('id_usuario_modifico')->nullable();
            $table->timestamps();

            $table->unique(['id_empresa', 'clave']);
            $table->index(['id_empresa', 'activo']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuarios')->nullOnDelete();
            $table->foreign('id_usuario_modifico')->references('id_usuario')->on('usuarios')->nullOnDelete();
        });

        Schema::create('insumo_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_insumo');
            $table->unsignedBigInteger('id_empresa');
            $table->string('tipo', 20)->comment('ENTRADA, SALIDA, AJUSTE');
            // Con signo: + entra al almacén, - sale.
            $table->decimal('cantidad', 12, 3);
            $table->decimal('costo_unitario', 12, 4)->nullable();
            $table->decimal('existencia_resultante', 12, 3);
            $table->string('proveedor', 150)->nullable();
            $table->string('referencia', 100)->nullable()->comment('Factura, nota, ticket');
            $table->string('motivo', 255)->nullable()->comment('Uso / destino / razón del ajuste');
            $table->unsignedBigInteger('id_poliza')->nullable()->comment('Póliza de egreso de la compra');
            $table->unsignedBigInteger('id_incidencia')->nullable()->comment('Incidencia donde se usó');
            $table->unsignedBigInteger('id_usuario');
            $table->timestamps();

            $table->index(['id_insumo', 'created_at']);
            $table->index(['id_empresa', 'created_at']);
            $table->foreign('id_insumo')->references('id')->on('insumos')->cascadeOnDelete();
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_poliza')->references('id')->on('polizas')->nullOnDelete();
            $table->foreign('id_incidencia')->references('id')->on('incidencias')->nullOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumo_movimientos');
        Schema::dropIfExists('insumos');
    }
};
