<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    use HasFactory;

    protected $table = 'personas';
    protected $primaryKey = 'id_persona';

    protected $fillable = [
        // Datos principales
        'tipo_persona',
        'activo',
        'empleado', // ✅ AGREGADO: campo empleado
        
        // Persona Física y Moral (TODO EN EL MISMO CAMPO)
        'Nombre',
        'Paterno',
        'Materno',
        'Fecha_nacimiento',
        'sexo',

        // Datos de contacto
        'rfc',
        'curp',
        'email',
        'telefono_particular',
        'telefono_trabajo',
        'extension_trabajo',
        
        // Dirección
        'direccion',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'ciudad',
        'municipio',
        'estado',
        'codigo_postal',
        
        // Representante Legal (para ambos tipos)
        'representante_nombre',
        'representante_paterno',
        'representante_materno',
        'representante_fecha_nacimiento',
        'representante_sexo',
        'representante_email',
        'representante_telefono_particular',
        'representante_telefono_trabajo',
        'representante_extension_trabajo',
        
        // Notas
        'notas',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'empleado' => 'boolean', // ✅ AGREGADO: cast a booleano
        'Fecha_nacimiento' => 'date',
        'representante_fecha_nacimiento' => 'date',
    ];

    protected $appends = [
        'nombre_completo',
        'tipo_persona_texto',
        'total_documentos',
        'polizas_count',
        'razon_social_display',
        'direccion_completa',
        'representante_nombre_completo'
    ];

    // ============================================
    // RELACIONES
    // ============================================
    
    public function documentos()
    {
        return $this->hasMany(DocumentoPersona::class, 'id_persona', 'id_persona');
    }

    public function polizas()
    {
        return $this->hasMany(Poliza::class, 'id_persona', 'id_persona');
    }

    // ============================================
    // ACCESSORS (GETTERS)
    // ============================================
    
    /**
     * Obtener el nombre completo de la persona
     */
    public function getNombreCompletoAttribute()
    {
        $nombre = $this->Nombre ?? '';
        $paterno = $this->Paterno ?? '';
        $materno = $this->Materno ?? '';
        $completo = trim("{$nombre} {$paterno} {$materno}");
        return $completo ?: 'Sin nombre';
    }

    /**
     * Obtener la razón social para mostrar
     */
    public function getRazonSocialDisplayAttribute()
    {
        return $this->getNombreCompletoAttribute();
    }

    /**
     * Obtener el tipo de persona en texto
     */
    public function getTipoPersonaTextoAttribute()
    {
        $tipos = [
            'FISICA' => 'Persona Física',
            'MORAL' => 'Persona Moral'
        ];
        return $tipos[$this->tipo_persona] ?? $this->tipo_persona;
    }

    /**
     * Obtener la dirección completa
     */
    public function getDireccionCompletaAttribute()
    {
        $partes = [];
        
        if ($this->calle) {
            $partes[] = $this->calle;
        }
        
        if ($this->numero_exterior) {
            $partes[] = '#' . $this->numero_exterior;
        }
        
        if ($this->numero_interior) {
            $partes[] = 'Int. ' . $this->numero_interior;
        }
        
        if ($this->colonia) {
            $partes[] = 'Col. ' . $this->colonia;
        }
        
        if ($this->ciudad) {
            $partes[] = $this->ciudad;
        }
        
        if ($this->municipio) {
            $partes[] = $this->municipio;
        }
        
        if ($this->estado) {
            $partes[] = $this->estado;
        }
        
        if ($this->codigo_postal) {
            $partes[] = 'CP ' . $this->codigo_postal;
        }
        
        return implode(', ', $partes);
    }

    /**
     * Obtener el nombre completo del representante
     */
    public function getRepresentanteNombreCompletoAttribute()
    {
        $nombre = $this->representante_nombre ?? '';
        $paterno = $this->representante_paterno ?? '';
        $materno = $this->representante_materno ?? '';
        $completo = trim("{$nombre} {$paterno} {$materno}");
        return $completo ?: null;
    }

    /**
     * Obtener el total de documentos de la persona
     */
    public function getTotalDocumentosAttribute()
    {
        return $this->documentos()->count();
    }

    /**
     * Obtener el total de pólizas de la persona
     */
    public function getPolizasCountAttribute()
    {
        return $this->polizas()->count();
    }

    /**
     * ✅ NUEVO: Obtener el estado de empleado en texto
     */
    public function getEmpleadoTextoAttribute()
    {
        return $this->empleado ? 'Sí' : 'No';
    }

    // ============================================
    // SCOPES
    // ============================================
    
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

    /**
     * ✅ NUEVO: Scope para empleados
     */
    public function scopeEmpleados($query)
    {
        return $query->where('empleado', true);
    }

    /**
     * ✅ NUEVO: Scope para no empleados
     */
    public function scopeNoEmpleados($query)
    {
        return $query->where('empleado', false);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('Nombre', 'LIKE', "%{$search}%")
              ->orWhere('Paterno', 'LIKE', "%{$search}%")
              ->orWhere('Materno', 'LIKE', "%{$search}%")
              ->orWhereRaw("CONCAT_WS(' ', Nombre, Paterno, Materno) LIKE ?", ["%{$search}%"])
              ->orWhere('rfc', 'LIKE', "%{$search}%")
              ->orWhere('curp', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('telefono_particular', 'LIKE', "%{$search}%")
              ->orWhere('telefono_trabajo', 'LIKE', "%{$search}%")
              ->orWhere('representante_nombre', 'LIKE', "%{$search}%")
              ->orWhere('representante_paterno', 'LIKE', "%{$search}%")
              ->orWhere('representante_materno', 'LIKE', "%{$search}%");
        });
    }

    // ============================================
    // MUTATORS (SETTERS)
    // ============================================
    
    public function setNombreAttribute($value)
    {
        $this->attributes['Nombre'] = $value ? strtoupper($value) : null;
    }

    public function setPaternoAttribute($value)
    {
        $this->attributes['Paterno'] = $value ? strtoupper($value) : null;
    }

    public function setMaternoAttribute($value)
    {
        // La columna `Materno` en algunas instalaciones es NOT NULL, pero el
        // apellido materno es opcional en toda la app. Guardamos '' en vez de
        // NULL para no romper el INSERT/UPDATE cuando viene vacío.
        // (La migración make_persona_materno_nullable arregla el esquema; esto
        //  mantiene el sistema funcionando aunque aún no se haya aplicado.)
        $this->attributes['Materno'] = $value ? strtoupper($value) : '';
    }

    public function setRfcAttribute($value)
    {
        $this->attributes['rfc'] = $value ? strtoupper($value) : null;
    }

    public function setCurpAttribute($value)
    {
        $this->attributes['curp'] = $value ? strtoupper($value) : null;
    }

    public function setRepresentanteNombreAttribute($value)
    {
        $this->attributes['representante_nombre'] = $value ? strtoupper($value) : null;
    }

    public function setRepresentantePaternoAttribute($value)
    {
        $this->attributes['representante_paterno'] = $value ? strtoupper($value) : null;
    }

    public function setRepresentanteMaternoAttribute($value)
    {
        $this->attributes['representante_materno'] = $value ? strtoupper($value) : null;
    }
}