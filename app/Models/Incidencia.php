<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Incidencia extends Model
{
    protected $table = 'incidencias';

    public const TIPOS = [
        'INCIDENCIA' => 'Incidencia / desperfecto',
        'SUGERENCIA' => 'Sugerencia',
    ];

    public const CATEGORIAS = [
        'ALUMBRADO' => 'Alumbrado',
        'AGUA' => 'Agua / fugas / drenaje',
        'VIALIDADES' => 'Calles / baches / banquetas',
        'AREAS_VERDES' => 'Áreas verdes',
        'LIMPIEZA' => 'Limpieza / basura',
        'AMENIDADES' => 'Amenidades (alberca, juegos, salón)',
        'ACCESOS' => 'Accesos / portones / bardas',
        'OTRO' => 'Otro',
    ];

    public const PRIORIDADES = [
        'BAJA' => 'Baja',
        'MEDIA' => 'Media',
        'ALTA' => 'Alta',
        'URGENTE' => 'Urgente',
    ];

    public const ESTATUS = [
        'PENDIENTE' => 'Pendiente',
        'EN PROCESO' => 'En proceso',
        'ATENDIDA' => 'Atendida',
        'SUPERVISADA' => 'Supervisada',
        'CANCELADA' => 'Cancelada',
    ];

    /**
     * Flujo permitido: estatus actual => [nuevo estatus => rol que lo puede hacer].
     *  'atender'    = mantenimiento y administración
     *  'supervisar' = sólo administración
     *  'reportante' = quien la reportó (sólo cancelar mientras siga pendiente)
     */
    public const TRANSICIONES = [
        'PENDIENTE' => ['EN PROCESO' => 'atender', 'CANCELADA' => 'supervisar|reportante'],
        'EN PROCESO' => ['ATENDIDA' => 'atender', 'CANCELADA' => 'supervisar'],
        'ATENDIDA' => ['SUPERVISADA' => 'supervisar', 'EN PROCESO' => 'supervisar'],
        'SUPERVISADA' => [],
        'CANCELADA' => ['PENDIENTE' => 'supervisar'],
    ];

    protected $fillable = [
        'id_empresa',
        'folio',
        'tipo',
        'categoria',
        'prioridad',
        'titulo',
        'descripcion',
        'ubicacion',
        'latitud',
        'longitud',
        'estatus',
        'id_usuario_reporta',
        'id_casa',
        'id_usuario_asignado',
        'fecha_inicio',
        'fecha_atencion',
        'fecha_supervision',
        'fecha_cancelacion',
    ];

    protected $attributes = [
        'estatus' => 'PENDIENTE',
        'tipo' => 'INCIDENCIA',
        'prioridad' => 'MEDIA',
    ];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'fecha_inicio' => 'datetime',
        'fecha_atencion' => 'datetime',
        'fecha_supervision' => 'datetime',
        'fecha_cancelacion' => 'datetime',
    ];

    public function condominio()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
    }

    public function reporta()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_reporta', 'id_usuario');
    }

    public function asignado()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_asignado', 'id_usuario');
    }

    public function seguimientos()
    {
        return $this->hasMany(IncidenciaSeguimiento::class, 'id_incidencia')->oldest();
    }

    public function fotos()
    {
        return $this->hasMany(IncidenciaFoto::class, 'id_incidencia');
    }

    public function gastos()
    {
        return $this->hasMany(IncidenciaGasto::class, 'id_incidencia');
    }

    /** Siguiente folio del condominio: INC-0001, INC-0002… (llamar dentro de una transacción). */
    public static function siguienteFolio(int $empresaId): string
    {
        $ultimo = static::where('id_empresa', $empresaId)
            ->lockForUpdate()
            ->selectRaw("MAX(CAST(SUBSTRING(folio, 5) AS UNSIGNED)) as n")
            ->value('n');

        return 'INC-' . str_pad(((int) $ultimo) + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Estatus a los que este usuario puede mover la incidencia.
     */
    public function transicionesPara(Usuario $usuario, bool $puedeAtender, bool $puedeSupervisar): array
    {
        $esReportante = $this->id_usuario_reporta === $usuario->id_usuario;

        return collect(self::TRANSICIONES[$this->estatus] ?? [])
            ->filter(function ($rol) use ($puedeAtender, $puedeSupervisar, $esReportante) {
                foreach (explode('|', $rol) as $r) {
                    if (($r === 'atender' && $puedeAtender)
                        || ($r === 'supervisar' && $puedeSupervisar)
                        || ($r === 'reportante' && $esReportante)) {
                        return true;
                    }
                }
                return false;
            })
            ->keys()
            ->values()
            ->all();
    }

    /**
     * Condóminos y personal ven las incidencias de sus condominios.
     */
    public function scopeVisiblesPara(Builder $query, Usuario $usuario): Builder
    {
        return $query->whereIn('id_empresa', $usuario->idsCondominios());
    }
}
