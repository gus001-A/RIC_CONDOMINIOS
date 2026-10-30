<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un celular o navegador donde el usuario activó las notificaciones push. */
class PushSuscripcion extends Model
{
    protected $table = 'push_suscripciones';

    protected $fillable = ['id_usuario', 'endpoint', 'endpoint_hash', 'p256dh', 'auth', 'dispositivo', 'ultimo_envio'];

    protected $casts = ['ultimo_envio' => 'datetime'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
