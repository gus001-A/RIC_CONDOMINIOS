<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo de codigos postales de Mexico (SEPOMEX, abril 2016).
 * Alimenta el autocompletado de direccion (CP -> estado / municipio / ciudad /
 * colonias) en los formularios de Personas y Empresas.
 *
 * Se llena con `php artisan db:seed --class=CodigosPostalesSeeder`
 * (lee database/data/sepomex.csv).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('codigos_postales')) {
            return;
        }

        Schema::create('codigos_postales', function (Blueprint $table) {
            $table->id();
            $table->char('cp', 5)->index();
            $table->string('estado', 100);
            $table->string('municipio', 150);
            $table->string('ciudad', 150)->nullable();
            $table->string('asentamiento', 200);       // colonia / fraccionamiento / etc.
            $table->string('tipo_asentamiento', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_postales');
    }
};
