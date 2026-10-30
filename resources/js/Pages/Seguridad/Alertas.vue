<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { message } from 'ant-design-vue';
import {
    BellOutlined, WarningOutlined, UnorderedListOutlined, EnvironmentOutlined, EyeInvisibleOutlined, ExportOutlined, CheckOutlined, StopOutlined,
    SearchOutlined, SendOutlined, UserOutlined, HomeOutlined, PaperClipOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import MapaGps from '@/Components/MapaGps.vue';
import Galeria from '@/Components/Galeria.vue';
import SelectorArchivos from '@/Components/SelectorArchivos.vue';
import Registro from '@/Components/Registro.vue';
import { fechaHora } from '@/composables/useFechaHora';
import { iconoAlerta, colorAlerta } from '@/composables/useAlertas';
import { pulso, consultarPulso } from '@/composables/usePulso';

const props = defineProps({
    tipos: Object,
    estatus: Object,
    condominios: Array,
    centros: Object,
});

const page = usePage();
const permisos = computed(() => page.props.permisos || {});
const esCondomino = computed(() => page.props.auth?.user?.tipo_usuario === 'CONDOMINO');

// ===== Listado =====
const alertas = ref([]);
const falsasAlarmas = ref([]);
const cargando = ref(false);
const filtros = reactive({ estatus: null, tipo: null, desde: null });

const cargar = async () => {
    cargando.value = true;
    try {
        const params = Object.fromEntries(Object.entries(filtros).filter(([, v]) => v));
        const { data } = await axios.get(route('seguridad.alertas.lista'), { params });
        alertas.value = data.alertas;
        falsasAlarmas.value = data.falsas_alarmas || [];
    } finally {
        cargando.value = false;
    }
};
// Si el pulso trae una alerta nueva, refrescar la lista.
watch(() => pulso.alertas.map((a) => a.id).join(','), cargar);

const evidencias = (a) => a.archivos.map((f) => ({ id: f.id, url: f.url, nombre: f.nombre, mime: f.es_video ? 'video/mp4' : 'image/jpeg', etiqueta: f.es_video ? 'Video' : null }));

// ===== Emitir =====
const dialogoEmitir = ref(false);
const conUbicacion = ref(false);
const enviando = ref(false);
const errorArchivos = ref('');
const archivos = ref([]);
const nueva = reactive({ tipo: null, id_empresa: props.condominios[0]?.id ?? null, ubicacion: '', descripcion: '', latitud: null, longitud: null, anonima: false });

const abrirEmitir = (tipo) => {
    Object.assign(nueva, { tipo, ubicacion: '', descripcion: '', latitud: null, longitud: null, anonima: false });
    archivos.value = [];
    errorArchivos.value = '';
    conUbicacion.value = false;
    dialogoEmitir.value = true;
};

const emitir = async () => {
    enviando.value = true;
    errorArchivos.value = '';
    try {
        const fd = new FormData();
        for (const [k, v] of Object.entries(nueva)) {
            if (typeof v === 'boolean') fd.append(k, v ? '1' : '0');
            else if (v !== null && v !== '') fd.append(k, v);
        }
        archivos.value.forEach((f) => fd.append('archivos[]', f));
        await axios.post(route('seguridad.alertas.store'), fd);
        dialogoEmitir.value = false;
        message.success('Alerta enviada a todo el condominio.');
        await consultarPulso();
        cargar();
    } catch (e) {
        const errs = e?.response?.data?.errors || {};
        errorArchivos.value = Object.entries(errs).find(([k]) => k.startsWith('archivos'))?.[1]?.[0] || '';
        if (!errorArchivos.value) {
            message.error(e?.response?.status === 413
                ? 'Los archivos pesan demasiado para el servidor.'
                : (Object.values(errs)[0]?.[0] || e?.response?.data?.message || 'No se pudo enviar la alerta.'));
        }
    } finally {
        enviando.value = false;
    }
};

// ===== Cerrar =====
const dialogoCerrar = ref(false);
const alertaCerrar = ref(null);
const cierre = reactive({ estatus: 'ATENDIDA', comentario: '' });
const errorCierre = ref('');

const abrirCerrar = (a) => {
    alertaCerrar.value = a;
    Object.assign(cierre, { estatus: 'ATENDIDA', comentario: '' });
    errorCierre.value = '';
    dialogoCerrar.value = true;
};
const cerrar = async () => {
    enviando.value = true;
    try {
        await axios.post(route('seguridad.alertas.cerrar', alertaCerrar.value.id), cierre);
        dialogoCerrar.value = false;
        message.success('Alerta cerrada.');
        await consultarPulso();
        cargar();
    } catch (e) {
        errorCierre.value = Object.values(e?.response?.data?.errors || {})[0]?.[0] || e?.response?.data?.message || 'No se pudo cerrar.';
    } finally {
        enviando.value = false;
    }
};

const columnasFalsas = [
    { title: 'Usuario', dataIndex: 'usuario', key: 'usuario', icon: UserOutlined },
    { title: 'Casa', dataIndex: 'casa', key: 'casa', icon: HomeOutlined },
    { title: 'Alertas', dataIndex: 'total', key: 'total', align: 'right', icon: BellOutlined },
    { title: 'Falsas', dataIndex: 'falsas', key: 'falsas', align: 'right', icon: StopOutlined },
    { title: 'Anónimas', dataIndex: 'anonimas', key: 'anonimas', align: 'right', icon: EyeInvisibleOutlined },
];

let timer = null;
onMounted(() => { cargar(); timer = setInterval(cargar, 30000); });
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Alertas de seguridad vecinal" subtitle="Llegan al instante a todos los usuarios del condominio" :icon="BellOutlined" />

            <!-- Botones de alerta -->
            <div v-if="permisos.puede_emitir_alertas" class="vt-card mb-4">
                <div class="vt-card-title">
                    <h3 style="color: #b42318"><WarningOutlined style="color: #d9363e" /> Emitir alerta</h3>
                    <span class="vt-small vt-muted">Úsalo sólo en una situación real. Toda alerta queda registrada.</span>
                </div>
                <div class="al-botones">
                    <button v-for="(t, clave) in tipos" :key="clave" type="button" class="al-boton" @click="abrirEmitir(clave)">
                        <component :is="iconoAlerta(clave)" />
                        <span>{{ t.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Listado -->
            <div class="vt-card mb-4">
                <div class="vt-card-title">
                    <h3><UnorderedListOutlined /> Alertas</h3>
                    <div class="flex gap-2 flex-wrap">
                        <a-select v-model:value="filtros.estatus" allow-clear placeholder="Todos los estatus" style="width: 170px" :options="Object.entries(estatus).map(([value, label]) => ({ value, label }))" @change="cargar" />
                        <a-select v-model:value="filtros.tipo" allow-clear placeholder="Todos los tipos" style="width: 220px" :options="Object.entries(tipos).map(([value, t]) => ({ value, label: t.label }))" @change="cargar" />
                        <a-date-picker v-model:value="filtros.desde" value-format="YYYY-MM-DD" format="DD/MM/YYYY" placeholder="Desde" @change="cargar" />
                    </div>
                </div>

                <a-skeleton v-if="cargando && !alertas.length" active avatar />
                <div v-else-if="!alertas.length" class="vt-empty">No hay alertas.</div>

                <div class="flex flex-col gap-3">
                    <div v-for="a in alertas" :key="a.id" class="al-item" :class="a.estatus.toLowerCase()" :style="{ '--c': colorAlerta(a.estatus) }">
                        <div class="flex items-start gap-3">
                            <span class="al-icono"><component :is="iconoAlerta(a.tipo)" /></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <b style="color: var(--vt-navy); font-size: 15px">{{ a.tipo_texto }}</b>
                                    <span class="vt-tag solido" :style="{ background: colorAlerta(a.estatus) }">{{ estatus[a.estatus] }}</span>
                                    <span v-if="a.anonima" class="vt-tag" style="background: #eef2f7; color: #44566c"><EyeInvisibleOutlined /> Anónima</span>
                                    <span class="vt-small vt-muted">{{ fechaHora(a.created_at) }}</span>
                                </div>
                                <div v-if="a.ubicacion" class="mt-1"><EnvironmentOutlined class="vt-muted" /> {{ a.ubicacion }}</div>
                                <p v-if="a.descripcion" class="mt-1 mb-0 whitespace-pre-line" style="color: var(--vt-navy)">{{ a.descripcion }}</p>
                                <div class="vt-small vt-muted mt-1">
                                    Emitió:
                                    <template v-if="a.emisor"><b>{{ a.emisor }}</b><template v-if="a.casa"> · Casa {{ a.casa }}</template>
                                        <span v-if="a.anonima && permisos.puede_rastrear_alertas" style="color: #d9363e" class="ml-1">(anónima para vecinos)</span></template>
                                    <i v-else>Anónimo</i>
                                    <a v-if="a.latitud !== null" :href="`https://www.google.com/maps?q=${a.latitud},${a.longitud}`" target="_blank" rel="noopener" class="ml-2" style="color: #1a5a96"><ExportOutlined /> Ver en mapa</a>
                                </div>
                                <Galeria v-if="a.archivos.length" class="mt-2" :items="evidencias(a)" tamano="chico" />
                                <div v-if="a.estatus !== 'ACTIVA'" class="al-cierre">
                                    <Registro :etiqueta="estatus[a.estatus] + ' ·'" :usuario="a.cierre" :fecha="a.fecha_cierre" en-linea />
                                    <div v-if="a.comentario_cierre" class="mt-1">{{ a.comentario_cierre }}</div>
                                </div>
                            </div>
                            <a-button v-if="a.estatus === 'ACTIVA' && permisos.puede_cerrar_alertas" @click="abrirCerrar(a)"><CheckOutlined /> Cerrar</a-button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trazabilidad (administración) -->
            <div v-if="permisos.puede_rastrear_alertas && falsasAlarmas.length" class="vt-card" style="padding: 0; overflow: hidden">
                <div class="vt-card-title" style="padding: 14px 16px 0"><h3><SearchOutlined /> Usuarios con falsas alarmas</h3></div>
                <a-table class="vt-table" size="small" :columns="columnasFalsas" :data-source="falsasAlarmas" row-key="usuario" :pagination="false">
                    <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                    <template #bodyCell="{ column, text }">
                        <b v-if="column.key === 'usuario'">{{ text }}</b>
                        <b v-else-if="column.key === 'falsas'" style="color: #d9363e">{{ text }}</b>
                        <template v-else-if="column.key === 'casa'">{{ text || '—' }}</template>
                    </template>
                </a-table>
            </div>
        </div>

        <!-- ================= Emitir ================= -->
        <a-modal v-model:open="dialogoEmitir" width="760px" centered :footer="null">
            <template #title>
                <div class="vt-modal-title">
                    <span class="vt-head-icon" style="background: linear-gradient(135deg, #b42318, #d9363e)"><component :is="iconoAlerta(nueva.tipo)" /></span>
                    <div>Alerta: {{ tipos[nueva.tipo]?.label || '' }}<small>Se avisa al instante a todo el condominio</small></div>
                </div>
            </template>
            <a-form layout="vertical">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <a-form-item v-if="!esCondomino && condominios.length > 1" label="Condominio" required>
                            <a-select v-model:value="nueva.id_empresa" :options="condominios.map((c) => ({ value: c.id, label: c.nombre_empresa }))" />
                        </a-form-item>
                        <a-form-item label="¿Dónde?">
                            <a-input v-model:value="nueva.ubicacion" :maxlength="255" placeholder="Calle, frente a casa, parque, caseta…"><template #prefix><EnvironmentOutlined class="vt-muted" /></template></a-input>
                        </a-form-item>
                        <a-form-item label="¿Qué está pasando?"><a-textarea v-model:value="nueva.descripcion" :rows="4" :maxlength="2000" /></a-form-item>
                        <a-checkbox v-model:checked="nueva.anonima"><EyeInvisibleOutlined /> Enviar como anónima (los vecinos no verán quién la envió; la administración sí)</a-checkbox>
                    </div>
                    <a-form-item label="Fotos o videos (hasta 3, 50 MB c/u)" :validate-status="errorArchivos ? 'error' : ''" :help="errorArchivos">
                        <SelectorArchivos v-model:archivos="archivos" :max="3" :max-mb="50" accept="image/*,video/*" ayuda="Imágenes o videos · máx. 50 MB · hasta 3" texto="Arrastra o elige la evidencia" />
                    </a-form-item>
                </div>
                <a-checkbox v-model:checked="conUbicacion" class="mt-3 mb-2"><EnvironmentOutlined /> Marcar ubicación en el mapa</a-checkbox>
                <MapaGps v-if="conUbicacion" v-model:lat="nueva.latitud" v-model:lng="nueva.longitud" :centro="centros?.[nueva.id_empresa] || null" editable height="220px" />
                <div class="flex justify-end gap-2 mt-4">
                    <a-button size="large" @click="dialogoEmitir = false">Cancelar</a-button>
                    <a-button type="primary" danger size="large" :loading="enviando" @click="emitir"><SendOutlined /> Enviar alerta a todos</a-button>
                </div>
            </a-form>
        </a-modal>

        <!-- ================= Cerrar ================= -->
        <a-modal v-model:open="dialogoCerrar" width="540px" centered :confirm-loading="enviando" ok-text="Cerrar alerta" cancel-text="Cancelar" @ok="cerrar">
            <template #title>
                <div class="vt-modal-title"><span class="vt-head-icon"><CheckOutlined /></span><div>Cerrar alerta<small v-if="alertaCerrar">{{ alertaCerrar.tipo_texto }} · {{ fechaHora(alertaCerrar.created_at) }}</small></div></div>
            </template>
            <a-segmented v-model:value="cierre.estatus" block class="mb-3"
                :options="[{ value: 'ATENDIDA', label: 'Atendida' }, { value: 'FALSA_ALARMA', label: 'Falsa alarma' }]" />
            <a-form layout="vertical">
                <a-form-item label="Comentario" required :validate-status="errorCierre ? 'error' : ''" :help="errorCierre" style="margin-bottom: 0">
                    <a-textarea v-model:value="cierre.comentario" :rows="3" :maxlength="500" show-count placeholder="¿Cómo se atendió? ¿Por qué es falsa alarma?" />
                </a-form-item>
            </a-form>
        </a-modal>
    </AppLayout>
</template>

<style>
.al-botones { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; }
@media (max-width: 1100px) { .al-botones { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 560px) { .al-botones { grid-template-columns: repeat(2, 1fr); } }
.al-boton { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; min-height: 96px; padding: 10px; border-radius: 16px; font-size: 12.5px; font-weight: 700; text-align: center; background: linear-gradient(135deg, #fff5f5, #fdecec); color: #b42318; border: 1.5px solid #f6c8c8; transition: all 0.2s; }
.al-boton .anticon { font-size: 28px; }
.al-boton:hover { background: linear-gradient(135deg, #d9363e, #b42318); color: #fff; border-color: #b42318; transform: translateY(-2px); box-shadow: 0 10px 22px rgba(217, 54, 62, 0.28); }
.al-item { border: 1px solid var(--vt-line); border-radius: 14px; padding: 12px 14px; background: #fff; }
.al-item.activa { border-color: #f6c8c8; background: #fff7f7; }
.al-icono { width: 44px; height: 44px; border-radius: 12px; background: color-mix(in srgb, var(--c) 14%, #fff); color: var(--c); display: grid; place-items: center; font-size: 20px; flex: none; }
.al-item.activa .al-icono { animation: al-latido 1.6s ease-in-out infinite; }
@keyframes al-latido { 50% { box-shadow: 0 0 0 6px rgba(217, 54, 62, 0.18); } }
.al-cierre { margin-top: 8px; font-size: 12.5px; color: #44566c; background: #f6f8fb; border-radius: 10px; padding: 8px 10px; }
</style>
