<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insumo extends Model
{
    protected $table = 'insumos';

    public const CATEGORIAS = [
        'ELECTRICO' => 'Material eléctrico',
        'PLOMERIA' => 'Plomería',
        'LIMPIEZA' => 'Limpieza',
        'JARDINERIA' => 'Jardinería',
        'PINTURA' => 'Pintura',
        'FERRETERIA' => 'Ferretería / herramienta',
        'ALBERCA' => 'Alberca / químicos',
        'OFICINA' => 'Papelería / oficina',
        'OTRO' => 'Otro',
    ];

    public const UNIDADES = [
        'PIEZA' => 'Pieza',
        'CAJA' => 'Caja',
        'PAQUETE' => 'Paquete',
        'LITRO' => 'Litro',
        'GALON' => 'Galón',
        'CUBETA' => 'Cubeta',
        'KILO' => 'Kilo',
        'BULTO' => 'Bulto',
        'METRO' => 'Metro',
        'ROLLO' => 'Rollo',
    ];

    protected $fillable = [
        'id_empresa', 'clave', 'nombre', 'categoria', 'unidad', 'stock_minimo', 'ubicacion', 'notas', 'activo',
        'id_usuario_registro', 'id_usuario_modifico',
    ];

    protected $attributes = [
        'activo' => true,
        'existencia' => 0,
        'costo_promedio' => 0,
        'stock_minimo' => 0,
        'categoria' => 'OTRO',
        'unidad' => 'PIEZA',
    ];

    protected $casts = [
        'existencia' => 'float',
        'stock_minimo' => 'float',
        'costo_promedio' => 'float',
        'activo' => 'boolean',
    ];

    protected $appends = ['bajo_minimo'];

    public function movimientos()
    {
        return $this->hasMany(InsumoMovimiento::class, 'id_insumo');
    }

    public function registro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    public function modifico()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_modifico', 'id_usuario');
    }

    public function getBajoMinimoAttribute(): bool
    {
        return $this->stock_minimo > 0 && $this->existencia <= $this->stock_minimo;
    }
}
