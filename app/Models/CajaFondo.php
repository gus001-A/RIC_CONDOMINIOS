<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CajaFondo extends Model
{
    use HasFactory;

    protected $table = 'cajas_fondo';

    protected $fillable = [
        'nombre_caja',
        'saldo_actual',
        'es_fondeadora',
        'activo'
    ];

    protected $casts = [
        'saldo_actual' => 'decimal:2',
        'es_fondeadora' => 'boolean',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function movimientos()
    {
        return $this->hasMany(MovimientoPoliza::class, 'id_caja_fondo');
    }

    // Métodos
    public function incrementarSaldo($monto)
    {
        $this->saldo_actual += $monto;
        $this->save();
        return $this;
    }

    public function decrementarSaldo($monto)
    {
        if ($this->saldo_actual < $monto) {
            throw new \Exception("Saldo insuficiente en la caja {$this->nombre_caja}");
        }
        $this->saldo_actual -= $monto;
        $this->save();
        return $this;
    }

    public function tieneSaldoSuficiente($monto)
    {
        return $this->saldo_actual >= $monto;
    }

    // Scopes
    public function scopeFondeadoras($query)
    {
        return $query->where('es_fondeadora', true);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}