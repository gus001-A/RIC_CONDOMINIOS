<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Gate;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // ✅ Si no hay usuario autenticado, permisos por defecto
        if (!$user) {
            return [
                ...parent::share($request),
                'auth' => [
                    'user' => null,
                ],
                'permisos' => $this->getDefaultPermisos(),
                'ziggy' => fn () => [
                    ...(new Ziggy)->toArray(),
                    'location' => $request->url(),
                ],
            ];
        }

        // ✅ Obtener TODOS los permisos del usuario usando los Gates definidos
        $permisos = [
            // Movimientos
            'puede_ver' => Gate::allows('ver-movimientos', $user),
            'puede_crear' => Gate::allows('crear-movimientos', $user),
            'puede_editar' => Gate::allows('editar-movimientos', $user),
            'puede_subir_recursos' => Gate::allows('subir-recursos', $user),
            'puede_eliminar' => Gate::allows('eliminar-movimientos', $user),
            'puede_autorizar' => Gate::allows('autorizar-polizas', $user),
            'puede_ver_todos_movimientos' => Gate::allows('ver-todos-movimientos', $user),
            
            // Personas
            'puede_ver_personas' => Gate::allows('ver-personas', $user),
            'puede_crear_personas' => Gate::allows('crear-personas', $user),
            'puede_editar_personas' => Gate::allows('editar-personas', $user),
            'puede_eliminar_personas' => Gate::allows('eliminar-personas', $user),
            
            // Cuentas
            'puede_ver_cuentas' => Gate::allows('ver-cuentas', $user),
            'puede_crear_cuentas' => Gate::allows('crear-cuentas', $user),
            'puede_editar_cuentas' => Gate::allows('editar-cuentas', $user),
            'puede_eliminar_cuentas' => Gate::allows('eliminar-cuentas', $user),
            
            // Usuarios ⭐ ESTOS SON LOS QUE FALTABAN
            'puede_ver_usuarios' => Gate::allows('ver-usuarios', $user),
            'puede_crear_usuarios' => Gate::allows('crear-usuarios', $user),
            'puede_editar_usuarios' => Gate::allows('editar-usuarios', $user),
            'puede_eliminar_usuarios' => Gate::allows('eliminar-usuarios', $user),
            
            // Empresas
            'puede_ver_empresas' => Gate::allows('ver-empresas', $user),
            'puede_crear_empresas' => Gate::allows('crear-empresas', $user),
            'puede_editar_empresas' => Gate::allows('editar-empresas', $user),
            'puede_eliminar_empresas' => Gate::allows('eliminar-empresas', $user),
            
            // Reportes
            'puede_ver_reportes' => Gate::allows('ver-reportes', $user),

            // Condominios
            'puede_ver_casas' => Gate::allows('ver-casas', $user),
            'puede_gestionar_casas' => Gate::allows('gestionar-casas', $user),
            'puede_ver_mi_casa' => Gate::allows('ver-mi-casa', $user),
            'puede_ver_transparencia' => Gate::allows('ver-transparencia', $user),
            'puede_anotar_polizas' => Gate::allows('anotar-polizas', $user),
            'puede_resolver_anotaciones' => Gate::allows('resolver-anotaciones', $user),
            'puede_gestionar_cuotas' => Gate::allows('gestionar-cuotas', $user),
            'puede_usar_mensajes' => Gate::allows('usar-mensajes', $user),
            'puede_atender_mensajes' => Gate::allows('atender-mensajes', $user),
            'puede_ver_incidencias' => Gate::allows('ver-incidencias', $user),
            'puede_reportar_incidencias' => Gate::allows('reportar-incidencias', $user),
            'puede_atender_incidencias' => Gate::allows('atender-incidencias', $user),
            'puede_supervisar_incidencias' => Gate::allows('supervisar-incidencias', $user),
            'puede_operar_caseta' => Gate::allows('operar-caseta', $user),
            'puede_ver_alertas' => Gate::allows('ver-alertas', $user),
            'puede_emitir_alertas' => Gate::allows('emitir-alertas', $user),
            'puede_cerrar_alertas' => Gate::allows('cerrar-alertas', $user),
            'puede_rastrear_alertas' => Gate::allows('rastrear-alertas', $user),
            'puede_solicitar_servicios' => Gate::allows('solicitar-servicios', $user),
            'puede_atender_servicios' => Gate::allows('atender-servicios', $user),
            'puede_ver_inventario' => Gate::allows('ver-inventario', $user),
            'puede_usar_inventario' => Gate::allows('usar-inventario', $user),
            'puede_gestionar_inventario' => Gate::allows('gestionar-inventario', $user),

            // Cuentas fondeadoras por usuario (sólo SUPERUSUARIO)
            'puede_ver_cuentas_fondeadoras' => Gate::allows('gestionar-fondeadoras-usuarios', $user),
        ];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id_usuario' => $user->id_usuario,
                    'nombre_completo' => $user->nombre_completo,
                    'nombre_usuario' => $user->nombre_usuario,
                    'email' => $user->email,
                    'tipo_usuario' => $user->tipo_usuario,
                    'activo' => $user->activo,
                ] : null,
            ],
            'permisos' => $permisos,
            // Módulos de RIC ocultos en RIC Condominios (menú y botones los esconden).
            'modulos_ocultos' => config('condominios.modulos_ocultos', []),
            // Llave pública VAPID para que el navegador se suscriba a las notificaciones push.
            'push_llave' => config('services.webpush.public_key'),
            // Conversaciones con mensajes sin leer (globo del menú "Mensajes").
            'mensajes_no_leidos' => fn () => $permisos['puede_usar_mensajes']
                ? \App\Models\Conversacion::visiblesPara($user)->sinLeer($user->id_usuario)->count()
                : 0,
            // Flash compartido para TODAS las páginas (useFlashMessages -> toasts).
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info'    => $request->session()->get('info'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /**
     * Obtener permisos por defecto (todos false)
     */
    private function getDefaultPermisos(): array
    {
        return [
            'puede_ver' => false,
            'puede_crear' => false,
            'puede_editar' => false,
            'puede_subir_recursos' => false,
            'puede_eliminar' => false,
            'puede_autorizar' => false,
            'puede_ver_personas' => false,
            'puede_crear_personas' => false,
            'puede_editar_personas' => false,
            'puede_eliminar_personas' => false,
            'puede_ver_cuentas' => false,
            'puede_crear_cuentas' => false,
            'puede_editar_cuentas' => false,
            'puede_eliminar_cuentas' => false,
            'puede_ver_usuarios' => false,
            'puede_crear_usuarios' => false,
            'puede_editar_usuarios' => false,
            'puede_eliminar_usuarios' => false,
            'puede_ver_empresas' => false,
            'puede_crear_empresas' => false,
            'puede_editar_empresas' => false,
            'puede_eliminar_empresas' => false,
            'puede_ver_reportes' => false,
            'puede_ver_casas' => false,
            'puede_gestionar_casas' => false,
            'puede_ver_mi_casa' => false,
            'puede_ver_transparencia' => false,
            'puede_anotar_polizas' => false,
            'puede_resolver_anotaciones' => false,
            'puede_gestionar_cuotas' => false,
            'puede_usar_mensajes' => false,
            'puede_atender_mensajes' => false,
            'puede_ver_incidencias' => false,
            'puede_reportar_incidencias' => false,
            'puede_atender_incidencias' => false,
            'puede_supervisar_incidencias' => false,
            'puede_operar_caseta' => false,
            'puede_ver_alertas' => false,
            'puede_emitir_alertas' => false,
            'puede_cerrar_alertas' => false,
            'puede_rastrear_alertas' => false,
            'puede_solicitar_servicios' => false,
            'puede_atender_servicios' => false,
            'puede_ver_inventario' => false,
            'puede_usar_inventario' => false,
            'puede_gestionar_inventario' => false,
            'puede_ver_cuentas_fondeadoras' => false,
            'puede_ver_todos_movimientos' => false,
        ];
    }
}