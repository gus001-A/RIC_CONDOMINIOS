<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbonoPoliza extends Model
{
    use HasFactory;

    protected $table = 'abonos_poliza';

    protected $fillable = [
        'id_poliza',
        'monto_abonado',
        'fecha_abono',
        'id_usuario',
        'comprobante_ruta',
        'nota'
    ];

    protected $casts = [
        'monto_abonado' => 'decimal:2',
        'fecha_abono' => 'datetime',
    ];

    // Relaciones
    public function poliza()
    {
        return $this->belongsTo(Poliza::class, 'id_poliza');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    // Métodos
    public function getMontoFormateadoAttribute()
    {
        return '$' . number_format($this->monto_abonado, 2);
    }

    public function getTieneComprobanteAttribute()
    {
        return !empty($this->comprobante_ruta);
    }

    // Scopes
    public function scopeByPoliza($query, $polizaId)
    {
        return $query->where('id_poliza', $polizaId);
    }

    public function scopeByUsuario($query, $usuarioId)
    {
        return $query->where('id_usuario', $usuarioId);
    }

    public function scopeByDate($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_abono', [$fechaInicio, $fechaFin]);
    }
}