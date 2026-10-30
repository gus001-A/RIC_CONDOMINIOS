import { ref, computed } from 'vue';
import axios from 'axios';

/**
 * Apoyo para capturar direcciones en México:
 *  - catálogo de estados y municipios (INEGI)
 *  - autocompletado por código postal (SEPOMEX): al escribir 5 dígitos se
 *    obtienen estado, municipio, ciudad y la lista de colonias.
 *
 * Uso típico en un formulario:
 *
 *   const dir = useDireccionMx();
 *   onMounted(dir.cargarEstados);
 *
 *   // al cambiar el estado manualmente
 *   const onEstado = (e) => { form.estado = e; form.municipio = ''; dir.cargarMunicipios(e); };
 *
 *   // al terminar de escribir el CP
 *   const onCP = async () => {
 *     const d = await dir.buscarCP(form.codigo_postal);
 *     if (d) {
 *       form.estado = d.estado;
 *       form.municipio = d.municipio;
 *       form.ciudad = d.ciudad;
 *       await dir.cargarMunicipios(d.estado);
 *       if (dir.colonias.value.length === 1) form.colonia = dir.colonias.value[0].nombre;
 *     }
 *   };
 */
export function useDireccionMx() {
    const estados = ref([]);
    const municipios = ref([]);
    const colonias = ref([]); // [{ nombre, tipo }]
    const coloniasNombres = computed(() => colonias.value.map((c) => c.nombre));
    const cargandoCP = ref(false);
    const cargandoMunicipios = ref(false);
    const cpMensaje = ref('');

    const normalizarCP = (cp) => String(cp ?? '').replace(/\D/g, '').slice(0, 5);

    const cargarEstados = async () => {
        if (estados.value.length) return;
        try {
            const { data } = await axios.get(route('direccion.estados'));
            estados.value = Array.isArray(data) ? data : [];
        } catch (e) {
            estados.value = [];
        }
    };

    const cargarMunicipios = async (estado) => {
        municipios.value = [];
        if (!estado) return;
        cargandoMunicipios.value = true;
        try {
            const { data } = await axios.get(route('direccion.municipios'), { params: { estado } });
            municipios.value = Array.isArray(data) ? data : [];
        } catch (e) {
            municipios.value = [];
        } finally {
            cargandoMunicipios.value = false;
        }
    };

    /**
     * Consulta el CP. Devuelve el objeto
     *   { found:true, cp, estado, municipio, ciudad, colonias:[{nombre,tipo}] }
     * o `null` si no se encontró / hubo error (y deja un mensaje en cpMensaje).
     */
    const buscarCP = async (cp) => {
        const limpio = normalizarCP(cp);
        colonias.value = [];
        cpMensaje.value = '';

        if (limpio.length !== 5) return null;

        cargandoCP.value = true;
        try {
            const { data } = await axios.get(route('direccion.cp', limpio));
            if (data && data.found) {
                colonias.value = Array.isArray(data.colonias) ? data.colonias : [];
                return data;
            }
            cpMensaje.value = (data && data.message)
                ? data.message
                : 'No encontramos ese código postal. Puedes capturar la dirección manualmente.';
            return null;
        } catch (e) {
            cpMensaje.value = e?.response?.data?.message
                || 'No se pudo consultar el código postal en este momento. Puedes capturar la dirección manualmente.';
            return null;
        } finally {
            cargandoCP.value = false;
        }
    };

    return {
        estados,
        municipios,
        colonias,
        coloniasNombres,
        cargandoCP,
        cargandoMunicipios,
        cpMensaje,
        normalizarCP,
        cargarEstados,
        cargarMunicipios,
        buscarCP,
    };
}
