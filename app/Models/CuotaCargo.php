<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotaCargo extends Model
{
    protected $table = 'cuota_cargos';

    protected $fillable = [
        'id_empresa',
        'id_casa',
        'periodo',
        'concepto',
        'monto',
        'fecha_vencimiento',
        'cancelado',
        'motivo_cancelacion',
        'id_usuario_registro',
        'id_usuario_modifico',
    ];

    protected $attributes = [
        'cancelado' => false,
        'concepto' => 'Cuota de mantenimiento',
    ];

    protected $casts = [
        'periodo' => 'date:Y-m-d',
        'fecha_vencimiento' => 'date:Y-m-d',
        'monto' => 'decimal:2',
        'cancelado' => 'boolean',
    ];

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
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
