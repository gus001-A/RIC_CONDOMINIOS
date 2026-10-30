<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { message } from 'ant-design-vue';
import {
    SafetyOutlined, BellOutlined, LoginOutlined, LogoutOutlined, ClockCircleOutlined, CarOutlined, PictureOutlined, CloseOutlined,
    BookOutlined, UserOutlined, FlagOutlined, FieldTimeOutlined, SearchOutlined, ClearOutlined, SendOutlined, HomeOutlined, TeamOutlined,
    IdcardOutlined, InboxOutlined, HistoryOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectorArchivos from '@/Components/SelectorArchivos.vue';
import LectorPlacas from '@/Components/LectorPlacas.vue';
import VisorArchivo from '@/Components/VisorArchivo.vue';
import { colorVisita, hora, fecha, hoyIso } from '@/composables/useVisitas';
import { coincide } from '@/composables/texto';

const props = defineProps({
    condominios: Array,
    casas: Array,
    tipos: Object,
    estatus: Object,
});

const CADA_MS = 8000;
const opciones = (obj) => Object.entries(obj).map(([value, label]) => ({ value, label }));

// ===== Registro de llegada =====
const vacio = () => ({ id_casa: null, tipo_visita: 'VISITA', num_personas: 1, nombre_visitante: '', identificacion: '', placas: '', vehiculo: '', motivo: '', sin_autorizacion: false });
const form = reactive(vacio());
const foto = ref([]);
const errores = ref({});
const enviando = ref(false);
const err = (k) => ({ 'validate-status': errores.value[k] ? 'error' : '', help: errores.value[k] });

const limpiarForm = () => { Object.assign(form, vacio()); foto.value = []; errores.value = {}; placaConocida.value = null; };

// ===== Placa leída con la cámara =====
// Llena las placas y, si el vehículo ya vino antes, también el vehículo; la
// última visita se muestra para reutilizar nombre y casa con un clic.
const placaConocida = ref(null);
const alLeerPlaca = async ({ placa, foto: archivo, guardarFoto }) => {
    form.placas = placa;
    if (guardarFoto && archivo) {
        if (foto.value.length) message.info('Ya había una foto; se conservó la anterior.');
        else foto.value = [archivo];
    }
    placaConocida.value = null;
    try {
        const { data } = await axios.get(route('seguridad.visitas.placa', placa));
        placaConocida.value = data.visita;
        if (data.visita?.vehiculo && !form.vehiculo) form.vehiculo = data.visita.vehiculo;
    } catch (e) { /* sin historial: sólo se llenan las placas */ }
};
const usarUltimaVisita = () => {
    const v = placaConocida.value;
    if (!form.nombre_visitante) form.nombre_visitante = v.nombre_visitante;
    if (!form.id_casa && props.casas.some((c) => c.id === v.id_casa)) form.id_casa = v.id_casa;
    form.tipo_visita = v.tipo_visita || form.tipo_visita;
    placaConocida.value = null;
};

const registrar = async () => {
    enviando.value = true;
    errores.value = {};
    try {
        const fd = new FormData();
        for (const [k, v] of Object.entries(form)) {
            if (v === null || v === '') continue;
            fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v);
        }
        if (foto.value[0]) fd.append('foto', foto.value[0]);
        const { data } = await axios.post(route('seguridad.visitas.store'), fd);
        message.success(form.sin_autorizacion ? 'Registrado. Ya puede pasar.' : `Se pidió autorización a la casa ${data.visita.casa}.`);
        limpiarForm();
        cargarEnCurso();
    } catch (e) {
        const errs = e?.response?.data?.errors;
        if (errs) errores.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]));
        else message.error(e?.response?.data?.message || 'No se pudo registrar.');
    } finally {
        enviando.value = false;
    }
};

// ===== En curso (se refresca solo) =====
const enCurso = ref([]);
let conocidas = null;

const cargarEnCurso = async () => {
    try {
        const { data } = await axios.get(route('seguridad.visitas'));
        const activos = data.visitas.filter((v) => ['ESPERANDO', 'AUTORIZADA', 'RECHAZADA', 'DENTRO', 'PREAUTORIZADA'].includes(v.estatus)
            && !(v.estatus === 'RECHAZADA' && Date.now() - new Date(v.fecha_respuesta).getTime() > 30 * 60000));
        // Avisar a la caseta cuando una casa responde.
        if (conocidas) {
            for (const v of activos) {
                const antes = conocidas.get(v.id);
                if (antes === 'ESPERANDO' && v.estatus === 'AUTORIZADA') message.success(`Casa ${v.casa} autorizó a ${v.nombre_visitante}`, 6);
                if (antes === 'ESPERANDO' && v.estatus === 'RECHAZADA') message.error(`Casa ${v.casa} rechazó a ${v.nombre_visitante}`, 6);
            }
        }
        conocidas = new Map(activos.map((v) => [v.id, v.estatus]));
        enCurso.value = activos;
    } catch (e) { /* se reintenta */ }
};

const accion = async (v, tipo) => {
    try {
        await axios.post(route(`seguridad.visitas.${tipo}`, v.id));
        cargarEnCurso();
        if (bitacora.fecha === hoyIso()) cargarBitacora();
    } catch (e) {
        message.error(e?.response?.data?.message || 'No se pudo completar la acción.');
    }
};

const visor = reactive({ open: false, url: null, nombre: '' });
const verFoto = (v) => Object.assign(visor, { open: true, url: v.foto_url, nombre: `Visita · ${v.nombre_visitante}` });

// ===== Bitácora del día =====
const bitacora = reactive({ fecha: hoyIso(), visitante: '', vehiculo: '', estatus: null });
const hayFiltros = computed(() => !!bitacora.visitante || !!bitacora.vehiculo || !!bitacora.estatus);
const limpiarBitacora = () => Object.assign(bitacora, { visitante: '', vehiculo: '', estatus: null });
const registros = ref([]);
const cargandoBitacora = ref(false);
const cargarBitacora = async () => {
    cargandoBitacora.value = true;
    try {
        const { data } = await axios.get(route('seguridad.visitas'), { params: { fecha: bitacora.fecha } });
        registros.value = data.visitas;
    } finally {
        cargandoBitacora.value = false;
    }
};
const sinEspacios = (s) => (s || '').toUpperCase().replace(/[\s-]/g, '');
const bitacoraFiltrada = computed(() => registros.value.filter((v) =>
    coincide(bitacora.visitante, v.nombre_visitante, v.casa)
    && (!bitacora.vehiculo || sinEspacios(v.placas).includes(sinEspacios(bitacora.vehiculo)) || coincide(bitacora.vehiculo, v.vehiculo))
    && (!bitacora.estatus || v.estatus === bitacora.estatus)));

const columnas = [
    { title: 'Visitante / casa', key: 'visitante', icon: UserOutlined, width: 200 },
    { title: 'Vehículo', key: 'vehiculo', icon: CarOutlined, width: 140 },
    { title: 'Estatus', key: 'estatus', icon: FlagOutlined, width: 130 },
    { title: 'Llegada / respuesta', key: 'llegada', icon: ClockCircleOutlined, width: 160 },
    { title: 'Entrada / salida', key: 'entrada', icon: FieldTimeOutlined, width: 160 },
];

let timer = null;
onMounted(() => {
    cargarEnCurso();
    cargarBitacora();
    timer = setInterval(() => {
        cargarEnCurso();
        if (bitacora.fecha === hoyIso()) cargarBitacora();
    }, CADA_MS);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Caseta de vigilancia" subtitle="Registro de visitas y vehículos · la casa autoriza desde su app" :icon="SafetyOutlined">
                <template #extra>
                    <Link :href="route('seguridad.alertas')"><a-button size="large" danger><BellOutlined /> Alertas</a-button></Link>
                </template>
            </PageHeader>

            <div class="grid gap-4 xl:grid-cols-5">
                <!-- ================= Registro de llegada ================= -->
                <div class="vt-card xl:col-span-2 self-start">
                    <div class="vt-card-title"><h3><LoginOutlined /> Registrar llegada</h3></div>
                    <a-form layout="vertical">
                        <div class="vt-form-grid">
                            <a-form-item class="full" label="¿A qué casa va?" required v-bind="err('id_casa')">
                                <a-select v-model:value="form.id_casa" show-search option-filter-prop="label" size="large" placeholder="Busca por casa, responsable o calle"
                                    :options="casas.map((c) => ({ value: c.id, label: c.etiqueta }))">
                                    <template #suffixIcon><HomeOutlined /></template>
                                </a-select>
                            </a-form-item>
                            <a-form-item label="Tipo"><a-select v-model:value="form.tipo_visita" :options="opciones(tipos)" /></a-form-item>
                            <a-form-item label="Personas"><a-input-number v-model:value="form.num_personas" :min="1" :max="50" style="width: 100%"><template #prefix><TeamOutlined class="vt-muted" /></template></a-input-number></a-form-item>
                            <a-form-item class="full" label="Nombre de quien llega" required v-bind="err('nombre_visitante')">
                                <a-input v-model:value="form.nombre_visitante" :maxlength="150"><template #prefix><UserOutlined class="vt-muted" /></template></a-input>
                            </a-form-item>
                            <a-form-item class="full" label="Identificación">
                                <a-input v-model:value="form.identificacion" :maxlength="100" placeholder="INE, licencia… y número"><template #prefix><IdcardOutlined class="vt-muted" /></template></a-input>
                            </a-form-item>
                            <a-form-item class="full" label="Placas" help="Toma la foto de la placa y se llena sola.">
                                <div class="flex gap-2">
                                    <a-input v-model:value="form.placas" :maxlength="20" class="flex-1" style="text-transform: uppercase" @change="placaConocida = null" />
                                    <LectorPlacas @leida="alLeerPlaca" />
                                </div>
                            </a-form-item>
                            <div v-if="placaConocida" class="full vt-alert info mb-2">
                                <HistoryOutlined />
                                <span class="flex-1">
                                    Esta placa ya vino {{ placaConocida.visitas }} vez(es). Última: <b>{{ placaConocida.nombre_visitante }}</b> a la casa <b>{{ placaConocida.casa }}</b>.
                                    <a-button type="link" size="small" style="padding: 0 4px" @click="usarUltimaVisita">Usar estos datos</a-button>
                                </span>
                            </div>
                            <a-form-item class="full" label="Vehículo"><a-input v-model:value="form.vehiculo" :maxlength="100" placeholder="Marca, modelo, color" /></a-form-item>
                            <a-form-item class="full" label="Motivo / comentarios"><a-input v-model:value="form.motivo" :maxlength="255" /></a-form-item>
                            <a-form-item class="full" label="Foto (identificación o vehículo)" v-bind="err('foto')">
                                <SelectorArchivos v-model:archivos="foto" solo-imagenes :max-mb="8" compacto texto="Elige o arrastra la foto" />
                            </a-form-item>
                            <a-form-item class="full" style="margin-bottom: 8px">
                                <a-checkbox v-model:checked="form.sin_autorizacion"><InboxOutlined /> Entra sin pedir autorización a la casa (paquetería, servicio ya acordado)</a-checkbox>
                            </a-form-item>
                        </div>
                        <div class="flex justify-end gap-2">
                            <a-button size="large" @click="limpiarForm">Limpiar</a-button>
                            <a-button type="primary" size="large" :loading="enviando" @click="registrar">
                                <SendOutlined /> {{ form.sin_autorizacion ? 'Registrar' : 'Pedir autorización a la casa' }}
                            </a-button>
                        </div>
                    </a-form>
                </div>

                <!-- ================= En curso y bitácora ================= -->
                <div class="xl:col-span-3 flex flex-col gap-4">
                    <div class="vt-card">
                        <div class="vt-card-title">
                            <h3><ClockCircleOutlined /> En curso <span class="vt-tag" style="background: #eef2f7; color: #44566c">{{ enCurso.length }}</span></h3>
                            <span class="vt-small vt-muted"><span class="cs-vivo"></span> Se actualiza solo cada {{ CADA_MS / 1000 }} s</span>
                        </div>
                        <div v-if="!enCurso.length" class="vt-empty">No hay visitas en curso.</div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div v-for="v in enCurso" :key="v.id" class="cs-visita" :class="{ esperando: v.estatus === 'ESPERANDO' }" :style="{ '--c': colorVisita(v.estatus) }">
                                <div class="flex items-start gap-3">
                                    <a-avatar v-if="v.foto_url" :size="46" shape="square" :src="v.foto_url" style="cursor: zoom-in; border-radius: 10px" @click="verFoto(v)" />
                                    <a-avatar v-else :size="46" shape="square" style="border-radius: 10px" :style="{ background: colorVisita(v.estatus) }"><UserOutlined /></a-avatar>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <b class="truncate" style="color: var(--vt-navy)">{{ v.nombre_visitante }}</b>
                                            <span class="vt-tag solido" :style="{ background: colorVisita(v.estatus) }">{{ estatus[v.estatus] }}</span>
                                        </div>
                                        <div class="vt-small vt-muted">Casa <b>{{ v.casa }}</b> · {{ tipos[v.tipo_visita] }} · {{ v.num_personas }} pers.</div>
                                    </div>
                                </div>
                                <div v-if="v.placas || v.vehiculo" class="vt-small mt-2"><CarOutlined class="vt-muted" /> <b>{{ v.placas }}</b> {{ v.vehiculo }}</div>
                                <div v-if="v.motivo" class="vt-small vt-muted mt-1">{{ v.motivo }}</div>
                                <div v-if="v.comentario_respuesta && v.estatus !== 'PREAUTORIZADA'" class="vt-small mt-1" style="font-style: italic">“{{ v.comentario_respuesta }}”</div>
                                <div class="vt-small vt-muted mt-1">
                                    <template v-if="v.estatus === 'PREAUTORIZADA'">Pre-autorizó <b>{{ v.respuesta }}</b> para el {{ fecha(v.fecha_esperada) }}</template>
                                    <template v-else>
                                        Llegó {{ hora(v.fecha_llegada) }} (<b>{{ v.registro }}</b>)
                                        <template v-if="v.fecha_respuesta"> · respondió <b>{{ v.respuesta }}</b> {{ hora(v.fecha_respuesta) }}</template>
                                        <template v-if="v.fecha_entrada"> · entró {{ hora(v.fecha_entrada) }}</template>
                                    </template>
                                </div>
                                <div class="flex gap-2 mt-3 flex-wrap">
                                    <a-button v-if="['AUTORIZADA', 'PREAUTORIZADA'].includes(v.estatus)" type="primary" size="small" @click="accion(v, 'entrada')"><LoginOutlined /> Entrada</a-button>
                                    <a-button v-if="v.estatus === 'DENTRO'" size="small" @click="accion(v, 'salida')"><LogoutOutlined /> Salida</a-button>
                                    <a-button v-if="['ESPERANDO', 'AUTORIZADA', 'PREAUTORIZADA', 'RECHAZADA'].includes(v.estatus)" size="small" danger @click="accion(v, 'cancelar')">
                                        <CloseOutlined /> {{ v.estatus === 'RECHAZADA' ? 'Archivar' : 'Cancelar' }}
                                    </a-button>
                                    <a-button v-if="v.foto_url" size="small" @click="verFoto(v)"><PictureOutlined /> Foto</a-button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="vt-card" style="padding: 0; overflow: hidden">
                        <div class="vt-card-title" style="padding: 14px 16px 0">
                            <h3><BookOutlined /> Bitácora</h3>
                            <a-date-picker v-model:value="bitacora.fecha" value-format="YYYY-MM-DD" format="DD/MM/YYYY" :allow-clear="false" @change="cargarBitacora" />
                        </div>
                        <a-table table-layout="fixed" class="vt-table" :columns="columnas" :data-source="bitacoraFiltrada" row-key="id" :loading="cargandoBitacora && !registros.length"
                            :scroll="{ x: 790, y: 320 }" :pagination="false" :locale="{ emptyText: 'Sin registros ese día.' }">
                            <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                            <template #bodyCell="{ column, record: v }">
                                <template v-if="column.key === 'visitante'">
                                    <b class="block truncate">{{ v.nombre_visitante }}</b>
                                    <span class="vt-small vt-muted">Casa <b>{{ v.casa }}</b> · {{ tipos[v.tipo_visita] }}</span>
                                </template>
                                <template v-else-if="column.key === 'vehiculo'">
                                    <span v-if="v.placas || v.vehiculo" class="vt-small"><b>{{ v.placas }}</b> {{ v.vehiculo }}</span>
                                    <span v-else class="vt-muted">—</span>
                                </template>
                                <template v-else-if="column.key === 'estatus'">
                                    <span class="vt-tag solido" :style="{ background: colorVisita(v.estatus) }">{{ estatus[v.estatus] }}</span>
                                </template>
                                <template v-else-if="column.key === 'llegada'">
                                    <div class="vt-small">{{ hora(v.fecha_llegada) }} <b>{{ v.registro }}</b></div>
                                    <div v-if="v.fecha_respuesta" class="vt-small vt-muted">{{ hora(v.fecha_respuesta) }} <b>{{ v.respuesta }}</b></div>
                                </template>
                                <template v-else-if="column.key === 'entrada'">
                                    <div v-if="v.fecha_entrada" class="vt-small"><LoginOutlined style="color: #1faf5c" /> {{ hora(v.fecha_entrada) }} <b>{{ v.entrada }}</b></div>
                                    <div v-if="v.fecha_salida" class="vt-small"><LogoutOutlined style="color: #d9363e" /> {{ hora(v.fecha_salida) }} <b>{{ v.salida }}</b></div>
                                </template>
                            </template>
                            <template #summary>
                                <a-table-summary fixed="bottom">
                                    <a-table-summary-row class="vt-fsum">
                                        <a-table-summary-cell :index="0">
                                            <a-input v-model:value="bitacora.visitante" allow-clear placeholder="Nombre o casa"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                        </a-table-summary-cell>
                                        <a-table-summary-cell :index="1"><a-input v-model:value="bitacora.vehiculo" allow-clear placeholder="Placas o vehículo" /></a-table-summary-cell>
                                        <a-table-summary-cell :index="2">
                                            <a-select v-model:value="bitacora.estatus" allow-clear placeholder="Todos" style="width: 100%" :options="opciones(estatus)" />
                                        </a-table-summary-cell>
                                        <a-table-summary-cell :index="3"><span class="vt-small vt-muted">{{ bitacoraFiltrada.length }} registros</span></a-table-summary-cell>
                                        <a-table-summary-cell :index="4" align="right">
                                            <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="limpiarBitacora"><ClearOutlined /></a-button></a-tooltip>
                                        </a-table-summary-cell>
                                    </a-table-summary-row>
                                </a-table-summary>
                            </template>
                        </a-table>
                    </div>
                </div>
            </div>
        </div>
        <VisorArchivo v-model:open="visor.open" :url="visor.url" :nombre="visor.nombre" mime="image/jpeg" />
    </AppLayout>
</template>

<style>
.cs-visita { border: 1px solid color-mix(in srgb, var(--c) 35%, #fff); background: color-mix(in srgb, var(--c) 6%, #fff); border-radius: 14px; padding: 12px 14px; }
.cs-visita.esperando { animation: cs-latido 1.8s ease-in-out infinite; }
@keyframes cs-latido { 50% { box-shadow: 0 0 0 5px color-mix(in srgb, var(--c) 22%, transparent); } }
.cs-vivo { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #1faf5c; margin-right: 4px; animation: cs-vivo 1.6s ease-in-out infinite; }
@keyframes cs-vivo { 50% { opacity: 0.3; } }
</style>
