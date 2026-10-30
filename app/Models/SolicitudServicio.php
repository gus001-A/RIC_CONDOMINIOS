<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudServicio extends Model
{
    protected $table = 'solicitudes_servicio';

    public const TIPOS = [
        'MANTENIMIENTO' => 'Mantenimiento en mi casa',
        'RENTA' => 'Asesoría para rentar mi casa',
        'VENTA' => 'Asesoría para vender mi casa',
        'ASESORIA' => 'Otra asesoría / trámite',
    ];

    public const CATEGORIAS = [
        'PLOMERIA' => 'Plomería',
        'ELECTRICIDAD' => 'Electricidad',
        'ALBANILERIA' => 'Albañilería',
        'PINTURA' => 'Pintura / impermeabilización',
        'CERRAJERIA' => 'Cerrajería / herrería',
        'JARDINERIA' => 'Jardinería',
        'LIMPIEZA' => 'Limpieza / fumigación',
        'ELECTRODOMESTICOS' => 'Electrodomésticos / A.C.',
        'OTRO' => 'Otro',
    ];

    public const ESTATUS = [
        'NUEVA' => 'Nueva',
        'EN_REVISION' => 'En revisión',
        'COTIZADA' => 'Cotizada',
        'EN_PROCESO' => 'En proceso',
        'CONCLUIDA' => 'Concluida',
        'CANCELADA' => 'Cancelada',
    ];

    protected $fillable = [
        'id_empresa', 'id_casa', 'folio', 'tipo', 'categoria', 'titulo', 'descripcion', 'precio_deseado',
        'fecha_disponible', 'estatus', 'id_usuario_solicita', 'id_usuario_atiende', 'fecha_cierre',
    ];

    protected $attributes = [
        'estatus' => 'NUEVA',
    ];

    protected $casts = [
        'precio_deseado' => 'decimal:2',
        'fecha_disponible' => 'date:Y-m-d',
        'fecha_cierre' => 'datetime',
    ];

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
    }

    public function condominio()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function solicita()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_solicita', 'id_usuario');
    }

    public function atiende()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_atiende', 'id_usuario');
    }

    public function seguimientos()
    {
        return $this->hasMany(SolicitudSeguimiento::class, 'id_solicitud')->oldest();
    }

    public function cotizaciones()
    {
        return $this->hasMany(SolicitudCotizacion::class, 'id_solicitud')->oldest();
    }

    public function documentos()
    {
        return $this->hasMany(DocumentoGenerado::class, 'id_solicitud')->latest();
    }

    public function estaCerrada(): bool
    {
        return in_array($this->estatus, ['CONCLUIDA', 'CANCELADA'], true);
    }

    /** Siguiente folio del condominio: SRV-0001… (llamar dentro de una transacción). */
    public static function siguienteFolio(int $empresaId): string
    {
        $ultimo = static::where('id_empresa', $empresaId)
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(folio, 5) AS UNSIGNED)) as n')
            ->value('n');

        return 'SRV-' . str_pad(((int) $ultimo) + 1, 4, '0', STR_PAD_LEFT);
    }
}
