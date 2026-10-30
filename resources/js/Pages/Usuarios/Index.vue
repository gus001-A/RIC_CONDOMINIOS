<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';
import {
    TeamOutlined, PlusOutlined, UserOutlined, MailOutlined, PhoneOutlined, SafetyOutlined, ApartmentOutlined, HistoryOutlined,
    CheckCircleOutlined, StopOutlined, SettingOutlined, EditOutlined, KeyOutlined, SearchOutlined, ClearOutlined, ReloadOutlined,
    CopyOutlined, EyeOutlined, EyeInvisibleOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiBar from '@/Components/KpiBar.vue';
import Registro from '@/Components/Registro.vue';
import { confirmarEstado } from '@/composables/confirmar';
import { useAlturaTabla, useAnchoMovil } from '@/composables/useAlturaTabla';

const props = defineProps({
    usuarios: Object,
    stats: Object,
    filtros: Object,
    tipos: Object,
});

const page = usePage();
const permisos = computed(() => page.props.permisos || {});
const yo = computed(() => page.props.auth?.user?.id_usuario);
const altoTabla = useAlturaTabla(470);
const anchoMovil = useAnchoMovil(1300);

/** Color de cada tipo de usuario (los del condominio y los contables). */
const COLOR_TIPO = {
    SUPERUSUARIO: '#0f2a47', ADMINISTRADOR: '#1a5a96', AUDITOR: '#6e4bc2', CAPTURISTA: '#1597a5', LECTOR: '#5d7085',
    CONDOMINO: '#1faf5c', VIGILANCIA: '#d9363e', MANTENIMIENTO: '#f0a202',
};

// ===== Filtros al pie: uno por columna (se aplican en el servidor) =====
const f = reactive({
    search: props.filtros?.search || '',
    tipo: props.filtros?.tipo || null,
    estado: props.filtros?.estado || null,
});
const hayFiltros = computed(() => Object.values(f).some((v) => v !== '' && v !== null));
const cargando = ref(false);
const aplicar = (pagina = 1) => {
    const params = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
    if (pagina > 1) params.page = pagina;
    cargando.value = true;
    router.get(route('usuarios.index'), params, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => (cargando.value = false) });
};
let t = null;
const aplicarLuego = () => { clearTimeout(t); t = setTimeout(aplicar, 350); };
const limpiar = () => { Object.assign(f, { search: '', tipo: null, estado: null }); aplicar(); };

const columnas = [
    { title: 'Usuario', key: 'usuario', icon: UserOutlined, width: 250 },
    { title: 'Contacto', key: 'contacto', icon: MailOutlined, width: 230 },
    { title: 'Tipo', key: 'tipo', icon: SafetyOutlined, width: 150 },
    { title: 'Condominios', key: 'condominios', icon: ApartmentOutlined, width: 200 },
    { title: 'Registro', key: 'traza', icon: HistoryOutlined, width: 150 },
    { title: 'Estado', key: 'estado', icon: CheckCircleOutlined, width: 100, align: 'center' },
    { title: 'Acciones', key: 'acciones', icon: SettingOutlined, width: 130, align: 'center' },
];

const kpis = computed(() => [
    { key: 't', icon: TeamOutlined, valor: props.stats.total, label: 'Usuarios', color: '#1a5a96' },
    { key: 'a', icon: CheckCircleOutlined, valor: props.stats.activos, label: 'Activos', color: '#1faf5c' },
    { key: 'i', icon: StopOutlined, valor: props.stats.inactivos, label: 'Inactivos', color: '#94a3b8' },
]);

const paginacion = computed(() => ({
    current: props.usuarios.current_page,
    pageSize: props.usuarios.per_page,
    total: props.usuarios.total,
    showSizeChanger: false,
    showTotal: (total, rango) => `${rango[0]}–${rango[1]} de ${total} usuarios`,
}));
const opcionesTipo = computed(() => Object.entries(props.tipos).map(([value, label]) => ({ value, label })));
const iniciales = (n) => (n || '?').split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase();

// ===== Activar / desactivar =====
const alternar = async (u) => {
    if (!(await confirmarEstado(u.activo, 'el usuario', u.nombre_completo))) return;
    router.post(route('usuarios.toggle-activo', u.id_usuario), {}, {
        preserveScroll: true,
        onSuccess: (p) => { if (!p.props.flash?.error) message.success(`Usuario ${u.activo ? 'desactivado' : 'activado'}.`); },
    });
};

// ===== Restablecer contraseña =====
const dialogoClave = ref(false);
const usuarioClave = ref(null);
const clave = ref('');
const verClave = ref(false);
const guardandoClave = ref(false);
const generar = () => {
    const letras = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    const otros = '23456789';
    const simbolos = '!@#$%*?';
    const azar = (s) => s[Math.floor(Math.random() * s.length)];
    const base = Array.from({ length: 7 }, () => azar(letras)).concat([azar(otros), azar(otros), azar(simbolos)]);
    clave.value = base.sort(() => Math.random() - 0.5).join('');
    verClave.value = true;
};
const abrirClave = (u) => {
    usuarioClave.value = u;
    clave.value = '';
    verClave.value = false;
    generar();
    dialogoClave.value = true;
};
const copiar = async () => {
    try { await navigator.clipboard.writeText(clave.value); message.success('Contraseña copiada.'); } catch { message.info('Selecciónala y cópiala manualmente.'); }
};
const guardarClave = () => {
    if (clave.value.length < 8) { message.warning('La contraseña debe tener al menos 8 caracteres.'); return; }
    guardandoClave.value = true;
    router.post(route('usuarios.reset-password', usuarioClave.value.id_usuario), { password: clave.value }, {
        preserveScroll: true,
        onSuccess: (p) => { if (!p.props.flash?.error) dialogoClave.value = false; },
        onFinish: () => (guardandoClave.value = false),
    });
};
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Usuarios" subtitle="Cuentas de acceso, su tipo de usuario y los condominios que pueden operar" :icon="TeamOutlined">
                <template #extra>
                    <Link v-if="permisos.puede_crear_usuarios" :href="route('usuarios.create')">
                        <a-button type="primary" size="large"><PlusOutlined /> Nuevo usuario</a-button>
                    </Link>
                </template>
            </PageHeader>

            <KpiBar :items="kpis" />

            <div class="vt-card" style="padding: 0; overflow: hidden">
                <a-table table-layout="fixed" :scroll="{ x: anchoMovil, y: altoTabla }" class="vt-table" :columns="columnas" :data-source="usuarios.data" row-key="id_usuario"
                    :loading="cargando" :pagination="paginacion" :row-class-name="(r) => (r.activo ? '' : 'vt-inactivo')"
                    :locale="{ emptyText: hayFiltros ? 'No hay usuarios con estos filtros.' : 'Aún no hay usuarios.' }"
                    @change="(p) => aplicar(p.current)">
                    <template #headerCell="{ column }">
                        <span class="vt-th"><component :is="column.icon" v-if="column.icon" />{{ column.title }}</span>
                    </template>

                    <template #bodyCell="{ column, record: u }">
                        <template v-if="column.key === 'usuario'">
                            <component :is="permisos.puede_editar_usuarios ? Link : 'div'" :href="permisos.puede_editar_usuarios ? route('usuarios.edit', u.id_usuario) : undefined" class="flex items-center gap-3 min-w-0">
                                <span class="us-av" :style="{ '--c': u.activo ? COLOR_TIPO[u.tipo_usuario] || '#1a5a96' : '#94a3b8' }">{{ iniciales(u.nombre_completo) }}</span>
                                <div class="min-w-0">
                                    <b class="block truncate">{{ u.nombre_completo }}</b>
                                    <span class="vt-small vt-muted block truncate">@{{ u.nombre_usuario }}<template v-if="u.id_usuario === yo"> · <b style="color: #1a5a96">tú</b></template></span>
                                </div>
                            </component>
                        </template>

                        <template v-else-if="column.key === 'contacto'">
                            <span class="block truncate"><MailOutlined class="vt-muted" /> {{ u.email }}</span>
                            <span v-if="u.telefono" class="vt-small vt-muted block truncate"><PhoneOutlined /> {{ u.telefono }}</span>
                        </template>

                        <template v-else-if="column.key === 'tipo'">
                            <span class="vt-tag" :style="{ background: `color-mix(in srgb, ${COLOR_TIPO[u.tipo_usuario] || '#5d7085'} 12%, #fff)`, color: COLOR_TIPO[u.tipo_usuario] || '#5d7085' }">
                                <SafetyOutlined /> {{ u.tipo_usuario_texto || tipos[u.tipo_usuario] || u.tipo_usuario }}
                            </span>
                        </template>

                        <template v-else-if="column.key === 'condominios'">
                            <span v-if="u.tipo_usuario === 'SUPERUSUARIO'" class="vt-small vt-muted">Todos</span>
                            <template v-else-if="u.empresas?.length">
                                <a-tooltip :title="u.empresas.map((e) => e.nombre_empresa).join(' · ')">
                                    <span class="block truncate">{{ u.empresas[0].nombre_empresa }}</span>
                                    <span v-if="u.empresas.length > 1" class="vt-small vt-muted">y {{ u.empresas.length - 1 }} más</span>
                                </a-tooltip>
                            </template>
                            <span v-else class="vt-tag" style="background: #fff4d9; color: #a86f00">Sin asignar</span>
                        </template>

                        <template v-else-if="column.key === 'traza'">
                            <Registro :fecha="u.updated_at || u.created_at" />
                        </template>

                        <template v-else-if="column.key === 'estado'">
                            <span class="vt-tag solido" :style="{ background: u.activo ? '#1faf5c' : '#94a3b8' }">{{ u.activo ? 'Activo' : 'Inactivo' }}</span>
                        </template>

                        <template v-else-if="column.key === 'acciones'">
                            <div class="vt-acciones-fila">
                                <a-tooltip v-if="permisos.puede_editar_usuarios" title="Editar"><Link :href="route('usuarios.edit', u.id_usuario)"><a-button class="vt-ab naranja"><EditOutlined /></a-button></Link></a-tooltip>
                                <a-tooltip v-if="permisos.puede_crear_usuarios" title="Restablecer contraseña"><a-button class="vt-ab azul" @click="abrirClave(u)"><KeyOutlined /></a-button></a-tooltip>
                                <a-tooltip v-if="permisos.puede_crear_usuarios && u.id_usuario !== yo" :title="u.activo ? 'Desactivar' : 'Activar'">
                                    <a-button class="vt-ab" :class="u.activo ? 'rojo' : 'verde'" @click="alternar(u)"><StopOutlined v-if="u.activo" /><CheckCircleOutlined v-else /></a-button>
                                </a-tooltip>
                            </div>
                        </template>
                    </template>

                    <template #summary>
                        <a-table-summary fixed="bottom">
                            <a-table-summary-row class="vt-fsum">
                                <a-table-summary-cell :index="0" :col-span="2">
                                    <a-input v-model:value="f.search" placeholder="Nombre, usuario o correo" allow-clear @change="aplicarLuego"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="2">
                                    <a-select v-model:value="f.tipo" allow-clear placeholder="Todos" style="width: 100%" :options="opcionesTipo" @change="aplicar()" />
                                </a-table-summary-cell>
                                <a-table-summary-cell :index="3"></a-table-summary-cell>
                                <a-table-summary-cell :index="4"></a-table-summary-cell>
                                <a-table-summary-cell :index="5">
                                    <a-select v-model:value="f.estado" allow-clear placeholder="Todos" style="width: 100%" :options="[{ value: 'activo', label: 'Activos' }, { value: 'inactivo', label: 'Inactivos' }]" @change="aplicar()" />
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

        <!-- Restablecer contraseña -->
        <a-modal v-model:open="dialogoClave" centered width="460px" :confirm-loading="guardandoClave" ok-text="Guardar contraseña" cancel-text="Cancelar" @ok="guardarClave">
            <template #title><span class="flex items-center gap-2"><KeyOutlined /> Restablecer contraseña</span></template>
            <template v-if="usuarioClave">
                <div class="flex items-center gap-3 mb-4">
                    <span class="us-av" :style="{ '--c': COLOR_TIPO[usuarioClave.tipo_usuario] || '#1a5a96' }">{{ iniciales(usuarioClave.nombre_completo) }}</span>
                    <div class="min-w-0"><b class="block truncate">{{ usuarioClave.nombre_completo }}</b><span class="vt-small vt-muted">@{{ usuarioClave.nombre_usuario }}</span></div>
                </div>
                <a-form layout="vertical">
                    <a-form-item label="Nueva contraseña" help="Mínimo 8 caracteres. Compártela con la persona; podrá usarla de inmediato.">
                        <a-input v-model:value="clave" :type="verClave ? 'text' : 'password'" :maxlength="64">
                            <template #suffix>
                                <a-tooltip :title="verClave ? 'Ocultar' : 'Mostrar'"><span class="cursor-pointer vt-muted" @click="verClave = !verClave"><EyeInvisibleOutlined v-if="verClave" /><EyeOutlined v-else /></span></a-tooltip>
                            </template>
                        </a-input>
                    </a-form-item>
                    <div class="flex gap-2">
                        <a-button @click="generar"><ReloadOutlined /> Generar otra</a-button>
                        <a-button @click="copiar"><CopyOutlined /> Copiar</a-button>
                    </div>
                </a-form>
            </template>
        </a-modal>
    </AppLayout>
</template>

<style>
.us-av { --c: #1a5a96; flex: none; width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 13px; color: var(--c); background: color-mix(in srgb, var(--c) 13%, #fff); border: 2px solid color-mix(in srgb, var(--c) 28%, #fff); }
</style>
