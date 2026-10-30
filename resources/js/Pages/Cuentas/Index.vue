<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { message } from 'ant-design-vue';
import {
    BankOutlined, PlusOutlined, NumberOutlined, ApartmentOutlined, PartitionOutlined, SwapOutlined, TagOutlined, SettingOutlined,
    EditOutlined, SearchOutlined, ClearOutlined, ArrowUpOutlined, ArrowDownOutlined, StopOutlined, ReloadOutlined, FundOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiBar from '@/Components/KpiBar.vue';
import { confirmar } from '@/composables/confirmar';
import { normalizar } from '@/composables/texto';
import { useAlturaTabla, useAnchoMovil } from '@/composables/useAlturaTabla';

const props = defineProps({
    cuentas: { type: Object, default: () => ({ data: [], total: 0 }) },
    stats: { type: Object, default: null },
    empresas: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
    empresa_seleccionada: { type: [Number, String], default: null },
});

const page = usePage();
const permisos = computed(() => page.props.permisos || {});
const altoTabla = useAlturaTabla(470);
const anchoMovil = useAnchoMovil(1150);

const empresaId = ref(props.empresa_seleccionada ? Number(props.empresa_seleccionada) : null);

// ===== Filtros al pie: uno por columna (se aplican en el servidor) =====
const f = reactive({
    codigo_cuenta: props.filtros?.codigo_cuenta || '',
    nombre_cuenta: props.filtros?.nombre_cuenta || '',
    cuenta_madre: props.filtros?.cuenta_madre || '',
    nivel: props.filtros?.nivel ? Number(props.filtros.nivel) : null,
    naturaleza: props.filtros?.naturaleza || null,
});
const hayFiltros = computed(() => Object.values(f).some((v) => v !== '' && v !== null));
const cargando = ref(false);
const aplicar = (pagina = 1) => {
    if (!empresaId.value) return;
    const params = { empresa_id: empresaId.value, ...Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null)) };
    if (pagina > 1) params.page = pagina;
    cargando.value = true;
    router.get(route('cuentas.index'), params, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (cargando.value = false) });
};
let t = null;
const aplicarLuego = () => { clearTimeout(t); t = setTimeout(aplicar, 350); };
const limpiar = () => { Object.assign(f, { codigo_cuenta: '', nombre_cuenta: '', cuenta_madre: '', nivel: null, naturaleza: null }); aplicar(); };

const columnas = [
    { title: 'Código', key: 'codigo', icon: NumberOutlined, width: 170 },
    { title: 'Cuenta', key: 'cuenta', icon: BankOutlined, width: 300 },
    { title: 'Cuenta madre', key: 'madre', icon: PartitionOutlined, width: 230 },
    { title: 'Nivel', key: 'nivel', icon: ApartmentOutlined, width: 110, align: 'center' },
    { title: 'Naturaleza', key: 'naturaleza', icon: SwapOutlined, width: 140 },
    { title: 'Tipo', key: 'tipo', icon: TagOutlined, width: 140 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 100, align: 'center' },
];

const kpis = computed(() => [
    { key: 't', icon: BankOutlined, valor: props.stats?.total ?? 0, label: 'Cuentas activas', color: '#1a5a96' },
    { key: 'd', icon: ArrowUpOutlined, valor: props.stats?.deudoras ?? 0, label: 'Deudoras', color: '#1597a5' },
    { key: 'a', icon: ArrowDownOutlined, valor: props.stats?.acreedoras ?? 0, label: 'Acreedoras', color: '#6e4bc2' },
]);

const paginacion = computed(() => ({
    current: props.cuentas.current_page || 1,
    pageSize: props.cuentas.per_page || 100,
    total: props.cuentas.total || 0,
    showSizeChanger: false,
    showTotal: (total, rango) => `${rango[0]}–${rango[1]} de ${total} cuentas`,
}));

const tipoCuenta = (c) => (c.es_cuenta_resultados ? { label: 'De resultados', color: '#6e4bc2', bg: '#efeafa' }
    : Number(c.fondeo_c) ? { label: 'Fondeadora', color: '#0e7d89', bg: '#e1f5f7' }
        : { label: 'Normal', color: '#5d7085', bg: '#eef2f7' });

const cambiarEmpresa = () => {
    Object.assign(f, { codigo_cuenta: '', nombre_cuenta: '', cuenta_madre: '', nivel: null, naturaleza: null });
    aplicar();
};

// ===== Cuentas inactivas (modal con restaurar) =====
const dialogoInactivas = ref(false);
const inactivas = ref([]);
const cargandoInactivas = ref(false);
const buscarInactiva = ref('');
const inactivasFiltradas = computed(() => {
    const q = normalizar(buscarInactiva.value);
    return q ? inactivas.value.filter((c) => normalizar(`${c.codigo_cuenta} ${c.nombre_cuenta} ${c.cuenta_madre || ''}`).includes(q)) : inactivas.value;
});
const abrirInactivas = async () => {
    dialogoInactivas.value = true;
    cargandoInactivas.value = true;
    buscarInactiva.value = '';
    try {
        const { data } = await axios.get(route('cuentas.inactivas'), { params: { empresa_id: empresaId.value } });
        inactivas.value = (data.data || []).sort((a, b) => String(a.codigo_cuenta).localeCompare(String(b.codigo_cuenta), 'es', { numeric: true }));
    } catch {
        message.error('No se pudieron cargar las cuentas inactivas.');
    } finally {
        cargandoInactivas.value = false;
    }
};
const restaurar = async (c) => {
    if (!(await confirmar({ tipo: 'success', titulo: '¿Restaurar la cuenta?', texto: `“${c.codigo_cuenta} · ${c.nombre_cuenta}” volverá a estar activa.`, confirmar: 'Sí, restaurar' }))) return;
    router.post(route('cuentas.restaurar', c.id_cuenta), {}, {
        preserveScroll: true,
        onSuccess: (p) => {
            if (!p.props.flash?.error && !p.props.flash?.warning) message.success('Cuenta restaurada.');
            inactivas.value = inactivas.value.filter((x) => x.id_cuenta !== c.id_cuenta);
        },
    });
};
const columnasInactivas = [
    { title: 'Código', dataIndex: 'codigo_cuenta', key: 'codigo', width: 130 },
    { title: 'Cuenta', dataIndex: 'nombre_cuenta', key: 'nombre', ellipsis: true },
    { title: 'Cuenta madre', dataIndex: 'cuenta_madre', key: 'madre', ellipsis: true, width: 200 },
    { title: '', key: 'acc', width: 60, align: 'center' },
];
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Catálogo de cuentas" subtitle="Cuentas contables del condominio: niveles, naturaleza y cuentas de resultados" :icon="BankOutlined">
                <template #extra>
                    <a-select v-if="empresas.length > 1" v-model:value="empresaId" size="large" style="min-width: 240px" show-search option-filter-prop="label"
                        :options="empresas.map((e) => ({ value: e.id, label: e.nombre_empresa }))" @change="cambiarEmpresa" />
                    <a-button v-if="empresaId" size="large" @click="abrirInactivas"><StopOutlined /> Inactivas</a-button>
                    <Link v-if="permisos.puede_crear_cuentas && empresaId" :href="route('cuentas.create', { empresa_id: empresaId })">
                        <a-button type="primary" size="large"><PlusOutlined /> Nueva cuenta</a-button>
                    </Link>
                </template>
            </PageHeader>

            <div v-if="!empresas.length" class="vt-alert"><ApartmentOutlined /> <span>No tienes condominios asignados. Pide a un administrador que te asigne uno en Usuarios.</span></div>

            <template v-else>
                <KpiBar :items="kpis" />

                <div class="vt-card" style="padding: 0; overflow: hidden">
                    <a-table table-layout="fixed" :scroll="{ x: anchoMovil, y: altoTabla }" class="vt-table" :columns="columnas" :data-source="cuentas.data" row-key="id_cuenta"
                        :loading="cargando" :pagination="paginacion"
                        :locale="{ emptyText: hayFiltros ? 'No hay cuentas con estos filtros.' : 'Este condominio aún no tiene cuentas.' }"
                        @change="(p) => aplicar(p.current)">
                        <template #headerCell="{ column }">
                            <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
                        </template>

                        <template #bodyCell="{ column, record: c }">
                            <template v-if="column.key === 'codigo'">
                                <span class="cu-codigo" :style="{ paddingLeft: Math.max(0, (c.nivel || 1) - 1) * 12 + 'px' }">
                                    <span class="cu-punto" :style="{ background: c.Naturaleza === 'ACREEDORA' ? '#6e4bc2' : '#1597a5' }"></span>{{ c.codigo_cuenta }}
                                </span>
                            </template>

                            <template v-else-if="column.key === 'cuenta'">
                                <component :is="permisos.puede_editar_cuentas ? Link : 'div'" :href="permisos.puede_editar_cuentas ? route('cuentas.edit', c.id_cuenta) : undefined" class="flex items-center gap-3 min-w-0">
                                    <span class="cs-ic cu-ic" :class="{ res: c.es_cuenta_resultados }"><FundOutlined v-if="c.es_cuenta_resultados" /><BankOutlined v-else /></span>
                                    <div class="min-w-0">
                                        <b class="block truncate">{{ c.nombre_cuenta }}</b>
                                        <span v-if="c.descripcion" class="vt-small vt-muted block truncate">{{ c.descripcion }}</span>
                                    </div>
                                </component>
                            </template>

                            <template v-else-if="column.key === 'madre'">
                                <span v-if="c.cuenta_madre && c.cuenta_madre !== 'SIN MADRE'" class="block truncate"><PartitionOutlined class="vt-muted" /> {{ c.cuenta_madre }}</span>
                                <span v-else class="vt-small vt-muted">Cuenta principal</span>
                            </template>

                            <template v-else-if="column.key === 'nivel'">
                                <span class="vt-tag" style="background: #e6eef8; color: #1a5a96">Nivel {{ c.nivel }}</span>
                            </template>

                            <template v-else-if="column.key === 'naturaleza'">
                                <span class="vt-tag solido" :style="{ background: c.Naturaleza === 'ACREEDORA' ? '#6e4bc2' : '#1597a5' }">
                                    <ArrowDownOutlined v-if="c.Naturaleza === 'ACREEDORA'" /><ArrowUpOutlined v-else /> {{ c.Naturaleza === 'ACREEDORA' ? 'Acreedora' : 'Deudora' }}
                                </span>
                            </template>

                            <template v-else-if="column.key === 'tipo'">
                                <span class="vt-tag" :style="{ background: tipoCuenta(c).bg, color: tipoCuenta(c).color }">{{ tipoCuenta(c).label }}</span>
                            </template>

                            <template v-else-if="column.key === 'acciones'">
                                <div class="vt-acciones-fila">
                                    <a-tooltip v-if="permisos.puede_editar_cuentas" title="Editar"><Link :href="route('cuentas.edit', c.id_cuenta)"><a-button class="vt-ab naranja"><EditOutlined /></a-button></Link></a-tooltip>
                                </div>
                            </template>
                        </template>

                        <template #summary>
                            <a-table-summary fixed="bottom">
                                <a-table-summary-row class="vt-fsum">
                                    <a-table-summary-cell :index="0">
                                        <a-input v-model:value="f.codigo_cuenta" placeholder="Código" allow-clear @change="aplicarLuego"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="1">
                                        <a-input v-model:value="f.nombre_cuenta" placeholder="Nombre de la cuenta" allow-clear @change="aplicarLuego"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="2">
                                        <a-input v-model:value="f.cuenta_madre" placeholder="Cuenta madre" allow-clear @change="aplicarLuego"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="3">
                                        <a-select v-model:value="f.nivel" allow-clear placeholder="Todos" style="width: 100%" :options="[1, 2, 3, 4, 5].map((n) => ({ value: n, label: `Nivel ${n}` }))" @change="aplicar()" />
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="4">
                                        <a-select v-model:value="f.naturaleza" allow-clear placeholder="Todas" style="width: 100%" :options="[{ value: 'DEUDORA', label: 'Deudora' }, { value: 'ACREEDORA', label: 'Acreedora' }]" @change="aplicar()" />
                                    </a-table-summary-cell>
                                    <a-table-summary-cell :index="5"></a-table-summary-cell>
                                    <a-table-summary-cell :index="6" align="center">
                                        <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="limpiar"><ClearOutlined /></a-button></a-tooltip>
                                    </a-table-summary-cell>
                                </a-table-summary-row>
                            </a-table-summary>
                        </template>
                    </a-table>
                </div>
            </template>
        </div>

        <!-- Cuentas inactivas -->
        <a-modal v-model:open="dialogoInactivas" centered width="760px" :footer="null">
            <template #title><span class="flex items-center gap-2"><StopOutlined /> Cuentas inactivas</span></template>
            <a-input v-model:value="buscarInactiva" allow-clear placeholder="Buscar por código, nombre o cuenta madre" class="mb-3"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
            <a-table size="small" class="vt-table" :columns="columnasInactivas" :data-source="inactivasFiltradas" row-key="id_cuenta" :loading="cargandoInactivas"
                :pagination="{ pageSize: 8, showSizeChanger: false }" :locale="{ emptyText: 'No hay cuentas inactivas.' }">
                <template #bodyCell="{ column, record: c }">
                    <template v-if="column.key === 'acc'">
                        <a-tooltip v-if="permisos.puede_editar_cuentas" title="Restaurar"><a-button class="vt-ab verde" @click="restaurar(c)"><ReloadOutlined /></a-button></a-tooltip>
                    </template>
                </template>
            </a-table>
        </a-modal>
    </AppLayout>
</template>

<style>
.cs-ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 17px; color: #fff; background: linear-gradient(135deg, #1a3a5c, #1a5a96); flex: none; }
.cu-ic.res { background: linear-gradient(135deg, #4b2f99, #6e4bc2); }
.cu-codigo { display: inline-flex; align-items: center; gap: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: var(--vt-navy); }
.cu-punto { width: 8px; height: 8px; border-radius: 50%; flex: none; }
</style>
