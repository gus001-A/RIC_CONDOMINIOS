<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaSeguimiento extends Model
{
    protected $table = 'incidencia_seguimientos';

    protected $fillable = [
        'id_incidencia',
        'id_usuario',
        'estatus_anterior',
        'estatus_nuevo',
        'comentario',
    ];

    public function incidencia()
    {
        return $this->belongsTo(Incidencia::class, 'id_incidencia');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function fotos()
    {
        return $this->hasMany(IncidenciaFoto::class, 'id_seguimiento');
    }

    public function gastos()
    {
        return $this->hasMany(IncidenciaGasto::class, 'id_seguimiento');
    }
}
