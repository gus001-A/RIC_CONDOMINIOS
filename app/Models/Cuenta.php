<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuenta extends Model
{
    use HasFactory;

    protected $table = 'cuentas';
    protected $primaryKey = 'id_cuenta';

    protected $fillable = [
        'id_empresa',
        'codigo_cuenta',
        'nombre_cuenta',
        'descripcion',
        'Naturaleza',
        'tipo_cuenta',
        'id_cuenta_madre',
        'en_uso',
        'es_cuenta_resultados',
        'saldo_inicial',
        'nivel',
        'indice_c',
        'clave_c',
        'madre_c',
        'empresa_c',
        'origen_c',
        'depreciacion_c',
        'usuario_c',
        'fecha_c',
        'id_empresa_c',
        'fondeo_c',
        'cuenta_resultados',
        'regimen_c',
        'check_resultados',
        'check_uso'
    ];

    protected $casts = [
        'fecha_c' => 'datetime',
    ];

    // Relaciones
    public function cuentaMadre()
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta_madre', 'id_cuenta');
    }

    public function cuentasHijas()
    {
        return $this->hasMany(Cuenta::class, 'id_cuenta_madre', 'id_cuenta');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id');
    }

    /**
     * Cuenta de resultados padre a la que pertenece esta cuenta
     */
    public function cuentaResultadoPadre()
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_resultados', 'id_cuenta');
    }

    /**
     * Cuentas hijas que pertenecen a esta cuenta de resultados
     */
    public function cuentasHijasResultado()
    {
        return $this->hasMany(Cuenta::class, 'cuenta_resultados', 'id_cuenta');
    }

    /**
     * Obtener nombre completo (código + nombre)
     */
    public function getNombreCompletoAttribute()
    {
        return $this->codigo_cuenta . ' - ' . $this->nombre_cuenta;
    }

    /**
     * Obtener el nivel jerárquico usando indice_c si existe
     */
    public function getNivelJerarquicoAttribute()
    {
        if (!empty($this->indice_c)) {
            return $this->indice_c;
        }
        
        $empresaId = $this->id_empresa;
        
        if ($this->nivel == 0) {
            return (string)$empresaId;
        }
        
        $jerarquia = [(string)$empresaId];
        $cuentaActual = $this;
        $codigos = [];
        $contador = 0;
        $maxIteraciones = 10;
        
        while ($cuentaActual && $cuentaActual->nivel > 0 && $contador < $maxIteraciones) {
            $codigoCuenta = $cuentaActual->codigo_cuenta;
            $numero = preg_replace('/[^0-9]/', '', $codigoCuenta);
            if (empty($numero)) {
                $numero = str_pad($cuentaActual->id_cuenta, 3, '0', STR_PAD_LEFT);
            }
            $numero = str_pad($numero, 3, '0', STR_PAD_LEFT);
            array_unshift($codigos, $numero);
            
            if ($cuentaActual->id_cuenta_madre && $cuentaActual->id_cuenta_madre != $cuentaActual->id_cuenta) {
                $cuentaActual = Cuenta::find($cuentaActual->id_cuenta_madre);
            } else {
                break;
            }
            $contador++;
        }
        
        foreach ($codigos as $codigo) {
            $jerarquia[] = $codigo;
        }
        
        return implode('-', $jerarquia);
    }

    public function getNivelTextoAttribute()
    {
        $niveles = ['', 'Primer Nivel', 'Segundo Nivel', 'Tercer Nivel', 'Cuarto Nivel', 'Quinto Nivel'];
        return $niveles[$this->nivel] ?? "Nivel {$this->nivel}";
    }

    /**
     * Verificar si la cuenta puede recibir un movimiento de débito (cargo)
     */
    public function puedeRecibirDebito()
    {
        // Si es cuenta de resultados, siempre puede recibir débitos
        if ($this->es_cuenta_resultados) {
            return true;
        }
        
        // Si no tiene naturaleza definida, puede recibir ambos
        if (is_null($this->Naturaleza)) {
            return true;
        }
        
        // Si es DEUDORA, solo recibe débitos (cargos)
        return $this->Naturaleza === 'DEUDORA';
    }

    /**
     * Verificar si la cuenta puede recibir un movimiento de crédito (abono)
     */
    public function puedeRecibirCredito()
    {
        // Si es cuenta de resultados, siempre puede recibir créditos
        if ($this->es_cuenta_resultados) {
            return true;
        }
        
        // Si no tiene naturaleza definida, puede recibir ambos
        if (is_null($this->Naturaleza)) {
            return true;
        }
        
        // Si es ACREEDORA, solo recibe créditos (abonos)
        return $this->Naturaleza === 'ACREEDORA';
    }

    /**
     * Obtener el saldo de la cuenta incluyendo sus hijas si es cuenta de resultados padre
     */
    public function getSaldoTotal($fechaInicio = null, $fechaFin = null)
    {
        // Si es cuenta de resultados padre, sumar todas sus hijas
        if ($this->es_cuenta_resultados && $this->tipo_cuenta === 'RESULTADO') {
            $total = 0;
            foreach ($this->cuentasHijasResultado as $hija) {
                $total += $hija->getSaldoPeriodo($fechaInicio, $fechaFin);
            }
            return $total;
        }
        
        // Si es cuenta hija de resultados, calcular su saldo individual
        return $this->getSaldoPeriodo($fechaInicio, $fechaFin);
    }

    /**
     * Obtener el saldo de la cuenta en un período
     */
    public function getSaldoPeriodo($fechaInicio = null, $fechaFin = null)
    {
        // Aquí iría la lógica para calcular el saldo basado en pólizas
        // Por ahora retornamos el saldo_inicial
        return $this->saldo_inicial ?? 0;
    }

    // Scopes
    public function scopeEnUso($query)
    {
        return $query->where('en_uso', true);
    }

    public function scopeDeudoras($query)
    {
        return $query->where('Naturaleza', 'DEUDORA');
    }

    public function scopeAcreedoras($query)
    {
        return $query->where('Naturaleza', 'ACREEDORA');
    }

    /**
     * Scope para obtener solo cuentas que pueden recibir débitos
     */
    public function scopePuedeRecibirDebito($query)
    {
        return $query->where(function($q) {
            $q->where('es_cuenta_resultados', true)
              ->orWhereNull('Naturaleza')
              ->orWhere('Naturaleza', 'DEUDORA');
        });
    }

    /**
     * Scope para obtener solo cuentas que pueden recibir créditos
     */
    public function scopePuedeRecibirCredito($query)
    {
        return $query->where(function($q) {
            $q->where('es_cuenta_resultados', true)
              ->orWhereNull('Naturaleza')
              ->orWhere('Naturaleza', 'ACREEDORA');
        });
    }

    /**
     * Scope para obtener cuentas de resultados (padres)
     */
    public function scopeCuentasResultadosPadre($query)
    {
        return $query->where('es_cuenta_resultados', true)
                     ->where('tipo_cuenta', 'RESULTADO')
                     ->where('en_uso', true);
    }

    /**
     * Scope para obtener cuentas hijas de una cuenta de resultados
     */
    public function scopeHijasDeResultado($query, $cuentaResultadosId)
    {
        return $query->where('cuenta_resultados', $cuentaResultadosId)
                     ->where('en_uso', true);
    }




/**
 * Limita una consulta de fondeadoras a las que el usuario actual tiene
 * asignadas en esa empresa. Sin ninguna asignada, no devuelve ninguna.
 */
public function scopeFondeadorasAsignadas($query, $empresaId, $user = null)
{
    $user = $user ?: auth()->user();
    $ids = $user ? $user->idsFondeadorasAsignadas((int) $empresaId) : [];

    return $query->whereIn('cuentas.id_cuenta', $ids);
}

/**
 * Scope para excluir cuentas fondeadoras
 */
public function scopeNoFondeadoras($query)
{
    return $query->where(function($q) {
        $q->where('fondeo_c', '!=', 1)
          ->orWhereNull('fondeo_c')
          ->where('tipo_cuenta', '!=', 'FONDEADORA');
    });
}

// En tu modelo Cuenta.php

/**
 * Scope para cuentas activas
 */
public function scopeActivas($query)
{
    return $query->where('en_uso', true);
}

/**
 * Scope para cuentas fondeadoras
 */
public function scopeFondeadoras($query)
{
    return $query->where(function($q) {
        $q->where('fondeo_c', 1)
          ->orWhere('tipo_cuenta', 'FONDEADORA');
    });
}




}