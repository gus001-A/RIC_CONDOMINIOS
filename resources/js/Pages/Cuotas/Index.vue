<script setup>
import { ref, reactive, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import dayjs from 'dayjs';
import {
    WalletOutlined, FileDoneOutlined, CheckCircleOutlined, HomeOutlined, CalendarOutlined, PlusCircleOutlined, InfoCircleOutlined,
    DollarOutlined, SwapOutlined, ExclamationCircleOutlined, SettingOutlined, SearchOutlined, ClearOutlined, UnorderedListOutlined,
    PhoneOutlined, SaveOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiBar from '@/Components/KpiBar.vue';
import EstadoCuentaCasa from '@/Components/EstadoCuentaCasa.vue';
import { useAlturaTabla, useAnchoMovil } from '@/composables/useAlturaTabla';
import { moneda } from '@/composables/useFechaHora';
import { coincide } from '@/composables/texto';

const props = defineProps({
    condominios: Array,
    empresaSeleccionada: Number,
    casas: Array,
    totales: Object,
    ultimoPeriodo: String,
    formasPago: Object,
});

const empresaId = ref(props.empresaSeleccionada);
const altoTabla = useAlturaTabla(560);
const anchoMovil = useAnchoMovil(1120); // en el celular la tabla se desliza de lado

const fecha = (f) => (f ? f.split('-').reverse().join('/') : '');
const nombreMes = (ym) => dayjs(`${ym}-01`).format('MMMM YYYY');
const hoyIso = () => dayjs().format('YYYY-MM-DD');

// Sugerir el mes siguiente al último generado (o el mes actual).
const siguienteMes = () => (props.ultimoPeriodo ? dayjs(`${props.ultimoPeriodo}-01`).add(1, 'month') : dayjs()).format('YYYY-MM');

const kpis = computed(() => [
    { key: 'c', icon: FileDoneOutlined, valor: moneda(props.totales.cargos), label: 'Cargado', color: '#1a5a96' },
    { key: 'p', icon: CheckCircleOutlined, valor: moneda(props.totales.pagos), label: 'Cobrado', color: '#1faf5c' },
    { key: 's', icon: WalletOutlined, valor: moneda(props.totales.saldo), label: 'Por cobrar', color: '#d9363e' },
    { key: 'm', icon: HomeOutlined, valor: `${props.totales.deudoras} / ${props.totales.casas}`, label: 'Casas morosas (vencido)', color: '#f0a202' },
]);

// ===== Generar cargos =====
const cargosForm = useForm({ empresa_id: props.empresaSeleccionada, periodo: siguienteMes(), dia_vencimiento: 10 });
const generar = () => cargosForm.post(route('cuotas.generar'), { preserveScroll: true, onSuccess: () => (cargosForm.periodo = siguienteMes()) });

// ===== Filtros al pie (uno por columna) =====
const filtros = reactive({ casa: '', cuota: null, saldo: null, vencidos: null });
const hayFiltros = computed(() => Object.values(filtros).some((v) => v !== '' && v !== null));
const limpiar = () => Object.assign(filtros, { casa: '', cuota: null, saldo: null, vencidos: null });

const casasFiltradas = computed(() => props.casas.filter((c) => {
    if (!coincide(filtros.casa, c.identificador, c.responsable, c.telefono)) return false;
    if (filtros.cuota === 'con' && c.cuota === null) return false;
    if (filtros.cuota === 'sin' && c.cuota !== null) return false;
    if (filtros.saldo === 'adeudo' && !(c.saldo > 0.009)) return false;
    if (filtros.saldo === 'corriente' && Math.abs(c.saldo) > 0.009) return false;
    if (filtros.saldo === 'favor' && !(c.saldo < -0.009)) return false;
    if (filtros.vencidos === '0' && c.cargos_vencidos > 0) return false;
    if (filtros.vencidos === 'pocos' && !(c.cargos_vencidos > 0 && c.cargos_vencidos < 3)) return false;
    if (filtros.vencidos === '3' && c.cargos_vencidos < 3) return false;
    return true;
}));
const saldoFiltrado = computed(() => casasFiltradas.value.reduce((s, c) => s + Number(c.saldo || 0), 0));

const columnas = [
    { title: 'Casa / responsable', key: 'casa', icon: HomeOutlined, width: 260 },
    { title: 'Cuota', key: 'cuota', icon: DollarOutlined, width: 140, align: 'right' },
    { title: 'Cargos / pagos', key: 'movs', icon: SwapOutlined, width: 180, align: 'right' },
    { title: 'Saldo', key: 'saldo', icon: WalletOutlined, width: 170, align: 'right' },
    { title: 'Vencidos / último pago', key: 'vencidos', icon: ExclamationCircleOutlined, width: 200 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 130, align: 'center' },
];

// ===== Pagos =====
const dialogoPago = ref(false);
const casaPago = ref(null);
const pagoForm = useForm({ fecha_pago: hoyIso(), monto: null, forma_pago: 'TRANSFERENCIA', referencia: '', id_poliza: null, notas: '' });
const polizasIngreso = ref([]);
const cargandoPolizas = ref(false);

let tp = null;
const buscarPolizas = (q = '') => {
    clearTimeout(tp);
    tp = setTimeout(async () => {
        cargandoPolizas.value = true;
        try {
            const { data } = await axios.get(route('cuotas.polizas-ingreso'), { params: { empresa_id: props.empresaSeleccionada, q } });
            polizasIngreso.value = data;
        } finally {
            cargandoPolizas.value = false;
        }
    }, 250);
};

const abrirPago = (casa) => {
    casaPago.value = casa;
    pagoForm.reset();
    pagoForm.clearErrors();
    pagoForm.fecha_pago = hoyIso();
    pagoForm.monto = casa.saldo > 0 ? Number(casa.saldo) : (casa.cuota ? Number(casa.cuota) : null);
    dialogoPago.value = true;
    buscarPolizas();
};
const guardarPago = () => pagoForm.post(route('cuotas.pagos.store', casaPago.value.id), { preserveScroll: true, onSuccess: () => (dialogoPago.value = false) });

const casaEstadoCuenta = ref(null);
const err = (k) => ({ 'validate-status': pagoForm.errors[k] ? 'error' : '', help: pagoForm.errors[k] });
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Cuotas de mantenimiento" subtitle="Cargos mensuales, pagos y estado de cuenta por casa" :icon="WalletOutlined">
                <template #extra>
                    <a-select v-if="condominios.length > 1" v-model:value="empresaId" size="large" style="min-width: 240px" show-search option-filter-prop="label"
                        :options="condominios.map((c) => ({ value: c.id, label: c.nombre_empresa }))" @change="router.get(route('cuotas.index'), { empresa_id: empresaId })" />
                </template>
            </PageHeader>

            <div v-if="!empresaSeleccionada" class="vt-card"><a-result status="info" title="No tienes un condominio asignado." /></div>

            <template v-else>
                <KpiBar :items="kpis" />

                <!-- Generar cargos -->
                <div class="vt-card vt-toolbar mb-3">
                    <span class="vt-toolbar-label"><CalendarOutlined /> Mes a cobrar</span>
                    <a-date-picker v-model:value="cargosForm.periodo" picker="month" value-format="YYYY-MM" format="MMMM YYYY" :allow-clear="false" style="width: 170px" />
                    <span class="vt-toolbar-label">Vence el día</span>
                    <a-input-number v-model:value="cargosForm.dia_vencimiento" :min="1" :max="28" style="width: 80px" />
                    <a-button type="primary" :loading="cargosForm.processing" :disabled="!cargosForm.periodo" @click="generar"><PlusCircleOutlined /> Generar cargos del mes</a-button>
                    <span class="vt-small vt-muted flex-1" style="min-width: 220px">
                        <InfoCircleOutlined /> Se cobra la cuota de cada casa activa; si ya tiene el cargo de ese mes no se duplica.
                        <template v-if="ultimoPeriodo"> Último generado: <b style="text-transform: capitalize">{{ nombreMes(ultimoPeriodo) }}</b>.</template>
                    </span>
                </div>

                <div class="vt-card" style="padding: 0; overflow: hidden">
                    <a-table table-layout="fixed" class="vt-table" :columns="columnas" :data-source="casasFiltradas" row-key="id" :scroll="{ x: anchoMovil, y: altoTabla }"
                        :pagination="false" :row-class-name="(r) => (r.activo ? '' : 'vt-inactivo')" :locale="{ emptyText: 'No hay casas con esos filtros.' }">
                        <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                        <template #bodyCell="{ column, record: c }">
                            <template v-if="column.key === 'casa'">
                                <b>{{ c.identificador }}</b>
                                <span v-if="!c.activo" class="vt-tag solido ml-1" style="background: #94a3b8">Inactiva</span>
                                <div class="vt-small vt-muted truncate">{{ c.responsable || 'Sin responsable' }}<template v-if="c.telefono"> · <PhoneOutlined /> {{ c.telefono }}</template></div>
                            </template>
                            <template v-else-if="column.key === 'cuota'">
                                <span v-if="c.cuota !== null">{{ moneda(c.cuota) }}</span>
                                <span v-else class="vt-tag" style="background: #fff4d9; color: #a86f00">Sin cuota</span>
                            </template>
                            <template v-else-if="column.key === 'movs'">
                                <div class="vt-small"><span class="vt-muted">Cargos</span> {{ moneda(c.cargos) }}</div>
                                <div class="vt-small" style="color: #15803d"><span class="vt-muted">Pagos</span> {{ moneda(c.pagos) }}</div>
                            </template>
                            <template v-else-if="column.key === 'saldo'">
                                <b :style="{ color: c.saldo > 0.009 ? '#d9363e' : c.saldo < -0.009 ? '#1a5a96' : '#15803d' }">{{ moneda(c.saldo) }}</b>
                                <div v-if="c.saldo < -0.009" class="vt-small" style="color: #1a5a96">Saldo a favor</div>
                            </template>
                            <template v-else-if="column.key === 'vencidos'">
                                <span class="vt-tag solido" :style="{ background: c.cargos_vencidos >= 3 ? '#d9363e' : c.cargos_vencidos ? '#f0a202' : '#1faf5c' }">{{ c.cargos_vencidos }} vencidos</span>
                                <div class="vt-small vt-muted mt-1">Último pago: {{ c.ultimo_pago ? fecha(c.ultimo_pago) : 'sin pagos' }}</div>
                            </template>
                            <template v-else-if="column.key === 'acciones'">
                                <div class="vt-acciones-fila">
                                    <a-tooltip title="Registrar pago"><a-button class="vt-ab verde" @click="abrirPago(c)"><DollarOutlined /></a-button></a-tooltip>
                                    <a-tooltip title="Estado de cuenta"><a-button class="vt-ab azul" @click="casaEstadoCuenta = c.id"><UnorderedListOutlined /></a-button></a-tooltip>
                                </div>
                            </template>
                        </template>

                        <template #summary>
                            <a-table-summary fixed="bottom">
                                <a-table-summary-row class="vt-fsum">
                                    <a-table-summary-cell :index="0">
                                        <a-input v-model:value="filtros.casa" allow-clear placeholder="Casa, responsable o teléfono"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="1">
                                        <a-select v-model:value="filtros.cuota" allow-clear placeholder="Todas" style="width: 100%" :options="[{ value: 'con', label: 'Con cuota' }, { value: 'sin', label: 'Sin cuota' }]" />
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="2" align="right"><span class="vt-small vt-muted">{{ casasFiltradas.length }} casas</span></a-table-summary-cell>
                                    <a-table-summary-cell :index="3">
                                        <a-select v-model:value="filtros.saldo" allow-clear placeholder="Todos" style="width: 100%"
                                            :options="[{ label: 'Con adeudo', value: 'adeudo' }, { label: 'Al corriente', value: 'corriente' }, { label: 'Saldo a favor', value: 'favor' }]" />
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="4">
                                        <a-select v-model:value="filtros.vencidos" allow-clear placeholder="Todos" style="width: 100%"
                                            :options="[{ label: 'Sin vencidos', value: '0' }, { label: '1 o 2', value: 'pocos' }, { label: '3 o más', value: '3' }]" />
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="5" align="center">
                                        <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="limpiar"><ClearOutlined /></a-button></a-tooltip>
                                    </a-table-summary-cell>
                                </a-table-summary-row>
                            </a-table-summary>
                        </template>
                    </a-table>
                    <div class="vt-total-pie">Saldo de las casas mostradas: <b :style="{ color: saldoFiltrado > 0.009 ? '#d9363e' : '#15803d' }">{{ moneda(saldoFiltrado) }}</b></div>
                </div>
            </template>
        </div>

        <!-- Registrar pago -->
        <a-modal v-model:open="dialogoPago" width="600px" centered :confirm-loading="pagoForm.processing" ok-text="Registrar pago" cancel-text="Cancelar" @ok="guardarPago">
            <template #title>
                <div class="vt-modal-title"><span class="vt-head-icon" style="background: linear-gradient(135deg, #138a48, #1faf5c)"><DollarOutlined /></span>
                    <div>Registrar pago<small v-if="casaPago">Casa {{ casaPago.identificador }}</small></div></div>
            </template>
            <template v-if="casaPago">
                <div class="vt-alert info">
                    <InfoCircleOutlined />
                    <span>Saldo actual: <b :style="{ color: casaPago.saldo > 0 ? '#d9363e' : '#15803d' }">{{ moneda(casaPago.saldo) }}</b>
                        <template v-if="casaPago.cuota"> · Cuota mensual {{ moneda(casaPago.cuota) }}</template></span>
                </div>
                <a-form layout="vertical">
                    <div class="vt-form-grid">
                        <a-form-item label="Fecha de pago" required v-bind="err('fecha_pago')">
                            <a-date-picker v-model:value="pagoForm.fecha_pago" value-format="YYYY-MM-DD" format="DD/MM/YYYY" style="width: 100%" :allow-clear="false" />
                        </a-form-item>
                        <a-form-item label="Monto" required v-bind="err('monto')">
                            <a-input-number v-model:value="pagoForm.monto" :min="0.01" :precision="2" prefix="$" style="width: 100%" />
                        </a-form-item>
                        <a-form-item label="Forma de pago">
                            <a-select v-model:value="pagoForm.forma_pago" :options="Object.entries(formasPago).map(([value, label]) => ({ value, label }))" />
                        </a-form-item>
                        <a-form-item label="Referencia"><a-input v-model:value="pagoForm.referencia" :maxlength="100" placeholder="Folio de transferencia, recibo…" /></a-form-item>
                        <a-form-item class="full" label="Póliza de ingreso (opcional)" v-bind="err('id_poliza')">
                            <a-select v-model:value="pagoForm.id_poliza" show-search allow-clear :filter-option="false" :loading="cargandoPolizas"
                                placeholder="Ligar con la póliza donde se registró el ingreso" :not-found-content="cargandoPolizas ? 'Buscando…' : 'Sin pólizas de ingreso'"
                                :options="polizasIngreso.map((p) => ({ value: p.id, label: p.etiqueta }))" @search="buscarPolizas" />
                        </a-form-item>
                        <a-form-item class="full" label="Notas" style="margin-bottom: 0"><a-textarea v-model:value="pagoForm.notas" :rows="2" /></a-form-item>
                    </div>
                </a-form>
            </template>
        </a-modal>

        <EstadoCuentaCasa :visible="!!casaEstadoCuenta" :casa-id="casaEstadoCuenta" puede-cancelar
            @update:visible="(v) => { if (!v) casaEstadoCuenta = null; }" @cambio="router.reload({ only: ['casas', 'totales'] })" />
    </AppLayout>
</template>
