<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CasaFoto extends Model
{
    protected $table = 'casa_fotos';

    public const TIPOS = [
        'FACHADA' => 'Fachada',
        'RESIDENTE' => 'Residente',
        'VEHICULO' => 'Vehículo',
        'OTRO' => 'Otro',
    ];

    protected $fillable = [
        'id_casa',
        'tipo',
        'ruta',
        'nombre_original',
        'descripcion',
        'id_usuario_subio',
    ];

    protected $appends = ['url'];

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
    }

    public function subio()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_subio', 'id_usuario');
    }

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->ruta);
    }
}
