<script setup>
/**
 * Reportes del condominio: se elige el reporte, el periodo y se genera la vista
 * previa; de ahí mismo se descarga el Excel o se abre el PDF en el visor.
 */
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import dayjs from 'dayjs';
import { message } from 'ant-design-vue';
import {
    WalletOutlined, ClockCircleOutlined, DollarOutlined, ToolOutlined, ShopOutlined, IdcardOutlined, BellOutlined, InboxOutlined,
    SwapOutlined, TeamOutlined, FileExcelOutlined, FilePdfOutlined, PlayCircleOutlined, CalendarOutlined, SearchOutlined, BarChartOutlined,
    NumberOutlined, FileTextOutlined, PercentageOutlined,
} from '@ant-design/icons-vue';
import KpiBar from './KpiBar.vue';
import VisorArchivo from './VisorArchivo.vue';
import { coincide } from '@/composables/texto';
import { moneda } from '@/composables/useFechaHora';

const props = defineProps({
    empresaId: { type: [Number, String], default: null },
});

const ICONOS = { wallet: WalletOutlined, clock: ClockCircleOutlined, dollar: DollarOutlined, tool: ToolOutlined, shop: ShopOutlined, idcard: IdcardOutlined, bell: BellOutlined, inbox: InboxOutlined, swap: SwapOutlined, team: TeamOutlined };
const COLOR_GRUPO = { 'Cuotas y cobranza': '#1faf5c', Operación: '#f0a202', Seguridad: '#d9363e', Almacén: '#6e4bc2', Padrón: '#1a5a96' };

// ===== Catálogo =====
const catalogo = ref([]);
const elegido = ref(null);
const grupos = computed(() => catalogo.value.reduce((g, r) => ((g[r.grupo] ??= []).push(r), g), {}));
const actual = computed(() => catalogo.value.find((r) => r.clave === elegido.value));

// ===== Periodo =====
const RANGOS = {
    mes: () => [dayjs().startOf('month'), dayjs()],
    anterior: () => [dayjs().subtract(1, 'month').startOf('month'), dayjs().subtract(1, 'month').endOf('month')],
    d30: () => [dayjs().subtract(29, 'day'), dayjs()],
    anio: () => [dayjs().startOf('year'), dayjs()],
};
const rangoRapido = ref('mes');
const periodo = ref(RANGOS.mes().map((d) => d.format('YYYY-MM-DD')));
const elegirRango = (k) => { rangoRapido.value = k; periodo.value = RANGOS[k]().map((d) => d.format('YYYY-MM-DD')); generar(); };

// ===== Resultado =====
const cargando = ref(false);
const reporte = ref(null);
const busqueda = ref('');

const params = () => ({
    empresa_id: props.empresaId,
    ...(actual.value?.rango && periodo.value?.length === 2 ? { desde: periodo.value[0], hasta: periodo.value[1] } : {}),
});

async function generar() {
    if (!elegido.value || !props.empresaId) return;
    cargando.value = true;
    try {
        const { data } = await axios.get(route('reportes.condominio.datos', elegido.value), { params: params() });
        reporte.value = data;
        busqueda.value = '';
    } catch (e) {
        message.error(e?.response?.data?.message || 'No se pudo generar el reporte.');
    } finally {
        cargando.value = false;
    }
}

const elegir = (clave) => { elegido.value = clave; reporte.value = null; generar(); };

const urlExport = (formato) => route(`reportes.condominio.${formato}`, { tipo: elegido.value, ...params() });
const descargarExcel = () => { window.location.href = urlExport('excel'); message.success('Descargando el Excel…'); };
const visor = ref({ open: false, url: null, nombre: '' });
const verPdf = () => (visor.value = { open: true, url: urlExport('pdf'), nombre: `${actual.value.titulo}.pdf` });

// ===== Tabla =====
const NUMERICOS = ['moneda', 'numero', 'porcentaje'];
const fmt = (v, tipo) => {
    if (v === null || v === undefined || v === '') return '—';
    if (tipo === 'moneda') return moneda(v);
    if (tipo === 'numero') return Number(v).toLocaleString('es-MX', { maximumFractionDigits: 3 });
    if (tipo === 'porcentaje') return `${Number(v).toLocaleString('es-MX', { maximumFractionDigits: 1 })}%`;
    if (tipo === 'fecha') return dayjs(v).format('DD/MM/YYYY');
    if (tipo === 'fechahora') return dayjs(v).format('DD/MM/YYYY HH:mm');
    return v;
};
const ICONO_TIPO = { moneda: DollarOutlined, numero: NumberOutlined, porcentaje: PercentageOutlined, fecha: CalendarOutlined, fechahora: CalendarOutlined };
const columnas = computed(() => (reporte.value?.columnas || []).map((c) => ({
    title: c.titulo,
    key: c.key,
    dataIndex: c.key,
    tipo: c.tipo,
    icon: ICONO_TIPO[c.tipo] || FileTextOutlined,
    width: Math.max(90, Math.min(300, (c.ancho || 14) * 8.5)),
    align: NUMERICOS.includes(c.tipo) ? 'right' : 'left',
    ellipsis: c.tipo === 'texto' && (c.ancho || 0) >= 20,
    sorter: (a, b) => {
        const x = a[c.key]; const y = b[c.key];
        if (x === null || x === undefined) return -1;
        if (y === null || y === undefined) return 1;
        return NUMERICOS.includes(c.tipo) ? x - y : String(x).localeCompare(String(y), 'es');
    },
})));
const anchoTotal = computed(() => columnas.value.reduce((s, c) => s + c.width, 0));
const filas = computed(() => (reporte.value?.filas || [])
    .map((f, i) => ({ ...f, __k: i }))
    .filter((f) => !busqueda.value || coincide(busqueda.value, ...Object.values(f))));
const kpis = computed(() => (reporte.value?.resumen || []).map((k, i) => ({
    key: i, icon: BarChartOutlined, valor: fmt(k.valor, k.tipo), label: k.label, color: k.color || '#1a5a96',
})));
const hayTotales = computed(() => Object.keys(reporte.value?.totales || {}).length > 0 && filas.value.length > 0);

onMounted(async () => {
    try {
        const { data } = await axios.get(route('reportes.condominio.catalogo'));
        catalogo.value = data;
        elegir(data[0]?.clave);
    } catch (e) {
        message.error('No se pudo cargar la lista de reportes.');
    }
});
watch(() => props.empresaId, () => { reporte.value = null; generar(); });
</script>

<template>
    <div class="vt-page rc-wrap">
        <div class="rc-grid">
            <!-- ===== Lista de reportes ===== -->
            <aside class="vt-card rc-menu">
                <div v-for="(lista, grupo) in grupos" :key="grupo" class="rc-grupo">
                    <div class="rc-grupo-t" :style="{ '--c': COLOR_GRUPO[grupo] || '#1a5a96' }">{{ grupo }}</div>
                    <button v-for="r in lista" :key="r.clave" type="button" class="rc-item" :class="{ activo: elegido === r.clave }"
                        :style="{ '--c': COLOR_GRUPO[grupo] || '#1a5a96' }" @click="elegir(r.clave)">
                        <span class="rc-item-ic"><component :is="ICONOS[r.icono] || FileTextOutlined" /></span>
                        <span class="min-w-0">
                            <b>{{ r.titulo }}</b>
                            <small>{{ r.descripcion }}</small>
                        </span>
                    </button>
                </div>
            </aside>

            <!-- ===== Reporte elegido ===== -->
            <section class="min-w-0 flex flex-col gap-3">
                <div v-if="actual" class="vt-card">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <h3 class="rc-titulo"><component :is="ICONOS[actual.icono] || FileTextOutlined" /> {{ actual.titulo }}</h3>
                            <p class="vt-small vt-muted mb-0">{{ actual.descripcion }}</p>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <a-button :disabled="!reporte || cargando" @click="descargarExcel"><FileExcelOutlined style="color: #1d7a45" /> Excel</a-button>
                            <a-button :disabled="!reporte || cargando" @click="verPdf"><FilePdfOutlined style="color: #d9363e" /> PDF</a-button>
                        </div>
                    </div>

                    <div v-if="actual.rango" class="vt-toolbar mt-3" style="margin-bottom: 0">
                        <span class="vt-toolbar-label"><CalendarOutlined /> Periodo</span>
                        <a-range-picker v-model:value="periodo" value-format="YYYY-MM-DD" format="DD/MM/YYYY" :allow-clear="false" @change="rangoRapido = null" />
                        <a-segmented :value="rangoRapido" @change="elegirRango"
                            :options="[{ value: 'mes', label: 'Este mes' }, { value: 'anterior', label: 'Mes anterior' }, { value: 'd30', label: 'Últimos 30 días' }, { value: 'anio', label: 'Este año' }]" />
                        <a-button type="primary" :loading="cargando" @click="generar"><PlayCircleOutlined /> Generar</a-button>
                    </div>
                    <div v-else class="vt-alert info mt-3 mb-0"><ClockCircleOutlined /> <span>Este reporte muestra la situación al día de hoy; no usa periodo.</span></div>
                </div>

                <template v-if="reporte">
                    <KpiBar v-if="kpis.length" :items="kpis" />

                    <div class="vt-card" style="padding: 0; overflow: hidden">
                        <div class="flex items-center justify-between gap-2 flex-wrap" style="padding: 12px 14px">
                            <span class="vt-small vt-muted">{{ reporte.subtitulo }}</span>
                            <a-input v-model:value="busqueda" allow-clear placeholder="Buscar en el reporte" style="width: 240px">
                                <template #prefix><SearchOutlined class="vt-muted" /></template>
                            </a-input>
                        </div>
                        <a-table class="vt-table" size="small" :columns="columnas" :data-source="filas" row-key="__k" :loading="cargando"
                            :scroll="{ x: anchoTotal, y: 'calc(100vh - 470px)' }"
                            :pagination="{ pageSize: 50, showSizeChanger: false, hideOnSinglePage: true, showTotal: (t) => `${t} registros` }"
                            :locale="{ emptyText: 'Sin registros para este periodo.' }">
                            <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                            <template #bodyCell="{ column, text }">
                                <b v-if="column.tipo === 'moneda' && text !== null" :style="{ color: text < 0 ? '#d9363e' : undefined }">{{ fmt(text, column.tipo) }}</b>
                                <span v-else :class="{ 'vt-nowrap': column.tipo !== 'texto' }">{{ fmt(text, column.tipo) }}</span>
                            </template>
                            <template v-if="hayTotales" #summary>
                                <a-table-summary fixed="bottom">
                                    <a-table-summary-row class="rc-total">
                                        <a-table-summary-cell v-for="(c, i) in columnas" :key="c.key" :index="i" :align="c.align">
                                            <template v-if="i === 0"><b>Total</b></template>
                                            <b v-else-if="c.key in reporte.totales">{{ fmt(reporte.totales[c.key], c.tipo) }}</b>
                                        </a-table-summary-cell>
                                    </a-table-summary-row>
                                </a-table-summary>
                            </template>
                        </a-table>
                    </div>
                </template>
                <div v-else class="vt-card"><a-skeleton active :paragraph="{ rows: 6 }" /></div>
            </section>
        </div>

        <VisorArchivo v-model:open="visor.open" :url="visor.url" :nombre="visor.nombre" mime="application/pdf" />
    </div>
</template>

<style>
.rc-grid { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 16px; align-items: start; }
@media (max-width: 1024px) { .rc-grid { grid-template-columns: 1fr; } }
.rc-menu { padding: 10px !important; position: sticky; top: 76px; max-height: calc(100vh - 100px); overflow-y: auto; }
.rc-grupo + .rc-grupo { margin-top: 8px; }
.rc-grupo-t { font: 700 11px Figtree, sans-serif; letter-spacing: 0.6px; text-transform: uppercase; color: var(--c); padding: 8px 8px 4px; }
.rc-item { width: 100%; display: flex; align-items: flex-start; gap: 10px; text-align: left; padding: 9px 10px; border-radius: 12px; transition: background 0.15s; }
.rc-item:hover { background: #f3f7fc; }
.rc-item.activo { background: color-mix(in srgb, var(--c) 10%, #fff); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--c) 30%, #fff); }
.rc-item-ic { flex: none; width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; font-size: 15px; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); }
.rc-item.activo .rc-item-ic { background: var(--c); color: #fff; }
.rc-item b { display: block; color: var(--vt-navy); font-size: 13.5px; }
.rc-item small { display: block; color: var(--vt-muted); font-size: 11.5px; line-height: 1.3; margin-top: 1px; }
.rc-titulo { margin: 0 0 2px; font: 700 18px 'Bricolage Grotesque', Figtree, sans-serif; color: var(--vt-navy); display: flex; align-items: center; gap: 8px; }
.rc-titulo .anticon { color: var(--vt-blue); }
.rc-total td { background: #e8f0fa !important; }
</style>
