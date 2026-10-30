<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaUsuario extends Model
{
    use HasFactory;

    protected $table = 'empresas_usuarios';
    
    protected $fillable = [
        'id_empresa',
        'id_usuario',
        'fecha_asignacion'
    ];

    protected $casts = [
        'fecha_asignacion' => 'datetime',
    ];

    // Relaciones
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}