/**
 * Formato de fecha y hora para la trazabilidad (Registró / Modificó):
 *   fechaHora('2026-10-25T18:03:11.000000Z')  ->  '25/10/2026 12:03'
 */
export function fechaHora(valor) {
    if (!valor) return '';
    const d = new Date(valor);
    if (Number.isNaN(d.getTime())) return '';
    const dos = (n) => String(n).padStart(2, '0');
    return `${dos(d.getDate())}/${dos(d.getMonth() + 1)}/${d.getFullYear()} ${dos(d.getHours())}:${dos(d.getMinutes())}`;
}

export function moneda(valor) {
    if (valor === null || valor === undefined || valor === '') return '';
    return Number(valor).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}
