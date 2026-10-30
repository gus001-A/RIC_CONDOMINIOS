<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ShopOutlined, FileTextOutlined, PlusOutlined, SearchOutlined, FlagOutlined, FileDoneOutlined, UserOutlined, TeamOutlined,
    SettingOutlined, ClearOutlined, EyeOutlined, ToolOutlined, KeyOutlined, TagOutlined, QuestionCircleOutlined, HomeOutlined, SendOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import SelectorArchivos from '@/Components/SelectorArchivos.vue';
import { useAlturaTabla, useAnchoMovil } from '@/composables/useAlturaTabla';
import { moneda } from '@/composables/useFechaHora';
import { colorServicio } from '@/composables/useServicios';

const props = defineProps({
    solicitudes: Object,
    esAdministracion: Boolean,
    condominios: Array,
    miCasa: Object,
    catalogos: Object,
    filtros: Object,
});

const page = usePage();
const permisos = computed(() => page.props.permisos || {});
const altoTabla = useAlturaTabla(470);
const anchoMovil = useAnchoMovil(1200); // en el celular la tabla se desliza de lado
const opciones = (obj) => Object.entries(obj).map(([value, label]) => ({ value, label }));

const ICONOS = { MANTENIMIENTO: ToolOutlined, RENTA: KeyOutlined, VENTA: TagOutlined, ASESORIA: QuestionCircleOutlined };
const PLACEHOLDERS = {
    MANTENIMIENTO: 'Ej. Fuga en el baño de arriba',
    RENTA: 'Ej. Quiero rentar mi casa a partir de enero',
    VENTA: 'Ej. Quiero vender mi casa',
    ASESORIA: 'Ej. Trámite de escrituras',
};

// ===== Filtros al pie (uno por columna) =====
const vacio = () => ({ buscar: '', tipo: null, estatus: null, ver_cerradas: false });
const f = props.filtros || {};
const filtros = ref({
    ...vacio(),
    buscar: f.buscar || f.folio || f.casa || '',
    tipo: f.tipo || null,
    estatus: f.estatus || null,
    ver_cerradas: ['1', 'true', true].includes(f.ver_cerradas),
});
const filtrosActivos = computed(() => Object.values(filtros.value).some((v) => v !== '' && v !== false && v !== null));
const cargando = ref(false);

const aplicar = (pagina = 1) => {
    const params = {};
    for (const [k, v] of Object.entries(filtros.value)) {
        if (v === true) params[k] = 1;
        else if (v !== '' && v !== false && v !== null) params[k] = v;
    }
    if (pagina > 1) params.page = pagina;
    cargando.value = true;
    router.get(route('servicios.index'), params, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (cargando.value = false) });
};
let t = null;
const aplicarConRetraso = () => { clearTimeout(t); t = setTimeout(aplicar, 350); };
const limpiar = () => { filtros.value = vacio(); aplicar(); };

const columnas = [
    { title: 'Solicitud', key: 'solicitud', icon: FileTextOutlined, width: 260 },
    { title: 'Tipo', key: 'tipo', icon: TagOutlined, width: 230 },
    { title: 'Estatus', key: 'estatus', icon: FlagOutlined, width: 150 },
    { title: 'Cotizaciones', key: 'cotizaciones', icon: FileDoneOutlined, width: 130, align: 'center' },
    { title: 'Solicitó', key: 'solicito', icon: UserOutlined, width: 180 },
    { title: 'Atiende', key: 'atiende', icon: TeamOutlined, width: 180 },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 90, align: 'center' },
];
const paginacion = computed(() => ({
    current: props.solicitudes.current_page, pageSize: props.solicitudes.per_page, total: props.solicitudes.total, showSizeChanger: false,
    showTotal: (total, r) => `${r[0]}–${r[1]} de ${total} solicitudes`,
}));
const ver = (id) => router.visit(route('servicios.show', id));

// ===== Nueva solicitud =====
const dialogoNueva = ref(false);
const adjunto = ref([]);
const nueva = useForm({ tipo: 'MANTENIMIENTO', categoria: null, titulo: '', descripcion: '', precio_deseado: null, fecha_disponible: null, adjunto: null });
const err = (k) => ({ 'validate-status': nueva.errors[k] ? 'error' : '', help: nueva.errors[k] });
const abrirNueva = () => { nueva.reset(); nueva.clearErrors(); adjunto.value = []; dialogoNueva.value = true; };
const enviar = () => nueva.transform((d) => ({ ...d, adjunto: adjunto.value[0] || null }))
    .post(route('servicios.store'), { forceFormData: true, onSuccess: () => (dialogoNueva.value = false) });
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Servicios y asesoría" :icon="ShopOutlined"
                :subtitle="esAdministracion ? 'Solicitudes privadas de las casas: reparaciones, renta y venta' : `Pide apoyo a la administración para tu casa ${miCasa?.identificador || ''}. Sólo tú y la administración lo ven.`">
                <template #extra>
                    <Link v-if="esAdministracion" :href="route('documentos.plantillas')"><a-button size="large"><FileTextOutlined /> Documentos</a-button></Link>
                    <a-button v-if="permisos.puede_solicitar_servicios" type="primary" size="large" @click="abrirNueva"><PlusOutlined /> Nueva solicitud</a-button>
                </template>
            </PageHeader>

            <div class="vt-card vt-toolbar mb-3">
                <a-segmented v-model:value="filtros.tipo" :options="[{ value: null, label: 'Todos' }, ...opciones(catalogos.tipos)]" @change="aplicar()" />
                <a-checkbox v-if="!filtros.estatus" v-model:checked="filtros.ver_cerradas" @change="aplicar()">Incluir concluidas y canceladas</a-checkbox>
            </div>

            <div class="vt-card" style="padding: 0; overflow: hidden">
                <a-table table-layout="fixed" class="vt-table" :columns="columnas" :data-source="solicitudes.data" row-key="id" :loading="cargando"
                    :scroll="{ x: anchoMovil, y: altoTabla }" :pagination="paginacion" :custom-row="(r) => ({ onClick: () => ver(r.id), style: 'cursor:pointer' })"
                    :locale="{ emptyText: permisos.puede_solicitar_servicios && !filtrosActivos ? 'Aún no tienes solicitudes. Usa «Nueva solicitud».' : 'No hay solicitudes con esos filtros.' }"
                    @change="(p) => aplicar(p.current)">
                    <template #headerCell="{ column }"><span class="vt-th"><component :is="column.icon" />{{ column.title }}</span></template>
                    <template #bodyCell="{ column, record: s }">
                        <template v-if="column.key === 'solicitud'">
                            <div class="flex items-center gap-2">
                                <b style="color: #1a5a96">{{ s.folio }}</b>
                                <span v-if="esAdministracion && s.casa" class="vt-small" style="color: #1597a5"><HomeOutlined /> Casa {{ s.casa.identificador }}</span>
                            </div>
                            <div class="font-semibold truncate" style="color: var(--vt-navy)">{{ s.titulo }}</div>
                            <div v-if="s.precio_deseado" class="vt-small vt-muted">Precio deseado {{ moneda(s.precio_deseado) }}</div>
                        </template>
                        <template v-else-if="column.key === 'tipo'">
                            <span class="vt-tag" style="background: #e8f0fa; color: #1a5a96"><component :is="ICONOS[s.tipo]" /> {{ catalogos.tipos[s.tipo] }}</span>
                            <div v-if="s.categoria" class="vt-small vt-muted mt-1">{{ catalogos.categorias[s.categoria] }}</div>
                        </template>
                        <template v-else-if="column.key === 'estatus'">
                            <span class="vt-tag solido" :style="{ background: colorServicio(s.estatus) }">{{ catalogos.estatus[s.estatus] }}</span>
                        </template>
                        <template v-else-if="column.key === 'cotizaciones'">
                            <span v-if="s.cotizaciones_count" class="vt-tag" style="background: #efeafa; color: #6e4bc2"><FileDoneOutlined /> {{ s.cotizaciones_count }}</span>
                            <span v-else class="vt-muted">—</span>
                        </template>
                        <template v-else-if="column.key === 'solicito'"><Registro :usuario="s.solicita?.nombre_usuario" :fecha="s.created_at" /></template>
                        <template v-else-if="column.key === 'atiende'"><Registro :usuario="s.atiende?.nombre_usuario || 'Sin asignar'" :fecha="s.updated_at" /></template>
                        <template v-else-if="column.key === 'acciones'">
                            <a-tooltip title="Ver solicitud"><a-button class="vt-ab azul" @click.stop="ver(s.id)"><EyeOutlined /></a-button></a-tooltip>
                        </template>
                    </template>
                    <template #summary>
                        <a-table-summary fixed="bottom">
                            <a-table-summary-row class="vt-fsum">
                                <a-table-summary-cell :index="0">
                                    <a-input v-model:value="filtros.buscar" allow-clear :placeholder="esAdministracion ? 'Folio, casa, título o descripción' : 'Folio, título o descripción'" @change="aplicarConRetraso">
                                        <template #prefix><SearchOutlined class="vt-muted" /></template>
                                    </a-input>
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="1">
                                    <a-select v-model:value="filtros.tipo" allow-clear placeholder="Todos" style="width: 100%" :options="opciones(catalogos.tipos)" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="2">
                                    <a-select v-model:value="filtros.estatus" allow-clear placeholder="Abiertas" style="width: 100%" :options="opciones(catalogos.estatus)" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="3"></a-table-summary-cell>
                                <a-table-summary-cell :index="4"></a-table-summary-cell>
                                <a-table-summary-cell :index="5"></a-table-summary-cell>
                                <a-table-summary-cell :index="6" align="center">
                                    <a-tooltip title="Limpiar filtros"><a-button class="vt-ab rojo" :disabled="!filtrosActivos" @click="limpiar"><ClearOutlined /></a-button></a-tooltip>
                                </a-table-summary-cell>
                            </a-table-summary-row>
                        </a-table-summary>
                    </template>
                </a-table>
            </div>
        </div>

        <!-- Nueva solicitud (condómino) -->
        <a-modal v-model:open="dialogoNueva" width="720px" centered :confirm-loading="nueva.processing" ok-text="Enviar a la administración" cancel-text="Cancelar" @ok="enviar">
            <template #title>
                <div class="vt-modal-title"><span class="vt-head-icon"><SendOutlined /></span><div>Nueva solicitud<small>Sólo tú y la administración la ven</small></div></div>
            </template>
            <div class="sv-tipos mb-4">
                <button v-for="(label, value) in catalogos.tipos" :key="value" type="button" class="sv-tipo" :class="{ activo: nueva.tipo === value }" @click="nueva.tipo = value">
                    <component :is="ICONOS[value]" /> {{ label }}
                </button>
            </div>
            <a-form layout="vertical">
                <div class="vt-form-grid">
                    <a-form-item v-if="nueva.tipo === 'MANTENIMIENTO'" class="full" label="¿Qué necesitas?" required v-bind="err('categoria')">
                        <a-select v-model:value="nueva.categoria" placeholder="Selecciona…" show-search option-filter-prop="label" :options="opciones(catalogos.categorias)" />
                    </a-form-item>
                    <a-form-item class="full" label="Título" required v-bind="err('titulo')">
                        <a-input v-model:value="nueva.titulo" :maxlength="150" :placeholder="PLACEHOLDERS[nueva.tipo]" />
                    </a-form-item>
                    <a-form-item class="full" label="Descripción" required v-bind="err('descripcion')">
                        <a-textarea v-model:value="nueva.descripcion" :rows="4" :maxlength="3000" show-count />
                    </a-form-item>
                    <a-form-item v-if="['RENTA', 'VENTA'].includes(nueva.tipo)" :label="nueva.tipo === 'RENTA' ? 'Renta mensual deseada' : 'Precio de venta deseado'">
                        <a-input-number v-model:value="nueva.precio_deseado" :min="0" :precision="2" prefix="$" style="width: 100%" />
                    </a-form-item>
                    <a-form-item :label="nueva.tipo === 'MANTENIMIENTO' ? 'Desde cuándo pueden venir' : 'Fecha de disponibilidad'">
                        <a-date-picker v-model:value="nueva.fecha_disponible" value-format="YYYY-MM-DD" format="DD/MM/YYYY" style="width: 100%" />
                    </a-form-item>
                    <a-form-item class="full" label="Foto o documento (opcional)" v-bind="err('adjunto')" style="margin-bottom: 0">
                        <SelectorArchivos v-model:archivos="adjunto" accept="image/*,.pdf" ayuda="Imagen o PDF · máx. 10 MB" compacto />
                    </a-form-item>
                </div>
            </a-form>
        </a-modal>
    </AppLayout>
</template>

<style>
.sv-tipos { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
@media (max-width: 640px) { .sv-tipos { grid-template-columns: repeat(2, 1fr); } }
.sv-tipo { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 12px 8px; border-radius: 14px; border: 1.5px solid var(--vt-line); font-size: 13px; font-weight: 600; color: #44566c; transition: all 0.2s; background: #fff; }
.sv-tipo .anticon { font-size: 22px; color: var(--vt-blue); }
.sv-tipo:hover { border-color: var(--vt-blue); }
.sv-tipo.activo { border-color: var(--vt-blue); background: #e8f0fa; color: var(--vt-navy); box-shadow: 0 4px 12px rgba(26, 90, 150, 0.15); }
</style>
