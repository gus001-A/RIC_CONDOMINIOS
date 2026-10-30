// resources/js/composables/usePermissions.js
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage();
    
    const user = computed(() => page.props.auth?.user || null);
    const tipoUsuario = computed(() => user.value?.tipo_usuario || null);

    // Verificar si tiene un permiso específico
    const puede = (accion) => {
        const tipo = tipoUsuario.value;
        
        // SUPERUSUARIO tiene todos los permisos
        if (tipo === 'SUPERUSUARIO') return true;

        // AUDITOR tiene casi todos los permisos
        if (tipo === 'AUDITOR') {
            const auditorPermisos = [
                'ver_movimientos', 'ver_personas', 'ver_cuentas', 'ver_reportes',
                'crear_movimientos', 'crear_personas', 'crear_usuarios', 'crear_cuentas',
                'editar_movimientos', 'editar_personas', 'editar_usuarios', 'editar_cuentas',
                'autorizar_polizas', 'autorizar_todo',
                'eliminar_movimientos', 'eliminar_personas', 'eliminar_usuarios',
                'ver_todos_movimientos'
            ];
            if (auditorPermisos.includes(accion)) return true;
        }

        // ADMINISTRADOR
        if (tipo === 'ADMINISTRADOR') {
            const adminPermisos = [
                'ver_movimientos', 'ver_personas', 'ver_cuentas', 'ver_reportes',
                'crear_movimientos', 'crear_personas', 'crear_usuarios', 'crear_cuentas',
                'editar_movimientos', 'editar_personas', 'editar_usuarios', 'editar_cuentas',
                'autorizar_polizas',
                'eliminar_movimientos', 'eliminar_personas', 'eliminar_usuarios',
                'ver_todos_movimientos'
            ];
            if (adminPermisos.includes(accion)) return true;
        }

        // CAPTURISTA
        if (tipo === 'CAPTURISTA') {
            const capturistaPermisos = [
                'ver_movimientos', 'ver_personas', 'ver_cuentas', 'ver_reportes',
                'crear_movimientos', 'crear_personas'
            ];
            if (capturistaPermisos.includes(accion)) return true;
        }

        // LECTOR
        if (tipo === 'LECTOR') {
            const lectorPermisos = [
                'ver_movimientos', 'ver_personas', 'ver_cuentas', 'ver_reportes'
            ];
            if (lectorPermisos.includes(accion)) return true;
        }

        return false;
    };

    // Verificar si el usuario es de un tipo específico
    const es = (tipo) => tipoUsuario.value === tipo;

    // Computed properties
    const esLector = computed(() => tipoUsuario.value === 'LECTOR');
    const esCapturista = computed(() => tipoUsuario.value === 'CAPTURISTA');
    const esAdministrador = computed(() => tipoUsuario.value === 'ADMINISTRADOR');
    const esAuditor = computed(() => tipoUsuario.value === 'AUDITOR');
    const esSuperUsuario = computed(() => tipoUsuario.value === 'SUPERUSUARIO');
    
    // Permisos compuestos
    const puedeCrear = computed(() => puede('crear_movimientos'));
    const puedeEditar = computed(() => puede('editar_movimientos'));
    const puedeEliminar = computed(() => puede('eliminar_movimientos'));
    const puedeAutorizar = computed(() => puede('autorizar_polizas'));
    const puedeVerTodos = computed(() => puede('ver_todos_movimientos'));
    const puedeCrearUsuarios = computed(() => puede('crear_usuarios'));
    const puedeCrearCuentas = computed(() => puede('crear_cuentas'));
    const puedeCrearEmpresas = computed(() => esSuperUsuario.value);

    return {
        // Información del usuario
        user,
        tipoUsuario,
        
        // Métodos
        puede,
        es,
        
        // Tipo de usuario
        esLector,
        esCapturista,
        esAdministrador,
        esAuditor,
        esSuperUsuario,
        
        // Permisos compuestos
        puedeCrear,
        puedeEditar,
        puedeEliminar,
        puedeAutorizar,
        puedeVerTodos,
        puedeCrearUsuarios,
        puedeCrearCuentas,
        puedeCrearEmpresas
    };
}