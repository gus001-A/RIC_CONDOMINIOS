<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudSeguimiento extends Model
{
    protected $table = 'solicitud_seguimientos';

    protected $fillable = [
        'id_solicitud', 'id_usuario', 'de_administracion', 'estatus_anterior', 'estatus_nuevo',
        'comentario', 'adjunto_ruta', 'adjunto_nombre', 'adjunto_mime',
    ];

    protected $attributes = [
        'de_administracion' => false,
    ];

    protected $casts = [
        'de_administracion' => 'boolean',
    ];

    protected $hidden = ['adjunto_ruta'];

    protected $appends = ['adjunto_url', 'adjunto_es_imagen'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    // Privado entre la casa y la administración: se sirve por una ruta con permisos.
    public function getAdjuntoUrlAttribute()
    {
        return $this->adjunto_ruta ? route('servicios.adjunto', ['tipo' => 'seguimiento', 'id' => $this->id]) : null;
    }

    public function getAdjuntoEsImagenAttribute()
    {
        return $this->adjunto_mime && str_starts_with($this->adjunto_mime, 'image/');
    }
}
