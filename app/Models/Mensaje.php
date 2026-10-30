<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    protected $table = 'mensajes';

    protected $fillable = [
        'id_conversacion',
        'id_usuario',
        'de_administracion',
        'texto',
        'adjunto_ruta',
        'adjunto_nombre',
        'adjunto_mime',
    ];

    protected $attributes = [
        'de_administracion' => false,
    ];

    protected $casts = [
        'de_administracion' => 'boolean',
    ];

    protected $hidden = ['adjunto_ruta'];

    protected $appends = ['adjunto_url', 'adjunto_es_imagen'];

    public function conversacion()
    {
        return $this->belongsTo(Conversacion::class, 'id_conversacion');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    // El adjunto se sirve por una ruta que valida permisos (no por /storage).
    public function getAdjuntoUrlAttribute()
    {
        return $this->adjunto_ruta ? route('mensajes.adjunto', $this->id) : null;
    }

    public function getAdjuntoEsImagenAttribute()
    {
        return $this->adjunto_mime && str_starts_with($this->adjunto_mime, 'image/');
    }
}
