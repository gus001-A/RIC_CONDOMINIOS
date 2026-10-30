<script setup>
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    ToolOutlined, PlusOutlined, FileSearchOutlined, TagsOutlined, ThunderboltOutlined, FlagOutlined, UserOutlined, TeamOutlined,
    DollarOutlined, SettingOutlined, SearchOutlined, ClearOutlined, EyeOutlined, EnvironmentOutlined, PictureOutlined, ClockCircleOutlined,
    SendOutlined, HomeOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiBar from '@/Components/KpiBar.vue';
import Registro from '@/Components/Registro.vue';
import MapaGps from '@/Components/MapaGps.vue';
import SelectorArchivos from '@/Components/SelectorArchivos.vue';
import { useAlturaTabla, useAnchoMovil } from '@/composables/useAlturaTabla';
import { fechaHora, moneda } from '@/composables/useFechaHora';
import { colorEstatus, colorPrioridad } from '@/composables/useIncidencias';

const props = defineProps({
    incidencias: Object,
    porEstatus: Object,
    condominios: Array,
    personalMantenimiento: Array,
    centros: Object,
    catalogos: Object,
    filtros: Object,
});

const page = usePage();
const permisos = computed(() => page.props.permisos || {});
const esCondomino = computed(() => page.props.auth?.user?.tipo_usuario === 'CONDOMINO');
const altoTabla = useAlturaTabla(560);
const anchoMovil = useAnchoMovil(1320); // en el celular la tabla se desliza de lado
const opciones = (obj) => Object.entries(obj).map(([value, label]) => ({ value, label }));

// ===== Filtros (query string, como el resto de RIC) =====
const vacio = () => ({ id_empresa: null, estatus: null, categoria: null, prioridad: null, tipo: null, id_usuario_asignado: null, buscar: '', mias: false, ver_cerradas: false });
const f = props.filtros || {};
const filtros = ref({
    ...vacio(),
    ...f,
    buscar: f.buscar || f.folio || '',
    id_empresa: f.id_empresa ? Number(f.id_empresa) : null,
    id_usuario_asignado: f.id_usuario_asignado ? Number(f.id_usuario_asignado) : null,
    mias: ['1', 'true', true].includes(f.mias),
    ver_cerradas: ['1', 'true', true].includes(f.ver_cerradas),
});
delete filtros.value.folio;
const filtrosActivos = computed(() => Object.values(filtros.value).some((v) => v !== '' && v !== null && v !== false && v !== undefined));
const cargando = ref(false);

const aplicar = (pagina = 1) => {
    const params = {};
    for (const [k, v] of Object.entries(filtros.value)) {
        if (v === true) params[k] = 1;
        else if (v !== '' && v !== null && v !== false && v !== undefined) params[k] = v;
    }
    if (pagina > 1) params.page = pagina;
    cargando.value = true;
    router.get(route('incidencias.index'), params, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (cargando.value = false) });
};
let t = null;
const aplicarConRetraso = () => { clearTimeout(t); t = setTimeout(aplicar, 350); };
const limpiar = () => { filtros.value = vacio(); aplicar(); };

// Indicadores por estatus (clic = filtrar)
const ICONO_ESTATUS = { PENDIENTE: ClockCircleOutlined, 'EN PROCESO': ToolOutlined, ATENDIDA: FlagOutlined, SUPERVISADA: EyeOutlined, CANCELADA: ClearOutlined };
const kpis = computed(() => Object.entries(props.catalogos.estatus).map(([clave, label]) => ({
    key: clave, icon: ICONO_ESTATUS[clave] || FlagOutlined, valor: props.porEstatus[clave] || 0, label, color: colorEstatus(clave), activo: filtros.value.estatus === clave,
})));
const filtrarEstatus = (clave) => { filtros.value.estatus = filtros.value.estatus === clave ? null : clave; aplicar(); };

const columnas = computed(() => [
    { title: 'Incidencia', key: 'incidencia', icon: FileSearchOutlined, width: 260 },
    { title: 'Categoría', key: 'categoria', icon: TagsOutlined, width: 170 },
    { title: 'Prioridad', key: 'prioridad', icon: ThunderboltOutlined, width: 125 },
    { title: 'Estatus', key: 'estatus', icon: FlagOutlined, width: 140 },
    { title: 'Reportó', key: 'reporto', icon: UserOutlined, width: 190 },
    { title: 'Asignado', key: 'asignado', icon: TeamOutlined, width: 165 },
    { title: 'Gastos / fotos', key: 'gastos', icon: DollarOutlined, width: 130 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 90, align: 'center' },
]);
const paginacion = computed(() => ({
    current: props.incidencias.current_page, pageSize: props.incidencias.per_page, total: props.incidencias.total, showSizeChanger: false,
    showTotal: (total, r) => `${r[0]}–${r[1]} de ${total} reportes`,
}));
const ver = (id) => router.visit(route('incidencias.show', id));

// ===== Reportar =====
const dialogoReporte = ref(false);
const conMapa = ref(false);
const fotos = ref([]);
const reporte = useForm({
    id_empresa: props.condominios.length === 1 ? props.condominios[0].id : null,
    tipo: 'INCIDENCIA', prioridad: 'MEDIA', categoria: null, titulo: '', descripcion: '', ubicacion: '', latitud: null, longitud: null, fotos: [],
});
const errorFotos = computed(() => Object.entries(reporte.errors).find(([k]) => k.startsWith('fotos'))?.[1]);
const centroReporte = computed(() => props.centros?.[reporte.id_empresa || props.condominios[0]?.id] || null);
const err = (k) => ({ 'validate-status': reporte.errors[k] ? 'error' : '', help: reporte.errors[k] });

const abrirReporte = () => {
    reporte.reset();
    reporte.clearErrors();
    conMapa.value = false;
    fotos.value = [];
    dialogoReporte.value = true;
};
const enviarReporte = () => {
    reporte.transform((d) => ({ ...d, fotos: fotos.value }))
        .post(route('incidencias.store'), { forceFormData: true, onSuccess: () => (dialogoReporte.value = false) });
};
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Incidencias y mantenimiento" subtitle="Desperfectos y sugerencias de las zonas comunes, con su seguimiento" :icon="ToolOutlined">
                <template #extra>
                    <a-button v-if="permisos.puede_reportar_incidencias" type="primary" size="large" @click="abrirReporte"><PlusOutlined /> Reportar</a-button>
                </template>
            </PageHeader>

            <KpiBar :items="kpis" clicables @elegir="filtrarEstatus" />

            <div class="vt-card vt-toolbar mb-3">
                <a-segmented v-model:value="filtros.tipo" :options="[{ value: null, label: 'Todo' }, ...opciones(catalogos.tipos)]" @change="aplicar()" />
                <a-checkbox v-model:checked="filtros.mias" @change="aplicar()">{{ permisos.puede_atender_incidencias ? 'Asignadas a mí / mis reportes' : 'Mis reportes' }}</a-checkbox>
                <a-checkbox v-if="!filtros.estatus" v-model:checked="filtros.ver_cerradas" @change="aplicar()">Incluir cerradas</a-checkbox>
                <span class="flex-1"></span>
                <a-select v-if="condominios.length > 1" v-model:value="filtros.id_empresa" allow-clear placeholder="Todos los condominios" style="min-width: 220px"
                    show-search option-filter-prop="label" :options="condominios.map((c) => ({ value: c.id, label: c.nombre_empresa }))" @change="aplicar()" />
            </div>

            <div class="vt-card" style="padding: 0; overflow: hidden">
                <a-table table-layout="fixed" class="vt-table" :columns="columnas" :data-source="incidencias.data" row-key="id" :loading="cargando"
                    :scroll="{ x: anchoMovil, y: altoTabla }" :pagination="paginacion" :custom-row="(r) => ({ onClick: () => ver(r.id), style: 'cursor:pointer' })"
                    :locale="{ emptyText: 'No hay reportes con esos filtros.' }" @change="(p) => aplicar(p.current)">
                    <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                    <template #bodyCell="{ column, record: i }">
                        <template v-if="column.key === 'incidencia'">
                            <div class="flex items-center gap-2 min-w-0">
                                <b class="vt-nowrap" style="color: #1a5a96">{{ i.folio }}</b>
                                <span class="vt-small vt-muted">{{ i.tipo === 'SUGERENCIA' ? 'Sugerencia' : 'Incidencia' }}</span>
                            </div>
                            <div class="font-semibold truncate" style="color: var(--vt-navy)">{{ i.titulo }}</div>
                            <div class="vt-small vt-muted truncate">
                                <template v-if="i.ubicacion"><EnvironmentOutlined /> {{ i.ubicacion }}</template>
                                <template v-if="condominios.length > 1"> · {{ i.condominio?.nombre_empresa }}</template>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'categoria'">
                            <span class="vt-tag" style="background: #eef2f7; color: #44566c">{{ catalogos.categorias[i.categoria] }}</span>
                        </template>
                        <template v-else-if="column.key === 'prioridad'">
                            <span class="vt-tag solido" :style="{ background: colorPrioridad(i.prioridad) }">{{ catalogos.prioridades[i.prioridad] }}</span>
                        </template>
                        <template v-else-if="column.key === 'estatus'">
                            <span class="vt-tag solido" :style="{ background: colorEstatus(i.estatus) }">{{ catalogos.estatus[i.estatus] }}</span>
                            <div class="vt-small vt-muted mt-1">{{ fechaHora(i.ultimo_movimiento) }}</div>
                        </template>
                        <template v-else-if="column.key === 'reporto'">
                            <Registro :usuario="i.reporta?.nombre_usuario" :fecha="i.created_at" />
                            <span v-if="i.casa" class="vt-small" style="color: #1597a5"><HomeOutlined /> Casa {{ i.casa.identificador }}</span>
                        </template>
                        <template v-else-if="column.key === 'asignado'">
                            <span v-if="i.asignado" class="font-semibold"><UserOutlined class="vt-muted" /> {{ i.asignado.nombre_usuario }}</span>
                            <span v-else class="vt-tag" style="background: #fff4d9; color: #a86f00">Sin asignar</span>
                        </template>
                        <template v-else-if="column.key === 'gastos'">
                            <div class="flex gap-1 flex-wrap">
                                <span v-if="Number(i.total_gastos)" class="vt-tag" style="background: #fff4d9; color: #a86f00">{{ moneda(i.total_gastos) }}</span>
                                <span v-if="i.fotos_count" class="vt-tag" style="background: #efeafa; color: #6e4bc2"><PictureOutlined /> {{ i.fotos_count }}</span>
                                <span v-if="!Number(i.total_gastos) && !i.fotos_count" class="vt-muted">—</span>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'acciones'">
                            <a-tooltip title="Ver seguimiento"><a-button class="vt-ab azul" @click.stop="ver(i.id)"><EyeOutlined /></a-button></a-tooltip>
                        </template>
                    </template>

                    <template #summary>
                        <a-table-summary fixed="bottom">
                            <a-table-summary-row class="vt-fsum">
                                <a-table-summary-cell :index="0">
                                    <a-input v-model:value="filtros.buscar" allow-clear placeholder="Folio, título, descripción o lugar" @change="aplicarConRetraso"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="1">
                                    <a-select v-model:value="filtros.categoria" allow-clear placeholder="Todas" style="width: 100%" :options="opciones(catalogos.categorias)" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="2">
                                    <a-select v-model:value="filtros.prioridad" allow-clear placeholder="Todas" style="width: 100%" :options="opciones(catalogos.prioridades)" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="3">
                                    <a-select v-model:value="filtros.estatus" allow-clear placeholder="Abiertas" style="width: 100%" :options="opciones(catalogos.estatus)" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="4"></a-table-summary-cell>
                                <a-table-summary-cell :index="5">
                                    <a-select v-if="personalMantenimiento.length" v-model:value="filtros.id_usuario_asignado" allow-clear placeholder="Todos" style="width: 100%"
                                        show-search option-filter-prop="label" :options="personalMantenimiento.map((p) => ({ value: p.id, label: p.nombre }))" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="6"></a-table-summary-cell>
                                <a-table-summary-cell :index="7" align="center">
                                    <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!filtrosActivos" @click="limpiar"><ClearOutlined /></a-button></a-tooltip>
                                </a-table-summary-cell>
                            </a-table-summary-row>
                        </a-table-summary>
                    </template>
                </a-table>
            </div>
        </div>

        <!-- ================= REPORTAR ================= -->
        <a-modal v-model:open="dialogoReporte" width="820px" centered :confirm-loading="reporte.processing" ok-text="Enviar reporte" cancel-text="Cancelar" @ok="enviarReporte">
            <template #title>
                <div class="vt-modal-title"><span class="vt-head-icon"><SendOutlined /></span><div>Reportar incidencia o sugerencia<small>Mantenimiento recibe el aviso al momento</small></div></div>
            </template>
            <a-form layout="vertical">
                <div class="grid gap-4 md:grid-cols-5">
                    <div class="md:col-span-3">
                        <div class="vt-form-grid">
                            <a-form-item v-if="!esCondomino" class="full" label="Condominio" required v-bind="err('id_empresa')">
                                <a-select v-model:value="reporte.id_empresa" placeholder="Selecciona…" show-search option-filter-prop="label"
                                    :options="condominios.map((c) => ({ value: c.id, label: c.nombre_empresa }))" />
                            </a-form-item>
                            <a-form-item label="Tipo"><a-select v-model:value="reporte.tipo" :options="opciones(catalogos.tipos)" /></a-form-item>
                            <a-form-item v-if="!esCondomino" label="Prioridad"><a-select v-model:value="reporte.prioridad" :options="opciones(catalogos.prioridades)" /></a-form-item>
                            <a-form-item :class="{ full: !esCondomino }" label="Categoría" required v-bind="err('categoria')">
                                <a-select v-model:value="reporte.categoria" placeholder="Selecciona…" show-search option-filter-prop="label" :options="opciones(catalogos.categorias)" />
                            </a-form-item>
                            <a-form-item class="full" label="Título" required v-bind="err('titulo')">
                                <a-input v-model:value="reporte.titulo" :maxlength="150" placeholder="Ej. Luminaria fundida en el parque" />
                            </a-form-item>
                            <a-form-item class="full" label="Descripción" required v-bind="err('descripcion')">
                                <a-textarea v-model:value="reporte.descripcion" :rows="3" :maxlength="3000" show-count />
                            </a-form-item>
                            <a-form-item class="full" label="¿Dónde está?">
                                <a-input v-model:value="reporte.ubicacion" :maxlength="255" placeholder="Parque central, caseta, frente a la casa M2-L05…"><template #prefix><EnvironmentOutlined class="vt-muted" /></template></a-input>
                            </a-form-item>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <a-form-item label="Fotos (hasta 5)" :validate-status="errorFotos ? 'error' : ''" :help="errorFotos">
                            <SelectorArchivos v-model:archivos="fotos" :max="5" solo-imagenes :max-mb="8" texto="Arrastra o elige las fotos" />
                        </a-form-item>
                    </div>
                </div>
                <a-checkbox v-model:checked="conMapa" class="mb-2"><EnvironmentOutlined /> Marcar el punto en el mapa</a-checkbox>
                <MapaGps v-if="conMapa" v-model:lat="reporte.latitud" v-model:lng="reporte.longitud" :centro="centroReporte" editable height="260px" />
            </a-form>
        </a-modal>
    </AppLayout>
</template>
