<script setup>
import { ref, reactive, watch, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import dayjs from 'dayjs';
import { message } from 'ant-design-vue';
import { IdcardOutlined, BellOutlined, UnorderedListOutlined, CarOutlined, CloseOutlined, CalendarOutlined, CheckOutlined, UserOutlined, TeamOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LectorPlacas from '@/Components/LectorPlacas.vue';
import { confirmar } from '@/composables/confirmar';
import { pulso } from '@/composables/usePulso';
import { colorVisita, hora, fecha, hoyIso } from '@/composables/useVisitas';

defineProps({
    casa: Object,
    tipos: Object,
    estatus: Object,
});

const opciones = (obj) => Object.entries(obj).map(([value, label]) => ({ value, label }));
const visitas = ref([]);
const cargando = ref(false);
const filtroFecha = ref(null);

const cargar = async () => {
    cargando.value = true;
    try {
        const { data } = await axios.get(route('seguridad.visitas'), { params: filtroFecha.value ? { fecha: filtroFecha.value } : {} });
        visitas.value = data.visitas;
    } finally {
        cargando.value = false;
    }
};

const vacio = () => ({ nombre_visitante: '', tipo_visita: 'VISITA', fecha_esperada: hoyIso(), placas: '', vehiculo: '', num_personas: 1, motivo: '' });
const pre = reactive(vacio());
const errores = ref({});
const enviando = ref(false);
const err = (k) => ({ 'validate-status': errores.value[k] ? 'error' : '', help: errores.value[k] });

const preautorizar = async () => {
    enviando.value = true;
    errores.value = {};
    try {
        await axios.post(route('seguridad.visitas.preautorizar'), pre);
        message.success(`Caseta ya sabe que ${pre.nombre_visitante} viene.`);
        Object.assign(pre, vacio());
        cargar();
    } catch (e) {
        const errs = e?.response?.data?.errors;
        if (errs) errores.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]));
        else message.error(e?.response?.data?.message || 'No se pudo registrar.');
    } finally {
        enviando.value = false;
    }
};

const cancelar = async (v) => {
    const ok = await confirmar({ tipo: 'warning', titulo: '¿Cancelar la pre-autorización?', texto: `Caseta ya no dejará pasar a ${v.nombre_visitante} sin avisarte.`, confirmar: 'Sí, cancelar' });
    if (!ok) return;
    try {
        await axios.post(route('seguridad.visitas.cancelar', v.id));
        cargar();
    } catch (e) {
        message.error(e?.response?.data?.message || 'No se pudo cancelar.');
    }
};

// Cuando se responde una visita desde el aviso flotante, refrescar la lista.
watch(() => pulso.visitasEsperando.length, () => { if (!filtroFecha.value) cargar(); });

let timer = null;
onMounted(() => {
    cargar();
    timer = setInterval(() => { if (!filtroFecha.value) cargar(); }, 15000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Mis visitas" :subtitle="`Casa ${casa.identificador} · autoriza a quien llega y avisa a caseta de tus visitas`" :icon="IdcardOutlined" />

            <div class="grid gap-4 xl:grid-cols-5">
                <div class="xl:col-span-3 flex flex-col gap-4">
                    <div v-if="pulso.visitasEsperando.length" class="vt-alert">
                        <BellOutlined />
                        <span><b>Esperando tu autorización:</b> tienes {{ pulso.visitasEsperando.length }} visita(s) en caseta. Responde desde el aviso de la esquina inferior derecha.</span>
                    </div>

                    <div class="vt-card">
                        <div class="vt-card-title">
                            <h3><UnorderedListOutlined /> {{ filtroFecha ? 'Visitas del ' + fecha(filtroFecha) : 'Visitas de hoy y pendientes' }}</h3>
                            <div class="flex gap-2 items-center">
                                <a-date-picker v-model:value="filtroFecha" value-format="YYYY-MM-DD" format="DD/MM/YYYY" placeholder="Otro día" @change="cargar" />
                                <a-button v-if="filtroFecha" @click="filtroFecha = null; cargar()">Hoy</a-button>
                            </div>
                        </div>
                        <a-skeleton v-if="cargando && !visitas.length" active />
                        <div v-else-if="!visitas.length" class="vt-empty">Sin visitas.</div>
                        <div class="flex flex-col gap-2">
                            <div v-for="v in visitas" :key="v.id" class="mv-fila">
                                <a-avatar :size="42" :style="{ background: colorVisita(v.estatus) }"><UserOutlined /></a-avatar>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <b style="color: var(--vt-navy)">{{ v.nombre_visitante }}</b>
                                        <span class="vt-tag solido" :style="{ background: colorVisita(v.estatus) }">{{ estatus[v.estatus] }}</span>
                                    </div>
                                    <div class="vt-small">
                                        {{ tipos[v.tipo_visita] }} · {{ v.num_personas }} pers.
                                        <template v-if="v.placas"> · <CarOutlined class="vt-muted" /> {{ v.placas }} {{ v.vehiculo }}</template>
                                        <template v-if="v.motivo"> · {{ v.motivo }}</template>
                                    </div>
                                    <div class="vt-small vt-muted">
                                        <template v-if="v.estatus === 'PREAUTORIZADA'">Esperada el {{ fecha(v.fecha_esperada) }} · registró <b>{{ v.respuesta }}</b></template>
                                        <template v-else>
                                            Llegó {{ hora(v.fecha_llegada) }} (caseta: <b>{{ v.registro }}</b>)
                                            <template v-if="v.respuesta"> · respondió <b>{{ v.respuesta }}</b> {{ hora(v.fecha_respuesta) }}</template>
                                            <template v-if="v.fecha_entrada"> · entró {{ hora(v.fecha_entrada) }}</template>
                                            <template v-if="v.fecha_salida"> · salió {{ hora(v.fecha_salida) }}</template>
                                        </template>
                                    </div>
                                </div>
                                <a-tooltip v-if="v.estatus === 'PREAUTORIZADA'" title="Cancelar pre-autorización">
                                    <a-button class="vt-ab rojo" @click="cancelar(v)"><CloseOutlined /></a-button>
                                </a-tooltip>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pre-autorizar -->
                <div class="vt-card xl:col-span-2 self-start">
                    <div class="vt-card-title"><h3><CalendarOutlined /> Avisar a caseta de una visita</h3></div>
                    <p class="vt-small vt-muted">Caseta la dejará pasar sin llamarte cuando llegue ese día.</p>
                    <a-form layout="vertical">
                        <div class="vt-form-grid">
                            <a-form-item class="full" label="Nombre" required v-bind="err('nombre_visitante')">
                                <a-input v-model:value="pre.nombre_visitante" :maxlength="150"><template #prefix><UserOutlined class="vt-muted" /></template></a-input>
                            </a-form-item>
                            <a-form-item label="Tipo"><a-select v-model:value="pre.tipo_visita" :options="opciones(tipos)" /></a-form-item>
                            <a-form-item label="Fecha" required v-bind="err('fecha_esperada')">
                                <a-date-picker v-model:value="pre.fecha_esperada" value-format="YYYY-MM-DD" format="DD/MM/YYYY" style="width: 100%" :allow-clear="false"
                                    :disabled-date="(d) => d && d.isBefore(dayjs(), 'day')" />
                            </a-form-item>
                            <a-form-item class="full" label="Placas">
                                <div class="flex gap-2">
                                    <a-input v-model:value="pre.placas" :maxlength="20" class="flex-1" style="text-transform: uppercase" />
                                    <LectorPlacas @leida="({ placa }) => (pre.placas = placa)" />
                                </div>
                            </a-form-item>
                            <a-form-item label="Personas"><a-input-number v-model:value="pre.num_personas" :min="1" :max="50" style="width: 100%"><template #prefix><TeamOutlined class="vt-muted" /></template></a-input-number></a-form-item>
                            <a-form-item class="full" label="Vehículo"><a-input v-model:value="pre.vehiculo" :maxlength="100" placeholder="Marca, modelo, color" /></a-form-item>
                            <a-form-item class="full" label="Comentario para caseta"><a-input v-model:value="pre.motivo" :maxlength="255" /></a-form-item>
                        </div>
                        <div class="flex justify-end">
                            <a-button type="primary" size="large" :loading="enviando" @click="preautorizar"><CheckOutlined /> Pre-autorizar</a-button>
                        </div>
                    </a-form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.mv-fila { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--vt-line); border-radius: 12px; background: #fff; }
.mv-fila:hover { background: #f8fbff; }
</style>
