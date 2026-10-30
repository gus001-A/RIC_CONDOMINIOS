<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogActividad extends Model
{
    use HasFactory;

    protected $table = 'logs_actividad';

    protected $fillable = [
        'id_usuario',
        'accion',
        'tabla_afectada',
        'id_registro_afectado',
        'datos_anteriores',
        'datos_nuevos',
        'ip_origen',
        'fecha_hora'
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
        'fecha_hora' => 'datetime',
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    // Métodos
    public function getAccionTextoAttribute()
    {
        $acciones = [
            'CREAR' => 'Creación',
            'EDITAR' => 'Edición',
            'ELIMINAR' => 'Eliminación',
            'AUTORIZAR' => 'Autorización',
            'ABONAR' => 'Abono',
            'LIQUIDAR' => 'Liquidación',
            'LOGIN' => 'Inicio de Sesión',
            'LOGOUT' => 'Cierre de Sesión',
        ];
        return $acciones[$this->accion] ?? $this->accion;
    }

    public function getCambiosTextoAttribute()
    {
        if (!$this->datos_anteriores && !$this->datos_nuevos) {
            return 'Sin cambios registrados';
        }
        return 'Se realizaron cambios';
    }

    // Scopes
    public function scopeByUsuario($query, $usuarioId)
    {
        return $query->where('id_usuario', $usuarioId);
    }

    public function scopeByAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopeByTabla($query, $tabla)
    {
        return $query->where('tabla_afectada', $tabla);
    }

    public function scopeByDate($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_hora', [$fechaInicio, $fechaFin]);
    }
}