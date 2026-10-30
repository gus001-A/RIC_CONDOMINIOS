<script setup>
/**
 * Estado de cuenta de cuotas de una casa (cargos, pagos y saldo corrido).
 * Las cancelaciones no borran nada: el movimiento queda tachado con su motivo.
 */
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { message } from 'ant-design-vue';
import { WalletOutlined, CalendarOutlined, FileTextOutlined, ArrowUpOutlined, ArrowDownOutlined, DollarOutlined, HistoryOutlined, StopOutlined } from '@ant-design/icons-vue';
import KpiBar from './KpiBar.vue';
import Registro from './Registro.vue';
import { moneda } from '@/composables/useFechaHora';

const props = defineProps({
    visible: Boolean,
    casaId: { type: Number, default: null },
    puedeCancelar: { type: Boolean, default: false },
});
const emit = defineEmits(['update:visible', 'cambio']);

const cargando = ref(false);
const datos = ref(null);

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const fecha = (f) => (f ? f.split('-').reverse().join('/') : '');
const mes = (f) => (f ? `${MESES[Number(f.slice(5, 7)) - 1]} ${f.slice(0, 4)}` : '');

const cargar = async () => {
    if (!props.casaId) return;
    cargando.value = true;
    try {
        const { data } = await axios.get(route('cuotas.estado-cuenta', props.casaId));
        datos.value = data;
    } catch (e) {
        message.error(e?.response?.data?.message || 'No se pudo cargar el estado de cuenta.');
        emit('update:visible', false);
    } finally {
        cargando.value = false;
    }
};

const kpis = computed(() => {
    const r = datos.value?.resumen;
    if (!r) return [];
    return [
        { key: 'c', icon: ArrowUpOutlined, valor: moneda(r.cargos), label: 'Cargos', color: '#1a5a96' },
        { key: 'p', icon: ArrowDownOutlined, valor: moneda(r.pagos), label: 'Pagos', color: '#1faf5c' },
        { key: 's', icon: DollarOutlined, valor: moneda(r.saldo), label: r.saldo > 0.009 ? 'Saldo pendiente' : 'Al corriente', color: r.saldo > 0.009 ? '#d9363e' : '#1faf5c' },
        { key: 'v', icon: CalendarOutlined, valor: r.cargos_vencidos, label: r.cargos_vencidos && r.adeudo_desde ? `Vencidos · desde ${mes(r.adeudo_desde)}` : 'Cargos vencidos', color: '#f0a202' },
    ];
});

const columnas = computed(() => [
    { title: 'Fecha', key: 'fecha', icon: CalendarOutlined, width: 105 },
    { title: 'Concepto', key: 'concepto', icon: FileTextOutlined, width: 220 },
    { title: 'Cargo', key: 'cargo', icon: ArrowUpOutlined, width: 115, align: 'right' },
    { title: 'Pago', key: 'abono', icon: ArrowDownOutlined, width: 115, align: 'right' },
    { title: 'Saldo', key: 'saldo', icon: DollarOutlined, width: 120, align: 'right' },
    { title: 'Registro', key: 'traza', icon: HistoryOutlined, width: 180 },
    ...(props.puedeCancelar ? [{ title: '', key: 'acc', width: 60, align: 'center' }] : []),
]);

// Cancelación con motivo
const cancelando = ref(null);
const motivo = ref('');
const procesando = ref(false);

const cancelar = (m) => { cancelando.value = m; motivo.value = ''; };
const confirmarCancelacion = () => {
    const m = cancelando.value;
    procesando.value = true;
    axios.post(route('cuotas.cancelar', { tipo: m.tipo === 'PAGO' ? 'pago' : 'cargo', id: m.id }), { motivo: motivo.value })
        .then(() => {
            message.success('Movimiento cancelado.');
            cancelando.value = null;
            cargar();
            emit('cambio');
        })
        .catch((e) => message.error(e?.response?.data?.message || 'No se pudo cancelar.'))
        .finally(() => { procesando.value = false; });
};

watch(() => [props.visible, props.casaId], ([v]) => {
    if (v) { cancelando.value = null; datos.value = null; cargar(); }
}, { immediate: true });
</script>

<template>
    <a-modal :open="visible" :footer="null" width="980px" centered destroy-on-close @update:open="emit('update:visible', $event)">
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon"><WalletOutlined /></span>
                <div>Estado de cuenta<small v-if="datos">Casa {{ datos.casa.identificador }}</small></div>
            </div>
        </template>

        <a-spin :spinning="cargando">
            <div style="min-height: 160px">
                <template v-if="datos">
                    <KpiBar :items="kpis" />

                    <a-modal :open="!!cancelando" :title="null" :footer="null" width="460px" centered @update:open="(v) => !v && (cancelando = null)">
                        <template v-if="cancelando">
                            <div class="vt-modal-title mb-3">
                                <span class="vt-head-icon" style="background: linear-gradient(135deg, #b42318, #d9363e)"><StopOutlined /></span>
                                <div>Cancelar {{ cancelando.tipo === 'PAGO' ? 'pago' : 'cargo' }}<small>{{ fecha(cancelando.fecha) }} · {{ moneda(cancelando.cargo || cancelando.abono) }}</small></div>
                            </div>
                            <p class="vt-small vt-muted mb-2">No se borra: queda tachado en el estado de cuenta con el motivo y quién lo canceló.</p>
                            <a-textarea v-model:value="motivo" :rows="3" :maxlength="255" show-count placeholder="Motivo de la cancelación" />
                            <div class="flex justify-end gap-2 mt-4">
                                <a-button @click="cancelando = null">No, regresar</a-button>
                                <a-button type="primary" danger :loading="procesando" :disabled="!motivo.trim()" @click="confirmarCancelacion">Sí, cancelar</a-button>
                            </div>
                        </template>
                    </a-modal>

                    <div class="vt-card" style="padding: 0; overflow: hidden">
                        <a-table class="vt-table" size="small" :columns="columnas" :data-source="datos.movimientos" :row-key="(m) => m.tipo + m.id"
                            :pagination="false" :scroll="{ y: '50vh' }" :row-class-name="(m) => (m.cancelado ? 'ec-cancelado' : '')"
                            :locale="{ emptyText: 'Sin movimientos.' }">
                            <template #headerCell="{ column }">
                                <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
                            </template>
                            <template #bodyCell="{ column, record: m }">
                                <template v-if="column.key === 'fecha'"><span class="vt-nowrap">{{ fecha(m.fecha) }}</span></template>
                                <template v-else-if="column.key === 'concepto'">
                                    <span class="ec-tachable">{{ m.concepto }}</span>
                                    <div v-if="m.cancelado" class="vt-small" style="color: #d9363e">Cancelado: {{ m.motivo_cancelacion }}</div>
                                </template>
                                <template v-else-if="column.key === 'cargo'"><span class="ec-tachable">{{ m.cargo ? moneda(m.cargo) : '' }}</span></template>
                                <template v-else-if="column.key === 'abono'"><span class="ec-tachable" style="color: #15803d">{{ m.abono ? moneda(m.abono) : '' }}</span></template>
                                <template v-else-if="column.key === 'saldo'"><b>{{ moneda(m.saldo) }}</b></template>
                                <template v-else-if="column.key === 'traza'">
                                    <Registro :usuario="m.registro" :fecha="m.created_at" />
                                    <Registro v-if="m.modifico && m.updated_at !== m.created_at" etiqueta="Modificó" :usuario="m.modifico" :fecha="m.updated_at" />
                                </template>
                                <template v-else-if="column.key === 'acc'">
                                    <a-tooltip v-if="!m.cancelado" :title="`Cancelar ${m.tipo === 'PAGO' ? 'pago' : 'cargo'}`">
                                        <a-button class="vt-ab rojo" size="small" @click="cancelar(m)"><StopOutlined /></a-button>
                                    </a-tooltip>
                                </template>
                            </template>
                        </a-table>
                    </div>
                </template>
            </div>
        </a-spin>
    </a-modal>
</template>

<style>
.ec-cancelado td { background: #fafbfc !important; color: #94a3b8; }
.ec-cancelado .ec-tachable { text-decoration: line-through; }
</style>
