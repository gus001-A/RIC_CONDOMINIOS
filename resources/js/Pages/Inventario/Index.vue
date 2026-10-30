<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { message } from 'ant-design-vue';
import {
    InboxOutlined, PlusOutlined, WarningOutlined, CloseCircleOutlined, WalletOutlined, AppstoreOutlined, TagsOutlined, BarChartOutlined,
    DollarOutlined, HistoryOutlined, SettingOutlined, SearchOutlined, ClearOutlined, PlusCircleOutlined, MinusCircleOutlined, SlidersOutlined,
    EditOutlined, SaveOutlined, CheckOutlined, EnvironmentOutlined, CalendarOutlined, SwapOutlined, FileTextOutlined, UserOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiBar from '@/Components/KpiBar.vue';
import Registro from '@/Components/Registro.vue';
import { useAlturaTabla, useAnchoMovil } from '@/composables/useAlturaTabla';
import { fechaHora, moneda } from '@/composables/useFechaHora';
import { coincide } from '@/composables/texto';

const props = defineProps({
    condominios: Array,
    categorias: Object,
    unidades: Object,
    tiposMovimiento: Object,
});

const page = usePage();
const notify = { success: (t) => message.success(t), error: (t) => message.error(t) };
const altoTabla = useAlturaTabla(520);
const anchoMovil = useAnchoMovil(1180); // en el celular la tabla se desliza de lado
const opciones = (obj) => Object.entries(obj).map(([value, label]) => ({ value, label }));
const permisos = computed(() => page.props.permisos || {});
const cantidad = (n) => Number(n || 0).toLocaleString('es-MX', { maximumFractionDigits: 3 });
const errorDe = (e) => Object.fromEntries(Object.entries(e?.response?.data?.errors || {}).map(([k, v]) => [k, v[0]]));

// ===== Existencias (se consultan periódicamente) =====
const empresaId = ref(props.condominios[0]?.id ?? null);
const insumos = ref([]);
const totales = ref({ insumos: 0, bajo_minimo: 0, agotados: 0, valor: 0 });
const cargando = ref(false);
const inactivos = ref(false);

const cargar = async () => {
    if (!empresaId.value) return;
    cargando.value = true;
    try {
        const { data } = await axios.get(route('inventario.lista'), { params: { id_empresa: empresaId.value, inactivos: inactivos.value ? 1 : 0 } });
        insumos.value = data.insumos;
        totales.value = data.totales;
    } catch (e) { /* se reintenta en el siguiente ciclo */ } finally {
        cargando.value = false;
    }
};

// Filtros al pie, uno por columna; sin distinguir mayúsculas ni acentos.
const filtros = reactive({ buscar: '', categoria: null, stock: null });
const filtrosActivos = computed(() => Object.values(filtros).some(Boolean));
const limpiar = () => Object.assign(filtros, { buscar: '', categoria: null, stock: null });
const filtrados = computed(() => insumos.value.filter((i) => {
    if (!coincide(filtros.buscar, i.nombre, i.clave)) return false;
    if (filtros.categoria && i.categoria !== filtros.categoria) return false;
    if (filtros.stock === 'bajo' && !i.bajo_minimo) return false;
    if (filtros.stock === 'agotado' && i.existencia > 0) return false;
    if (filtros.stock === 'con' && i.existencia <= 0) return false;
    return true;
}));

// ===== Insumo =====
const dialogoInsumo = ref(false);
const insumoEditando = ref(null);
const insumo = reactive({});
const errores = ref({});
const enviando = ref(false);

const abrirInsumo = (i = null) => {
    insumoEditando.value = i;
    errores.value = {};
    Object.assign(insumo, {
        nombre: i?.nombre ?? '', clave: i?.clave ?? '', categoria: i?.categoria ?? 'ELECTRICO', unidad: i?.unidad ?? 'PIEZA',
        stock_minimo: Number(i?.stock_minimo ?? 0), ubicacion: i?.ubicacion ?? '', notas: i?.notas ?? '', activo: i?.activo ?? true,
    });
    dialogoInsumo.value = true;
};

const guardarInsumo = async () => {
    enviando.value = true;
    errores.value = {};
    try {
        const url = insumoEditando.value ? route('inventario.update', insumoEditando.value.id) : route('inventario.store');
        await axios.post(url, { ...insumo, id_empresa: empresaId.value });
        dialogoInsumo.value = false;
        notify.success('Insumo guardado.');
        cargar();
    } catch (e) {
        errores.value = errorDe(e);
        if (!Object.keys(errores.value).length) notify.error(e?.response?.data?.message || 'No se pudo guardar.');
    } finally {
        enviando.value = false;
    }
};

// ===== Movimientos =====
const dialogoMovimiento = ref(false);
const mov = reactive({ insumo: null, tipo: 'ENTRADA', cantidad: null, costo_unitario: null, proveedor: '', referencia: '', motivo: '', id_poliza: null, id_incidencia: null });
const referencias = ref({ polizas: [], incidencias: [] });

const abrirMovimiento = async (i, tipo) => {
    errores.value = {};
    Object.assign(mov, {
        insumo: i, tipo, cantidad: tipo === 'AJUSTE' ? Number(i.existencia) : null, costo_unitario: tipo === 'ENTRADA' ? (Number(i.costo_promedio) || null) : null,
        proveedor: '', referencia: '', motivo: '', id_poliza: null, id_incidencia: null,
    });
    dialogoMovimiento.value = true;
    if (tipo !== 'AJUSTE') {
        const { data } = await axios.get(route('inventario.referencias'), { params: { id_empresa: empresaId.value } });
        referencias.value = data;
    }
};

const guardarMovimiento = async () => {
    enviando.value = true;
    errores.value = {};
    try {
        const { insumo: i, ...datos } = mov;
        const { data } = await axios.post(route('inventario.movimiento', i.id), datos);
        dialogoMovimiento.value = false;
        notify.success(data.sin_cambio ? 'La existencia ya coincidía; no se registró ajuste.' : `${props.tiposMovimiento[mov.tipo]} registrada. Existencia: ${cantidad(data.insumo.existencia)}`);
        cargar();
    } catch (e) {
        errores.value = errorDe(e);
        if (!Object.keys(errores.value).length) notify.error(e?.response?.data?.message || 'No se pudo registrar.');
    } finally {
        enviando.value = false;
    }
};

// ===== Kárdex =====
const dialogoKardex = ref(false);
const kardex = ref(null);
const columnas = [
    { title: 'Insumo', key: 'insumo', icon: InboxOutlined, width: 240 },
    { title: 'Categoría / ubicación', key: 'categoria', icon: TagsOutlined, width: 190 },
    { title: 'Existencia', key: 'existencia', icon: BarChartOutlined, width: 170 },
    { title: 'Costo / valor', key: 'valor', icon: DollarOutlined, width: 150, align: 'right' },
    { title: 'Último movimiento', key: 'traza', icon: HistoryOutlined, width: 180 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 200, align: 'center' },
];
const kpis = computed(() => [
    { key: 'i', icon: AppstoreOutlined, valor: totales.value.insumos, label: 'Insumos', color: '#1a5a96' },
    { key: 'bajo', icon: WarningOutlined, valor: totales.value.bajo_minimo, label: 'En o bajo el mínimo', color: '#f0a202', activo: filtros.stock === 'bajo' },
    { key: 'agotado', icon: CloseCircleOutlined, valor: totales.value.agotados, label: 'Agotados', color: '#d9363e', activo: filtros.stock === 'agotado' },
    { key: 'v', icon: WalletOutlined, valor: moneda(totales.value.valor), label: 'Valor del almacén', color: '#1faf5c' },
]);
const elegirKpi = (k) => { if (['bajo', 'agotado'].includes(k)) filtros.stock = filtros.stock === k ? null : k; };
const COLOR_MOV = { ENTRADA: '#1faf5c', SALIDA: '#d9363e', AJUSTE: '#1a5a96' };
const columnasKardex = [
    { title: 'Fecha', key: 'fecha', icon: CalendarOutlined, width: 150 },
    { title: 'Tipo', key: 'tipo', icon: SwapOutlined, width: 110 },
    { title: 'Cantidad', key: 'cantidad', icon: BarChartOutlined, width: 110, align: 'right' },
    { title: 'Existencia', key: 'existencia', icon: InboxOutlined, width: 110, align: 'right' },
    { title: 'Costo u.', key: 'costo', icon: DollarOutlined, width: 110, align: 'right' },
    { title: 'Detalle', key: 'detalle', icon: FileTextOutlined, width: 220 },
    { title: 'Usuario', key: 'usuario', icon: UserOutlined, width: 130 },
];

const abrirKardex = async (i) => {
    kardex.value = null;
    dialogoKardex.value = true;
    const { data } = await axios.get(route('inventario.kardex', i.id));
    kardex.value = data;
};

let timer = null;
onMounted(() => {
    cargar();
    timer = setInterval(() => { if (!document.hidden) cargar(); }, 20000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>
<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Inventario de insumos" subtitle="Existencias al día según las compras y el uso registrado · se actualiza solo" :icon="InboxOutlined">
                <template #extra>
                    <a-select v-if="condominios.length > 1" v-model:value="empresaId" size="large" style="min-width: 240px" show-search option-filter-prop="label"
                        :options="condominios.map((c) => ({ value: c.id, label: c.nombre_empresa }))" @change="cargar" />
                    <a-button v-if="permisos.puede_gestionar_inventario" type="primary" size="large" @click="abrirInsumo()"><PlusOutlined /> Nuevo insumo</a-button>
                </template>
            </PageHeader>

            <div v-if="!empresaId" class="vt-card"><a-result status="info" title="No tienes un condominio asignado." /></div>

            <template v-else>
                <KpiBar :items="kpis" clicables @elegir="elegirKpi" />

                <div class="vt-card vt-toolbar mb-3">
                    <a-checkbox v-model:checked="inactivos" @change="cargar">Ver inactivos</a-checkbox>
                </div>

                <div class="vt-card" style="padding: 0; overflow: hidden">
                    <a-table table-layout="fixed" class="vt-table" :columns="columnas" :data-source="filtrados" row-key="id" :loading="cargando && !insumos.length"
                        :scroll="{ x: anchoMovil, y: altoTabla }" :pagination="false" :row-class-name="(r) => (r.activo ? '' : 'vt-inactivo')"
                        :locale="{ emptyText: insumos.length ? 'Ningún insumo con esos filtros.' : 'Aún no hay insumos registrados.' }">
                        <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                        <template #bodyCell="{ column, record: i }">
                            <template v-if="column.key === 'insumo'">
                                <b class="block truncate" style="color: var(--vt-navy)">{{ i.nombre }}</b>
                                <span class="vt-small vt-muted">{{ i.clave || 'Sin clave' }}</span>
                            </template>
                            <template v-else-if="column.key === 'categoria'">
                                <span class="vt-tag" style="background: #eef2f7; color: #44566c">{{ categorias[i.categoria] }}</span>
                                <div v-if="i.ubicacion" class="vt-small vt-muted mt-1 truncate"><EnvironmentOutlined /> {{ i.ubicacion }}</div>
                            </template>
                            <template v-else-if="column.key === 'existencia'">
                                <span class="inv-ex" :class="i.existencia <= 0 ? 'agotado' : (i.bajo_minimo ? 'bajo' : '')">
                                    {{ cantidad(i.existencia) }} <small>{{ unidades[i.unidad]?.toLowerCase() }}</small>
                                </span>
                                <div class="vt-small vt-muted">Mínimo {{ cantidad(i.stock_minimo) }}</div>
                            </template>
                            <template v-else-if="column.key === 'valor'">
                                <b>{{ moneda(Math.max(0, i.existencia) * i.costo_promedio) }}</b>
                                <div class="vt-small vt-muted">{{ moneda(i.costo_promedio) }} c/u</div>
                            </template>
                            <template v-else-if="column.key === 'traza'"><Registro :usuario="i.modifico?.nombre_usuario" :fecha="i.ultimo_movimiento" /></template>
                            <template v-else-if="column.key === 'acciones'">
                                <div class="vt-acciones-fila">
                                    <template v-if="i.activo">
                                        <a-tooltip v-if="permisos.puede_gestionar_inventario" title="Entrada (compra)"><a-button class="vt-ab verde" @click="abrirMovimiento(i, 'ENTRADA')"><PlusCircleOutlined /></a-button></a-tooltip>
                                        <a-tooltip v-if="permisos.puede_usar_inventario" title="Salida (uso)"><a-button class="vt-ab rojo" :disabled="i.existencia <= 0" @click="abrirMovimiento(i, 'SALIDA')"><MinusCircleOutlined /></a-button></a-tooltip>
                                        <a-tooltip v-if="permisos.puede_gestionar_inventario" title="Ajuste por conteo"><a-button class="vt-ab teal" @click="abrirMovimiento(i, 'AJUSTE')"><SlidersOutlined /></a-button></a-tooltip>
                                    </template>
                                    <a-tooltip title="Kárdex"><a-button class="vt-ab azul" @click="abrirKardex(i)"><HistoryOutlined /></a-button></a-tooltip>
                                    <a-tooltip v-if="permisos.puede_gestionar_inventario" title="Editar"><a-button class="vt-ab naranja" @click="abrirInsumo(i)"><EditOutlined /></a-button></a-tooltip>
                                </div>
                            </template>
                        </template>
                        <template #summary>
                            <a-table-summary fixed="bottom">
                                <a-table-summary-row class="vt-fsum">
                                    <a-table-summary-cell :index="0">
                                        <a-input v-model:value="filtros.buscar" allow-clear placeholder="Nombre o clave"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="1">
                                        <a-select v-model:value="filtros.categoria" allow-clear placeholder="Todas" style="width: 100%" :options="opciones(categorias)" />
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="2">
                                        <a-select v-model:value="filtros.stock" allow-clear placeholder="Todas" style="width: 100%"
                                            :options="[{ value: 'bajo', label: 'En o bajo el mínimo' }, { value: 'agotado', label: 'Agotados' }, { value: 'con', label: 'Con existencia' }]" />
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="3"></a-table-summary-cell>
                                    <a-table-summary-cell :index="4"><span class="vt-small vt-muted">{{ filtrados.length }} insumos</span></a-table-summary-cell>
                                    <a-table-summary-cell :index="5" align="center">
                                        <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!filtrosActivos" @click="limpiar"><ClearOutlined /></a-button></a-tooltip>
                                    </a-table-summary-cell>
                                </a-table-summary-row>
                            </a-table-summary>
                        </template>
                    </a-table>
                </div>
            </template>
        </div>

        <!-- ================= Insumo ================= -->
        <a-modal v-model:open="dialogoInsumo" width="640px" centered :confirm-loading="enviando" ok-text="Guardar" cancel-text="Cancelar" @ok="guardarInsumo">
            <template #title>
                <div class="vt-modal-title"><span class="vt-head-icon"><component :is="insumoEditando ? EditOutlined : PlusOutlined" /></span>
                    <div>{{ insumoEditando ? 'Editar insumo' : 'Nuevo insumo' }}<small v-if="insumoEditando">{{ insumoEditando.nombre }}</small></div></div>
            </template>
            <a-form layout="vertical">
                <div class="vt-form-grid">
                    <a-form-item class="full" label="Nombre" required :validate-status="errores.nombre ? 'error' : ''" :help="errores.nombre">
                        <a-input v-model:value="insumo.nombre" :maxlength="150" placeholder="Ej. Foco LED 9W luz blanca" />
                    </a-form-item>
                    <a-form-item label="Clave" :validate-status="errores.clave ? 'error' : ''" :help="errores.clave"><a-input v-model:value="insumo.clave" :maxlength="30" /></a-form-item>
                    <a-form-item label="Categoría"><a-select v-model:value="insumo.categoria" :options="opciones(categorias)" /></a-form-item>
                    <a-form-item label="Unidad"><a-select v-model:value="insumo.unidad" :options="opciones(unidades)" /></a-form-item>
                    <a-form-item label="Stock mínimo"><a-input-number v-model:value="insumo.stock_minimo" :min="0" :step="1" style="width: 100%" /></a-form-item>
                    <a-form-item class="full" label="Ubicación"><a-input v-model:value="insumo.ubicacion" :maxlength="100" placeholder="Bodega, caseta, cuarto de máquinas…" /></a-form-item>
                    <a-form-item class="full" label="Notas"><a-textarea v-model:value="insumo.notas" :rows="2" :maxlength="1000" /></a-form-item>
                </div>
                <a-checkbox v-if="insumoEditando" v-model:checked="insumo.activo">Activo</a-checkbox>
                <div v-else class="vt-alert info mb-0"><WarningOutlined /> La existencia empieza en cero: regístrala con una entrada (compra) o un ajuste por conteo.</div>
            </a-form>
        </a-modal>

        <!-- ================= Movimiento ================= -->
        <a-modal v-model:open="dialogoMovimiento" width="600px" centered :confirm-loading="enviando" ok-text="Registrar" cancel-text="Cancelar" @ok="guardarMovimiento">
            <template #title>
                <div v-if="mov.insumo" class="vt-modal-title">
                    <span class="vt-head-icon" :style="{ background: COLOR_MOV[mov.tipo] }"><component :is="mov.tipo === 'ENTRADA' ? PlusCircleOutlined : mov.tipo === 'SALIDA' ? MinusCircleOutlined : SlidersOutlined" /></span>
                    <div>{{ tiposMovimiento[mov.tipo] }}<small>{{ mov.insumo.nombre }}</small></div>
                </div>
            </template>
            <template v-if="mov.insumo">
                <div class="vt-alert info">
                    <InboxOutlined />
                    <span>Existencia actual: <b>{{ cantidad(mov.insumo.existencia) }} {{ unidades[mov.insumo.unidad]?.toLowerCase() }}</b> · costo promedio {{ moneda(mov.insumo.costo_promedio) }}</span>
                </div>
                <a-form layout="vertical">
                    <div class="vt-form-grid">
                        <a-form-item :class="{ full: mov.tipo !== 'ENTRADA' }" :label="mov.tipo === 'AJUSTE' ? 'Existencia real contada' : 'Cantidad'" required
                            :validate-status="errores.cantidad ? 'error' : ''"
                            :help="errores.cantidad || (mov.tipo === 'AJUSTE' && mov.cantidad !== null ? `Diferencia: ${cantidad(mov.cantidad - mov.insumo.existencia)}` : '')">
                            <a-input-number v-model:value="mov.cantidad" :min="mov.tipo === 'AJUSTE' ? 0 : 0.001" :step="1" style="width: 100%" />
                        </a-form-item>
                        <a-form-item v-if="mov.tipo === 'ENTRADA'" label="Costo unitario" required :validate-status="errores.costo_unitario ? 'error' : ''" :help="errores.costo_unitario">
                            <a-input-number v-model:value="mov.costo_unitario" :min="0" :precision="2" prefix="$" style="width: 100%" />
                        </a-form-item>
                        <template v-if="mov.tipo === 'ENTRADA'">
                            <a-form-item label="Proveedor"><a-input v-model:value="mov.proveedor" :maxlength="150" /></a-form-item>
                            <a-form-item label="Factura / nota"><a-input v-model:value="mov.referencia" :maxlength="100" /></a-form-item>
                            <a-form-item class="full" label="Póliza de egreso (opcional)" :validate-status="errores.id_poliza ? 'error' : ''" :help="errores.id_poliza">
                                <a-select v-model:value="mov.id_poliza" allow-clear show-search option-filter-prop="label" placeholder="Sin ligar"
                                    :options="referencias.polizas.map((p) => ({ value: p.id, label: p.etiqueta }))" />
                            </a-form-item>
                        </template>
                        <a-form-item v-if="mov.tipo === 'SALIDA'" class="full" label="Incidencia (opcional)">
                            <a-select v-model:value="mov.id_incidencia" allow-clear show-search option-filter-prop="label" placeholder="No es de una incidencia"
                                :options="referencias.incidencias.map((x) => ({ value: x.id, label: x.etiqueta }))" />
                        </a-form-item>
                        <a-form-item class="full" :label="mov.tipo === 'ENTRADA' ? 'Notas' : (mov.tipo === 'AJUSTE' ? 'Motivo del ajuste' : '¿En qué se usó?')" :required="mov.tipo !== 'ENTRADA'"
                            :validate-status="errores.motivo ? 'error' : ''" :help="errores.motivo" style="margin-bottom: 0">
                            <a-input v-model:value="mov.motivo" :maxlength="255" :placeholder="mov.tipo === 'SALIDA' ? 'Ej. Cambio de focos en caseta' : ''" />
                        </a-form-item>
                    </div>
                </a-form>
            </template>
        </a-modal>

        <!-- ================= Kárdex ================= -->
        <a-modal v-model:open="dialogoKardex" width="1000px" centered :footer="null">
            <template #title>
                <div class="vt-modal-title"><span class="vt-head-icon"><HistoryOutlined /></span><div>Kárdex<small v-if="kardex">{{ kardex.insumo.nombre }}</small></div></div>
            </template>
            <a-spin :spinning="!kardex">
                <div class="vt-card" style="padding: 0; overflow: hidden; min-height: 120px">
                    <a-table v-if="kardex" class="vt-table" size="small" :columns="columnasKardex" :data-source="kardex.movimientos" row-key="id" :pagination="false" :scroll="{ y: '55vh' }"
                        :locale="{ emptyText: 'Sin movimientos.' }">
                        <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                        <template #bodyCell="{ column, record: m }">
                            <template v-if="column.key === 'fecha'"><span class="vt-nowrap">{{ fechaHora(m.created_at) }}</span></template>
                            <template v-else-if="column.key === 'tipo'"><span class="vt-tag solido" :style="{ background: COLOR_MOV[m.tipo] }">{{ m.tipo }}</span></template>
                            <template v-else-if="column.key === 'cantidad'"><b :style="{ color: m.cantidad < 0 ? '#d9363e' : '#15803d' }">{{ m.cantidad > 0 ? '+' : '' }}{{ cantidad(m.cantidad) }}</b></template>
                            <template v-else-if="column.key === 'existencia'">{{ cantidad(m.existencia_resultante) }}</template>
                            <template v-else-if="column.key === 'costo'">{{ m.costo_unitario !== null ? moneda(m.costo_unitario) : '' }}</template>
                            <template v-else-if="column.key === 'detalle'">
                                {{ m.motivo }}
                                <div v-if="m.proveedor || m.referencia" class="vt-small vt-muted">{{ [m.proveedor, m.referencia].filter(Boolean).join(' · ') }}</div>
                                <Link v-if="m.incidencia" :href="route('incidencias.show', m.id_incidencia)" style="color: #1a5a96; font-weight: 600">{{ m.incidencia.folio }}</Link>
                                <span v-if="m.poliza" class="vt-small" style="color: #1a5a96"> · Póliza {{ m.poliza.folio }}</span>
                            </template>
                            <template v-else-if="column.key === 'usuario'"><b>{{ m.usuario?.nombre_usuario }}</b></template>
                        </template>
                    </a-table>
                </div>
            </a-spin>
        </a-modal>
    </AppLayout>
</template>

<style>
.inv-ex { font-weight: 700; color: var(--vt-navy); font-size: 15px; }
.inv-ex small { font-weight: 500; color: var(--vt-muted); font-size: 12px; }
.inv-ex.bajo { color: #a86f00; }
.inv-ex.agotado { color: #d9363e; }
</style>
