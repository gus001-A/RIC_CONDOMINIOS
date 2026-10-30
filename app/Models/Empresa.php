<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        // Datos generales
        'nombre_empresa',
        'rfc',
        'regimen',
        'tipo_persona',
        'clave',
        
        // Dirección partida
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'ciudad',
        'municipio',
        'estado',
        'codigo_postal',
        
        // Contacto
        'correo',
        'telefono_personal',
        'telefono_trabajo',
        'extension',
        
        // Representante
        'representante_nombre',
        'representante_apellido_paterno',
        'representante_apellido_materno',
        'representante_rfc',
        'representante_curp',
        
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // ======================================================
    // ✅ RELACIONES
    // ======================================================

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'empresas_usuarios', 'id_empresa', 'id_usuario')
                    ->withTimestamps();
    }

    /** Casas del condominio. */
    public function casas()
    {
        return $this->hasMany(Casa::class, 'id_empresa');
    }

    public function cuentas()
    {
        return $this->hasMany(Cuenta::class, 'id_empresa');
    }

    public function polizas()
    {
        return $this->hasMany(Poliza::class, 'id_empresa');
    }

    // ======================================================
    // ✅ ATTRIBUTES
    // ======================================================

    public function getDireccionCompletaAttribute()
    {
        $partes = array_filter([
            $this->calle,
            $this->numero_exterior ? '#' . $this->numero_exterior : null,
            $this->numero_interior ? 'Int. ' . $this->numero_interior : null,
            $this->colonia,
            $this->ciudad,
            $this->municipio,
            $this->estado,
            $this->codigo_postal ? 'C.P. ' . $this->codigo_postal : null,
        ]);

        return implode(', ', $partes);
    }

    public function getDireccionCortaAttribute()
    {
        $partes = array_filter([
            $this->calle,
            $this->numero_exterior ? '#' . $this->numero_exterior : null,
            $this->colonia,
            $this->ciudad,
        ]);

        return implode(', ', $partes);
    }

    public function getTipoPersonaTextoAttribute()
    {
        return $this->tipo_persona === 'FISICA' ? 'Persona Física' : 'Persona Moral';
    }

    public function getTipoPersonaIconoAttribute()
    {
        return $this->tipo_persona === 'FISICA' ? '👤' : '🏢';
    }

    // ======================================================
    // ✅ SCOPES
    // ======================================================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeFisicas($query)
    {
        return $query->where('tipo_persona', 'FISICA');
    }

    public function scopeMorales($query)
    {
        return $query->where('tipo_persona', 'MORAL');
    }

    public function scopeSearch($query, $search)
    {
        // Los orWhere() deben ir agrupados en su propio paréntesis; si no, el
        // filtro de "Nombre" se combinaba mal con los DEMÁS filtros del index
        // (RFC, tipo, ubicación, contacto, activo): por precedencia de SQL,
        // "A OR B OR C AND D" sólo aplica el AND al último término (C), y las
        // filas que hacían match por A o B se colaban sin respetar el resto
        // de los filtros activos.
        return $query->where(function ($q) use ($search) {
            $q->where('nombre_empresa', 'LIKE', "%{$search}%")
              ->orWhere('rfc', 'LIKE', "%{$search}%")
              ->orWhere('clave', 'LIKE', "%{$search}%")
              ->orWhere('calle', 'LIKE', "%{$search}%")
              ->orWhere('colonia', 'LIKE', "%{$search}%")
              ->orWhere('ciudad', 'LIKE', "%{$search}%")
              ->orWhere('estado', 'LIKE', "%{$search}%");
        });
    }

    public function scopeByEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopeByCiudad($query, $ciudad)
    {
        return $query->where('ciudad', $ciudad);
    }
}