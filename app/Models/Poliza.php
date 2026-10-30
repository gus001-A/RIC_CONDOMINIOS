<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Poliza extends Model
{
    use HasFactory;

    protected $table = 'polizas';
    
    public $timestamps = true;

    protected $fillable = [
        'id_empresa',
        'fecha_poliza',
        'fecha_vencimiento',
        'tipo_poliza',
        'categoria',
        'estatus',
        'estatus_anterior',
        'es_por_pagar',
        'referencia',
        'nota',
        'id_persona',
        'id_usuario_creador',
        'id_usuario_autorizador',
        'id_usuario_revisor',
        'fecha_autorizacion',
        'fecha_revision',
        'fecha_factura',
        'numero_factura',
        'id_marcador',
        'ruta_pdf',
        'nombre_pdf',
        'nombre_xml',
        'uuid_factura',
        'serie_factura',
        'folio_factura',
        'fecha_creacion',
        'comentario_revision',
        'comentario_autorizacion',
        'motivo_rechazo',
        'fecha_cierre',
        'id_usuario_cierre',
        'motivo_cierre'
    ];

    protected $casts = [
        'fecha_poliza' => 'datetime',
        'fecha_vencimiento' => 'date',
        'fecha_factura' => 'date',
        'fecha_autorizacion' => 'datetime',
        'fecha_revision' => 'datetime',
        'fecha_cierre' => 'datetime',
        'es_por_pagar' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'estatus_texto',
        'estatus_color',
        'estatus_icono',
        'tipo_poliza_texto',
        'categoria_texto',
        'estado_texto'
    ];

    // 🔥 Boot - Genera folio automáticamente al crear
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($poliza) {
            if (empty($poliza->folio)) {
                // El folio es consecutivo POR EMPRESA (cada empresa lleva su
                // propia numeración 0001, 0002, ...).
                $poliza->folio = self::generarSiguienteFolio($poliza->id_empresa);
            }
            // Establecer estatus inicial
            if (empty($poliza->estatus)) {
                $poliza->estatus = 'CAPTURADO';
            }
        });
    }

    /**
     * Genera el siguiente folio: SÓLO números, secuencial (0001, 0002, 0003...),
     * consecutivo POR EMPRESA. Ignora folios antiguos que traían letras.
     */
    public static function generarSiguienteFolio($empresaId = null)
    {
        return self::obtenerSiguienteFolio($empresaId);
    }

    /**
     * Obtener el siguiente folio sin guardar (para previsualización).
     * Toma el mayor folio 100% numérico DE LA EMPRESA y le suma 1.
     */
    public static function obtenerSiguienteFolio($empresaId = null)
    {
        // MAX(CAST(folio AS UNSIGNED)) sólo sobre folios formados por dígitos,
        // acotado a la empresa para que cada una lleve su propia secuencia.
        $query = self::query()->whereRaw("folio REGEXP '^[0-9]+$'");

        if ($empresaId) {
            $query->where('id_empresa', $empresaId)->lockForUpdate();
        }

        $maximo = (int) $query
            ->selectRaw('MAX(CAST(folio AS UNSIGNED)) as m')
            ->value('m');

        return str_pad($maximo + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Reiniciar el contador de folios (solo para uso administrativo)
     */
    public static function reiniciarContadorFolio($inicio = 1)
    {
        // Esta función solo debe usarse en casos especiales
        // Normalmente no se recomienda reiniciar el contador
        $folioInicio = str_pad($inicio, 4, '0', STR_PAD_LEFT);
        
        // Obtener todas las pólizas y reasignar folios
        $polizas = self::orderBy('id')->get();
        $contador = $inicio;
        
        foreach ($polizas as $poliza) {
            $poliza->folio = str_pad($contador, 4, '0', STR_PAD_LEFT);
            $poliza->save();
            $contador++;
        }
        
        return $contador - $inicio;
    }

    // ============================================
    // ✅ RELACIONES
    // ============================================
    
    public function marcador()
    {
        return $this->belongsTo(Marcador::class, 'id_marcador');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona');
    }

    public function usuarioCreador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_creador');
    }

    public function usuarioAutorizador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_autorizador');
    }

    public function usuarioRevisor()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_revisor');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoPoliza::class, 'id_poliza');
    }

    public function abonos()
    {
        return $this->hasMany(AbonoPoliza::class, 'id_poliza');
    }

    /** Anotaciones de transparencia (condóminos / administración). */
    public function anotaciones()
    {
        return $this->hasMany(PolizaAnotacion::class, 'id_poliza');
    }

    // ============================================
    // ✅ ACCESORS - ESTADOS
    // ============================================
    
    public function getEstatusTextoAttribute()
    {
        $estados = [
            'CAPTURADO' => 'Capturado',
            'REVISADO' => 'Revisado',
            'AUTORIZADO' => 'Autorizado',
            'ABONADO' => 'Abonado',
            'LIQUIDADO' => 'Liquidado',
            'PENDIENTE' => 'Pendiente',
            'CERRADO' => 'Cerrado'
        ];
        return $estados[$this->estatus] ?? $this->estatus;
    }

    public function getEstatusColorAttribute()
    {
        $colores = [
            'CAPTURADO' => 'bg-gray-100 text-gray-700',
            'REVISADO' => 'bg-blue-100 text-blue-700',
            'AUTORIZADO' => 'bg-green-100 text-green-700',
            'ABONADO' => 'bg-yellow-100 text-yellow-700',
            'LIQUIDADO' => 'bg-purple-100 text-purple-700',
            'PENDIENTE' => 'bg-orange-100 text-orange-700',
            'CERRADO' => 'bg-red-100 text-red-700'
        ];
        return $colores[$this->estatus] ?? 'bg-gray-100 text-gray-700';
    }

    public function getEstatusIconoAttribute()
    {
        $iconos = [
            'CAPTURADO' => '📝',
            'REVISADO' => '👀',
            'AUTORIZADO' => '✅',
            'ABONADO' => '💰',
            'LIQUIDADO' => '🏁',
            'PENDIENTE' => '⏳',
            'CERRADO' => '🔒'
        ];
        return $iconos[$this->estatus] ?? '📝';
    }

    public function getEstadoTextoAttribute()
    {
        return $this->estatus_texto;
    }

    public function getTipoPolizaTextoAttribute()
    {
        $tipos = [
            'INGRESO' => 'Ingreso',
            'EGRESO' => 'Egreso',
            'TRASPASO' => 'Traspaso'
        ];
        return $tipos[$this->tipo_poliza] ?? $this->tipo_poliza;
    }

    public function getCategoriaTextoAttribute()
    {
        $categorias = [
            'FISCAL' => 'Fiscal',
            'NO_FISCAL' => 'No Fiscal'
        ];
        return $categorias[$this->categoria] ?? $this->categoria;
    }

    // ============================================
    // ✅ MÉTODOS PARA TOTALES
    // ============================================
    
    public function getTotalAttribute()
    {
        return $this->movimientos()->sum('monto');
    }

    public function getTotalAbonadoAttribute()
    {
        return $this->abonos()->sum('monto_abonado');
    }

    public function getSaldoPendienteAttribute()
    {
        return abs($this->total) - $this->total_abonado;
    }

    public function getEstaLiquidadaAttribute()
    {
        return $this->saldo_pendiente <= 0.01;
    }

    // ============================================
    // ✅ MÉTODOS DE ACCIÓN - FLUJO DE ESTADOS
    // ============================================
    
    public function revisar($usuarioId, $comentario = null)
    {
        if ($this->estatus !== 'CAPTURADO' && $this->estatus !== 'PENDIENTE') {
            throw new \Exception('La póliza debe estar en estado CAPTURADO o PENDIENTE para ser revisada');
        }

        $this->estatus_anterior = $this->estatus;
        $this->estatus = 'REVISADO';
        $this->id_usuario_revisor = $usuarioId;
        $this->fecha_revision = now();
        $this->comentario_revision = $comentario;
        $this->save();
        
        return $this;
    }

    public function autorizar($usuarioId, $comentario = null)
    {
        if ($this->estatus !== 'REVISADO') {
            throw new \Exception('La póliza debe estar en estado REVISADO para ser autorizada');
        }

        $this->estatus_anterior = $this->estatus;
        $this->estatus = 'AUTORIZADO';
        $this->id_usuario_autorizador = $usuarioId;
        $this->fecha_autorizacion = now();
        $this->comentario_autorizacion = $comentario;
        $this->save();
        
        return $this;
    }

    public function rechazar($usuarioId, $motivo)
    {
        if (!in_array($this->estatus, ['REVISADO', 'AUTORIZADO'])) {
            throw new \Exception('La póliza debe estar en estado REVISADO o AUTORIZADO para ser rechazada');
        }

        $this->estatus_anterior = $this->estatus;
        $this->estatus = 'CAPTURADO';
        $this->motivo_rechazo = $motivo;
        
        if ($this->estatus_anterior === 'REVISADO') {
            $this->id_usuario_revisor = null;
            $this->fecha_revision = null;
            $this->comentario_revision = null;
        } elseif ($this->estatus_anterior === 'AUTORIZADO') {
            $this->id_usuario_autorizador = null;
            $this->fecha_autorizacion = null;
            $this->comentario_autorizacion = null;
        }
        
        $this->save();
        
        return $this;
    }

    public function revertirRevision($usuarioId, $motivo)
    {
        if ($this->estatus !== 'REVISADO') {
            throw new \Exception('La póliza debe estar en estado REVISADO para revertir');
        }

        $this->estatus = 'CAPTURADO';
        $this->estatus_anterior = 'REVISADO';
        $this->motivo_rechazo = $motivo;
        $this->id_usuario_revisor = null;
        $this->fecha_revision = null;
        $this->comentario_revision = null;
        $this->save();
        
        return $this;
    }

    public function marcarAbonada()
    {
        if (!in_array($this->estatus, ['AUTORIZADO', 'CAPTURADO', 'REVISADO'])) {
            throw new \Exception('La póliza debe estar en estado AUTORIZADO, CAPTURADO o REVISADO para ser abonada');
        }

        $this->estatus_anterior = $this->estatus;
        $this->estatus = 'ABONADO';
        $this->save();
        
        return $this;
    }

    public function liquidar()
    {
        if ($this->estatus !== 'ABONADO') {
            throw new \Exception('La póliza debe estar en estado ABONADO para ser liquidada');
        }

        if ($this->saldo_pendiente > 0.01) {
            throw new \Exception('La póliza tiene saldo pendiente, no se puede liquidar');
        }

        $this->estatus_anterior = $this->estatus;
        $this->estatus = 'LIQUIDADO';
        $this->save();
        
        return $this;
    }

    public function cerrar($usuarioId, $motivo = null)
    {
        if (in_array($this->estatus, ['LIQUIDADO', 'AUTORIZADO'])) {
            throw new \Exception('No se puede cerrar una póliza en estado ' . $this->estatus);
        }

        $this->estatus = 'CERRADO';
        $this->fecha_cierre = now();
        $this->id_usuario_cierre = $usuarioId;
        $this->motivo_cierre = $motivo ?? 'Cierre manual de póliza';
        $this->save();
        
        return $this;
    }

    public function reabrir($usuarioId, $motivo = null)
    {
        if ($this->estatus !== 'CERRADO') {
            throw new \Exception('Solo se pueden reabrir pólizas en estado CERRADO');
        }

        $nuevoEstatus = $this->es_por_pagar ? 'PENDIENTE' : 'CAPTURADO';
        
        $this->estatus = $nuevoEstatus;
        $this->fecha_cierre = null;
        $this->id_usuario_cierre = null;
        $this->motivo_cierre = null;
        $this->motivo_rechazo = $motivo ?? 'Póliza reabierta';
        $this->save();
        
        return $this;
    }

    // ============================================
    // ✅ MÉTODOS PARA DOCUMENTOS
    // ============================================
    
    public function getTienePdfAttribute()
    {
        return !empty($this->ruta_pdf);
    }

    public function getTieneXmlAttribute()
    {
        return !empty($this->ruta_xml);
    }

    public function getTieneDocumentosAttribute()
    {
        return $this->tiene_pdf || $this->tiene_xml;
    }

    public function getRutaPdfCompletaAttribute()
    {
        if (empty($this->ruta_pdf)) {
            return null;
        }
        return asset('storage/' . $this->ruta_pdf);
    }

    public function getRutaXmlCompletaAttribute()
    {
        if (empty($this->ruta_xml)) {
            return null;
        }
        return asset('storage/' . $this->ruta_xml);
    }

    public function getNombreArchivoPdfAttribute()
    {
        return $this->nombre_pdf ?? 'factura.pdf';
    }

    public function getNombreArchivoXmlAttribute()
    {
        return $this->nombre_xml ?? 'factura.xml';
    }

    public function getDatosFacturaAttribute()
    {
        if (empty($this->uuid_factura) && empty($this->serie_factura) && empty($this->folio_factura)) {
            return null;
        }

        $datos = [];
        if (!empty($this->serie_factura)) {
            $datos[] = $this->serie_factura;
        }
        if (!empty($this->folio_factura)) {
            $datos[] = $this->folio_factura;
        }
        
        $datosFactura = implode('-', $datos);
        
        if (!empty($this->uuid_factura)) {
            $datosFactura .= ' | UUID: ' . $this->uuid_factura;
        }
        
        return $datosFactura;
    }

    // ============================================
    // ✅ SCOPES
    // ============================================
    
    public function scopePendientes($query)
    {
        return $query->where('estatus', 'PENDIENTE');
    }

    public function scopeCapturados($query)
    {
        return $query->where('estatus', 'CAPTURADO');
    }

    public function scopeRevisados($query)
    {
        return $query->where('estatus', 'REVISADO');
    }

    public function scopeAutorizados($query)
    {
        return $query->where('estatus', 'AUTORIZADO');
    }

    public function scopeAbonados($query)
    {
        return $query->where('estatus', 'ABONADO');
    }

    public function scopeLiquidados($query)
    {
        return $query->where('estatus', 'LIQUIDADO');
    }

    public function scopeCerrados($query)
    {
        return $query->where('estatus', 'CERRADO');
    }

    public function scopePendientesAutorizacion($query)
    {
        return $query->whereIn('estatus', ['CAPTURADO', 'REVISADO', 'PENDIENTE']);
    }

    public function scopeFiscales($query)
    {
        return $query->where('categoria', 'FISCAL');
    }

    public function scopeNoFiscales($query)
    {
        return $query->where('categoria', 'NO_FISCAL');
    }

    public function scopePorPagar($query)
    {
        return $query->where('es_por_pagar', true);
    }

    public function scopeIngresos($query)
    {
        return $query->where('tipo_poliza', 'INGRESO');
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo_poliza', 'EGRESO');
    }

    public function scopeTraspasos($query)
    {
        return $query->where('tipo_poliza', 'TRASPASO');
    }

    public function scopeVencidas($query)
    {
        return $query->where('fecha_vencimiento', '<', now())
                     ->where('estatus', '!=', 'LIQUIDADO')
                     ->where('estatus', '!=', 'CERRADO');
    }

    public function scopePorVencer($query, $dias = 7)
    {
        return $query->whereBetween('fecha_vencimiento', [now(), now()->addDays($dias)])
                     ->where('estatus', '!=', 'LIQUIDADO')
                     ->where('estatus', '!=', 'CERRADO');
    }

    public function scopeByEmpresa($query, $empresaId)
    {
        return $query->where('id_empresa', $empresaId);
    }

    public function scopeConPdf($query)
    {
        return $query->whereNotNull('ruta_pdf')->where('ruta_pdf', '!=', '');
    }

    public function scopeConXml($query)
    {
        return $query->whereNotNull('ruta_xml')->where('ruta_xml', '!=', '');
    }

    public function scopeConDocumentos($query)
    {
        return $query->where(function($q) {
            $q->whereNotNull('ruta_pdf')->orWhereNotNull('ruta_xml');
        });
    }

    public function scopeSinDocumentos($query)
    {
        return $query->where(function($q) {
            $q->whereNull('ruta_pdf')->orWhere('ruta_pdf', '');
        })->where(function($q) {
            $q->whereNull('ruta_xml')->orWhere('ruta_xml', '');
        });
    }

    public function scopePorUuid($query, $uuid)
    {
        return $query->where('uuid_factura', $uuid);
    }

    public function scopePorFolio($query, $folio)
    {
        return $query->where('folio', $folio);
    }

    // ============================================
    // ✅ MÉTODO PARA OBTENER FOLIO FORMATEADO
    // ============================================
    
    public function getFolioFormateadoAttribute()
    {
        return str_pad($this->folio, 4, '0', STR_PAD_LEFT);
    }
    // Relación con archivos adjuntos
    public function archivos()
    {
        return $this->hasMany(PolizaArchivo::class, 'id_poliza');
    }
}