<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Endpoints de apoyo para capturar direcciones en los formularios:
 *  - catalogo estados / municipios (JSON estatico INEGI)
 *  - busqueda por codigo postal (tabla `codigos_postales`, datos SEPOMEX)
 *
 * NOTA: la tabla `codigos_postales` se llena con
 *   php artisan db:seed --class=CodigosPostalesSeeder
 * o importando database/sql/codigos_postales.sql(.gz). Si NO existe / esta vacia
 * (p. ej. deploy por FTP sin correr seeders) estos endpoints degradan sin
 * romper el formulario: devuelven `found:false` con un mensaje claro.
 */
class DireccionController extends Controller
{
    /** Ruta al JSON { "Estado": ["Municipio", ...] }. */
    private function catalogoPath(): string
    {
        return resource_path('data/estados-municipios.json');
    }

    private function catalogo(): array
    {
        return Cache::rememberForever('direccion.catalogo_estados_municipios', function () {
            $path = $this->catalogoPath();

            return is_file($path)
                ? (json_decode(file_get_contents($path), true) ?: [])
                : [];
        });
    }

    /** GET /direccion/estados  ->  ["Aguascalientes", ...] */
    public function estados()
    {
        return response()->json(array_keys($this->catalogo()));
    }

    /** GET /direccion/municipios?estado=Morelos  ->  ["Cuernavaca", ...] */
    public function municipios(Request $request)
    {
        $estado = trim((string) $request->query('estado', ''));
        $catalogo = $this->catalogo();

        // match exacto y, si falla, sin acentos / case-insensitive
        if (isset($catalogo[$estado])) {
            return response()->json($catalogo[$estado]);
        }

        $norm = fn ($s) => mb_strtolower(strtr($s, 'áéíóúüÁÉÍÓÚÜ', 'aeiouuAEIOUU'));
        foreach ($catalogo as $nombre => $municipios) {
            if ($norm($nombre) === $norm($estado)) {
                return response()->json($municipios);
            }
        }

        return response()->json([]);
    }

    /**
     * GET /direccion/cp/62577
     * -> { found, cp, estado, municipio, ciudad, colonias: [{nombre, tipo}, ...] }
     *
     * Nunca lanza 500: si la tabla no existe / esta vacia o hay un error de BD,
     * responde 200 con found:false + message.
     */
    public function codigoPostal(string $cp)
    {
        $cp = preg_replace('/\D/', '', $cp);

        if (strlen($cp) !== 5) {
            return response()->json([
                'found'   => false,
                'message' => 'El código postal debe tener 5 dígitos.',
            ]);
        }

        try {
            if (! Schema::hasTable('codigos_postales')) {
                return response()->json([
                    'found'   => false,
                    'message' => 'El catálogo de códigos postales no está instalado en este servidor. '
                        . 'Importa database/sql/codigos_postales.sql.gz (ver database/sql/LEEME_codigos_postales.md) '
                        . 'o corre: php artisan db:seed --class=CodigosPostalesSeeder',
                ]);
            }

            $data = Cache::remember("direccion.cp.{$cp}", now()->addDays(30), function () use ($cp) {
                $rows = DB::table('codigos_postales')
                    ->where('cp', $cp)
                    ->get(['estado', 'municipio', 'ciudad', 'asentamiento', 'tipo_asentamiento']);

                if ($rows->isEmpty()) {
                    return ['found' => false];
                }

                $primero = $rows->first();

                return [
                    'found'     => true,
                    'estado'    => $primero->estado,
                    'municipio' => $primero->municipio,
                    // La "ciudad" ya no se captura por separado en los
                    // formularios: siempre es igual al municipio.
                    'ciudad'    => $primero->municipio,
                    'colonias'  => $rows->map(fn ($r) => [
                        'nombre' => $r->asentamiento,
                        'tipo'   => $r->tipo_asentamiento,
                    ])->sortBy('nombre')->values(),
                ];
            });

            if (empty($data['found'])) {
                return response()->json([
                    'found'   => false,
                    'cp'      => $cp,
                    'message' => 'No encontramos ese código postal.',
                ]);
            }

            $data['cp'] = $cp;

            return response()->json($data);
        } catch (\Throwable $e) {
            \Log::warning('DireccionController@codigoPostal falló: ' . $e->getMessage());

            return response()->json([
                'found'   => false,
                'cp'      => $cp,
                'message' => 'No se pudo consultar el código postal en este momento. '
                    . 'Puedes capturar la dirección manualmente.',
            ]);
        }
    }
}
