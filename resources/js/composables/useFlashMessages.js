import { watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';

// Claves de flash de Laravel → tipo de aviso de Ant Design
const TIPO = {
    success: 'success',
    created: 'success',
    updated: 'success',
    deleted: 'success',
    error: 'error',
    warning: 'warning',
    info: 'info',
};

/**
 * Escucha `page.props.flash` y muestra un aviso (estilo VenTrack) por cada mensaje.
 * Se monta una sola vez, en el layout principal.
 */
export function useFlashMessages() {
    const page = usePage();
    const mostrados = new WeakSet();

    const flush = (flash) => {
        if (!flash || mostrados.has(flash)) return;
        mostrados.add(flash);

        for (const [clave, texto] of Object.entries(flash)) {
            if (texto && TIPO[clave]) message[TIPO[clave]](texto);
        }
    };

    onMounted(() => flush(page.props.flash));
    watch(() => page.props.flash, flush, { deep: true });
}
