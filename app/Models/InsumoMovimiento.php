<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsumoMovimiento extends Model
{
    protected $table = 'insumo_movimientos';

    public const TIPOS = [
        'ENTRADA' => 'Entrada (compra)',
        'SALIDA' => 'Salida (uso)',
        'AJUSTE' => 'Ajuste por conteo',
    ];

    protected $fillable = [
        'id_insumo', 'id_empresa', 'tipo', 'cantidad', 'costo_unitario', 'existencia_resultante', 'proveedor',
        'referencia', 'motivo', 'id_poliza', 'id_incidencia', 'id_usuario',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'costo_unitario' => 'float',
        'existencia_resultante' => 'float',
    ];

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'id_insumo');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function poliza()
    {
        return $this->belongsTo(Poliza::class, 'id_poliza');
    }

    public function incidencia()
    {
        return $this->belongsTo(Incidencia::class, 'id_incidencia');
    }
}
