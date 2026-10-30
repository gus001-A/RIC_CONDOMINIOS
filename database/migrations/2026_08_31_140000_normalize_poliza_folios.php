<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza los folios de pólizas: sólo números, secuenciales (0001, 0002...).
 *
 * Las pólizas cuyo folio tenía letras (P-YYYYMMDD-xxxxxx, NOM-...) se renumeran
 * en orden de creación (id) a partir del siguiente número disponible; las que ya
 * eran numéricas se conservan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Corrección de datos históricos de MySQL; una base nueva no tiene folios que normalizar.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Mayor folio actualmente numérico.
        $siguiente = 1 + (int) DB::table('polizas')
            ->whereRaw("folio REGEXP '^[0-9]+$'")
            ->selectRaw('MAX(CAST(folio AS UNSIGNED)) as m')
            ->value('m');

        $pendientes = DB::table('polizas')
            ->where(function ($q) {
                $q->whereRaw("folio NOT REGEXP '^[0-9]+$'")
                  ->orWhereNull('folio');
            })
            ->orderBy('id')
            ->pluck('id');

        foreach ($pendientes as $id) {
            DB::table('polizas')
                ->where('id', $id)
                ->update(['folio' => str_pad($siguiente, 4, '0', STR_PAD_LEFT)]);
            $siguiente++;
        }
    }

    public function down(): void
    {
        // No se revierte: no se guarda el folio anterior.
    }
};
