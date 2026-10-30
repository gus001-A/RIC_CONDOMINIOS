/** Colores de estatus y prioridad de incidencias (hex, para vt-tag). */
const ESTATUS = {
    PENDIENTE: '#f0a202',
    'EN PROCESO': '#1a5a96',
    ATENDIDA: '#1faf5c',
    SUPERVISADA: '#6e4bc2',
    CANCELADA: '#94a3b8',
};
const PRIORIDAD = {
    URGENTE: '#d9363e',
    ALTA: '#f0a202',
    MEDIA: '#1a5a96',
    BAJA: '#94a3b8',
};

export const colorEstatus = (estatus) => ESTATUS[estatus] || '#94a3b8';
export const colorPrioridad = (prioridad) => PRIORIDAD[prioridad] || '#94a3b8';
