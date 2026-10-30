<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visita extends Model
{
    protected $table = 'visitas';

    public const TIPOS = [
        'VISITA' => 'Visita',
        'PROVEEDOR' => 'Proveedor / servicio',
        'PAQUETERIA' => 'Paquetería',
        'TRANSPORTE' => 'Taxi / app',
        'EMPLEADO' => 'Empleado doméstico',
        'OTRO' => 'Otro',
    ];

    public const ESTATUS = [
        'PREAUTORIZADA' => 'Pre-autorizada',
        'ESPERANDO' => 'Esperando autorización',
        'AUTORIZADA' => 'Autorizada',
        'RECHAZADA' => 'Rechazada',
        'DENTRO' => 'Dentro',
        'SALIO' => 'Salió',
        'CANCELADA' => 'Cancelada',
    ];

    protected $fillable = [
        'id_empresa', 'id_casa', 'tipo_visita', 'nombre_visitante', 'identificacion', 'placas', 'vehiculo',
        'num_personas', 'motivo', 'foto', 'estatus', 'fecha_esperada', 'id_usuario_registro', 'fecha_llegada',
        'id_usuario_respuesta', 'fecha_respuesta', 'comentario_respuesta', 'id_usuario_entrada', 'fecha_entrada',
        'id_usuario_salida', 'fecha_salida',
    ];

    protected $attributes = [
        'estatus' => 'ESPERANDO',
        'tipo_visita' => 'VISITA',
        'num_personas' => 1,
    ];

    protected $casts = [
        'fecha_esperada' => 'date:Y-m-d',
        'fecha_llegada' => 'datetime',
        'fecha_respuesta' => 'datetime',
        'fecha_entrada' => 'datetime',
        'fecha_salida' => 'datetime',
    ];

    protected $appends = ['foto_url'];

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
    }

    public function registro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    public function respuesta()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_respuesta', 'id_usuario');
    }

    public function entrada()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_entrada', 'id_usuario');
    }

    public function salida()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_salida', 'id_usuario');
    }

    /** Datos para la caseta, la casa y el pulso global. */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'casa' => $this->casa?->identificador,
            'id_casa' => $this->id_casa,
            'tipo_visita' => $this->tipo_visita,
            'nombre_visitante' => $this->nombre_visitante,
            'identificacion' => $this->identificacion,
            'placas' => $this->placas,
            'vehiculo' => $this->vehiculo,
            'num_personas' => $this->num_personas,
            'motivo' => $this->motivo,
            'foto_url' => $this->foto_url,
            'estatus' => $this->estatus,
            'fecha_esperada' => $this->fecha_esperada?->format('Y-m-d'),
            'fecha_llegada' => $this->fecha_llegada,
            'registro' => $this->registro?->nombre_usuario,
            'fecha_respuesta' => $this->fecha_respuesta,
            'respuesta' => $this->respuesta?->nombre_usuario,
            'comentario_respuesta' => $this->comentario_respuesta,
            'fecha_entrada' => $this->fecha_entrada,
            'entrada' => $this->entrada?->nombre_usuario,
            'fecha_salida' => $this->fecha_salida,
            'salida' => $this->salida?->nombre_usuario,
            'updated_at' => $this->updated_at,
        ];
    }

    // La foto (identificación / vehículo) se sirve con permisos, no desde /storage.
    public function getFotoUrlAttribute()
    {
        return $this->foto ? route('seguridad.visitas.foto', $this->id) : null;
    }
}
