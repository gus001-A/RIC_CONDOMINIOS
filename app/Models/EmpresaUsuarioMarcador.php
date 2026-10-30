<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaUsuarioMarcador extends Model
{
    use HasFactory;

    protected $table = 'empresas_usuarios_marcadores';

    protected $fillable = [
        'id_empresa_usuario',
        'id_marcador',
        'ver_todos'
    ];

    protected $casts = [
        'ver_todos' => 'boolean',
    ];

    // Relaciones
    public function empresaUsuario()
    {
        return $this->belongsTo(EmpresaUsuario::class, 'id_empresa_usuario');
    }

    public function marcador()
    {
        return $this->belongsTo(Marcador::class, 'id_marcador');
    }
}