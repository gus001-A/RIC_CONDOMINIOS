<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\CustomResetPasswordNotification; 

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Roles del sistema con su etiqueta. Los de RIC más los de condominios
     * (CONDOMINO, MANTENIMIENTO, VIGILANCIA).
     */
    public const TIPOS = [
        'LECTOR' => 'Lector',
        'CAPTURISTA' => 'Capturista',
        'ADMINISTRADOR' => 'Administrador',
        'AUDITOR' => 'Auditor',
        'SUPERUSUARIO' => 'Super Usuario',
        'CONDOMINO' => 'Condómino',
        'MANTENIMIENTO' => 'Mantenimiento',
        'VIGILANCIA' => 'Vigilancia',
    ];

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nombre_completo',
        'nombre_usuario',
        'email',
        'password_hash',
        'telefono',
        'tipo_usuario',
        'activo',
        'ultimo_acceso'
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'ultimo_acceso' => 'datetime',
    ];

    // ======================================================
    // ✅ RELACIONES
    // ======================================================

    /**
     * Cuentas fondeadoras que el SUPERUSUARIO le asignó a este usuario.
     */
    public function cuentasFondeadorasAsignadas()
    {
        return $this->belongsToMany(Cuenta::class, 'cuentas_fondeadoras_usuarios', 'id_usuario', 'id_cuenta')
                    ->withTimestamps();
    }

    /**
     * IDs de las cuentas fondeadoras (bancos / cajas) que el usuario puede usar
     * para capturar pólizas en ESA empresa.
     *
     * En RIC Condominios ya no hay asignación por usuario (se ocultó la pantalla
     * "Fondeadoras por usuario"): quien tiene asignado el condominio puede usar
     * todas sus fondeadoras activas. La tabla cuentas_fondeadoras_usuarios se
     * conserva por si se reactiva ese control.
     */
    public function idsFondeadorasAsignadas(int $empresaId): array
    {
        if (!$this->puedeVerCondominio($empresaId)) {
            return [];
        }

        return \DB::table('cuentas')
            ->where('id_empresa', $empresaId)
            ->where('fondeo_c', 1)
            ->where('en_uso', true)
            ->pluck('id_cuenta')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Relación muchos a muchos con Empresas
     */
    public function empresas()
    {
        return $this->belongsToMany(Empresa::class, 'empresas_usuarios', 'id_usuario', 'id_empresa')
                    ->withTimestamps();
    }

    /**
     * Registro de condómino ligado a este usuario (sólo tipo CONDOMINO).
     */
    public function condomino()
    {
        return $this->hasOne(Condomino::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Relación uno a muchos con EmpresaUsuario
     */
    public function empresasUsuarios()
    {
        return $this->hasMany(EmpresaUsuario::class, 'id_usuario');
    }

    /**
     * Relación uno a muchos con Pólizas (creadas)
     */
    public function polizasCreadas()
    {
        return $this->hasMany(Poliza::class, 'id_usuario_creador');
    }

    /**
     * Relación uno a muchos con Pólizas (autorizadas)
     */
    public function polizasAutorizadas()
    {
        return $this->hasMany(Poliza::class, 'id_usuario_autorizador');
    }

    /**
     * Relación uno a muchos con Abonos
     */
    public function abonos()
    {
        return $this->hasMany(AbonoPoliza::class, 'id_usuario');
    }

    /**
     * Relación uno a muchos con Documentos
     */
    public function documentosSubidos()
    {
        return $this->hasMany(DocumentoPersona::class, 'id_usuario_subio');
    }

    /**
     * Relación uno a muchos con Logs
     */
    public function logs()
    {
        return $this->hasMany(LogActividad::class, 'id_usuario');
    }

    // ======================================================
    // ✅ MÉTODOS DE AUTENTICACIÓN
    // ======================================================

    public function getAuthIdentifierName()
    {
        return 'id_usuario';
    }

    public function getAuthIdentifier()
    {
        return $this->id_usuario;
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    // ======================================================
    // ✅ PERMISOS
    // ======================================================

    public function esSuperUsuario()
    {
        return $this->tipo_usuario === 'SUPERUSUARIO';
    }

    public function esAdministrador()
    {
        return $this->tipo_usuario === 'ADMINISTRADOR';
    }

    public function esAuditor()
    {
        return $this->tipo_usuario === 'AUDITOR';
    }

    public function esCapturista()
    {
        return $this->tipo_usuario === 'CAPTURISTA';
    }

    public function esLector()
    {
        return $this->tipo_usuario === 'LECTOR';
    }

    public function esAdminOrSuper()
    {
        return in_array($this->tipo_usuario, ['ADMINISTRADOR', 'SUPERUSUARIO']);
    }

    /**
     * IDs de los condominios (empresas) que el usuario puede consultar.
     * El SUPERUSUARIO ve todos; los demás, los que tienen asignados.
     */
    public function idsCondominios(): array
    {
        $query = $this->esSuperUsuario()
            ? Empresa::query()
            : $this->empresas();

        return $query->where('empresas.activo', true)->pluck('empresas.id')->map(fn ($id) => (int) $id)->all();
    }

    public function puedeVerCondominio($empresaId): bool
    {
        return in_array((int) $empresaId, $this->idsCondominios(), true);
    }

    public function esCondomino()
    {
        return $this->tipo_usuario === 'CONDOMINO';
    }

    public function esMantenimiento()
    {
        return $this->tipo_usuario === 'MANTENIMIENTO';
    }

    public function esVigilancia()
    {
        return $this->tipo_usuario === 'VIGILANCIA';
    }

    // ======================================================
    // ✅ ATTRIBUTES
    // ======================================================

    public function getTipoUsuarioTextoAttribute()
    {
        return self::TIPOS[$this->tipo_usuario] ?? $this->tipo_usuario;
    }

    public function getNombreMostrarAttribute()
    {
        return "{$this->nombre_completo} ({$this->nombre_usuario})";
    }

    // ======================================================
    // ✅ SCOPES
    // ======================================================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeByTipo($query, $tipo)
    {
        return $query->where('tipo_usuario', $tipo);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('nombre_completo', 'LIKE', "%{$search}%")
                     ->orWhere('nombre_usuario', 'LIKE', "%{$search}%")
                     ->orWhere('email', 'LIKE', "%{$search}%");
    }
    
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPasswordNotification($token, $this->email));
    }
}