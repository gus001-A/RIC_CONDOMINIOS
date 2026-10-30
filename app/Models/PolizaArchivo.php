<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolizaArchivo extends Model
{
    protected $table = 'poliza_archivos';

    protected $fillable = [
        'id_poliza',
        'nombre_original',
        'nombre_guardado',
        'ruta',
        'tipo_archivo',
        'mime_type',
        'tamano',
        'id_usuario_subio',
        'descripcion'
    ];

    protected $casts = [
        'tamano' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function poliza(): BelongsTo
    {
        return $this->belongsTo(Poliza::class, 'id_poliza');
    }

    public function usuario(): BelongsTo
    {
        // La autenticación del sistema usa la tabla `usuarios` (PK id_usuario),
        // no la tabla por defecto `users`.
        return $this->belongsTo(Usuario::class, 'id_usuario_subio', 'id_usuario');
    }

    // 🔥 ESTO ES LO QUE FALTA - EL ACCESSOR PARA LA URL
    public function getUrlAttribute(): string
    {
        return route('movimientos.archivos.ver', $this->id);
    }
}