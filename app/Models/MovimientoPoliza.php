<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoPoliza extends Model
{
    use HasFactory;

    protected $table = 'movimientos_poliza';

    protected $fillable = [
        'id_poliza',
        'id_cuenta',
        'id_caja_fondo',
        'id_tipo_iva',
        'monto_traspaso',     // ✅ NUEVO: Monto del traspaso (sin signo)
        'monto',
        'monto_base',
        'monto_iva',
        'monto_iva_cero',
        'monto_iva_dieciseis',
        'iva_dieciseis',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_base' => 'decimal:2',
        'monto_iva' => 'decimal:2',
        'monto_traspaso' => 'decimal:2',
        'monto_iva_cero' => 'decimal:2',
        'monto_iva_dieciseis' => 'decimal:2',
        'iva_dieciseis' => 'decimal:2',
    ];

    // Relaciones
    public function poliza()
    {
        return $this->belongsTo(Poliza::class, 'id_poliza');
    }

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta');
    }

    public function cuentaFondeadora()
    {
        return $this->belongsTo(Cuenta::class, 'id_caja_fondo');
    }

    // Métodos
    public function getEsIngresoAttribute()
    {
        return $this->monto > 0;
    }

    public function getEsEgresoAttribute()
    {
        return $this->monto < 0;
    }

    public function getEsTraspasoAttribute()
    {
        return $this->poliza && $this->poliza->tipo_poliza === 'TRASPASO';
    }

    public function getMontoFormateadoAttribute()
    {
        $signo = $this->monto >= 0 ? '+' : '';
        return $signo . '$' . number_format($this->monto, 2);
    }

    public function getMontoTraspasoFormateadoAttribute()
    {
        return '$' . number_format($this->monto_traspaso ?? 0, 2);
    }

    // Scopes
    public function scopeIngresos($query)
    {
        return $query->where('monto', '>', 0);
    }

    public function scopeEgresos($query)
    {
        return $query->where('monto', '<', 0);
    }

    public function scopeTraspasos($query)
    {
        return $query->whereHas('poliza', function($q) {
            $q->where('tipo_poliza', 'TRASPASO');
        });
    }

    public function scopeSinIva($query)
    {
        return $query->whereNull('id_tipo_iva');
    }

    public function scopeConIva($query)
    {
        return $query->whereNotNull('id_tipo_iva');
    }
}