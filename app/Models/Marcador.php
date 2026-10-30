<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marcador extends Model
{
    use HasFactory;

    protected $table = 'marcadores';

    protected $fillable = [
        'nombre_marcador',
        'descripcion',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function empresasUsuarios()
    {
        return $this->hasMany(EmpresaUsuarioMarcador::class, 'id_marcador');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('nombre_marcador', 'LIKE', "%{$search}%");
    }
}