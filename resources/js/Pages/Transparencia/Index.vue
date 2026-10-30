<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { message } from 'ant-design-vue';
import {
    EyeOutlined, FileTextOutlined, BarChartOutlined, ExclamationCircleOutlined, InboxOutlined, CalendarOutlined, TagOutlined, SearchOutlined,
    DollarOutlined, FlagOutlined, CommentOutlined, PaperClipOutlined, SettingOutlined, ClearOutlined, ArrowDownOutlined, ArrowUpOutlined,
    HomeOutlined, WalletOutlined, ClockCircleOutlined, CheckCircleOutlined, ReloadOutlined, BankOutlined, UserOutlined, PhoneOutlined,
    UnorderedListOutlined, HistoryOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiBar from '@/Components/KpiBar.vue';
import PolizaTransparencia from '@/Components/PolizaTransparencia.vue';
import EstadoCuentaCasa from '@/Components/EstadoCuentaCasa.vue';
import { useAlturaTabla, useAnchoMovil } from '@/composables/useAlturaTabla';
import { moneda } from '@/composables/useFechaHora';
import { coincide } from '@/composables/texto';

const props = defineProps({
    condominios: Array,
    empresaSeleccionada: Number,
    tiposAnotacion: Object,
    casaPropia: Object,
    tab: String,
});

const page = usePage();
const esCondomino = computed(() => page.props.auth?.user?.tipo_usuario === 'CONDOMINO');
const altoPolizas = useAlturaTabla(520);
const anchoMovil = useAnchoMovil(1200); // en el celular la tabla se desliza de lado
const altoDeudores = useAlturaTabla(560);
const altoCuentas = useAlturaTabla(500);

const TABS = ['polizas', 'resultados', 'deudores'];
const empresaId = ref(props.empresaSeleccionada);
const condominioActual = computed(() => props.condominios.find((c) => c.id === empresaId.value));
const tab = ref(TABS.includes(props.tab) ? props.tab : 'polizas');

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const fecha = (f) => (f ? f.split('-').reverse().join('/') : '');
const mes = (f) => (f ? `${MESES[Number(f.slice(5, 7)) - 1]} ${f.slice(0, 4)}` : '—');
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const cambiarCondominio = () => router.get(route('transparencia.index'), { empresa_id: empresaId.value, tab: tab.value });

const cargados = reactive({ polizas: false, resultados: false, deudores: false });
const irA = (clave) => {
    tab.value = clave;
    if (cargados[clave]) return;
    if (clave === 'polizas') cargarPolizas();
    if (clave === 'resultados') cargarResultados();
    if (clave === 'deudores') cargarDeudores();
};

// ===================== PÓLIZAS =====================
const polizas = ref({ data: [], current_page: 1, per_page: 25, total: 0 });
const pendientes = ref(0);
const cargandoPolizas = ref(false);
const vacio = () => ({ fechas: null, tipo_poliza: null, texto: '', estatus: null, anotaciones: null });
const filtros = ref(vacio());
const filtrosActivos = computed(() => Object.values(filtros.value).some((v) => v !== '' && v !== null));

const COLOR_TIPO = { INGRESO: '#1faf5c', EGRESO: '#d9363e', TRASPASO: '#5d7085' };
const opcionesTipo = ['INGRESO', 'EGRESO', 'TRASPASO'].map((v) => ({ value: v, label: v[0] + v.slice(1).toLowerCase() }));
const opcionesEstatus = [
    { label: 'Capturado', value: 'CAPTURADO' }, { label: 'Pendiente', value: 'PENDIENTE' },
    { label: 'Revisado', value: 'REVISADO' }, { label: 'Autorizado', value: 'AUTORIZADO' },
    { label: 'Abonado', value: 'ABONADO' }, { label: 'Liquidado', value: 'LIQUIDADO' },
];
const opcionesAnotaciones = [
    { label: 'Sin resolver', value: 'abiertas' },
    { label: 'Con anotaciones', value: 'con' },
    { label: 'Sin anotaciones', value: 'sin' },
];

const cargarPolizas = async (pagina = 1) => {
    cargandoPolizas.value = true;
    try {
        const { fechas, ...resto } = filtros.value;
        const params = { empresa_id: empresaId.value, page: pagina };
        for (const [k, v] of Object.entries(resto)) if (v !== '' && v !== null) params[k] = v;
        if (fechas?.length === 2) { params.fecha_desde = fechas[0]; params.fecha_hasta = fechas[1]; }
        const { data } = await axios.get(route('transparencia.polizas'), { params });
        polizas.value = data.polizas;
        pendientes.value = data.pendientes;
        cargados.polizas = true;
    } catch (e) {
        message.error(e?.response?.data?.message || 'No se pudieron cargar las pólizas.');
    } finally {
        cargandoPolizas.value = false;
    }
};

let t = null;
const buscar = () => { clearTimeout(t); t = setTimeout(() => cargarPolizas(1), 350); };
const limpiarFiltros = () => { filtros.value = vacio(); cargarPolizas(1); };
const filtrarAbiertas = () => { tab.value = 'polizas'; filtros.value = { ...vacio(), anotaciones: 'abiertas' }; cargarPolizas(1); };

const columnasPolizas = [
    { title: 'Póliza', key: 'poliza', icon: FileTextOutlined, width: 190 },
    { title: 'Tipo', key: 'tipo', icon: TagOutlined, width: 130 },
    { title: 'Concepto / proveedor', key: 'concepto', icon: SearchOutlined, width: 260 },
    { title: 'Monto', key: 'monto', icon: DollarOutlined, width: 140, align: 'right' },
    { title: 'Estatus', key: 'estatus', icon: FlagOutlined, width: 140 },
    { title: 'Anotaciones', key: 'anotaciones', icon: CommentOutlined, width: 170 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 90, align: 'center' },
];
const paginacionPolizas = computed(() => ({
    current: polizas.value.current_page, pageSize: polizas.value.per_page, total: polizas.value.total, showSizeChanger: false,
    showTotal: (total, r) => `${r[0]}–${r[1]} de ${total} pólizas`,
}));

const dialogoPoliza = ref(false);
const polizaSeleccionada = ref(null);
const abrirPoliza = (id) => { polizaSeleccionada.value = id; dialogoPoliza.value = true; };

const alCambiarAnotaciones = ({ polizaId, total, abiertas }) => {
    const fila = polizas.value.data.find((p) => p.id === polizaId);
    if (fila) {
        pendientes.value += abiertas - fila.anotaciones_abiertas;
        fila.anotaciones_total = total;
        fila.anotaciones_abiertas = abiertas;
    }
};

// ===================== ESTADO DE RESULTADOS =====================
const hoy = new Date();
const periodo = ref([iso(new Date(hoy.getFullYear(), hoy.getMonth(), 1)), iso(new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0))]);
const resultados = ref(null);
const cargandoResultados = ref(false);
const rangoActivo = ref('mes');

const rango = (tipo) => {
    const y = hoy.getFullYear(); const m = hoy.getMonth();
    if (tipo === 'mes') periodo.value = [iso(new Date(y, m, 1)), iso(new Date(y, m + 1, 0))];
    if (tipo === 'anterior') periodo.value = [iso(new Date(y, m - 1, 1)), iso(new Date(y, m, 0))];
    if (tipo === 'anio') periodo.value = [iso(new Date(y, 0, 1)), iso(new Date(y, 11, 31))];
    rangoActivo.value = tipo;
    cargarResultados();
};

const cargarResultados = async () => {
    if (!periodo.value?.length) return;
    cargandoResultados.value = true;
    try {
        const { data } = await axios.get(route('reportes.movimientos'), {
            params: { empresa_id: empresaId.value, fecha_desde: periodo.value[0], fecha_hasta: periodo.value[1], vista: 'por_cuenta', tipo_filtro: 'todas' },
        });
        if (!data.success) throw new Error(data.message);
        resultados.value = data;
        cargados.resultados = true;
    } catch (e) {
        message.error(e?.response?.data?.message || e.message || 'No se pudo calcular el estado de resultados.');
    } finally {
        cargandoResultados.value = false;
    }
};

// Árbol de cuentas para a-table (cuenta padre con sus subcuentas con importe).
const arbolCuentas = computed(() => (resultados.value?.cuentas_resultados || [])
    .filter((c) => c.saldo || c.hijas?.some((h) => h.saldo))
    .map((c) => {
        const hijas = (c.hijas || []).filter((h) => h.saldo).map((h) => ({ key: `h${h.id_cuenta}`, nombre: `${h.codigo_cuenta} ${h.nombre_cuenta}`, saldo: h.saldo }));
        return { key: `c${c.id_cuenta}`, nombre: `${c.codigo_cuenta} ${c.nombre_cuenta}`, saldo: c.saldo, padre: true, ...(hijas.length ? { children: hijas } : {}) };
    }));
const kpisResultados = computed(() => {
    const r = resultados.value;
    if (!r) return [];
    return [
        { key: 'i', icon: ArrowDownOutlined, valor: moneda(r.totales_ingresos), label: 'Ingresos', color: '#1faf5c' },
        { key: 'e', icon: ArrowUpOutlined, valor: moneda(r.totales_egresos), label: 'Egresos', color: '#d9363e' },
        { key: 'u', icon: BarChartOutlined, valor: moneda(r.resultado_utilidad), label: r.resultado_utilidad >= 0 ? 'Superávit' : 'Déficit', color: r.resultado_utilidad >= 0 ? '#1a5a96' : '#d9363e' },
    ];
});
const columnasCuentas = [
    { title: 'Cuenta', key: 'nombre', icon: BankOutlined },
    { title: 'Importe', key: 'saldo', icon: DollarOutlined, width: 180, align: 'right' },
];

// ===================== DEUDORES =====================
const deudores = ref(null);
const cargandoDeudores = ref(false);
const casaEstadoCuenta = ref(null);
const filtroDeudor = reactive({ casa: '', vencidos: null, adeudo: null });
const hayFiltroDeudor = computed(() => !!filtroDeudor.casa || !!filtroDeudor.vencidos || !!filtroDeudor.adeudo);
const limpiarDeudores = () => Object.assign(filtroDeudor, { casa: '', vencidos: null, adeudo: null });

const cargarDeudores = async () => {
    cargandoDeudores.value = true;
    try {
        const { data } = await axios.get(route('transparencia.deudores'), { params: { empresa_id: empresaId.value } });
        deudores.value = data;
        cargados.deudores = true;
    } catch (e) {
        message.error(e?.response?.data?.message || 'No se pudo cargar el listado de deudores.');
    } finally {
        cargandoDeudores.value = false;
    }
};

// Filtros en el navegador, sin distinguir mayúsculas ni acentos.
const deudoresFiltrados = computed(() => (deudores.value?.deudores || []).filter((d) => {
    if (!coincide(filtroDeudor.casa, d.identificador, d.responsable, d.telefono)) return false;
    if (filtroDeudor.vencidos === 'pocos' && d.cargos_vencidos >= 3) return false;
    if (filtroDeudor.vencidos === '3' && d.cargos_vencidos < 3) return false;
    if (filtroDeudor.adeudo === 'por_vencer' && !(d.saldo - d.saldo_vencido > 0.009)) return false;
    return true;
}));
const totalFiltrado = computed(() => deudoresFiltrados.value.reduce((s, d) => s + Number(d.saldo_vencido || 0), 0));

const kpisDeudores = computed(() => {
    const x = deudores.value?.totales;
    if (!x) return [];
    return [
        { key: 'd', icon: HomeOutlined, valor: `${x.deudoras} / ${x.casas}`, label: 'Casas con adeudo', color: '#d9363e' },
        { key: 'a', icon: WalletOutlined, valor: moneda(x.adeudo), label: 'Adeudo vencido', color: '#d9363e' },
        { key: 't', icon: ClockCircleOutlined, valor: x.tres_o_mas, label: '3 o más cuotas vencidas', color: '#f0a202' },
        { key: 'c', icon: CheckCircleOutlined, valor: x.casas - x.deudoras, label: 'Al corriente', color: '#1faf5c' },
    ];
});
const columnasDeudores = computed(() => [
    { title: esCondomino.value ? 'Casa' : 'Casa / responsable', key: 'casa', icon: HomeOutlined },
    { title: 'Cuota', key: 'cuota', icon: DollarOutlined, width: 130, align: 'right' },
    { title: 'Adeudo', key: 'adeudo', icon: WalletOutlined, width: 180, align: 'right' },
    { title: 'Cuotas vencidas', key: 'vencidos', icon: ExclamationCircleOutlined, width: 160, align: 'center' },
    { title: 'Historial', key: 'historial', icon: HistoryOutlined, width: 190 },
    ...(!esCondomino.value ? [{ title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 90, align: 'center' }] : []),
]);

onMounted(() => {
    if (!empresaId.value) return;
    // Siempre se cargan las pólizas para el aviso de anotaciones pendientes.
    cargarPolizas();
    if (tab.value !== 'polizas') irA(tab.value);
});
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Transparencia contable" :icon="EyeOutlined">
                <template #subtitulo>
                    {{ condominioActual?.nombre_empresa || 'Sin condominio asignado' }}
                    <template v-if="casaPropia"> · Tu casa: <b>{{ casaPropia.identificador }}</b></template>
                </template>
                <template #extra>
                    <Link v-if="page.props.permisos?.puede_ver_inventario" :href="route('inventario.index')"><a-button size="large"><InboxOutlined /> Inventario</a-button></Link>
                    <a-select v-if="condominios.length > 1" v-model:value="empresaId" size="large" style="min-width: 240px" show-search option-filter-prop="label"
                        :options="condominios.map((c) => ({ value: c.id, label: c.nombre_empresa }))" @change="cambiarCondominio" />
                </template>
            </PageHeader>

            <div v-if="!empresaId" class="vt-card"><a-result status="info" title="No tienes un condominio asignado." /></div>

            <template v-else>
                <div v-if="pendientes" class="vt-alert error">
                    <ExclamationCircleOutlined />
                    <span>Hay <b>{{ pendientes }}</b> anotación(es) sin resolver en pólizas de este condominio.
                        <a-button type="link" size="small" style="padding: 0 4px" @click="filtrarAbiertas">Ver pólizas</a-button></span>
                </div>

                <a-tabs :active-key="tab" class="vt-tabs-form" @change="irA">
                    <!-- ===================== PÓLIZAS ===================== -->
                    <a-tab-pane key="polizas">
                        <template #tab><span class="vt-tab"><FileTextOutlined /> Pólizas</span></template>
                        <div class="vt-card" style="padding: 0; overflow: hidden">
                            <a-table table-layout="fixed" class="vt-table" :columns="columnasPolizas" :data-source="polizas.data" row-key="id" :loading="cargandoPolizas"
                                :scroll="{ x: anchoMovil, y: altoPolizas }" :pagination="paginacionPolizas" :custom-row="(r) => ({ onClick: () => abrirPoliza(r.id), style: 'cursor:pointer' })"
                                :locale="{ emptyText: 'No hay pólizas con esos filtros.' }" @change="(p) => cargarPolizas(p.current)">
                                <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                                <template #bodyCell="{ column, record: p }">
                                    <template v-if="column.key === 'poliza'">
                                        <b class="block">{{ p.folio }}</b>
                                        <span class="vt-small vt-muted"><CalendarOutlined /> {{ fecha(p.fecha_poliza) }}</span>
                                    </template>
                                    <template v-else-if="column.key === 'tipo'">
                                        <span class="vt-tag solido" :style="{ background: COLOR_TIPO[p.tipo_poliza] || '#5d7085' }">{{ p.tipo_poliza }}</span>
                                    </template>
                                    <template v-else-if="column.key === 'concepto'">
                                        <span class="block truncate">{{ p.referencia || p.nota || '—' }}</span>
                                        <span v-if="p.persona" class="vt-small vt-muted block truncate"><UserOutlined /> {{ p.persona }}</span>
                                    </template>
                                    <template v-else-if="column.key === 'monto'">
                                        <b :style="{ color: p.total < 0 ? '#d9363e' : '#15803d' }">{{ moneda(p.total) }}</b>
                                    </template>
                                    <template v-else-if="column.key === 'estatus'">
                                        <span class="vt-tag" style="background: #eef2f7; color: #44566c">{{ p.estatus_texto }}</span>
                                    </template>
                                    <template v-else-if="column.key === 'anotaciones'">
                                        <div class="flex gap-1 flex-wrap">
                                            <a-tooltip v-if="p.anotaciones_abiertas" title="Tiene anotaciones sin resolver">
                                                <span class="vt-tag solido tr-parpadeo" style="background: #d9363e"><ExclamationCircleOutlined /> {{ p.anotaciones_abiertas }}</span>
                                            </a-tooltip>
                                            <a-tooltip v-else-if="p.anotaciones_total" title="Anotaciones resueltas">
                                                <span class="vt-tag" style="background: #e6f7ee; color: #15803d"><CommentOutlined /> {{ p.anotaciones_total }}</span>
                                            </a-tooltip>
                                            <a-tooltip v-if="p.archivos_count" title="Comprobantes">
                                                <span class="vt-tag" style="background: #e8f0fa; color: #1a5a96"><PaperClipOutlined /> {{ p.archivos_count }}</span>
                                            </a-tooltip>
                                        </div>
                                    </template>
                                    <template v-else-if="column.key === 'acciones'">
                                        <a-tooltip title="Ver póliza"><a-button class="vt-ab azul" @click.stop="abrirPoliza(p.id)"><EyeOutlined /></a-button></a-tooltip>
                                    </template>
                                </template>

                                <template #summary>
                                    <a-table-summary fixed="bottom">
                                        <a-table-summary-row class="vt-fsum">
                                            <a-table-summary-cell :index="0">
                                                <a-range-picker v-model:value="filtros.fechas" value-format="YYYY-MM-DD" format="DD/MM/YY" :placeholder="['Desde', 'Hasta']" @change="cargarPolizas(1)" />
                                            </a-table-summary-cell>
                                            <a-table-summary-cell :index="1">
                                                <a-select v-model:value="filtros.tipo_poliza" allow-clear placeholder="Todos" :options="opcionesTipo" @change="cargarPolizas(1)" />
                                            </a-table-summary-cell>
                                            <a-table-summary-cell :index="2">
                                                <a-input v-model:value="filtros.texto" allow-clear placeholder="Folio, concepto o proveedor" @change="buscar"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                            </a-table-summary-cell>
                                            <a-table-summary-cell :index="3"></a-table-summary-cell>
                                            <a-table-summary-cell :index="4">
                                                <a-select v-model:value="filtros.estatus" allow-clear placeholder="Todos" :options="opcionesEstatus" @change="cargarPolizas(1)" />
                                            </a-table-summary-cell>
                                            <a-table-summary-cell :index="5">
                                                <a-select v-model:value="filtros.anotaciones" allow-clear placeholder="Todas" :options="opcionesAnotaciones" @change="cargarPolizas(1)" />
                                            </a-table-summary-cell>
                                            <a-table-summary-cell :index="6" align="center">
                                                <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!filtrosActivos" @click="limpiarFiltros"><ClearOutlined /></a-button></a-tooltip>
                                            </a-table-summary-cell>
                                        </a-table-summary-row>
                                    </a-table-summary>
                                </template>
                            </a-table>
                        </div>
                    </a-tab-pane>

                    <!-- ===================== ESTADO DE RESULTADOS ===================== -->
                    <a-tab-pane key="resultados">
                        <template #tab><span class="vt-tab"><BarChartOutlined /> Estado de resultados</span></template>
                        <div class="vt-card vt-toolbar mb-3">
                            <span class="vt-toolbar-label"><CalendarOutlined /> Periodo</span>
                            <a-range-picker v-model:value="periodo" value-format="YYYY-MM-DD" format="DD/MM/YYYY" :allow-clear="false" @change="rangoActivo = null" />
                            <a-segmented :value="rangoActivo" :options="[{ value: 'mes', label: 'Este mes' }, { value: 'anterior', label: 'Mes anterior' }, { value: 'anio', label: 'Este año' }]" @change="rango" />
                            <a-button type="primary" :loading="cargandoResultados" @click="cargarResultados"><ReloadOutlined /> Consultar</a-button>
                        </div>

                        <template v-if="resultados">
                            <KpiBar :items="kpisResultados" />
                            <div class="vt-card" style="padding: 0; overflow: hidden">
                                <a-table class="vt-table" :columns="columnasCuentas" :data-source="arbolCuentas" :pagination="false" :loading="cargandoResultados"
                                    :scroll="{ y: altoCuentas }" default-expand-all-rows :locale="{ emptyText: 'Sin movimientos en el periodo.' }">
                                    <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                                    <template #bodyCell="{ column, record: c }">
                                        <template v-if="column.key === 'nombre'"><component :is="c.padre ? 'b' : 'span'">{{ c.nombre }}</component></template>
                                        <template v-else-if="column.key === 'saldo'">
                                            <component :is="c.padre ? 'b' : 'span'" :style="{ color: c.saldo < 0 ? '#d9363e' : '#15803d' }">{{ moneda(c.saldo) }}</component>
                                        </template>
                                    </template>
                                </a-table>
                            </div>
                        </template>
                        <div v-else class="vt-card"><a-skeleton v-if="cargandoResultados" active /><div v-else class="vt-empty">Elige el periodo y presiona Consultar.</div></div>
                    </a-tab-pane>

                    <!-- ===================== DEUDORES ===================== -->
                    <a-tab-pane key="deudores">
                        <template #tab><span class="vt-tab"><ExclamationCircleOutlined /> Deudores</span></template>
                        <KpiBar v-if="deudores" :items="kpisDeudores" />
                        <div class="vt-card" style="padding: 0; overflow: hidden">
                            <a-table table-layout="fixed" class="vt-table" :columns="columnasDeudores" :data-source="deudoresFiltrados" row-key="id_casa" :loading="cargandoDeudores"
                                :scroll="{ x: anchoMovil, y: altoDeudores }" :pagination="false"
                                :locale="{ emptyText: deudores ? (hayFiltroDeudor ? 'Ninguna casa coincide con los filtros.' : 'Ninguna casa tiene adeudo. 🎉') : '' }">
                                <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                                <template #bodyCell="{ column, record: d }">
                                    <template v-if="column.key === 'casa'">
                                        <b>{{ d.identificador }}</b>
                                        <span v-if="casaPropia?.id === d.id_casa" class="vt-tag ml-1" style="background: #e8f0fa; color: #1a5a96">Tu casa</span>
                                        <div v-if="!esCondomino" class="vt-small vt-muted truncate">
                                            {{ d.responsable || 'Sin responsable' }}<template v-if="d.telefono"> · <PhoneOutlined /> {{ d.telefono }}</template>
                                        </div>
                                    </template>
                                    <template v-else-if="column.key === 'cuota'">{{ d.cuota !== null ? moneda(d.cuota) : '—' }}</template>
                                    <template v-else-if="column.key === 'adeudo'">
                                        <b style="color: #d9363e">{{ moneda(d.saldo_vencido) }}</b>
                                        <div v-if="d.saldo - d.saldo_vencido > 0.009" class="vt-small vt-muted">+ {{ moneda(d.saldo - d.saldo_vencido) }} por vencer</div>
                                    </template>
                                    <template v-else-if="column.key === 'vencidos'">
                                        <span class="vt-tag solido" :style="{ background: d.cargos_vencidos >= 3 ? '#d9363e' : d.cargos_vencidos ? '#f0a202' : '#94a3b8' }">{{ d.cargos_vencidos }}</span>
                                    </template>
                                    <template v-else-if="column.key === 'historial'">
                                        <div class="vt-small"><span class="vt-muted">Debe desde</span> <b>{{ mes(d.adeudo_desde) }}</b></div>
                                        <div class="vt-small"><span class="vt-muted">Último pago</span> <b>{{ d.ultimo_pago ? fecha(d.ultimo_pago) : 'Sin pagos' }}</b></div>
                                    </template>
                                    <template v-else-if="column.key === 'acciones'">
                                        <a-tooltip title="Estado de cuenta"><a-button class="vt-ab azul" @click="casaEstadoCuenta = d.id_casa"><UnorderedListOutlined /></a-button></a-tooltip>
                                    </template>
                                </template>

                                <template #summary>
                                    <a-table-summary fixed="bottom">
                                        <a-table-summary-row class="vt-fsum">
                                            <a-table-summary-cell :index="0">
                                                <a-input v-model:value="filtroDeudor.casa" allow-clear :placeholder="esCondomino ? 'Casa…' : 'Casa, responsable o teléfono'"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                            </a-table-summary-cell>
                                            <a-table-summary-cell :index="1"></a-table-summary-cell>
                                            <a-table-summary-cell :index="2" align="right">
                                                <a-select v-model:value="filtroDeudor.adeudo" allow-clear placeholder="Todo" style="width: 100%" :options="[{ value: 'por_vencer', label: 'Con cuotas por vencer' }]" />
                                            </a-table-summary-cell>
                                            <a-table-summary-cell :index="3">
                                                <a-select v-model:value="filtroDeudor.vencidos" allow-clear placeholder="Todas" style="width: 100%" :options="[{ label: '1 o 2', value: 'pocos' }, { label: '3 o más', value: '3' }]" />
                                            </a-table-summary-cell>
                                            <a-table-summary-cell :index="4"><span class="vt-small vt-muted">Vencido mostrado: <b style="color: #d9363e">{{ moneda(totalFiltrado) }}</b></span></a-table-summary-cell>
                                            <a-table-summary-cell v-if="!esCondomino" :index="5" align="center">
                                                <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltroDeudor" @click="limpiarDeudores"><ClearOutlined /></a-button></a-tooltip>
                                            </a-table-summary-cell>
                                        </a-table-summary-row>
                                    </a-table-summary>
                                </template>
                            </a-table>
                        </div>
                        <p v-if="casaPropia" class="vt-small vt-muted mt-3">
                            Tu estado de cuenta completo está en <Link :href="route('casas.mi')" style="color: #1a5a96; font-weight: 600">Mi casa</Link>.
                        </p>
                    </a-tab-pane>
                </a-tabs>
            </template>
        </div>

        <PolizaTransparencia v-model:visible="dialogoPoliza" :poliza-id="polizaSeleccionada" :tipos="tiposAnotacion" @cambio="alCambiarAnotaciones" />
        <EstadoCuentaCasa :visible="!!casaEstadoCuenta" :casa-id="casaEstadoCuenta" @update:visible="(v) => { if (!v) casaEstadoCuenta = null; }" />
    </AppLayout>
</template>

<style>
.tr-parpadeo { animation: tr-parpadeo 1.6s ease-in-out infinite; }
@keyframes tr-parpadeo { 50% { opacity: 0.55; } }
</style>
