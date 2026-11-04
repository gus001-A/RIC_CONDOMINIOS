<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas que el código usa pero que ninguna migración creaba (se habían
 * añadido a mano en las bases existentes). En una instalación desde cero, sin esta
 * migración, fallan el CondominiosDemoSeeder (cuentas.fondeo_c) y el panel
 * (polizas.id_empresa).
 *
 * Cada columna se agrega sólo si no existe, así que es segura en bases que ya las
 * tienen. polizas.id_empresa queda NULL-able porque no se puede agregar NOT NULL
 * sin valor por defecto a una tabla con registros.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->agregar('cuentas', [
            'indice_c' => fn (Blueprint $t) => $t->string('indice_c')->nullable(),
            'clave_c' => fn (Blueprint $t) => $t->string('clave_c')->nullable(),
            'madre_c' => fn (Blueprint $t) => $t->unsignedBigInteger('madre_c')->nullable(),
            'empresa_c' => fn (Blueprint $t) => $t->unsignedBigInteger('empresa_c')->nullable(),
            'origen_c' => fn (Blueprint $t) => $t->unsignedBigInteger('origen_c')->nullable(),
            'depreciacion_c' => fn (Blueprint $t) => $t->unsignedBigInteger('depreciacion_c')->nullable(),
            'usuario_c' => fn (Blueprint $t) => $t->unsignedBigInteger('usuario_c')->nullable(),
            'fecha_c' => fn (Blueprint $t) => $t->dateTime('fecha_c')->nullable(),
            'id_empresa_c' => fn (Blueprint $t) => $t->unsignedBigInteger('id_empresa_c')->nullable(),
            'fondeo_c' => fn (Blueprint $t) => $t->integer('fondeo_c')->nullable()->default(0),
            'cuenta_resultados' => fn (Blueprint $t) => $t->unsignedBigInteger('cuenta_resultados')->nullable()->default(0),
            'regimen_c' => fn (Blueprint $t) => $t->string('regimen_c')->nullable(),
            'check_resultados' => fn (Blueprint $t) => $t->string('check_resultados')->nullable(),
            'check_uso' => fn (Blueprint $t) => $t->string('check_uso')->nullable(),
        ]);

        $this->agregar('personas', [
            'razon_social' => fn (Blueprint $t) => $t->string('razon_social')->nullable(),
        ]);

        $nuevaEmpresa = ! Schema::hasColumn('polizas', 'id_empresa');
        $nuevoMarcador = ! Schema::hasColumn('polizas', 'id_marcador');

        $this->agregar('polizas', [
            'id_empresa' => fn (Blueprint $t) => $t->unsignedBigInteger('id_empresa')->nullable()->index(),
            'fecha_factura' => fn (Blueprint $t) => $t->date('fecha_factura')->nullable(),
            'numero_factura' => fn (Blueprint $t) => $t->string('numero_factura')->nullable(),
            'id_marcador' => fn (Blueprint $t) => $t->unsignedBigInteger('id_marcador')->nullable(),
            'ruta_pdf' => fn (Blueprint $t) => $t->string('ruta_pdf')->nullable(),
            'nombre_pdf' => fn (Blueprint $t) => $t->string('nombre_pdf')->nullable(),
            'nombre_xml' => fn (Blueprint $t) => $t->string('nombre_xml')->nullable(),
            'uuid_factura' => fn (Blueprint $t) => $t->string('uuid_factura')->nullable(),
            'serie_factura' => fn (Blueprint $t) => $t->string('serie_factura')->nullable(),
            'folio_factura' => fn (Blueprint $t) => $t->string('folio_factura')->nullable(),
        ]);

        // Va antes de 2026_09_05_120000 (folio por empresa), que ya usa
        // polizas.id_empresa. En bases donde esa migración corrió sin la columna
        // (SQLite lo permite), el UNIQUE (id_empresa, folio) quedó mal: se recrea.
        if ($nuevaEmpresa) {
            $indices = collect(Schema::getIndexes('polizas'))->pluck('name');
            Schema::table('polizas', function (Blueprint $t) use ($indices) {
                if ($indices->contains('polizas_empresa_folio_unique')) {
                    $t->dropUnique('polizas_empresa_folio_unique');
                }
            });
            Schema::table('polizas', fn (Blueprint $t) => $t->unique(['id_empresa', 'folio'], 'polizas_empresa_folio_unique'));
        }

        // Llaves foráneas sólo en MySQL/MariaDB (SQLite no permite agregarlas a una
        // tabla existente sin reconstruirla).
        if (($nuevaEmpresa || $nuevoMarcador) && DB::getDriverName() !== 'sqlite') {
            Schema::table('polizas', function (Blueprint $t) use ($nuevaEmpresa, $nuevoMarcador) {
                if ($nuevaEmpresa) {
                    $t->foreign('id_empresa')->references('id')->on('empresas');
                }
                if ($nuevoMarcador) {
                    $t->foreign('id_marcador')->references('id')->on('marcadores');
                }
            });
        }
    }

    public function down(): void
    {
        // No se eliminan: en las bases que ya tenían estas columnas (agregadas a mano)
        // borrarlas destruiría datos.
    }

    /** @param  array<string, callable(Blueprint): mixed>  $columnas */
    private function agregar(string $tabla, array $columnas): void
    {
        $faltantes = array_filter(
            $columnas,
            fn ($_, $columna) => ! Schema::hasColumn($tabla, $columna),
            ARRAY_FILTER_USE_BOTH
        );

        if ($faltantes === []) {
            return;
        }

        Schema::table($tabla, function (Blueprint $t) use ($faltantes) {
            foreach ($faltantes as $definir) {
                $definir($t);
            }
        });
    }
};
