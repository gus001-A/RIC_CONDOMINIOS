<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoIva extends Model
{
    use HasFactory;

    protected $table = 'tipos_iva';

    protected $fillable = [
        'nombre',
        'porcentaje',
        'activo'
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];


    // Métodos
    public function getPorcentajeFormateadoAttribute()
    {
        if ($this->nombre === 'EXENTO') {
            return 'Exento';
        }
        return "{$this->porcentaje}%";
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}