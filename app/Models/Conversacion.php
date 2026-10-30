<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Conversacion extends Model
{
    protected $table = 'conversaciones';

    public const CATEGORIAS = [
        'GENERAL' => 'General',
        'PAGOS' => 'Cuotas y pagos',
        'MANTENIMIENTO' => 'Mantenimiento',
        'SEGURIDAD' => 'Seguridad',
        'QUEJA' => 'Queja / sugerencia',
    ];

    protected $fillable = [
        'id_empresa',
        'id_casa',
        'asunto',
        'categoria',
        'estatus',
        'id_usuario_inicio',
        'iniciada_por_administracion',
        'id_usuario_cierre',
        'fecha_cierre',
        'ultimo_mensaje_at',
    ];

    protected $attributes = [
        'estatus' => 'ABIERTA',
        'categoria' => 'GENERAL',
        'iniciada_por_administracion' => false,
    ];

    protected $casts = [
        'iniciada_por_administracion' => 'boolean',
        'fecha_cierre' => 'datetime',
        'ultimo_mensaje_at' => 'datetime',
    ];

    public function casa()
    {
        return $this->belongsTo(Casa::class, 'id_casa');
    }

    public function condominio()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function mensajes()
    {
        return $this->hasMany(Mensaje::class, 'id_conversacion')->oldest();
    }

    public function ultimoMensaje()
    {
        return $this->hasOne(Mensaje::class, 'id_conversacion')->latestOfMany();
    }

    public function inicio()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_inicio', 'id_usuario');
    }

    public function cierre()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_cierre', 'id_usuario');
    }

    // ======================================================
    // ✅ SCOPES
    // ======================================================

    /**
     * Conversaciones que el usuario puede ver: el condómino, las de su casa;
     * la administración, las de sus condominios.
     */
    public function scopeVisiblesPara(Builder $query, Usuario $usuario): Builder
    {
        if ($usuario->esCondomino()) {
            $condomino = $usuario->condomino;

            return $query->where('id_casa', ($condomino && $condomino->activo) ? $condomino->id_casa : 0);
        }

        return $query->whereIn('id_empresa', $usuario->idsCondominios());
    }

    /** Subconsulta: mensajes de otros posteriores a lo último que leyó el usuario. */
    private static function noLeidosSql(): string
    {
        return 'SELECT COUNT(*) FROM mensajes m
                WHERE m.id_conversacion = conversaciones.id
                  AND m.id_usuario <> ?
                  AND m.created_at > COALESCE((
                      SELECT l.leido_hasta FROM conversacion_lecturas l
                      WHERE l.id_conversacion = conversaciones.id AND l.id_usuario = ?
                  ), \'1970-01-01\')';
    }

    public function scopeConNoLeidos(Builder $query, int $idUsuario): Builder
    {
        return $query->selectRaw('(' . self::noLeidosSql() . ') as no_leidos', [$idUsuario, $idUsuario]);
    }

    public function scopeSinLeer(Builder $query, int $idUsuario): Builder
    {
        return $query->whereRaw('(' . self::noLeidosSql() . ') > 0', [$idUsuario, $idUsuario]);
    }

    /** Marca la conversación como leída por el usuario hasta este momento. */
    public function marcarLeida(int $idUsuario): void
    {
        // El chat consulta cada pocos segundos: sólo se escribe si hay algo nuevo que marcar.
        $leido = DB::table('conversacion_lecturas')
            ->where('id_conversacion', $this->id)->where('id_usuario', $idUsuario)
            ->value('leido_hasta');
        if ($leido && $this->ultimo_mensaje_at && $this->ultimo_mensaje_at->lte($leido)) {
            return;
        }

        DB::table('conversacion_lecturas')->updateOrInsert(
            ['id_conversacion' => $this->id, 'id_usuario' => $idUsuario],
            ['leido_hasta' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
