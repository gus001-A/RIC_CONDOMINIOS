/** Colores (hex, para vt-tag) de los estatus de solicitudes de servicio y cotizaciones. */
export const colorServicio = (estatus) => ({
    NUEVA: '#f0a202',
    EN_REVISION: '#1597a5',
    COTIZADA: '#6e4bc2',
    EN_PROCESO: '#1a5a96',
    CONCLUIDA: '#1faf5c',
    CANCELADA: '#94a3b8',
}[estatus] || '#94a3b8');

export const colorCotizacion = (estatus) => ({
    PROPUESTA: '#f0a202',
    ACEPTADA: '#1faf5c',
    RECHAZADA: '#94a3b8',
}[estatus] || '#94a3b8');
