<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Crea la base SQLite como copia fiel de la base MySQL: misma estructura (nombres
 * exactos de columnas, tipos, valores por defecto, índices y llaves foráneas) y
 * los mismos datos, incluido el registro de migraciones.
 *
 * Se usa la estructura real de MySQL (no las migraciones) porque esa base se armó
 * importando un respaldo y con scripts SQL aplicados a mano: hay columnas que
 * ninguna migración crea.
 *
 * MySQL sólo se lee. Si ya existe el archivo SQLite, primero se respalda.
 *
 *   php artisan db:sqlite-desde-mysql
 */
class SqliteDesdeMysql extends Command
{
    protected $signature = 'db:sqlite-desde-mysql {--origen=mysql : Conexión MySQL de origen} {--sin-respaldo : No respaldar el archivo SQLite existente}';

    protected $description = 'Crea la base SQLite con la estructura y los datos de MySQL';

    /** Tablas temporales: se crea su estructura pero no se copian sus datos. */
    private const SIN_DATOS = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    public function handle(): int
    {
        $origen = DB::connection($this->option('origen'));
        $archivo = config('database.connections.sqlite.database');

        if (file_exists($archivo) && filesize($archivo) > 0 && !$this->option('sin-respaldo')) {
            $respaldo = $archivo . '.respaldo-' . date('Ymd-His');
            DB::purge('sqlite');
            copy($archivo, $respaldo);
            $this->line('Respaldo del SQLite anterior: ' . basename($respaldo));
        }
        if (!file_exists($archivo)) {
            touch($archivo);
        }
        $destino = DB::connection('sqlite');
        // Base vacía: se eliminan todas las tablas e índices (el archivo puede estar abierto).
        $destino->statement('PRAGMA foreign_keys = OFF');
        \Illuminate\Support\Facades\Schema::connection('sqlite')->dropAllTables();
        $destino->statement('VACUUM');

        $esquema = $origen->getDatabaseName();
        $tablas = collect($origen->select('SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME', [$esquema, 'BASE TABLE']))->pluck('t');

        // ---------- 1) Estructura ----------
        foreach ($tablas as $tabla) {
            $destino->statement($this->crearTabla($origen, $destino, $esquema, $tabla));
            foreach ($this->indices($origen, $esquema, $tabla) as $sql) {
                $destino->statement($sql);
            }
        }
        $this->info("Estructura creada: {$tablas->count()} tablas.");

        // ---------- 2) Datos ----------
        $diferencias = 0;
        foreach ($tablas as $tabla) {
            if (in_array($tabla, self::SIN_DATOS, true)) {
                continue;
            }
            $columnas = collect($origen->select('SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$esquema, $tabla]))->pluck('c')->all();
            $llave = in_array('id', $columnas, true) ? 'id' : $columnas[0];
            $lote = max(50, intdiv(30000, max(1, count($columnas)))); // límite de variables por consulta de SQLite

            $destino->transaction(function () use ($origen, $destino, $tabla, $columnas, $llave, $lote) {
                $consulta = $origen->table($tabla)->select($columnas);
                $insertar = fn ($filas) => $destino->table($tabla)->insert($filas->map(fn ($f) => (array) $f)->all());
                $llave === 'id' ? $consulta->chunkById($lote, $insertar, 'id') : $consulta->orderBy($llave)->chunk($lote, $insertar);
            });

            $n = $destino->table($tabla)->count();
            $ok = $n === $origen->table($tabla)->count();
            $diferencias += $ok ? 0 : 1;
            $this->line(sprintf('%s %-34s %7d filas', $ok ? '✔' : '✘', $tabla, $n));
        }

        $destino->statement('PRAGMA foreign_keys = ON');
        $huerfanos = count($destino->select('PRAGMA foreign_key_check'));
        if ($huerfanos) {
            $this->warn("{$huerfanos} registro(s) apuntan a datos inexistentes (ya venían así de MySQL).");
        }
        $integridad = $destino->selectOne('PRAGMA integrity_check')->integrity_check ?? 'desconocido';
        $this->line("Integridad de SQLite: {$integridad}");

        if ($diferencias) {
            $this->error("{$diferencias} tabla(s) con conteo distinto.");

            return self::FAILURE;
        }
        $this->info('Copia completa: estructura y datos idénticos a MySQL.');

        return self::SUCCESS;
    }

    private function crearTabla(Connection $origen, Connection $destino, string $esquema, string $tabla): string
    {
        $q = fn (string $n) => '"' . str_replace('"', '""', $n) . '"';
        $columnas = $origen->select('SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_KEY
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$esquema, $tabla]);
        $primaria = collect($origen->select("SELECT COLUMN_NAME AS c FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION", [$esquema, $tabla]))->pluck('c')->all();

        $partes = [];
        foreach ($columnas as $c) {
            $tipo = $this->tipo($c->DATA_TYPE, $c->COLUMN_TYPE);
            $autoincremento = str_contains($c->EXTRA, 'auto_increment') && $primaria === [$c->COLUMN_NAME];
            if ($autoincremento) {
                $partes[] = $q($c->COLUMN_NAME) . ' integer primary key autoincrement not null';
                continue;
            }
            $def = $q($c->COLUMN_NAME) . ' ' . $tipo;
            if ($c->IS_NULLABLE === 'NO') {
                $def .= ' not null';
            }
            if ($c->COLUMN_DEFAULT !== null) {
                $def .= ' default ' . $this->valorPorDefecto($destino, $c);
            }
            if ($c->DATA_TYPE === 'enum') {
                // Mismos valores permitidos que en MySQL.
                $def .= ' check (' . $q($c->COLUMN_NAME) . ' in ' . substr($c->COLUMN_TYPE, 4) . ')';
            }
            $partes[] = $def;
        }

        $tieneAuto = collect($columnas)->contains(fn ($c) => str_contains($c->EXTRA, 'auto_increment') && $primaria === [$c->COLUMN_NAME]);
        if ($primaria && !$tieneAuto) {
            $partes[] = 'primary key (' . implode(', ', array_map($q, $primaria)) . ')';
        }

        // Llaves foráneas reales de MySQL (con sus reglas ON DELETE / ON UPDATE).
        $fks = $origen->select('SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
            WHERE k.TABLE_SCHEMA = ? AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION', [$esquema, $tabla]);
        foreach (collect($fks)->groupBy('CONSTRAINT_NAME') as $fk) {
            $partes[] = sprintf(
                'foreign key (%s) references %s (%s) on delete %s on update %s',
                $fk->pluck('COLUMN_NAME')->map($q)->implode(', '),
                $q($fk->first()->REFERENCED_TABLE_NAME),
                $fk->pluck('REFERENCED_COLUMN_NAME')->map($q)->implode(', '),
                strtolower($fk->first()->DELETE_RULE),
                strtolower($fk->first()->UPDATE_RULE),
            );
        }

        return 'create table ' . $q($tabla) . " (\n    " . implode(",\n    ", $partes) . "\n)";
    }

    /** @return string[] sentencias CREATE INDEX */
    private function indices(Connection $origen, string $esquema, string $tabla): array
    {
        $q = fn (string $n) => '"' . str_replace('"', '""', $n) . '"';
        $filas = $origen->select("SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, INDEX_TYPE FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME <> 'PRIMARY' ORDER BY INDEX_NAME, SEQ_IN_INDEX", [$esquema, $tabla]);

        $sql = [];
        foreach (collect($filas)->groupBy('INDEX_NAME') as $nombre => $cols) {
            if ($cols->first()->INDEX_TYPE === 'FULLTEXT' || $cols->contains(fn ($c) => $c->COLUMN_NAME === null)) {
                continue;
            }
            // En SQLite los nombres de índice son globales: se antepone la tabla si hace falta.
            $nombreSqlite = str_starts_with($nombre, $tabla . '_') ? $nombre : "{$tabla}_{$nombre}";
            $sql[] = sprintf(
                'create %sindex %s on %s (%s)',
                (int) $cols->first()->NON_UNIQUE === 0 ? 'unique ' : '',
                $q($nombreSqlite),
                $q($tabla),
                $cols->pluck('COLUMN_NAME')->map($q)->implode(', '),
            );
        }

        return $sql;
    }

    /** Tipo SQLite equivalente (conserva el nombre para que Laravel lo reconozca). */
    private function tipo(string $dato, string $columna): string
    {
        return match ($dato) {
            'tinyint' => str_starts_with($columna, 'tinyint(1)') ? 'tinyint(1)' : 'integer',
            'smallint', 'mediumint', 'int', 'integer', 'bigint', 'year', 'bit' => 'integer',
            'decimal', 'numeric' => 'numeric',
            'float' => 'float',
            'double', 'real' => 'double',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'time' => 'time',
            'char', 'varchar', 'enum', 'set' => 'varchar',
            'json' => 'text',
            'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob' => 'blob',
            default => 'text', // text, tinytext, mediumtext, longtext
        };
    }

    private function valorPorDefecto(Connection $destino, object $c): string
    {
        $v = $c->COLUMN_DEFAULT;
        if (str_contains($c->EXTRA, 'DEFAULT_GENERATED') || preg_match('/^current_timestamp/i', $v)) {
            return 'CURRENT_TIMESTAMP';
        }
        if (in_array($c->DATA_TYPE, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'decimal', 'numeric', 'float', 'double', 'year'], true) && is_numeric($v)) {
            return $v;
        }

        return $destino->getPdo()->quote($v);
    }
}
