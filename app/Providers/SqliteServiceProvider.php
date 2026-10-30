<?php

namespace App\Providers;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Compatibilidad de SQLite con las pocas funciones de MySQL que usa el sistema.
 * SQLite ya trae CONCAT, CONCAT_WS, COALESCE, IFNULL y fechas; aquí se agregan:
 *   - REGEXP  (folios numéricos de pólizas)
 *   - FIELD() (ordenar por una lista de estatus / prioridades)
 * Con MySQL no hace nada.
 */
class SqliteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Lo antes posible: cualquier conexión SQLite que se abra recibe las funciones.
        $this->app['events']->listen(ConnectionEstablished::class, fn (ConnectionEstablished $e) => self::aplicar($e->connection));
    }

    public function boot(): void
    {
        // Y las que ya estuvieran abiertas antes de registrar el listener.
        foreach (DB::getConnections() as $conexion) {
            self::aplicar($conexion);
        }
    }

    public static function aplicar(Connection $conexion): void
    {
        if ($conexion->getDriverName() !== 'sqlite') {
            return;
        }
        $pdo = $conexion->getPdo();
        $crear = method_exists($pdo, 'createFunction')
            ? fn (string $nombre, callable $fn, int $args) => $pdo->createFunction($nombre, $fn, $args)
            : fn (string $nombre, callable $fn, int $args) => $pdo->sqliteCreateFunction($nombre, $fn, $args);

        // "x REGEXP 'patrón'" → SQLite llama regexp('patrón', x)
        $crear('regexp', function ($patron, $valor) {
            if ($valor === null || $patron === null) {
                return 0;
            }

            return preg_match('/' . str_replace('/', '\/', $patron) . '/u', (string) $valor) ? 1 : 0;
        }, 2);

        // FIELD(valor, 'A', 'B', ...) → posición (1..n) o 0 si no está
        $crear('field', function ($valor, ...$lista) {
            $i = array_search($valor, $lista, false);

            return $i === false ? 0 : $i + 1;
        }, -1);
    }
}
