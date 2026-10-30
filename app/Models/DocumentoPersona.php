<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoPersona extends Model
{
    use HasFactory;

    protected $table = 'documentos_persona';

    protected $fillable = [
        'id_persona',
        'titulo',
        'tipo_documento',
        'ruta_archivo',
        'fecha_subida',
        'finalizado',
        'id_usuario_subio'
    ];

    protected $casts = [
        'fecha_subida' => 'datetime',
        'finalizado' => 'boolean',
    ];

    // Relaciones
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona');
    }

    public function usuarioSubio()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_subio');
    }

    // Métodos
    public function getUrlArchivoAttribute()
    {
        return asset('storage/' . $this->ruta_archivo);
    }

    public function getTipoDocumentoTextoAttribute()
    {
        $tipos = [
            'INE' => 'INE',
            'RFC' => 'RFC',
            'CURP' => 'CURP',
            'COMPROBANTE' => 'Comprobante',
            'OTRO' => 'Otro'
        ];
        return $tipos[$this->tipo_documento] ?? $this->tipo_documento;
    }

    public function getEstaFinalizadoTextoAttribute()
    {
        return $this->finalizado ? '✅ Finalizado' : '⏳ Pendiente';
    }

    // Scopes
    public function scopeFinalizados($query)
    {
        return $query->where('finalizado', true);
    }

    public function scopePendientes($query)
    {
        return $query->where('finalizado', false);
    }

    public function scopeByPersona($query, $personaId)
    {
        return $query->where('id_persona', $personaId);
    }

    public function scopeByTipo($query, $tipo)
    {
        return $query->where('tipo_documento', $tipo);
    }
}