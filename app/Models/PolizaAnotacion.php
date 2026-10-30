<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolizaAnotacion extends Model
{
    protected $table = 'poliza_anotaciones';

    public const TIPOS = [
        'DUDA' => 'Duda',
        'INCONFORMIDAD' => 'Inconformidad',
        'COMENTARIO' => 'Comentario',
    ];

    protected $fillable = [
        'id_poliza',
        'id_padre',
        'id_usuario',
        'id_casa',
        'casa_identificador',
        'tipo',
        'texto',
        'resuelta',
        'id_usuario_resolvio',
        'fecha_resolucion',
    ];

    protected $attributes = [
        'resuelta' => false,
    ];

    protected $casts = [
        'resuelta' => 'boolean',
        'fecha_resolucion' => 'datetime',
    ];

    public function poliza()
    {
        return $this->belongsTo(Poliza::class, 'id_poliza');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function resolvio()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_resolvio', 'id_usuario');
    }

    public function respuestas()
    {
        return $this->hasMany(self::class, 'id_padre')->oldest();
    }

    /** Sólo anotaciones originales (no respuestas). */
    public function scopeRaiz($query)
    {
        return $query->whereNull('id_padre');
    }

    public function scopeAbiertas($query)
    {
        return $query->whereNull('id_padre')->where('resuelta', false);
    }
}
