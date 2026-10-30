<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga database/data/sepomex.csv en la tabla `codigos_postales`.
 * Idempotente: vacia la tabla antes de cargar.
 *
 *   php artisan db:seed --class=CodigosPostalesSeeder
 */
class CodigosPostalesSeeder extends Seeder
{
    public function run(): void
    {
        $csv = database_path('data/sepomex.csv');

        if (! is_file($csv)) {
            $this->command->error("No se encontro {$csv}");
            return;
        }

        $handle = fopen($csv, 'r');
        fgetcsv($handle); // saltar encabezado: idEstado,estado,idMunicipio,municipio,ciudad,zona,cp,asentamiento,tipo

        DB::table('codigos_postales')->truncate();
        DB::disableQueryLog();

        $buffer = [];
        $total  = 0;
        $flush  = function () use (&$buffer, &$total) {
            if ($buffer) {
                DB::table('codigos_postales')->insert($buffer);
                $total += count($buffer);
                $buffer = [];
            }
        };

        $clean = function ($v) {
            $v = trim((string) $v);

            return ($v === '' || strcasecmp($v, 'NULL') === 0) ? null : $v;
        };

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 9) {
                continue;
            }

            $buffer[] = [
                'cp'                => str_pad($clean($row[6]) ?? '', 5, '0', STR_PAD_LEFT),
                'estado'            => $clean($row[1]),
                'municipio'         => $clean($row[3]),
                'ciudad'            => $clean($row[4]),
                'asentamiento'      => $clean($row[7]),
                'tipo_asentamiento' => $clean($row[8]),
            ];

            if (count($buffer) >= 2000) {
                $flush();
            }
        }

        $flush();
        fclose($handle);

        $this->command->info("codigos_postales: {$total} registros cargados.");
    }
}
