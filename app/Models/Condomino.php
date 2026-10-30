<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Condomino extends Model
{
    protected $table = 'condominos';

    public const TIPOS = [
        'PROPIETARIO' => 'Propietario',
        'INQUILINO' => 'Inquilino',
        'FAMILIAR' => 'Familiar / Residente',
    ];

    protected $fillable = [
        'id_casa',
        'id_usuario',
        'tipo',
        'es_responsable',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'telefono',
        'telefono_alterno',
        'correo',
        'emergencia_nombre',
        'emergencia_telefono',
        'emergencia_parentesco',
        'foto',
        'notas',
        'activo',
        'id_usuario_registro',
        'id_usuario_modifico',
    ];

    // Mismos defaults que la tabla, para tenerlos en el modelo antes de guardar.
    protected $attributes = [
        'activo' => true,
        'es_responsable' => false,
    ];

    protected $casts = [
        'es_responsable' => 'boolean',
        'activo' => 'boolean',
    ];

    protected $appends = ['nombre_completo', 'foto_url'];

    // ======================================================
    // ✅ RELACIONES
    // ======================================================

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
    }

    /** Usuario con el que el condómino entra al sistema / app. */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function registro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    public function modifico()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_modifico', 'id_usuario');
    }

    // ======================================================
    // ✅ ATTRIBUTES
    // ======================================================

    public function getNombreCompletoAttribute()
    {
        return trim(implode(' ', array_filter([$this->nombre, $this->apellido_paterno, $this->apellido_materno])));
    }

    public function getFotoUrlAttribute()
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }
}
