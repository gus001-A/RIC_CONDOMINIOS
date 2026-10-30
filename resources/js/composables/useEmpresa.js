import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const empresaSeleccionada = ref(null);
const empresas = ref([]);

export function useEmpresa() {
    // Cargar empresa guardada
    const cargarEmpresaGuardada = () => {
        const empresaId = localStorage.getItem('empresa_seleccionada');
        if (empresaId) {
            empresaSeleccionada.value = parseInt(empresaId);
        }
        return empresaSeleccionada.value;
    };

    // Guardar empresa
    const guardarEmpresa = (id) => {
        if (id) {
            empresaSeleccionada.value = parseInt(id);
            localStorage.setItem('empresa_seleccionada', String(id));
        } else {
            empresaSeleccionada.value = null;
            localStorage.removeItem('empresa_seleccionada');
        }
    };

    // Cambiar empresa y redirigir
    const cambiarEmpresa = (id, routeName = null, params = {}) => {
        guardarEmpresa(id);
        
        if (routeName) {
            router.visit(route(routeName, { ...params, empresa_id: id }));
        }
    };

    // Obtener empresa actual
    const getEmpresaActual = () => {
        return empresaSeleccionada.value;
    };

    return {
        empresaSeleccionada,
        empresas,
        cargarEmpresaGuardada,
        guardarEmpresa,
        cambiarEmpresa,
        getEmpresaActual
    };
}