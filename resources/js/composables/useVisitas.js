/** Utilidades de las pantallas de visitas (caseta y casa). */
export const colorBadge = (estatus) => ({
    PREAUTORIZADA: 'morado',
    ESPERANDO: 'ambar',
    AUTORIZADA: 'verde',
    RECHAZADA: 'rojo',
    DENTRO: 'azul',
    SALIO: 'gris',
    CANCELADA: 'gris',
}[estatus] || 'gris');

/** Color (hex) de cada estatus de visita, para vt-tag y tarjetas. */
export const colorVisita = (estatus) => ({
    PREAUTORIZADA: '#6e4bc2',
    ESPERANDO: '#f0a202',
    AUTORIZADA: '#1faf5c',
    RECHAZADA: '#d9363e',
    DENTRO: '#1a5a96',
    SALIO: '#94a3b8',
    CANCELADA: '#94a3b8',
}[estatus] || '#94a3b8');

export const claseEstatus = (estatus) => estatus.toLowerCase();

export const hora = (f) => (f ? new Date(f).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : '');

export const fecha = (f) => (f ? String(f).slice(0, 10).split('-').reverse().join('/') : '');

export const hoyIso = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
