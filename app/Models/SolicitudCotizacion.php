<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCotizacion extends Model
{
    protected $table = 'solicitud_cotizaciones';

    protected $fillable = [
        'id_solicitud', 'proveedor', 'contacto_proveedor', 'concepto', 'monto', 'notas', 'archivo_ruta',
        'archivo_nombre', 'estatus', 'id_usuario_registro', 'id_usuario_respuesta', 'fecha_respuesta',
    ];

    protected $attributes = [
        'estatus' => 'PROPUESTA',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_respuesta' => 'datetime',
    ];

    protected $hidden = ['archivo_ruta'];

    protected $appends = ['archivo_url'];

    public function registro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    public function respuesta()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_respuesta', 'id_usuario');
    }

    public function getArchivoUrlAttribute()
    {
        return $this->archivo_ruta ? route('servicios.adjunto', ['tipo' => 'cotizacion', 'id' => $this->id]) : null;
    }
}
