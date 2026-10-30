import { message } from 'ant-design-vue';
import { confirmar } from '@/composables/confirmar';

/**
 * Avisos y confirmaciones de toda la app, con el estilo de VenTrack / SURVICOS:
 *  - avisos cortos con `message` de Ant Design (arriba al centro)
 *  - confirmaciones con el diálogo tipo SweetAlert (Components/ConfirmDialog.vue)
 *
 * Mantiene la misma forma de uso que tenía con PrimeVue, así que las pantallas
 * originales de RIC no cambiaron:
 *   const notify = useNotify();
 *   notify.success('Guardado correctamente');
 *   notify.confirmDelete({ message: '¿Eliminar este registro?', accept: () => ... });
 */
const conTitulo = (detalle, titulo) => (titulo && !['Éxito', 'Error', 'Información', 'Advertencia'].includes(titulo) ? `${titulo}: ${detalle}` : detalle);

export function useNotify() {
    const success = (detalle, titulo) => message.success(conTitulo(detalle, titulo));
    const error = (detalle, titulo) => message.error(conTitulo(detalle, titulo));
    const info = (detalle, titulo) => message.info(conTitulo(detalle, titulo));
    const warn = (detalle, titulo) => message.warning(conTitulo(detalle, titulo));

    const confirmDelete = async ({ message: texto, header = 'Confirmar eliminación', acceptLabel = 'Sí, eliminar', accept, reject }) => {
        (await confirmar({ tipo: 'danger', titulo: header, texto, confirmar: acceptLabel })) ? accept?.() : reject?.();
    };

    const confirmAction = async ({ message: texto, header = 'Confirmar', acceptLabel = 'Confirmar', acceptSeverity = null, accept, reject }) => {
        const tipo = { success: 'success', danger: 'danger', warn: 'warning', warning: 'warning', info: 'info' }[acceptSeverity] || 'question';
        (await confirmar({ tipo, titulo: header, texto, confirmar: acceptLabel })) ? accept?.() : reject?.();
    };

    return { success, error, info, warn, confirmDelete, confirmAction };
}
