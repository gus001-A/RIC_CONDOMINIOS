<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Casa extends Model
{
    protected $table = 'casas';

    public const TIPOS_PROPIEDAD = [
        'CASA' => 'Casa',
        'DEPARTAMENTO' => 'Departamento',
        'LOTE' => 'Lote baldío',
        'LOCAL' => 'Local comercial',
    ];

    public const ESTATUS_OCUPACION = [
        'HABITADA' => 'Habitada',
        'DESHABITADA' => 'Deshabitada',
        'EN RENTA' => 'En renta',
        'EN VENTA' => 'En venta',
        'EN CONSTRUCCION' => 'En construcción',
    ];

    protected $fillable = [
        'id_empresa',
        'identificador',
        'tipo_propiedad',
        'manzana',
        'lote',
        'calle',
        'numero_exterior',
        'numero_interior',
        'referencias',
        'latitud',
        'longitud',
        'estatus_ocupacion',
        'cuota_mantenimiento',
        'notas',
        'activo',
        'id_usuario_registro',
        'id_usuario_modifico',
    ];

    // Mismos defaults que la tabla, para tenerlos en el modelo antes de guardar.
    protected $attributes = [
        'activo' => true,
        'tipo_propiedad' => 'CASA',
        'estatus_ocupacion' => 'HABITADA',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'latitud' => 'float',
        'longitud' => 'float',
        'cuota_mantenimiento' => 'decimal:2',
    ];

    protected $appends = ['direccion_corta'];

    // ======================================================
    // ✅ RELACIONES
    // ======================================================

    /** El condominio (empresa de RIC) al que pertenece la casa. */
    public function condominio()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function condominos()
    {
        return $this->hasMany(Condomino::class, 'id_casa');
    }

    public function responsable()
    {
        return $this->hasOne(Condomino::class, 'id_casa')
                    ->where('es_responsable', true)
                    ->where('activo', true);
    }

    public function fotos()
    {
        return $this->hasMany(CasaFoto::class, 'id_casa')->latest();
    }

    public function cargosCuota()
    {
        return $this->hasMany(CuotaCargo::class, 'id_casa');
    }

    public function pagosCuota()
    {
        return $this->hasMany(CuotaPago::class, 'id_casa');
    }

    public function registro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    public function modifico()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_modifico', 'id_usuario');
    }

    // ======================================================
    // ✅ ATTRIBUTES
    // ======================================================

    public function getDireccionCortaAttribute()
    {
        $partes = array_filter([
            $this->manzana ? 'Mz. ' . $this->manzana : null,
            $this->lote ? 'Lt. ' . $this->lote : null,
            $this->calle,
            $this->numero_exterior ? '#' . $this->numero_exterior : null,
            $this->numero_interior ? 'Int. ' . $this->numero_interior : null,
        ]);

        return implode(', ', $partes);
    }

    public function getTieneGpsAttribute()
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /**
     * Punto medio de las casas con GPS de cada condominio, para abrir los
     * mapas ya sobre el condominio:  [id_empresa => [lat, lng]].
     */
    public static function centros(array $empresaIds): array
    {
        return static::whereIn('id_empresa', $empresaIds)
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->groupBy('id_empresa')
            ->selectRaw('id_empresa, AVG(latitud) as lat, AVG(longitud) as lng')
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id_empresa => [(float) $c->lat, (float) $c->lng]])
            ->all();
    }

    // ======================================================
    // ✅ SCOPES
    // ======================================================

    /**
     * Casas de los condominios que el usuario tiene asignados
     * (el SUPERUSUARIO ve todos).
     */
    public function scopeVisiblesPara($query, Usuario $usuario)
    {
        if ($usuario->esSuperUsuario()) {
            return $query;
        }

        return $query->whereIn('id_empresa', $usuario->empresas()->pluck('empresas.id'));
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('identificador', 'LIKE', "%{$search}%")
              ->orWhere('calle', 'LIKE', "%{$search}%")
              ->orWhere('manzana', 'LIKE', "%{$search}%")
              ->orWhere('lote', 'LIKE', "%{$search}%")
              ->orWhere('numero_exterior', 'LIKE', "%{$search}%");
        });
    }
}
