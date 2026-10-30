<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El folio de las pólizas pasa a ser consecutivo POR EMPRESA (cada empresa
 * lleva su propia numeración 0001, 0002, ...), en vez de una secuencia global.
 *
 *  1. Renumera las pólizas existentes: por cada empresa, en orden de creación
 *     (id), reasigna folio 0001, 0002, 0003, ...
 *  2. Quita el índice UNIQUE global sobre `folio` (permitía sólo un "0001" en
 *     todo el sistema) y lo reemplaza por un UNIQUE compuesto (id_empresa, folio).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Renumerar folios por empresa ──────────────────────────────
        $empresaIds = DB::table('polizas')
            ->select('id_empresa')
            ->distinct()
            ->pluck('id_empresa');

        foreach ($empresaIds as $empresaId) {
            $ids = DB::table('polizas')
                ->where('id_empresa', $empresaId)
                ->orderBy('id')
                ->pluck('id');

            $consecutivo = 1;
            foreach ($ids as $id) {
                DB::table('polizas')
                    ->where('id', $id)
                    ->update(['folio' => str_pad($consecutivo, 4, '0', STR_PAD_LEFT)]);
                $consecutivo++;
            }
        }

        // ── 2. Índices ───────────────────────────────────────────────────
        $indices = collect(Schema::getIndexes('polizas'))->pluck('name');

        Schema::table('polizas', function ($table) use ($indices) {
            if ($indices->contains('polizas_folio_unique')) {
                $table->dropUnique('polizas_folio_unique');
            }
            if (!$indices->contains('polizas_empresa_folio_unique')) {
                $table->unique(['id_empresa', 'folio'], 'polizas_empresa_folio_unique');
            }
        });
    }

    public function down(): void
    {
        $indices = collect(Schema::getIndexes('polizas'))->pluck('name');

        Schema::table('polizas', function ($table) use ($indices) {
            if ($indices->contains('polizas_empresa_folio_unique')) {
                $table->dropUnique('polizas_empresa_folio_unique');
            }
        });
        // No se restaura el UNIQUE global sobre `folio`: tras renumerar por
        // empresa habría folios repetidos entre empresas y fallaría.
    }
};
