<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Usuario;
use Illuminate\Support\Facades\Schema; // ← ¡AGREGA ESTA LÍNEA!

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Al cerrar sesión, el celular deja de recibir notificaciones push de ese usuario.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function () {
            if ($hash = session('push_endpoint_hash')) {
                \App\Models\PushSuscripcion::where('endpoint_hash', $hash)->delete();
            }
        });

        // ✅ ¡ESTA LÍNEA ES LA QUE SOLUCIONA EL ERROR!
        Schema::defaultStringLength(191);

        // ============================================================
        // PERMISOS POR ROL (según especificación del negocio)
        //  LECTOR       -> sólo ve la pantalla de Movimientos (nada más)
        //  CAPTURISTA   -> captura pólizas y personas; ve sólo lo suyo;
        //                  NO edita/revisa/autoriza pólizas; NO cuentas/usuarios/empresas
        //  ADMINISTRADOR-> crea usuarios (lector/capturista/admin), personas;
        //                  REVISA y EDITA pólizas (NO autoriza); ve todo; NO empresas
        //  AUDITOR      -> crea usuarios (todos menos super); AUTORIZA pólizas;
        //                  crea cuentas; NO empresas
        //  SUPERUSUARIO -> todo, incluye empresas
        // ============================================================
        $lector       = 'LECTOR';
        $capturista   = 'CAPTURISTA';
        $administrador = 'ADMINISTRADOR';
        $auditor      = 'AUDITOR';
        $super        = 'SUPERUSUARIO';

        // Módulos heredados de RIC ocultos en RIC Condominios (config/condominios.php):
        // sus permisos quedan en "no" y así desaparecen del menú y de las pantallas.
        $oculto = fn (string $modulo) => \App\Http\Middleware\ModuloActivo::oculto($modulo);

        // ✅ MOVIMIENTOS / PÓLIZAS
        Gate::define('ver-movimientos', fn (Usuario $user) => in_array($user->tipo_usuario, [$lector, $capturista, $administrador, $auditor, $super]));
        Gate::define('crear-movimientos', fn (Usuario $user) => in_array($user->tipo_usuario, [$capturista, $administrador, $auditor, $super]));
        // Editar una póliza: NO el capturista, NO el lector.
        Gate::define('editar-movimientos', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        // Subir el recurso (comprobante) de una póliza: el capturista TAMBIÉN puede
        // (sólo en las pólizas que él capturó; ver MovimientoController@subirArchivo).
        // Reemplazar/eliminar un recurso ya subido sigue requiriendo 'editar-movimientos'.
        Gate::define('subir-recursos', fn (Usuario $user) => in_array($user->tipo_usuario, [$capturista, $administrador, $auditor, $super]));
        // Editar una póliza: el capturista TAMBIÉN, pero sólo las que él capturó y
        // mientras sigan sin revisar (ver MovimientoController::puedeEditarPoliza()).
        Gate::define('editar-poliza', fn (Usuario $user) => in_array($user->tipo_usuario, [$capturista, $administrador, $auditor, $super]));
        // Asignar a los usuarios qué cuentas fondeadoras pueden ver (sólo SUPERUSUARIO).
        Gate::define('gestionar-fondeadoras-usuarios', fn (Usuario $user) => !$oculto('fondeadoras_usuario') && $user->tipo_usuario === $super);
        Gate::define('eliminar-movimientos', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        // Revisar: administrador (y super). El auditor autoriza, no revisa.
        Gate::define('revisar-polizas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $super]));
        // Autorizar: SÓLO auditor y super (el administrador YA NO autoriza).
        Gate::define('autorizar-polizas', fn (Usuario $user) => in_array($user->tipo_usuario, [$auditor, $super]));
        // Ver los movimientos de todos los usuarios (el capturista sólo ve los suyos).
        Gate::define('ver-todos-movimientos', fn (Usuario $user) => in_array($user->tipo_usuario, [$lector, $administrador, $auditor, $super]));

        // ✅ PERSONAS  (el LECTOR no ve personas)
        Gate::define('ver-personas', fn (Usuario $user) => !$oculto('personas') && in_array($user->tipo_usuario, [$capturista, $administrador, $auditor, $super]));
        Gate::define('crear-personas', fn (Usuario $user) => !$oculto('personas') && in_array($user->tipo_usuario, [$capturista, $administrador, $auditor, $super]));
        Gate::define('editar-personas', fn (Usuario $user) => !$oculto('personas') && in_array($user->tipo_usuario, [$capturista, $administrador, $auditor, $super]));
        Gate::define('eliminar-personas', fn (Usuario $user) => !$oculto('personas') && in_array($user->tipo_usuario, [$administrador, $auditor, $super]));

        // ✅ CUENTAS  (ni LECTOR ni CAPTURISTA)
        Gate::define('ver-cuentas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        Gate::define('crear-cuentas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        Gate::define('editar-cuentas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        Gate::define('eliminar-cuentas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));

        // ✅ USUARIOS  (qué TIPOS puede crear cada rol se valida en UsuarioController)
        Gate::define('ver-usuarios', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        Gate::define('crear-usuarios', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        Gate::define('editar-usuarios', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        Gate::define('eliminar-usuarios', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));

        // ✅ EMPRESAS (= condominios): el administrador también da de alta y edita
        // (sólo los suyos; ver EmpresaController). Activar/desactivar: sólo SUPERUSUARIO.
        Gate::define('ver-empresas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $super]));
        Gate::define('crear-empresas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $super]));
        Gate::define('editar-empresas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $super]));
        Gate::define('eliminar-empresas', fn (Usuario $user) => $user->tipo_usuario === $super);

        // ✅ REPORTES  (ni LECTOR ni CAPTURISTA)
        Gate::define('ver-reportes', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));

        // ============================================================
        // CONDOMINIOS
        //  CONDOMINO     -> sólo "Mi casa": ve su casa, sube fotos y fija el GPS
        //  MANTENIMIENTO -> consulta el directorio de casas (ubicación/contacto)
        //  VIGILANCIA    -> consulta el directorio de casas (a qué casa va la visita)
        //  CAPTURISTA    -> consulta el directorio
        //  ADMINISTRADOR / AUDITOR / SUPERUSUARIO -> alta y edición de casas y condóminos
        // ============================================================
        $condomino     = 'CONDOMINO';
        $mantenimiento = 'MANTENIMIENTO';
        $vigilancia    = 'VIGILANCIA';

        Gate::define('ver-casas', fn (Usuario $user) => in_array($user->tipo_usuario, [$capturista, $administrador, $auditor, $super, $mantenimiento, $vigilancia]));
        Gate::define('gestionar-casas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        Gate::define('ver-mi-casa', fn (Usuario $user) => $user->tipo_usuario === $condomino);

        // TRANSPARENCIA CONTABLE
        //  Pólizas, estado de resultados y deudores del condominio (sólo lectura).
        //  Anotar: condóminos y administración. Responder / resolver: administración.
        //  Cuotas (cargos y pagos): administración y capturista.
        Gate::define('ver-transparencia', fn (Usuario $user) => in_array($user->tipo_usuario, [$condomino, $lector, $capturista, $administrador, $auditor, $super]));
        Gate::define('anotar-polizas', fn (Usuario $user) => in_array($user->tipo_usuario, [$condomino, $administrador, $auditor, $super]));
        Gate::define('resolver-anotaciones', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
        Gate::define('gestionar-cuotas', fn (Usuario $user) => in_array($user->tipo_usuario, [$capturista, $administrador, $auditor, $super]));

        // MENSAJERÍA INTERNA: el condómino escribe a la administración de su
        // condominio; la administración atiende y también puede escribir a una casa.
        Gate::define('usar-mensajes', fn (Usuario $user) => in_array($user->tipo_usuario, [$condomino, $administrador, $auditor, $super]));
        Gate::define('atender-mensajes', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));

        // INCIDENCIAS DE ZONAS COMUNES
        //  Ver y comentar: condóminos y personal del condominio.
        //  Reportar: condóminos, vigilancia, mantenimiento y administración.
        //  Atender (en proceso / atendida, fotos y gastos): mantenimiento y administración.
        //  Supervisar (asignar, supervisar, cancelar, reabrir): administración.
        Gate::define('ver-incidencias', fn (Usuario $user) => in_array($user->tipo_usuario, [$condomino, $mantenimiento, $vigilancia, $lector, $capturista, $administrador, $auditor, $super]));
        Gate::define('reportar-incidencias', fn (Usuario $user) => in_array($user->tipo_usuario, [$condomino, $mantenimiento, $vigilancia, $administrador, $auditor, $super]));
        Gate::define('atender-incidencias', fn (Usuario $user) => in_array($user->tipo_usuario, [$mantenimiento, $administrador, $auditor, $super]));
        Gate::define('supervisar-incidencias', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));

        // SEGURIDAD
        //  Caseta (registrar llegadas, entradas y salidas): vigilancia y administración.
        //  Mis visitas (autorizar / pre-autorizar): condómino de la casa.
        //  Alertas vecinales: las emite y las ve todo el condominio; las cierra
        //  vigilancia o administración; sólo la administración ve quién emitió
        //  una alerta anónima (para detectar mal uso).
        Gate::define('operar-caseta', fn (Usuario $user) => in_array($user->tipo_usuario, [$vigilancia, $administrador, $auditor, $super]));
        Gate::define('ver-alertas', fn (Usuario $user) => in_array($user->tipo_usuario, [$condomino, $vigilancia, $mantenimiento, $lector, $capturista, $administrador, $auditor, $super]));
        Gate::define('emitir-alertas', fn (Usuario $user) => in_array($user->tipo_usuario, [$condomino, $vigilancia, $mantenimiento, $administrador, $auditor, $super]));
        Gate::define('cerrar-alertas', fn (Usuario $user) => in_array($user->tipo_usuario, [$vigilancia, $administrador, $auditor, $super]));
        Gate::define('rastrear-alertas', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));

        // SERVICIOS PARTICULARES, ASESORÍA INMOBILIARIA Y DOCUMENTOS
        //  Privados entre la casa y la administración (nadie más los ve).
        //  El condómino solicita; la administración atiende, cotiza y genera
        //  documentos desde plantillas que ella misma administra.
        Gate::define('solicitar-servicios', fn (Usuario $user) => $user->tipo_usuario === $condomino);

        // INVENTARIO DE INSUMOS
        //  Ver existencias: personal y condóminos (transparencia, sin poder mover nada).
        //  Registrar uso (salidas): mantenimiento y administración.
        //  Catálogo, compras (entradas) y ajustes: administración y capturista.
        Gate::define('ver-inventario', fn (Usuario $user) => in_array($user->tipo_usuario, [$condomino, $mantenimiento, $lector, $capturista, $administrador, $auditor, $super]));
        Gate::define('usar-inventario', fn (Usuario $user) => in_array($user->tipo_usuario, [$mantenimiento, $capturista, $administrador, $auditor, $super]));
        Gate::define('gestionar-inventario', fn (Usuario $user) => in_array($user->tipo_usuario, [$capturista, $administrador, $auditor, $super]));
        Gate::define('atender-servicios', fn (Usuario $user) => in_array($user->tipo_usuario, [$administrador, $auditor, $super]));
    }

    /**
     * Tipos de usuario que un rol puede CREAR (usado por UsuarioController).
     */
    public static function tiposUsuarioQuePuedeCrear(string $tipoUsuario): array
    {
        return match ($tipoUsuario) {
            'SUPERUSUARIO'  => ['LECTOR', 'CAPTURISTA', 'ADMINISTRADOR', 'AUDITOR', 'SUPERUSUARIO', 'CONDOMINO', 'MANTENIMIENTO', 'VIGILANCIA'],
            'AUDITOR'       => ['LECTOR', 'CAPTURISTA', 'ADMINISTRADOR', 'AUDITOR', 'CONDOMINO', 'MANTENIMIENTO', 'VIGILANCIA'],
            'ADMINISTRADOR' => ['LECTOR', 'CAPTURISTA', 'ADMINISTRADOR', 'CONDOMINO', 'MANTENIMIENTO', 'VIGILANCIA'],
            default         => [],
        };
    }
}