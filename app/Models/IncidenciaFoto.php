<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaFoto extends Model
{
    protected $table = 'incidencia_fotos';

    protected $fillable = [
        'id_incidencia',
        'id_seguimiento',
        'ruta',
        'nombre_original',
        'id_usuario',
    ];

    protected $appends = ['url'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->ruta);
    }
}
