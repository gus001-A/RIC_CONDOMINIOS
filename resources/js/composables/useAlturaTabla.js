import { onBeforeUnmount, onMounted, ref } from 'vue';

const esCelular = () => window.innerWidth < 768;

/**
 * Alto del cuerpo de una tabla para que la pantalla completa quepa sin
 * desplazarse: el scroll queda dentro de la tabla (igual que VenTrack).
 * `reservado` = espacio que ocupa todo lo demás (menú, encabezado,
 * indicadores, cabecera de la tabla y filtros al pie).
 * En el celular todo va apilado, así que la tabla toma ~60 % de la pantalla.
 */
export function useAlturaTabla(reservado, minimo = 240) {
    const alto = ref(400);
    const medir = () => (alto.value = esCelular()
        ? Math.max(320, Math.round(window.innerHeight * 0.6))
        : Math.max(minimo, window.innerHeight - reservado));
    onMounted(() => {
        medir();
        window.addEventListener('resize', medir);
    });
    onBeforeUnmount(() => window.removeEventListener('resize', medir));
    return alto;
}

/**
 * Ancho mínimo de una tabla en el celular: las columnas conservan un ancho
 * legible y la tabla se desliza de lado dentro de su tarjeta.
 * En pantallas grandes regresa undefined (la tabla ocupa el 100 %).
 *   :scroll="{ x: anchoMovil, y: altoTabla }"
 */
export function useAnchoMovil(ancho = 820) {
    const valor = ref(undefined);
    const medir = () => (valor.value = esCelular() ? ancho : undefined);
    onMounted(() => {
        medir();
        window.addEventListener('resize', medir);
    });
    onBeforeUnmount(() => window.removeEventListener('resize', medir));
    return valor;
}
