<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotaPago extends Model
{
    protected $table = 'cuota_pagos';

    public const FORMAS_PAGO = [
        'TRANSFERENCIA' => 'Transferencia',
        'DEPOSITO' => 'Depósito',
        'EFECTIVO' => 'Efectivo',
        'TARJETA' => 'Tarjeta',
        'OTRO' => 'Otro',
    ];

    protected $fillable = [
        'id_empresa',
        'id_casa',
        'fecha_pago',
        'monto',
        'forma_pago',
        'referencia',
        'id_poliza',
        'notas',
        'cancelado',
        'motivo_cancelacion',
        'id_usuario_registro',
        'id_usuario_modifico',
    ];

    protected $attributes = [
        'cancelado' => false,
    ];

    protected $casts = [
        'fecha_pago' => 'date:Y-m-d',
        'monto' => 'decimal:2',
        'cancelado' => 'boolean',
    ];

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
    }

    public function poliza()
    {
        return $this->belongsTo(Poliza::class, 'id_poliza');
    }

    public function registro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    public function modifico()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_modifico', 'id_usuario');
    }

    public function scopeVigentes($query)
    {
        return $query->where('cancelado', false);
    }
}
