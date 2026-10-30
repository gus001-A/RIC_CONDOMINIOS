<script setup>
/**
 * Detalle de una póliza para transparencia: resumen, movimientos, comprobantes
 * (visor en modal) y anotaciones de los condóminos con sus respuestas.
 */
import { ref, reactive, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    FileTextOutlined, PaperClipOutlined, CommentOutlined, ExclamationCircleOutlined, CheckOutlined, RedoOutlined, SendOutlined,
    EnterOutlined, BankOutlined, WalletOutlined, PercentageOutlined, DollarOutlined,
} from '@ant-design/icons-vue';
import Registro from './Registro.vue';
import Galeria from './Galeria.vue';
import { moneda } from '@/composables/useFechaHora';

const props = defineProps({
    visible: Boolean,
    polizaId: { type: Number, default: null },
    tipos: { type: Object, default: () => ({ DUDA: 'Duda', INCONFORMIDAD: 'Inconformidad', COMENTARIO: 'Comentario' }) },
});
// `cambio` avisa al padre cuántas anotaciones hay para refrescar su alerta.
const emit = defineEmits(['update:visible', 'cambio']);

const page = usePage();
const permisos = computed(() => page.props.permisos || {});

const cargando = ref(false);
const enviando = ref(false);
const error = ref('');
const poliza = ref(null);
const anotaciones = ref([]);
const nueva = reactive({ tipo: 'DUDA', texto: '' });
const erroresNueva = ref('');
const respuestas = reactive({});

const abiertas = computed(() => anotaciones.value.filter((a) => !a.resuelta).length);
const fecha = (f) => (f ? f.split('-').reverse().join('/') : '');
const COLOR_ANOT = { INCONFORMIDAD: '#d9363e', DUDA: '#f0a202', COMENTARIO: '#1a5a96' };
const COLOR_TIPO_POL = { INGRESO: '#1faf5c', EGRESO: '#d9363e', TRASPASO: '#5d7085' };

const comprobantes = computed(() => (poliza.value?.archivos || []).map((a) => ({ id: a.id, url: a.url, nombre: a.nombre, mime: a.mime || null, detalle: a.nombre })));
const columnas = [
    { title: 'Cuenta', key: 'cuenta', icon: BankOutlined },
    { title: 'Cuenta de fondeo', key: 'fondeadora', icon: WalletOutlined },
    { title: 'IVA', key: 'iva', icon: PercentageOutlined, width: 110, align: 'right' },
    { title: 'Monto', key: 'monto', icon: DollarOutlined, width: 130, align: 'right' },
];

const cargar = async () => {
    if (!props.polizaId) return;
    cargando.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('transparencia.poliza', props.polizaId));
        poliza.value = data.poliza;
        anotaciones.value = data.anotaciones;
    } catch (e) {
        error.value = e?.response?.data?.message || 'No se pudo cargar la póliza.';
    } finally {
        cargando.value = false;
    }
};

const actualizar = (data) => {
    anotaciones.value = data.anotaciones;
    emit('cambio', { polizaId: props.polizaId, total: anotaciones.value.length, abiertas: abiertas.value });
};

const enviar = async (url, payload, alTerminar) => {
    enviando.value = true;
    erroresNueva.value = '';
    try {
        const { data } = await axios.post(url, payload);
        actualizar(data);
        alTerminar?.();
    } catch (e) {
        erroresNueva.value = Object.values(e?.response?.data?.errors || {})[0]?.[0] || e?.response?.data?.message || 'No se pudo guardar.';
    } finally {
        enviando.value = false;
    }
};

const anotar = () => enviar(route('anotaciones.store', props.polizaId), { ...nueva }, () => { nueva.texto = ''; });
const responder = (a) => enviar(route('anotaciones.responder', a.id), { texto: respuestas[a.id] }, () => { respuestas[a.id] = ''; });
const resolver = (a) => enviar(route('anotaciones.resolver', a.id), {});

watch(() => [props.visible, props.polizaId], ([v]) => {
    if (v) { poliza.value = null; anotaciones.value = []; cargar(); }
}, { immediate: true });
</script>

<template>
    <a-modal :open="visible" :footer="null" width="900px" centered @update:open="emit('update:visible', $event)">
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon"><FileTextOutlined /></span>
                <div>Póliza {{ poliza?.folio || '' }}<small v-if="poliza">{{ fecha(poliza.fecha_poliza) }} · {{ poliza.estatus_texto }}</small></div>
            </div>
        </template>

        <a-spin :spinning="cargando">
            <div style="min-height: 140px">
                <a-result v-if="error" status="warning" :title="error" />

                <template v-else-if="poliza">
                    <div class="vt-seccion">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="vt-dato"><span>Fecha</span><span>{{ fecha(poliza.fecha_poliza) }}</span></div>
                            <div class="vt-dato"><span>Tipo</span><span><span class="vt-tag solido" :style="{ background: COLOR_TIPO_POL[poliza.tipo_poliza] || '#5d7085' }">{{ poliza.tipo_poliza }}</span></span></div>
                            <div class="vt-dato"><span>Estatus</span><span>{{ poliza.estatus_texto }}</span></div>
                            <div class="vt-dato"><span>Total</span><span :style="{ color: poliza.total < 0 ? '#d9363e' : '#15803d', fontWeight: 700 }">{{ moneda(poliza.total) }}</span></div>
                            <div class="vt-dato col-span-2"><span>Proveedor / persona</span><span>{{ poliza.persona || '—' }}</span></div>
                            <div class="vt-dato col-span-2"><span>Concepto</span><span>{{ poliza.referencia || poliza.nota || '—' }}</span></div>
                        </div>
                        <a-divider style="margin: 12px 0 8px" />
                        <Registro etiqueta="Capturó" :usuario="poliza.capturo" :fecha="poliza.created_at" en-linea />
                    </div>

                    <div class="vt-card mt-3" style="padding: 0; overflow: hidden">
                        <a-table class="vt-table" size="small" :columns="columnas" :data-source="poliza.movimientos" :row-key="(m, i) => i" :pagination="false">
                            <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                            <template #bodyCell="{ column, record: m }">
                                <template v-if="column.key === 'cuenta'">{{ m.cuenta || '—' }}</template>
                                <template v-else-if="column.key === 'fondeadora'">{{ m.fondeadora || '—' }}</template>
                                <template v-else-if="column.key === 'iva'">{{ m.iva ? moneda(m.iva) : '' }}</template>
                                <template v-else-if="column.key === 'monto'"><b :style="{ color: m.monto < 0 ? '#d9363e' : '#15803d' }">{{ moneda(m.monto) }}</b></template>
                            </template>
                        </a-table>
                    </div>

                    <div class="vt-seccion mt-3">
                        <div class="vt-seccion-t"><PaperClipOutlined /> Comprobantes</div>
                        <Galeria :items="comprobantes" tamano="chico" vacio="Sin comprobantes adjuntos." />
                    </div>

                    <div class="vt-seccion mt-3">
                        <div class="vt-seccion-t">
                            <CommentOutlined /> Anotaciones
                            <span v-if="abiertas" class="vt-tag solido" style="background: #d9363e"><ExclamationCircleOutlined /> {{ abiertas }} sin resolver</span>
                        </div>

                        <div v-if="permisos.puede_anotar_polizas" class="pt-nueva">
                            <div class="flex gap-2 flex-wrap">
                                <a-segmented v-model:value="nueva.tipo" :options="Object.entries(tipos).map(([value, label]) => ({ value, label }))" />
                            </div>
                            <a-textarea v-model:value="nueva.texto" class="mt-2" :rows="2" :maxlength="2000" placeholder="Escribe tu duda o comentario sobre esta póliza…" />
                            <div class="flex items-center justify-between gap-2 mt-2">
                                <span class="vt-small" style="color: #d9363e">{{ erroresNueva }}</span>
                                <a-button type="primary" :loading="enviando" :disabled="!nueva.texto.trim()" @click="anotar"><SendOutlined /> Agregar anotación</a-button>
                            </div>
                        </div>

                        <div v-if="!anotaciones.length" class="vt-empty">Esta póliza no tiene anotaciones.</div>

                        <div class="flex flex-col gap-3">
                            <div v-for="a in anotaciones" :key="a.id" class="pt-anot" :class="{ resuelta: a.resuelta }">
                                <div class="flex items-start justify-between gap-2 flex-wrap">
                                    <div class="flex gap-1 flex-wrap">
                                        <span class="vt-tag solido" :style="{ background: COLOR_ANOT[a.tipo] || '#1a5a96' }">{{ tipos[a.tipo] || a.tipo }}</span>
                                        <span v-if="a.resuelta" class="vt-tag" style="background: #e6f7ee; color: #15803d"><CheckOutlined /> Resuelta</span>
                                        <span v-else class="vt-tag" style="background: #fdecec; color: #b42318">Abierta</span>
                                    </div>
                                    <a-button v-if="permisos.puede_resolver_anotaciones" size="small" :type="a.resuelta ? 'default' : 'primary'" :disabled="enviando" @click="resolver(a)">
                                        <component :is="a.resuelta ? RedoOutlined : CheckOutlined" /> {{ a.resuelta ? 'Reabrir' : 'Marcar resuelta' }}
                                    </a-button>
                                </div>
                                <p class="my-2 whitespace-pre-line" style="color: var(--vt-navy)">{{ a.texto }}</p>
                                <div class="flex gap-4 flex-wrap">
                                    <Registro :etiqueta="a.casa_identificador ? `Casa ${a.casa_identificador} ·` : ''" :usuario="a.usuario?.nombre_usuario" :fecha="a.created_at" en-linea />
                                    <Registro v-if="a.resuelta" etiqueta="Resolvió" :usuario="a.resolvio?.nombre_usuario" :fecha="a.fecha_resolucion" en-linea />
                                </div>

                                <div v-if="a.respuestas.length" class="pt-resps">
                                    <div v-for="r in a.respuestas" :key="r.id" class="pt-resp" :class="{ admin: r.usuario?.tipo_usuario !== 'CONDOMINO' }">
                                        <p class="whitespace-pre-line" style="margin: 0 0 4px">{{ r.texto }}</p>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <Registro :etiqueta="r.casa_identificador ? `Casa ${r.casa_identificador} ·` : ''" :usuario="r.usuario?.nombre_usuario" :fecha="r.created_at" en-linea />
                                            <span v-if="r.usuario?.tipo_usuario !== 'CONDOMINO'" class="vt-tag" style="background: #e8f0fa; color: #1a5a96">Administración</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="permisos.puede_anotar_polizas" class="flex gap-2 mt-2">
                                    <a-input v-model:value="respuestas[a.id]" :maxlength="2000" placeholder="Responder…" @press-enter="(respuestas[a.id] || '').trim() && responder(a)" />
                                    <a-button :disabled="enviando || !(respuestas[a.id] || '').trim()" @click="responder(a)"><EnterOutlined /></a-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </a-spin>
    </a-modal>
</template>

<style>
.pt-nueva { background: #f7fafd; border: 1px solid var(--vt-line); border-radius: 12px; padding: 12px; margin-bottom: 12px; }
.pt-anot { border: 1px solid #fde7b0; background: #fffbf0; border-radius: 12px; padding: 12px 14px; }
.pt-anot.resuelta { border-color: #c6ecd6; background: #f3fbf6; }
.pt-resps { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
.pt-resp { background: #fff; border-radius: 10px; padding: 8px 12px; border: 1px solid var(--vt-line); }
.pt-resp.admin { border-color: #c7dbf2; background: #f7fbff; }
</style>
