<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlantillaDocumento extends Model
{
    protected $table = 'plantillas_documento';

    public const TIPOS = [
        'CONTRATO_ARRENDAMIENTO' => 'Contrato de arrendamiento',
        'CONTRATO_COMPRAVENTA' => 'Promesa / contrato de compraventa',
        'CONVENIO_PAGO' => 'Convenio de pago',
        'CARTA' => 'Carta / constancia',
        'REGLAMENTO' => 'Reglamento / aviso',
        'OTRO' => 'Otro',
    ];

    protected $fillable = [
        'id_empresa', 'nombre', 'tipo', 'descripcion', 'contenido', 'activo',
        'id_usuario_registro', 'id_usuario_modifico',
    ];

    protected $attributes = [
        'activo' => true,
        'tipo' => 'OTRO',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function condominio()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function registro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    public function modifico()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_modifico', 'id_usuario');
    }

    /** Plantillas generales + las de los condominios del usuario. */
    public function scopeDisponiblesPara(Builder $query, Usuario $usuario): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('id_empresa')->orWhereIn('id_empresa', $usuario->idsCondominios()));
    }
}
