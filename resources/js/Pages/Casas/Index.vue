<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    HomeOutlined, PlusOutlined, TeamOutlined, EnvironmentOutlined, WarningOutlined, SearchOutlined, ClearOutlined, EyeOutlined,
    EditOutlined, UserOutlined, PhoneOutlined, FlagOutlined, HistoryOutlined, SettingOutlined, CameraOutlined, ApartmentOutlined,
    CheckCircleOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiBar from '@/Components/KpiBar.vue';
import Registro from '@/Components/Registro.vue';
import { useAlturaTabla, useAnchoMovil } from '@/composables/useAlturaTabla';

const props = defineProps({
    casas: Object,
    stats: Object,
    condominios: Array,
    estatusOcupacion: Object,
    filtros: Object,
});

const page = usePage();
const permisos = computed(() => page.props.permisos || {});
const altoTabla = useAlturaTabla(470);
const anchoMovil = useAnchoMovil(1300); // en el celular la tabla se desliza de lado

const COLOR_OCUPACION = { HABITADA: '#1faf5c', DESHABITADA: '#94a3b8', 'EN RENTA': '#1a5a96', 'EN VENTA': '#6e4bc2', 'EN CONSTRUCCION': '#f0a202' };
const TIPO_CONDOMINO = { PROPIETARIO: 'Propietario', INQUILINO: 'Inquilino', FAMILIAR: 'Familiar' };

// ===== Filtros al pie: UNO por columna (se aplican en el servidor) =====
const f = reactive({
    direccion: props.filtros?.direccion || props.filtros?.search || '',
    id_empresa: props.filtros?.id_empresa ? Number(props.filtros.id_empresa) : null,
    responsable: props.filtros?.responsable || '',
    gps: props.filtros?.gps || null,
    estatus_ocupacion: props.filtros?.estatus_ocupacion || null,
    activo: props.filtros?.activo ?? null,
});
const hayFiltros = computed(() => Object.values(f).some((v) => v !== '' && v !== null));
const cargando = ref(false);

const aplicar = (pagina = 1) => {
    const params = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
    if (pagina > 1) params.page = pagina;
    cargando.value = true;
    router.get(route('casas.index'), params, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (cargando.value = false) });
};
let t = null;
const aplicarLuego = () => { clearTimeout(t); t = setTimeout(aplicar, 350); };
const limpiar = () => { Object.assign(f, { direccion: '', id_empresa: null, responsable: '', gps: null, estatus_ocupacion: null, activo: null }); aplicar(); };

const columnas = computed(() => [
    { title: 'Casa', key: 'casa', icon: HomeOutlined, ellipsis: true, width: 240 },
    { title: 'Responsable / contacto', key: 'responsable', icon: UserOutlined, width: 250 },
    { title: 'Expediente', key: 'expediente', icon: ApartmentOutlined, width: 170 },
    { title: 'Ocupación', key: 'ocupacion', icon: FlagOutlined, width: 150 },
    { title: 'Registro', key: 'traza', icon: HistoryOutlined, width: 200 },
    { title: 'Estado', key: 'estado', icon: CheckCircleOutlined, width: 120, align: 'center' },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 110, align: 'center' },
]);

const kpis = computed(() => [
    { key: 't', icon: HomeOutlined, valor: props.stats.total, label: 'Casas', color: '#1a5a96' },
    { key: 'h', icon: TeamOutlined, valor: props.stats.habitadas, label: 'Habitadas', color: '#1faf5c' },
    { key: 'g', icon: EnvironmentOutlined, valor: props.stats.con_gps, label: 'Con ubicación GPS', color: '#1597a5' },
    { key: 's', icon: WarningOutlined, valor: props.stats.sin_responsable, label: 'Sin responsable', color: '#f0a202' },
]);

const paginacion = computed(() => ({
    current: props.casas.current_page,
    pageSize: props.casas.per_page,
    total: props.casas.total,
    showSizeChanger: false,
    showTotal: (total, rango) => `${rango[0]}–${rango[1]} de ${total} casas`,
}));
const opcionesOcupacion = computed(() => Object.entries(props.estatusOcupacion).map(([value, label]) => ({ value, label })));
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Casas y condóminos" subtitle="Propiedades de cada condominio, sus residentes, fotos y ubicación" :icon="HomeOutlined">
                <template #extra>
                    <Link v-if="permisos.puede_gestionar_casas" :href="route('casas.create')">
                        <a-button type="primary" size="large"><PlusOutlined /> Nueva casa</a-button>
                    </Link>
                </template>
            </PageHeader>

            <KpiBar :items="kpis" />

            <div class="vt-card" style="padding: 0; overflow: hidden">
                <a-table table-layout="fixed" :scroll="{ x: anchoMovil, y: altoTabla }" class="vt-table" :columns="columnas" :data-source="casas.data" row-key="id"
                    :loading="cargando" :pagination="paginacion" :row-class-name="(r) => (r.activo ? '' : 'vt-inactivo')"
                    :locale="{ emptyText: hayFiltros ? 'No hay casas con estos filtros.' : (condominios.length ? 'Aún no hay casas registradas.' : 'No tienes condominios asignados.') }"
                    @change="(p) => aplicar(p.current)">
                    <template #headerCell="{ column }">
                        <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
                    </template>

                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'casa'">
                            <Link :href="route('casas.show', record.id)" class="flex items-center gap-3 min-w-0">
                                <span class="cs-ic" :class="{ off: !record.activo }"><HomeOutlined /></span>
                                <div class="min-w-0">
                                    <b class="block truncate">{{ record.identificador }}</b>
                                    <span class="vt-small vt-muted block truncate">{{ record.direccion_corta || 'Sin dirección' }}</span>
                                    <span v-if="condominios.length > 1" class="vt-small block truncate" style="color: #1597a5">{{ record.condominio?.nombre_empresa }}</span>
                                </div>
                            </Link>
                        </template>

                        <template v-else-if="column.key === 'responsable'">
                            <template v-if="record.responsable">
                                <b class="block truncate">{{ record.responsable.nombre_completo }}</b>
                                <span class="vt-small vt-muted block truncate">
                                    {{ TIPO_CONDOMINO[record.responsable.tipo] || record.responsable.tipo }}
                                    <template v-if="record.responsable.telefono"> · <PhoneOutlined /> {{ record.responsable.telefono }}</template>
                                </span>
                            </template>
                            <span v-else class="vt-tag" style="background: #fff4d9; color: #a86f00"><WarningOutlined /> Sin responsable</span>
                        </template>

                        <template v-else-if="column.key === 'expediente'">
                            <div class="flex gap-1 flex-wrap">
                                <a-tooltip title="Residentes activos"><span class="vt-tag" style="background: #eef2f7; color: #44566c"><TeamOutlined /> {{ record.condominos_count }}</span></a-tooltip>
                                <a-tooltip title="Fotos"><span class="vt-tag" style="background: #efeafa; color: #6e4bc2"><CameraOutlined /> {{ record.fotos_count }}</span></a-tooltip>
                                <a-tooltip :title="record.latitud !== null ? 'Con ubicación GPS' : 'Sin GPS'">
                                    <span class="vt-tag" :style="record.latitud !== null ? 'background:#e1f5f7;color:#0e7d89' : 'background:#f1f5f9;color:#94a3b8'"><EnvironmentOutlined /></span>
                                </a-tooltip>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'ocupacion'">
                            <span class="vt-tag solido" :style="{ background: COLOR_OCUPACION[record.estatus_ocupacion] || '#94a3b8' }">{{ estatusOcupacion[record.estatus_ocupacion] }}</span>
                        </template>

                        <template v-else-if="column.key === 'traza'">
                            <Registro :usuario="record.modifico?.nombre_usuario || record.registro?.nombre_usuario" :fecha="record.updated_at" />
                        </template>

                        <template v-else-if="column.key === 'estado'">
                            <span class="vt-tag solido" :style="{ background: record.activo ? '#1faf5c' : '#94a3b8' }">{{ record.activo ? 'Activa' : 'Inactiva' }}</span>
                        </template>

                        <template v-else-if="column.key === 'acciones'">
                            <div class="vt-acciones-fila">
                                <a-tooltip title="Ver ficha"><Link :href="route('casas.show', record.id)"><a-button class="vt-ab azul"><EyeOutlined /></a-button></Link></a-tooltip>
                                <a-tooltip v-if="permisos.puede_gestionar_casas" title="Editar"><Link :href="route('casas.edit', record.id)"><a-button class="vt-ab naranja"><EditOutlined /></a-button></Link></a-tooltip>
                            </div>
                        </template>
                    </template>

                    <!-- Un filtro por columna, al pie -->
                    <template #summary>
                        <a-table-summary fixed="bottom">
                            <a-table-summary-row class="vt-fsum">
                                <a-table-summary-cell :index="0">
                                    <div class="flex gap-1">
                                        <a-input v-model:value="f.direccion" placeholder="Casa, calle, manzana…" allow-clear @change="aplicarLuego"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                        <a-select v-if="condominios.length > 1" v-model:value="f.id_empresa" allow-clear placeholder="Condominio" style="min-width: 140px"
                                            show-search option-filter-prop="label" :options="condominios.map((c) => ({ value: c.id, label: c.nombre_empresa }))" @change="aplicar()" />
                                    </div>
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="1">
                                    <a-input v-model:value="f.responsable" placeholder="Nombre, teléfono o correo" allow-clear @change="aplicarLuego"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="2">
                                    <a-select v-model:value="f.gps" allow-clear placeholder="GPS" :options="[{ value: 'con', label: 'Con GPS' }, { value: 'sin', label: 'Sin GPS' }]" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="3">
                                    <a-select v-model:value="f.estatus_ocupacion" allow-clear placeholder="Todas" :options="opcionesOcupacion" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="4"></a-table-summary-cell>
                                <a-table-summary-cell :index="5">
                                    <a-select v-model:value="f.activo" allow-clear placeholder="Todas" :options="[{ value: 'true', label: 'Activas' }, { value: 'false', label: 'Inactivas' }]" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="6" align="center">
                                    <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!hayFiltros" @click="limpiar"><ClearOutlined /></a-button></a-tooltip>
                                </a-table-summary-cell>
                            </a-table-summary-row>
                        </a-table-summary>
                    </template>
                </a-table>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.cs-ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 17px; color: #fff; background: linear-gradient(135deg, #1a3a5c, #1a5a96); flex: none; }
.cs-ic.off { background: linear-gradient(135deg, #94a3b8, #64748b); }
</style>
