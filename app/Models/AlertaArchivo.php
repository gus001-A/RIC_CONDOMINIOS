<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaArchivo extends Model
{
    protected $table = 'alerta_archivos';

    protected $fillable = ['id_alerta', 'ruta', 'nombre_original', 'mime'];

    public function alerta()
    {
        return $this->belongsTo(AlertaSeguridad::class, 'id_alerta');
    }
}
