<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaSeguridad extends Model
{
    protected $table = 'alertas_seguridad';

    public const TIPOS = [
        'INTRUSO' => ['label' => 'Intruso', 'icono' => 'pi pi-user-minus'],
        'ROBO' => ['label' => 'Robo', 'icono' => 'pi pi-shopping-bag'],
        'AGRESION' => ['label' => 'Agresión', 'icono' => 'pi pi-exclamation-triangle'],
        'SOSPECHOSO' => ['label' => 'Persona / vehículo sospechoso', 'icono' => 'pi pi-eye'],
        'INCENDIO' => ['label' => 'Incendio', 'icono' => 'pi pi-bolt'],
        'EMERGENCIA_MEDICA' => ['label' => 'Emergencia médica', 'icono' => 'pi pi-heart'],
        'OTRO' => ['label' => 'Otra emergencia', 'icono' => 'pi pi-megaphone'],
    ];

    public const ESTATUS = [
        'ACTIVA' => 'Activa',
        'ATENDIDA' => 'Atendida',
        'FALSA_ALARMA' => 'Falsa alarma',
    ];

    protected $fillable = [
        'id_empresa', 'tipo', 'descripcion', 'ubicacion', 'latitud', 'longitud', 'anonima',
        'id_usuario', 'id_casa', 'estatus', 'id_usuario_cierre', 'fecha_cierre', 'comentario_cierre',
    ];

    protected $attributes = [
        'estatus' => 'ACTIVA',
        'anonima' => false,
    ];

    protected $casts = [
        'anonima' => 'boolean',
        'latitud' => 'float',
        'longitud' => 'float',
        'fecha_cierre' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
    }

    public function cierre()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_cierre', 'id_usuario');
    }

    public function archivos()
    {
        return $this->hasMany(AlertaArchivo::class, 'id_alerta');
    }

    /**
     * Datos para mostrar. Si es anónima, los vecinos no ven quién la emitió;
     * la administración (y quien la emitió) sí.
     */
    public function paraUsuario(Usuario $usuario, bool $verEmisor): array
    {
        $mostrarEmisor = !$this->anonima || $verEmisor || $this->id_usuario === $usuario->id_usuario;

        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'tipo_texto' => self::TIPOS[$this->tipo]['label'] ?? $this->tipo,
            'descripcion' => $this->descripcion,
            'ubicacion' => $this->ubicacion,
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'anonima' => $this->anonima,
            'estatus' => $this->estatus,
            'created_at' => $this->created_at,
            'emisor' => $mostrarEmisor ? $this->usuario?->nombre_usuario : null,
            'emisor_tipo' => $mostrarEmisor ? $this->usuario?->tipo_usuario : null,
            'casa' => $mostrarEmisor ? $this->casa?->identificador : null,
            'es_mia' => $this->id_usuario === $usuario->id_usuario,
            'cierre' => $this->cierre?->nombre_usuario,
            'fecha_cierre' => $this->fecha_cierre,
            'comentario_cierre' => $this->comentario_cierre,
            'archivos' => $this->archivos->map(fn ($a) => [
                'id' => $a->id,
                'nombre' => $a->nombre_original,
                'es_video' => str_starts_with((string) $a->mime, 'video/'),
                'url' => route('seguridad.alertas.archivo', $a->id),
            ]),
        ];
    }
}
