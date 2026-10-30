<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { message } from 'ant-design-vue';
import {
    ApartmentOutlined, PlusOutlined, HomeOutlined, TeamOutlined, EnvironmentOutlined, MailOutlined, PhoneOutlined, SearchOutlined,
    ClearOutlined, EditOutlined, HistoryOutlined, SettingOutlined, CheckCircleOutlined, StopOutlined, IdcardOutlined, BankOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiBar from '@/Components/KpiBar.vue';
import Registro from '@/Components/Registro.vue';
import { confirmarEstado } from '@/composables/confirmar';
import { useAlturaTabla, useAnchoMovil } from '@/composables/useAlturaTabla';

const props = defineProps({
    empresas: Object,
    stats: Object,
    estados: Array,
    filtros: Object,
});

const page = usePage();
const permisos = computed(() => page.props.permisos || {});
const altoTabla = useAlturaTabla(470);
const anchoMovil = useAnchoMovil(1300);

// ===== Filtros al pie: uno por columna (se aplican en el servidor) =====
const f = reactive({
    search: props.filtros?.search || '',
    estado: props.filtros?.estado || null,
    contacto: props.filtros?.contacto || '',
    tipo_persona: props.filtros?.tipo_persona || null,
    activo: props.filtros?.activo ?? null,
});
const hayFiltros = computed(() => Object.values(f).some((v) => v !== '' && v !== null));
const cargando = ref(false);

const aplicar = (pagina = 1) => {
    const params = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
    if (pagina > 1) params.page = pagina;
    cargando.value = true;
    router.get(route('empresas.index'), params, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (cargando.value = false) });
};
let t = null;
const aplicarLuego = () => { clearTimeout(t); t = setTimeout(aplicar, 350); };
const limpiar = () => { Object.assign(f, { search: '', estado: null, contacto: '', tipo_persona: null, activo: null }); aplicar(); };

const columnas = [
    { title: 'Condominio', key: 'condominio', icon: ApartmentOutlined, width: 250 },
    { title: 'Ubicación', key: 'ubicacion', icon: EnvironmentOutlined, width: 220 },
    { title: 'Contacto', key: 'contacto', icon: PhoneOutlined, width: 190 },
    { title: 'Resumen', key: 'resumen', icon: HomeOutlined, width: 120 },
    { title: 'Régimen', key: 'tipo', icon: BankOutlined, width: 125 },
    { title: 'Registro', key: 'traza', icon: HistoryOutlined, width: 150 },
    { title: 'Estado', key: 'estado', icon: CheckCircleOutlined, width: 100, align: 'center' },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 95, align: 'center' },
];

const kpis = computed(() => [
    { key: 't', icon: ApartmentOutlined, valor: props.stats.total, label: 'Condominios', color: '#1a5a96' },
    { key: 'a', icon: CheckCircleOutlined, valor: props.stats.activas, label: 'Activos', color: '#1faf5c' },
    { key: 'c', icon: HomeOutlined, valor: props.stats.casas ?? 0, label: 'Casas registradas', color: '#1597a5' },
    { key: 'i', icon: StopOutlined, valor: props.stats.inactivas, label: 'Inactivos', color: '#94a3b8' },
]);

const paginacion = computed(() => ({
    current: props.empresas.current_page,
    pageSize: props.empresas.per_page,
    total: props.empresas.total,
    showSizeChanger: false,
    showTotal: (total, rango) => `${rango[0]}–${rango[1]} de ${total} condominios`,
}));

const ubicacion = (e) => [e.colonia, e.municipio || e.ciudad, e.estado].filter(Boolean).join(', ');
const calle = (e) => [e.calle, e.numero_exterior, e.numero_interior ? `int. ${e.numero_interior}` : null].filter(Boolean).join(' ');

// Activar / desactivar: sólo superusuario
const cambiando = ref(null);
const alternar = async (e) => {
    if (!(await confirmarEstado(e.activo, 'el condominio', e.nombre_empresa))) return;
    cambiando.value = e.id;
    try {
        await axios.post(route('empresas.toggle-active', e.id));
        message.success(`Condominio ${e.activo ? 'desactivado' : 'activado'}.`);
        router.reload({ only: ['empresas', 'stats'] });
    } catch (err) {
        message.error(err.response?.data?.flash?.error || 'No se pudo cambiar el estado.');
    } finally {
        cambiando.value = null;
    }
};
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Condominios" subtitle="Alta y datos generales de cada condominio: dirección, contacto y régimen fiscal" :icon="ApartmentOutlined">
                <template #extra>
                    <Link v-if="permisos.puede_crear_empresas" :href="route('empresas.create')">
                        <a-button type="primary" size="large"><PlusOutlined /> Nuevo condominio</a-button>
                    </Link>
                </template>
            </PageHeader>

            <KpiBar :items="kpis" />

            <div class="vt-card" style="padding: 0; overflow: hidden">
                <a-table table-layout="fixed" :scroll="{ x: anchoMovil, y: altoTabla }" class="vt-table" :columns="columnas" :data-source="empresas.data" row-key="id"
                    :loading="cargando" :pagination="paginacion" :row-class-name="(r) => (r.activo ? '' : 'vt-inactivo')"
                    :locale="{ emptyText: hayFiltros ? 'No hay condominios con estos filtros.' : 'Aún no hay condominios. Usa «Nuevo condominio».' }"
                    @change="(p) => aplicar(p.current)">
                    <template #headerCell="{ column }">
                        <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
                    </template>

                    <template #bodyCell="{ column, record: e }">
                        <template v-if="column.key === 'condominio'">
                            <component :is="permisos.puede_editar_empresas ? Link : 'div'" :href="permisos.puede_editar_empresas ? route('empresas.edit', e.id) : undefined" class="flex items-center gap-3 min-w-0">
                                <span class="cs-ic" :class="{ off: !e.activo }"><ApartmentOutlined /></span>
                                <div class="min-w-0">
                                    <b class="block truncate">{{ e.nombre_empresa }}</b>
                                    <span class="vt-small vt-muted block truncate"><IdcardOutlined /> {{ e.rfc }}<template v-if="e.clave"> · Clave {{ e.clave }}</template></span>
                                </div>
                            </component>
                        </template>

                        <template v-else-if="column.key === 'ubicacion'">
                            <template v-if="ubicacion(e) || calle(e)">
                                <b class="block truncate">{{ calle(e) || 'Sin calle' }}</b>
                                <span class="vt-small vt-muted block truncate">{{ ubicacion(e) }}{{ e.codigo_postal ? ` · C.P. ${e.codigo_postal}` : '' }}</span>
                            </template>
                            <span v-else class="vt-tag" style="background: #fff4d9; color: #a86f00"><EnvironmentOutlined /> Sin dirección</span>
                        </template>

                        <template v-else-if="column.key === 'contacto'">
                            <template v-if="e.correo || e.telefono_trabajo || e.telefono_personal">
                                <span v-if="e.correo" class="block truncate"><MailOutlined class="vt-muted" /> {{ e.correo }}</span>
                                <span v-if="e.telefono_trabajo || e.telefono_personal" class="vt-small vt-muted block truncate">
                                    <PhoneOutlined /> {{ e.telefono_trabajo || e.telefono_personal }}<template v-if="e.extension"> ext. {{ e.extension }}</template>
                                </span>
                            </template>
                            <span v-else class="vt-muted vt-small">Sin contacto</span>
                        </template>

                        <template v-else-if="column.key === 'resumen'">
                            <div class="flex gap-1 flex-wrap">
                                <a-tooltip title="Casas activas"><span class="vt-tag" style="background: #e6eef8; color: #1a5a96"><HomeOutlined /> {{ e.casas_count ?? 0 }}</span></a-tooltip>
                                <a-tooltip title="Usuarios asignados"><span class="vt-tag" style="background: #eef2f7; color: #44566c"><TeamOutlined /> {{ e.usuarios_count }}</span></a-tooltip>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'tipo'">
                            <span class="vt-tag" :style="e.tipo_persona === 'MORAL' ? 'background:#efeafa;color:#6e4bc2' : 'background:#e1f5f7;color:#0e7d89'">
                                {{ e.tipo_persona === 'MORAL' ? 'Persona moral' : 'Persona física' }}
                            </span>
                            <span v-if="e.regimen" class="vt-small vt-muted block truncate mt-1">{{ e.regimen }}</span>
                        </template>

                        <template v-else-if="column.key === 'traza'">
                            <Registro :fecha="e.updated_at || e.created_at" />
                        </template>

                        <template v-else-if="column.key === 'estado'">
                            <span class="vt-tag solido" :style="{ background: e.activo ? '#1faf5c' : '#94a3b8' }">{{ e.activo ? 'Activo' : 'Inactivo' }}</span>
                        </template>

                        <template v-else-if="column.key === 'acciones'">
                            <div class="vt-acciones-fila">
                                <a-tooltip v-if="permisos.puede_editar_empresas" title="Editar"><Link :href="route('empresas.edit', e.id)"><a-button class="vt-ab naranja"><EditOutlined /></a-button></Link></a-tooltip>
                                <a-tooltip v-if="permisos.puede_eliminar_empresas" :title="e.activo ? 'Desactivar' : 'Activar'">
                                    <a-button class="vt-ab" :class="e.activo ? 'rojo' : 'verde'" :loading="cambiando === e.id" @click="alternar(e)">
                                        <StopOutlined v-if="e.activo" /><CheckCircleOutlined v-else />
                                    </a-button>
                                </a-tooltip>
                            </div>
                        </template>
                    </template>

                    <!-- Un filtro por columna, al pie -->
                    <template #summary>
                        <a-table-summary fixed="bottom">
                            <a-table-summary-row class="vt-fsum">
                                <a-table-summary-cell :index="0">
                                    <a-input v-model:value="f.search" placeholder="Nombre, RFC o clave" allow-clear @change="aplicarLuego"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="1">
                                    <a-select v-model:value="f.estado" allow-clear show-search placeholder="Estado" style="width: 100%" :options="estados.map((x) => ({ value: x, label: x }))" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="2">
                                    <a-input v-model:value="f.contacto" placeholder="Correo o teléfono" allow-clear @change="aplicarLuego"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="3"></a-table-summary-cell>
                                <a-table-summary-cell :index="4">
                                    <a-select v-model:value="f.tipo_persona" allow-clear placeholder="Todos" style="width: 100%" :options="[{ value: 'FISICA', label: 'Persona física' }, { value: 'MORAL', label: 'Persona moral' }]" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="5"></a-table-summary-cell>
                                <a-table-summary-cell :index="6">
                                    <a-select v-model:value="f.activo" allow-clear placeholder="Todos" style="width: 100%" :options="[{ value: 'true', label: 'Activos' }, { value: 'false', label: 'Inactivos' }]" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="7" align="center">
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
