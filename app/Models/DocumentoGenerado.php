<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoGenerado extends Model
{
    protected $table = 'documentos_generados';

    protected $fillable = [
        'id_empresa', 'id_casa', 'id_plantilla', 'id_solicitud', 'titulo', 'contenido',
        'id_usuario_registro', 'id_usuario_modifico',
    ];

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
    }

    public function plantilla()
    {
        return $this->belongsTo(PlantillaDocumento::class, 'id_plantilla');
    }

    public function solicitud()
    {
        return $this->belongsTo(SolicitudServicio::class, 'id_solicitud');
    }

    public function registro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    public function modifico()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_modifico', 'id_usuario');
    }
}
