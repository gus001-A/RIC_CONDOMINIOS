/** Texto para comparar en filtros: sin mayúsculas ni acentos ("José" = "jose"). */
export const normalizar = (s) => (s ?? '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

/** ¿Alguno de los valores contiene la búsqueda? (vacío = sí). */
export const coincide = (busqueda, ...valores) => {
    const q = normalizar(busqueda);
    return !q || valores.some((v) => normalizar(v).includes(q));
};
