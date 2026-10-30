<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaGasto extends Model
{
    protected $table = 'incidencia_gastos';

    protected $fillable = [
        'id_incidencia',
        'id_seguimiento',
        'concepto',
        'monto',
        'proveedor',
        'id_poliza',
        'cancelado',
        'motivo_cancelacion',
        'id_usuario',
        'id_usuario_modifico',
    ];

    protected $attributes = [
        'cancelado' => false,
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'cancelado' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function modifico()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_modifico', 'id_usuario');
    }

    public function poliza()
    {
        return $this->belongsTo(Poliza::class, 'id_poliza');
    }
}
