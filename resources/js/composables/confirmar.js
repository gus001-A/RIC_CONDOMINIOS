import { reactive } from 'vue';

/**
 * Diálogo de confirmación global estilo SweetAlert (igual que VenTrack / SURVICOS).
 * Lo dibuja Components/ConfirmDialog.vue, montado una sola vez en el layout.
 *
 *   if (await confirmar({ tipo: 'danger', titulo: '¿Eliminar?', texto: '…', confirmar: 'Sí, eliminar' })) { … }
 *
 * tipo: 'warning' | 'danger' | 'success' | 'question' | 'info'
 */
export const dialogo = reactive({
    open: false,
    tipo: 'warning',
    titulo: '',
    texto: '',
    confirmar: 'Sí, continuar',
    cancelar: 'Cancelar',
    resolver: null,
});

export function confirmar(opciones) {
    dialogo.resolver?.(false);
    return new Promise((resolve) => {
        Object.assign(dialogo, {
            tipo: 'warning', texto: '', confirmar: 'Sí, continuar', cancelar: 'Cancelar',
            ...opciones,
            open: true,
            resolver: resolve,
        });
    });
}

export function responder(valor) {
    dialogo.open = false;
    dialogo.resolver?.(valor);
    dialogo.resolver = null;
}

/** Confirmación para activar / desactivar un registro. */
export const confirmarEstado = (activo, que, nombre) => confirmar({
    tipo: activo ? 'danger' : 'success',
    titulo: activo ? `¿Desactivar ${que}?` : `¿Activar ${que}?`,
    texto: activo
        ? `“${nombre}” dejará de aparecer como activo. Podrás activarlo de nuevo cuando quieras; su historial se conserva.`
        : `“${nombre}” volverá a estar disponible.`,
    confirmar: activo ? 'Sí, desactivar' : 'Sí, activar',
});
