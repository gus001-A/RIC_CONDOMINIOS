<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    DashboardOutlined, HomeOutlined, DollarOutlined, WarningOutlined, WalletOutlined, ToolOutlined, BellOutlined, CommentOutlined,
    IdcardOutlined, LoginOutlined, LogoutOutlined, ClockCircleOutlined, CheckCircleOutlined, StopOutlined, ThunderboltOutlined,
    InboxOutlined, ShopOutlined, AuditOutlined, RightOutlined, CarOutlined, EyeOutlined, PlusOutlined, CalendarOutlined, UserOutlined,
    AlertOutlined, EnvironmentOutlined, TeamOutlined, FileDoneOutlined, HistoryOutlined, FieldTimeOutlined, ScheduleOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import KpiBar from '@/Components/KpiBar.vue';
import { fechaHora, moneda } from '@/composables/useFechaHora';
import { colorEstatus, colorPrioridad } from '@/composables/useIncidencias';
import { colorVisita, hora } from '@/composables/useVisitas';
import { colorServicio } from '@/composables/useServicios';

const props = defineProps({
    rol: String,
    datos: Object,
    comunes: Object,
    catalogos: Object,
});

const page = usePage();
const usuario = computed(() => page.props.auth?.user || {});
const permisos = computed(() => page.props.permisos || {});
const d = computed(() => props.datos || {});

const saludo = computed(() => {
    const h = new Date().getHours();
    const nombre = (usuario.value.nombre_completo || '').split(' ')[0];
    const s = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
    return nombre ? `${s}, ${nombre.charAt(0)}${nombre.slice(1).toLowerCase()}` : s;
});
const mayuscula = (t) => t.charAt(0).toUpperCase() + t.slice(1);
const fechaHoy = mayuscula(new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
const SUBTITULO = {
    ADMINISTRACION: 'Resumen del condominio: cobranza, reportes y avisos',
    CONDOMINO: 'Tu casa, tus visitas y lo que pasa en el condominio',
    VIGILANCIA: 'Agenda y movimiento de la caseta',
    MANTENIMIENTO: 'Tu agenda de trabajo e insumos por reponer',
};

const corto = (n) => {
    const v = Number(n || 0);
    return v >= 1000 ? `$${(v / 1000).toLocaleString('es-MX', { maximumFractionDigits: 1 })}k` : moneda(v);
};
const iniciales = (n) => (n || '?').split(' ').filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
const fechaCorta = (f) => (f ? new Date(f).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) : '');
const diaSemana = (offset) => {
    const f = new Date();
    f.setDate(f.getDate() + offset);
    return mayuscula(f.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' }));
};
/** Tiempo transcurrido desde una fecha: "35 min", "2 h 10 min". */
const desde = (f, hasta = null) => {
    if (!f) return '';
    const min = Math.max(0, Math.round(((hasta ? new Date(hasta) : new Date()) - new Date(f)) / 60000));
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    return h < 24 ? `${h} h${min % 60 ? ` ${min % 60} min` : ''}` : `${Math.floor(h / 24)} d`;
};

// ---------- Indicadores por rol ----------
const kpis = computed(() => {
    const k = d.value.kpis || {};
    if (props.rol === 'ADMINISTRACION') {
        return [
            { key: 'casas', icon: HomeOutlined, valor: k.casas, label: 'Casas activas', color: '#1a5a96' },
            { key: 'deudoras', icon: WarningOutlined, valor: k.deudoras, label: 'Con adeudo vencido', color: '#d9363e' },
            { key: 'adeudo', icon: WalletOutlined, valor: corto(k.adeudo_vencido), label: 'Adeudo vencido', color: '#f0a202' },
            { key: 'cobrado', icon: DollarOutlined, valor: corto(k.cobrado_mes), label: 'Cobrado este mes', color: '#1faf5c' },
            { key: 'incidencias', icon: ToolOutlined, valor: k.incidencias_abiertas, label: 'Incidencias abiertas', color: '#1597a5' },
            { key: 'servicios', icon: ShopOutlined, valor: k.servicios_abiertos, label: 'Servicios en curso', color: '#6e4bc2' },
        ];
    }
    if (props.rol === 'VIGILANCIA') {
        return [
            { key: 'dentro', icon: LoginOutlined, valor: k.dentro, label: 'Dentro ahora', color: '#1a5a96' },
            { key: 'entrar', icon: ClockCircleOutlined, valor: k.por_entrar, label: 'Por entrar', color: '#f0a202' },
            { key: 'pre', icon: ScheduleOutlined, valor: k.preautorizadas_hoy, label: 'Pre-autorizadas hoy', color: '#6e4bc2' },
            { key: 'llegadas', icon: IdcardOutlined, valor: k.llegadas_hoy, label: 'Llegadas hoy', color: '#1597a5' },
            { key: 'salidas', icon: LogoutOutlined, valor: k.salidas_hoy, label: 'Salidas hoy', color: '#1faf5c' },
            { key: 'rechazadas', icon: StopOutlined, valor: k.rechazadas_hoy, label: 'Rechazadas hoy', color: '#d9363e' },
        ];
    }
    if (props.rol === 'MANTENIMIENTO') {
        return [
            { key: 'pendientes', icon: ClockCircleOutlined, valor: k.pendientes, label: 'Pendientes', color: '#f0a202' },
            { key: 'proceso', icon: ToolOutlined, valor: k.en_proceso, label: 'En proceso', color: '#1a5a96' },
            { key: 'mias', icon: UserOutlined, valor: k.mias, label: 'Asignadas a mí', color: '#1597a5' },
            { key: 'urgentes', icon: ThunderboltOutlined, valor: k.urgentes, label: 'Urgentes / alta', color: '#d9363e' },
            { key: 'atendidas', icon: CheckCircleOutlined, valor: k.atendidas_mes, label: 'Atendidas este mes', color: '#1faf5c' },
            { key: 'insumos', icon: InboxOutlined, valor: k.insumos_bajos, label: 'Insumos por reponer', color: '#6e4bc2' },
        ];
    }
    const c = d.value.cuenta || {};
    const v = d.value.visitas || {};
    return [
        { key: 'saldo', icon: WalletOutlined, valor: moneda(c.saldo || 0), label: c.saldo > 0 ? 'Saldo por pagar' : 'Al corriente', color: c.saldo_vencido > 0 ? '#d9363e' : '#1faf5c' },
        { key: 'vencido', icon: WarningOutlined, valor: c.cargos_vencidos || 0, label: 'Cuotas vencidas', color: '#f0a202' },
        { key: 'esperando', icon: ClockCircleOutlined, valor: v.esperando || 0, label: 'Visitas por autorizar', color: '#6e4bc2' },
        { key: 'dentro', icon: LoginOutlined, valor: v.dentro || 0, label: 'Visitas dentro', color: '#1a5a96' },
    ];
});

// ---------- Accesos rápidos (administración no los usa: tiene todo en el menú) ----------
const accesos = computed(() => {
    const p = permisos.value;
    const lista = {
        CONDOMINO: [
            [true, 'Pre-autorizar visita', '/mis-visitas', IdcardOutlined, '#6e4bc2'],
            [p.puede_ver_incidencias, 'Reportar incidencia', '/incidencias', ToolOutlined, '#1597a5'],
            [p.puede_ver_alertas, 'Alerta vecinal', '/seguridad/alertas', AlertOutlined, '#d9363e'],
            [p.puede_ver_transparencia, 'Estado de cuenta', '/transparencia', AuditOutlined, '#1a5a96'],
        ],
        VIGILANCIA: [
            [true, 'Registrar llegada', '/seguridad/caseta', CarOutlined, '#1a5a96'],
            [p.puede_ver_alertas, 'Alertas', '/seguridad/alertas', BellOutlined, '#d9363e'],
            [p.puede_ver_casas, 'Casas', '/casas', HomeOutlined, '#1597a5'],
            [p.puede_ver_incidencias, 'Reportar incidencia', '/incidencias', ToolOutlined, '#f0a202'],
        ],
        MANTENIMIENTO: [
            [true, 'Incidencias', '/incidencias', ToolOutlined, '#1a5a96'],
            [p.puede_ver_inventario, 'Inventario', '/inventario', InboxOutlined, '#6e4bc2'],
            [p.puede_ver_alertas, 'Alertas', '/seguridad/alertas', BellOutlined, '#d9363e'],
            [p.puede_ver_casas, 'Casas', '/casas', HomeOutlined, '#1597a5'],
        ],
    }[props.rol] || [];
    return lista.filter(([ok]) => ok).map(([, label, href, icono, color]) => ({ label, href, icono, color }));
});

// ---------- Agenda (vigilancia y mantenimiento) ----------
const DIAS = [
    { key: 'hoy', titulo: 'Hoy', sub: diaSemana(0), color: '#1a5a96' },
    { key: 'manana', titulo: 'Mañana', sub: diaSemana(1), color: '#1597a5' },
    { key: 'semana', titulo: 'Esta semana', sub: `${diaSemana(2)} – ${diaSemana(7)}`, color: '#6e4bc2' },
];
const diaAgenda = ref('hoy'); // en el celular se ve una columna a la vez

// ---------- Administración: gráficas ----------
const maxCobranza = computed(() => Math.max(1, ...(d.value.cobranza || []).flatMap((m) => [m.cargos, m.pagos])));
const cobranzaMes = computed(() => {
    const k = d.value.kpis || {};
    return k.cargado_mes > 0 ? Math.min(100, Math.round((k.cobrado_mes / k.cargado_mes) * 100)) : null;
});
const COLOR_OCUPACION = { HABITADA: '#1faf5c', DESHABITADA: '#94a3b8', 'EN RENTA': '#1a5a96', 'EN VENTA': '#f0a202', 'EN CONSTRUCCION': '#6e4bc2' };
const ocupacion = computed(() => {
    const o = d.value.ocupacion || {};
    const total = Object.values(o).reduce((s, n) => s + n, 0);
    let acumulado = 0;
    return Object.entries(o).sort((a, b) => b[1] - a[1]).map(([k, n]) => {
        const pct = total ? (n / total) * 100 : 0;
        const seg = { k, n, pct, inicio: acumulado, color: COLOR_OCUPACION[k] || '#1597a5', label: mayuscula(k.toLowerCase()) };
        acumulado += pct;
        return seg;
    });
});
const ocupacionTotal = computed(() => ocupacion.value.reduce((s, o) => s + o.n, 0));
const segActivo = ref(null);
const segmento = computed(() => ocupacion.value.find((o) => o.k === segActivo.value));
// Dona en SVG: circunferencia de r=15.915 ≈ 100, así cada % es una unidad de trazo.
const R = 15.915;
const hueco = computed(() => (ocupacion.value.length > 1 ? 0.8 : 0));

// ---------- Vigilancia ----------
const maxHora = computed(() => Math.max(1, ...(d.value.por_hora || []).map((h) => h.total)));
const horaActual = new Date().getHours();

// ---------- Condómino: avance de solicitudes y reportes ----------
const PASOS_SERVICIO = ['NUEVA', 'EN_REVISION', 'COTIZADA', 'EN_PROCESO', 'CONCLUIDA'];
const PASOS_INCIDENCIA = ['PENDIENTE', 'EN PROCESO', 'ATENDIDA', 'SUPERVISADA'];
const paso = (pasos, estatus) => Math.max(0, pasos.indexOf(estatus));
const ICONO_VISITA = { VISITA: UserOutlined, PROVEEDOR: ToolOutlined, PAQUETERIA: InboxOutlined, TRANSPORTE: CarOutlined, EMPLEADO: TeamOutlined, OTRO: UserOutlined };
const textoVisita = (v) => {
    if (v.estatus === 'PREAUTORIZADA') return v.esperada ? `Esperada el ${fechaCorta(v.esperada + 'T12:00:00')}` : 'Pre-autorizada';
    if (v.estatus === 'SALIO') return `${fechaCorta(v.entrada || v.llegada)} · entró ${hora(v.entrada || v.llegada)} · salió ${hora(v.salida)}`;
    if (v.estatus === 'DENTRO') return `Dentro desde las ${hora(v.entrada || v.llegada)}`;
    return v.llegada ? `Llegó el ${fechaCorta(v.llegada)} a las ${hora(v.llegada)}` : '';
};
const colorInsumo = (x) => (x.existencia <= 0 ? '#d9363e' : x.nivel < 50 ? '#f0a202' : '#1597a5');
const estadoInsumo = (x) => (x.existencia <= 0 ? 'Agotado' : x.nivel < 50 ? 'Casi agotado' : x.nivel >= 100 ? 'En el mínimo' : 'Bajo mínimo');
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap vt-enter">
            <PageHeader :title="saludo" :icon="DashboardOutlined">
                <template #subtitulo>{{ fechaHoy }} · {{ SUBTITULO[rol] }}</template>
                <template #extra>
                    <Link v-if="comunes.mensajes_sin_leer" href="/mensajes">
                        <a-button size="large"><CommentOutlined /> {{ comunes.mensajes_sin_leer }} mensaje{{ comunes.mensajes_sin_leer > 1 ? 's' : '' }} sin leer</a-button>
                    </Link>
                </template>
            </PageHeader>

            <!-- Alertas vecinales activas: lo primero que hay que ver -->
            <Link v-for="a in comunes.alertas" :key="a.id" href="/seguridad/alertas" class="vt-alert ini-alerta">
                <AlertOutlined />
                <span class="flex-1 min-w-0"><b>Alerta activa: {{ a.tipo }}</b>
                    <span class="vt-small"> · {{ a.casa ? `Casa ${a.casa}` : 'Anónima' }}{{ a.ubicacion ? ` · ${a.ubicacion}` : '' }} · {{ fechaHora(a.fecha) }}</span>
                </span>
                <RightOutlined />
            </Link>

            <div v-if="rol === 'CONDOMINO' && !d.casa" class="vt-alert info"><HomeOutlined /> <span>Aún no tienes una casa asignada. Pide a la administración que te dé de alta como condómino.</span></div>

            <template v-else>
                <!-- Tarjeta de la casa (condómino) -->
                <div v-if="rol === 'CONDOMINO'" class="ini-casa mb-3">
                    <div class="min-w-0">
                        <small>{{ d.casa.condominio }}</small>
                        <h2>Casa {{ d.casa.identificador }}</h2>
                        <p v-if="d.casa.direccion">{{ d.casa.direccion }}</p>
                    </div>
                    <div class="ini-casa-saldo">
                        <small>{{ d.cuenta.saldo_vencido > 0 ? 'Adeudo vencido' : 'Tu cuenta' }}</small>
                        <b>{{ d.cuenta.saldo_vencido > 0 ? moneda(d.cuenta.saldo_vencido) : 'Al corriente' }}</b>
                        <span>Cuota mensual {{ moneda(d.casa.cuota) }}<template v-if="d.cuenta.ultimo_pago"> · último pago {{ d.cuenta.ultimo_pago.split('-').reverse().join('/') }}</template></span>
                    </div>
                </div>

                <KpiBar :items="kpis" />

                <div v-if="accesos.length" class="ini-accesos mb-3">
                    <Link v-for="a in accesos" :key="a.href + a.label" :href="a.href" class="ini-acceso" :style="{ '--c': a.color }">
                        <span class="ini-acceso-ic"><component :is="a.icono" /></span>
                        <span>{{ a.label }}</span>
                        <RightOutlined class="ini-acceso-flecha" />
                    </Link>
                </div>

                <!-- ================= ADMINISTRACIÓN ================= -->
                <div v-if="rol === 'ADMINISTRACION'" class="ini-grid mt-3">
                    <div class="vt-card ini-ancho">
                        <div class="vt-card-title">
                            <h3><DollarOutlined /> Cobranza de cuotas · últimos 6 meses</h3>
                            <span class="ini-leyenda"><i style="background: #c6dbf3"></i>Cargado <i style="background: #1faf5c"></i>Cobrado</span>
                        </div>
                        <div class="ini-barras">
                            <div v-for="m in d.cobranza" :key="m.mes" class="ini-barra-col">
                                <div class="ini-barra-par">
                                    <a-tooltip :title="`Cargado ${moneda(m.cargos)}`"><span class="ini-barra" :style="{ height: (m.cargos / maxCobranza) * 100 + '%', background: '#c6dbf3' }"></span></a-tooltip>
                                    <a-tooltip :title="`Cobrado ${moneda(m.pagos)}`"><span class="ini-barra" :style="{ height: (m.pagos / maxCobranza) * 100 + '%', background: '#1faf5c' }"></span></a-tooltip>
                                </div>
                                <small>{{ m.etiqueta }}</small>
                            </div>
                        </div>
                        <div v-if="cobranzaMes !== null" class="vt-small vt-muted mt-2">Este mes se ha cobrado el <b style="color: var(--vt-navy)">{{ cobranzaMes }} %</b> de lo cargado ({{ moneda(d.kpis.cobrado_mes) }} de {{ moneda(d.kpis.cargado_mes) }}).</div>
                    </div>

                    <!-- Ocupación: gráfica de dona -->
                    <div class="vt-card">
                        <div class="vt-card-title"><h3><HomeOutlined /> Ocupación de las casas</h3></div>
                        <div v-if="!ocupacion.length" class="ini-vacio"><HomeOutlined /> Sin casas registradas.</div>
                        <div v-else class="ini-dona-wrap">
                            <svg viewBox="0 0 42 42" class="ini-dona" @mouseleave="segActivo = null">
                                <circle cx="21" cy="21" :r="R" fill="none" stroke="#eef2f6" stroke-width="5.5" />
                                <circle v-for="o in ocupacion" :key="o.k" cx="21" cy="21" :r="R" fill="none" :stroke="o.color"
                                    :stroke-width="segActivo === o.k ? 7.5 : 5.5" :stroke-dasharray="`${Math.max(0, o.pct - hueco)} ${100 - o.pct + hueco}`"
                                    :stroke-dashoffset="25 - o.inicio" class="ini-dona-seg" :class="{ apagado: segActivo && segActivo !== o.k }" @mouseenter="segActivo = o.k" />
                                <text x="21" y="21.2" text-anchor="middle" class="ini-dona-num">{{ segmento ? segmento.n : ocupacionTotal }}</text>
                                <text x="21" y="26" text-anchor="middle" class="ini-dona-txt">{{ segmento ? segmento.label : 'casas' }}</text>
                            </svg>
                            <ul class="ini-dona-ley">
                                <li v-for="o in ocupacion" :key="o.k" :class="{ on: segActivo === o.k }" @mouseenter="segActivo = o.k" @mouseleave="segActivo = null">
                                    <i :style="{ background: o.color }"></i>
                                    <span class="flex-1">{{ o.label }}</span>
                                    <b>{{ o.n }}</b>
                                    <small>{{ Math.round(o.pct) }}%</small>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="vt-card">
                        <div class="vt-card-title"><h3><WarningOutlined /> Mayores adeudos</h3><Link href="/cuotas" class="ini-ver">Ver cuotas <RightOutlined /></Link></div>
                        <div v-if="!d.deudores.length" class="ini-vacio"><CheckCircleOutlined /> Todas las casas están al corriente.</div>
                        <Link v-for="x in d.deudores" :key="x.id" href="/cuotas" class="ini-fila">
                            <span class="ini-fila-ic" style="--c: #d9363e"><HomeOutlined /></span>
                            <span class="min-w-0 flex-1"><b>Casa {{ x.casa }}</b><small class="truncate">{{ x.responsable || 'Sin responsable' }} · {{ x.cargos_vencidos }} cuota{{ x.cargos_vencidos > 1 ? 's' : '' }} vencida{{ x.cargos_vencidos > 1 ? 's' : '' }}</small></span>
                            <b class="ini-monto">{{ moneda(x.saldo_vencido) }}</b>
                        </Link>
                    </div>

                    <div class="vt-card">
                        <div class="vt-card-title"><h3><ToolOutlined /> Incidencias abiertas</h3><Link href="/incidencias" class="ini-ver">Ver todas <RightOutlined /></Link></div>
                        <div v-if="d.kpis.por_supervisar" class="vt-alert info mb-2"><EyeOutlined /> <span>{{ d.kpis.por_supervisar }} atendida{{ d.kpis.por_supervisar > 1 ? 's' : '' }} por supervisar.</span></div>
                        <div v-if="!d.incidencias.length" class="ini-vacio"><CheckCircleOutlined /> Sin incidencias abiertas.</div>
                        <Link v-for="i in d.incidencias" :key="i.id" :href="`/incidencias/${i.id}`" class="ini-fila">
                            <span class="ini-fila-ic" :style="{ '--c': colorPrioridad(i.prioridad) }"><ToolOutlined /></span>
                            <span class="min-w-0 flex-1"><b class="truncate">{{ i.titulo }}</b><small class="truncate">{{ i.folio }} · {{ i.categoria }}{{ i.casa ? ` · Casa ${i.casa}` : '' }}</small></span>
                            <span class="vt-tag solido" :style="{ background: colorEstatus(i.estatus) }">{{ catalogos.incidencia_estatus[i.estatus] }}</span>
                        </Link>
                    </div>

                    <div class="vt-card">
                        <div class="vt-card-title"><h3><ShopOutlined /> Servicios y asesoría</h3><Link href="/servicios" class="ini-ver">Ver todos <RightOutlined /></Link></div>
                        <div v-if="!d.servicios.length" class="ini-vacio"><CheckCircleOutlined /> Sin solicitudes en curso.</div>
                        <Link v-for="s in d.servicios" :key="s.id" :href="`/servicios/${s.id}`" class="ini-fila">
                            <span class="ini-fila-ic" style="--c: #6e4bc2"><ShopOutlined /></span>
                            <span class="min-w-0 flex-1"><b class="truncate">{{ s.titulo }}</b><small class="truncate">{{ s.folio }}{{ s.casa ? ` · Casa ${s.casa}` : '' }} · {{ catalogos.servicio_tipos[s.tipo] }}</small></span>
                            <span class="vt-tag solido" :style="{ background: colorServicio(s.estatus) }">{{ catalogos.servicio_estatus[s.estatus] }}</span>
                        </Link>
                    </div>
                </div>

                <!-- ================= CONDÓMINO ================= -->
                <div v-else-if="rol === 'CONDOMINO'" class="ini-grid">
                    <!-- Visitas: tarjeta por visita con avatar, tipo y horario -->
                    <div class="vt-card">
                        <div class="vt-card-title"><h3><IdcardOutlined /> Mis últimas visitas</h3><Link href="/mis-visitas" class="ini-ver">Ver visitas <RightOutlined /></Link></div>
                        <div v-if="!d.ultimas_visitas.length" class="ini-vacio-grande">
                            <span><CalendarOutlined /></span>
                            <b>Aún no tienes visitas</b>
                            <small>Pre-autoriza a quien esperas y entrará sin que caseta te llame.</small>
                            <Link href="/mis-visitas"><a-button type="primary"><PlusOutlined /> Pre-autorizar visita</a-button></Link>
                        </div>
                        <div class="ini-visitas">
                            <Link v-for="v in d.ultimas_visitas" :key="v.id" href="/mis-visitas" class="ini-visita" :style="{ '--c': colorVisita(v.estatus) }">
                                <span class="ini-avatar">{{ iniciales(v.nombre) }}<i><component :is="ICONO_VISITA[v.tipo] || UserOutlined" /></i></span>
                                <span class="min-w-0 flex-1">
                                    <b class="truncate">{{ v.nombre }}</b>
                                    <small class="truncate">{{ catalogos.visita_tipos[v.tipo] }}<template v-if="v.placas"> · <CarOutlined /> {{ v.placas }}</template></small>
                                    <small class="ini-visita-hora truncate"><FieldTimeOutlined /> {{ textoVisita(v) }}</small>
                                </span>
                                <span class="ini-pill">{{ catalogos.visita_estatus[v.estatus] }}</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Reportes: con el avance de su atención -->
                    <div class="vt-card">
                        <div class="vt-card-title"><h3><ToolOutlined /> Mis reportes</h3><Link href="/incidencias" class="ini-ver">Ver todos <RightOutlined /></Link></div>
                        <div v-if="!d.incidencias.length" class="ini-vacio-grande">
                            <span style="--c: #1faf5c"><CheckCircleOutlined /></span>
                            <b>Sin reportes abiertos</b>
                            <small>¿Algo dañado en las áreas comunes? Repórtalo y dale seguimiento aquí.</small>
                            <Link href="/incidencias"><a-button><PlusOutlined /> Reportar</a-button></Link>
                        </div>
                        <Link v-for="i in d.incidencias" :key="i.id" :href="`/incidencias/${i.id}`" class="ini-seg" :style="{ '--c': colorEstatus(i.estatus) }">
                            <div class="ini-seg-top">
                                <span class="ini-fila-ic"><ToolOutlined /></span>
                                <span class="min-w-0 flex-1"><b class="truncate">{{ i.titulo }}</b><small class="truncate">{{ i.folio }} · {{ i.categoria }}</small></span>
                                <span class="ini-pill">{{ catalogos.incidencia_estatus[i.estatus] }}</span>
                            </div>
                            <div class="ini-pasos">
                                <span v-for="(p, n) in PASOS_INCIDENCIA" :key="p" :class="{ hecho: n <= paso(PASOS_INCIDENCIA, i.estatus) }">
                                    <i></i><small>{{ catalogos.incidencia_estatus[p] }}</small>
                                </span>
                            </div>
                            <div class="ini-seg-pie">
                                <span><CalendarOutlined /> Reportado {{ fechaCorta(i.fecha) }}</span>
                                <span><UserOutlined /> {{ i.asignado || 'Sin asignar' }}</span>
                                <span><HistoryOutlined /> {{ fechaCorta(i.actualizada) }}</span>
                            </div>
                        </Link>
                    </div>

                    <!-- Solicitudes de servicio: con el avance y quién la atiende -->
                    <div class="vt-card">
                        <div class="vt-card-title"><h3><ShopOutlined /> Mis solicitudes de servicio</h3><Link href="/servicios" class="ini-ver">Ver todas <RightOutlined /></Link></div>
                        <div v-if="!d.servicios.length" class="ini-vacio-grande">
                            <span style="--c: #6e4bc2"><ShopOutlined /></span>
                            <b>Sin solicitudes en curso</b>
                            <small>Pide apoyo para mantenimiento, rentar o vender tu casa.</small>
                            <Link href="/servicios"><a-button><PlusOutlined /> Pedir apoyo</a-button></Link>
                        </div>
                        <Link v-for="s in d.servicios" :key="s.id" :href="`/servicios/${s.id}`" class="ini-seg" :style="{ '--c': colorServicio(s.estatus) }">
                            <div class="ini-seg-top">
                                <span class="ini-fila-ic"><ShopOutlined /></span>
                                <span class="min-w-0 flex-1"><b class="truncate">{{ s.titulo }}</b><small class="truncate">{{ s.folio }} · {{ catalogos.servicio_tipos[s.tipo] }}</small></span>
                                <span class="ini-pill">{{ catalogos.servicio_estatus[s.estatus] }}</span>
                            </div>
                            <div class="ini-pasos">
                                <span v-for="(p, n) in PASOS_SERVICIO" :key="p" :class="{ hecho: n <= paso(PASOS_SERVICIO, s.estatus) }">
                                    <i></i><small>{{ catalogos.servicio_estatus[p] }}</small>
                                </span>
                            </div>
                            <div class="ini-seg-pie">
                                <span><UserOutlined /> {{ s.atiende || 'Sin asignar' }}</span>
                                <span v-if="s.cotizaciones"><FileDoneOutlined /> {{ s.cotizaciones }} cotización{{ s.cotizaciones > 1 ? 'es' : '' }}</span>
                                <span><HistoryOutlined /> {{ fechaCorta(s.actualizada) }}</span>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- ================= VIGILANCIA ================= -->
                <template v-else-if="rol === 'VIGILANCIA'">
                    <!-- Agenda de pre-autorizadas -->
                    <div class="vt-card mb-3">
                        <div class="vt-card-title">
                            <h3><ScheduleOutlined /> Agenda de pre-autorizadas</h3>
                            <a-segmented v-model:value="diaAgenda" class="ini-solo-movil" :options="DIAS.map((x) => ({ value: x.key, label: `${x.titulo} (${d.agenda[x.key].length})` }))" />
                        </div>
                        <div class="ini-agenda">
                            <div v-for="dia in DIAS" :key="dia.key" class="ini-dia" :class="{ visible: diaAgenda === dia.key }" :style="{ '--c': dia.color }">
                                <div class="ini-dia-head"><b>{{ dia.titulo }}</b><small>{{ dia.sub }}</small><span>{{ d.agenda[dia.key].length }}</span></div>
                                <div v-if="!d.agenda[dia.key].length" class="ini-dia-vacio">Sin visitas pre-autorizadas</div>
                                <Link v-for="v in d.agenda[dia.key]" :key="v.id" href="/seguridad/caseta" class="ini-cita">
                                    <span class="ini-avatar sm" style="--c: #6e4bc2">{{ iniciales(v.nombre) }}</span>
                                    <span class="min-w-0 flex-1">
                                        <b class="truncate">{{ v.nombre }}</b>
                                        <small class="truncate">Casa {{ v.casa }} · {{ catalogos.visita_tipos[v.tipo] }}</small>
                                        <small v-if="v.placas || v.motivo" class="truncate"><template v-if="v.placas"><CarOutlined /> {{ v.placas }} </template>{{ v.motivo }}</small>
                                    </span>
                                    <small v-if="dia.key === 'semana' && v.esperada" class="ini-cita-fecha">{{ fechaCorta(v.esperada + 'T12:00:00') }}</small>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="ini-grid">
                        <!-- Cola de quienes ya entraron (el que lleva más tiempo, primero) -->
                        <div class="vt-card">
                            <div class="vt-card-title"><h3><LoginOutlined /> Dentro · cola de salida</h3><span class="ini-cuenta" style="--c: #1a5a96">{{ d.dentro.length }}</span></div>
                            <div v-if="!d.dentro.length" class="ini-vacio"><CheckCircleOutlined /> No hay visitas dentro.</div>
                            <ol class="ini-cola">
                                <li v-for="(v, n) in d.dentro" :key="v.id">
                                    <Link href="/seguridad/caseta" class="ini-cola-item">
                                        <span class="ini-turno">{{ n + 1 }}</span>
                                        <span class="min-w-0 flex-1"><b class="truncate">{{ v.nombre }}</b><small class="truncate">Casa {{ v.casa }}{{ v.placas ? ` · ${v.placas}` : '' }} · entró {{ hora(v.entrada || v.llegada) }}</small></span>
                                        <span class="ini-tiempo"><ClockCircleOutlined /> {{ desde(v.entrada || v.llegada) }}</span>
                                    </Link>
                                </li>
                            </ol>
                        </div>

                        <!-- Por entrar: esperando respuesta de la casa o ya autorizadas -->
                        <div class="vt-card">
                            <div class="vt-card-title"><h3><ClockCircleOutlined /> Por entrar</h3><span class="ini-cuenta" style="--c: #f0a202">{{ d.por_entrar.length }}</span></div>
                            <div v-if="!d.por_entrar.length" class="ini-vacio"><CheckCircleOutlined /> Nadie esperando en la pluma.</div>
                            <ol class="ini-cola">
                                <li v-for="(v, n) in d.por_entrar" :key="v.id">
                                    <Link href="/seguridad/caseta" class="ini-cola-item">
                                        <span class="ini-turno" :style="{ background: colorVisita(v.estatus) }">{{ n + 1 }}</span>
                                        <span class="min-w-0 flex-1"><b class="truncate">{{ v.nombre }}</b><small class="truncate">Casa {{ v.casa }} · {{ catalogos.visita_tipos[v.tipo] }}{{ v.llegada ? ` · llegó ${hora(v.llegada)}` : '' }}</small></span>
                                        <span class="vt-tag solido" :style="{ background: colorVisita(v.estatus) }">{{ v.estatus === 'ESPERANDO' ? 'Esperando' : 'Autorizada' }}</span>
                                    </Link>
                                </li>
                            </ol>
                        </div>

                        <!-- Ya salieron hoy -->
                        <div class="vt-card">
                            <div class="vt-card-title"><h3><LogoutOutlined /> Estuvieron dentro hoy</h3><span class="ini-cuenta" style="--c: #1faf5c">{{ d.salieron.length }}</span></div>
                            <div v-if="!d.salieron.length" class="ini-vacio"><HistoryOutlined /> Aún no sale ninguna visita hoy.</div>
                            <div class="ini-scroll">
                                <div v-for="v in d.salieron" :key="v.id" class="ini-fila">
                                    <span class="ini-avatar sm" style="--c: #94a3b8">{{ iniciales(v.nombre) }}</span>
                                    <span class="min-w-0 flex-1"><b class="truncate">{{ v.nombre }}</b><small class="truncate">Casa {{ v.casa }} · {{ hora(v.entrada || v.llegada) }} → {{ hora(v.salida) }}</small></span>
                                    <span class="ini-tiempo gris">{{ desde(v.entrada || v.llegada, v.salida) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="vt-card ini-completo">
                            <div class="vt-card-title"><h3><ClockCircleOutlined /> Llegadas por hora · hoy</h3><span class="vt-small vt-muted">{{ d.kpis.llegadas_hoy }} en total</span></div>
                            <div class="ini-barras ini-barras-hora">
                                <div v-for="h in d.por_hora" :key="h.hora" class="ini-barra-col">
                                    <div class="ini-barra-par">
                                        <a-tooltip :title="`${h.hora}:00 · ${h.total} llegada${h.total === 1 ? '' : 's'}`">
                                            <span class="ini-barra" :style="{ height: Math.max(h.total ? 6 : 2, (h.total / maxHora) * 100) + '%', background: h.hora === horaActual ? '#1a5a96' : '#9cc0e6' }"></span>
                                        </a-tooltip>
                                    </div>
                                    <small>{{ h.hora }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ================= MANTENIMIENTO ================= -->
                <template v-else-if="rol === 'MANTENIMIENTO'">
                    <div class="vt-card mb-3">
                        <div class="vt-card-title">
                            <h3><ScheduleOutlined /> Mi agenda de trabajo</h3>
                            <a-segmented v-model:value="diaAgenda" class="ini-solo-movil" :options="DIAS.map((x) => ({ value: x.key, label: `${x.titulo} (${d.agenda[x.key].length})` }))" />
                        </div>
                        <div class="ini-agenda">
                            <div v-for="dia in DIAS" :key="dia.key" class="ini-dia" :class="{ visible: diaAgenda === dia.key }" :style="{ '--c': dia.color }">
                                <div class="ini-dia-head"><b>{{ dia.titulo }}</b><small>{{ dia.sub }}</small><span>{{ d.agenda[dia.key].length }}</span></div>
                                <div v-if="!d.agenda[dia.key].length" class="ini-dia-vacio">Nada programado</div>
                                <Link v-for="i in d.agenda[dia.key]" :key="i.id" :href="`/incidencias/${i.id}`" class="ini-cita prioridad" :style="{ '--p': colorPrioridad(i.prioridad) }">
                                    <span class="min-w-0 flex-1">
                                        <span class="ini-cita-tags">
                                            <span class="vt-tag solido" :style="{ background: colorPrioridad(i.prioridad) }">{{ catalogos.prioridades[i.prioridad] }}</span>
                                            <span v-if="i.atrasada" class="vt-tag solido" style="background: #d9363e"><WarningOutlined /> Atrasada</span>
                                            <span v-if="i.mia" class="vt-tag" style="background: #e6f6f7; color: #1597a5"><UserOutlined /> Mía</span>
                                        </span>
                                        <b class="truncate">{{ i.titulo }}</b>
                                        <small class="truncate">{{ i.folio }} · {{ i.categoria }}</small>
                                        <small v-if="i.casa || i.ubicacion" class="truncate"><EnvironmentOutlined /> {{ i.casa ? `Casa ${i.casa}` : i.ubicacion }}</small>
                                    </span>
                                    <span class="vt-tag solido ini-cita-est" :style="{ background: colorEstatus(i.estatus) }">{{ catalogos.incidencia_estatus[i.estatus] }}</span>
                                </Link>
                            </div>
                        </div>
                        <div class="vt-small vt-muted mt-2">
                            Fecha compromiso según prioridad: urgente el mismo día, alta en 1 día, media en 3 y baja en 7, contando desde que se reportó.
                            <template v-if="d.agenda.despues"> Hay {{ d.agenda.despues }} más para después de esta semana.</template>
                        </div>
                    </div>

                    <div class="vt-card">
                        <div class="vt-card-title"><h3><InboxOutlined /> Insumos por reponer</h3><Link href="/inventario" class="ini-ver">Inventario <RightOutlined /></Link></div>
                        <div v-if="!d.insumos.length" class="ini-vacio"><CheckCircleOutlined /> Todas las existencias están por arriba del mínimo.</div>
                        <div v-else class="ini-insumos">
                            <div class="ini-insumo ini-insumo-head"><span>#</span><span>Insumo</span><span>Existencia / mínimo</span><span>Nivel</span><span>Estado</span></div>
                            <Link v-for="(x, n) in d.insumos" :key="x.id" href="/inventario" class="ini-insumo">
                                <span class="ini-turno" :style="{ background: colorInsumo(x) }">{{ n + 1 }}</span>
                                <span class="min-w-0"><b class="truncate">{{ x.nombre }}</b><small class="truncate">{{ x.categoria }}{{ x.ubicacion ? ` · ${x.ubicacion}` : '' }}</small></span>
                                <span class="ini-insumo-cant"><b>{{ x.existencia }}</b> / {{ x.minimo }} <small>{{ x.unidad.toLowerCase() }}</small></span>
                                <span class="ini-nivel"><span :style="{ width: Math.max(x.nivel, 3) + '%', background: colorInsumo(x) }"></span></span>
                                <span class="vt-tag solido" :style="{ background: colorInsumo(x) }">{{ estadoInsumo(x) }}</span>
                            </Link>
                        </div>
                    </div>
                </template>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.ini-alerta { color: inherit; background: #fde8e9; border-left-color: var(--vt-red); }
.ini-alerta .anticon { color: var(--vt-red); }
.ini-alerta:hover { filter: brightness(0.98); }

.ini-casa {
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
    padding: 18px 20px; border-radius: 16px; color: #fff; background: var(--vt-gradient);
    box-shadow: 0 12px 28px rgba(26, 90, 150, 0.22);
}
.ini-casa small { opacity: 0.8; font-size: 12px; }
.ini-casa h2 { margin: 2px 0; font-size: 24px; font-weight: 800; color: #fff; }
.ini-casa p { margin: 0; opacity: 0.85; font-size: 13px; }
.ini-casa-saldo { text-align: right; display: flex; flex-direction: column; }
.ini-casa-saldo b { font-size: 24px; line-height: 1.2; }
.ini-casa-saldo span { font-size: 12px; opacity: 0.85; }

.ini-accesos { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
.ini-acceso {
    display: flex; align-items: center; gap: 10px; padding: 11px 12px; border-radius: 14px; background: #fff;
    border: 1px solid var(--vt-line); color: var(--vt-navy); font-weight: 600; font-size: 14px; transition: all 0.2s;
}
.ini-acceso:hover { border-color: var(--c); transform: translateY(-2px); box-shadow: 0 8px 18px rgba(15, 42, 71, 0.08); color: var(--vt-navy); }
.ini-acceso-ic { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: #fff; background: var(--c); font-size: 16px; }
.ini-acceso-flecha { margin-left: auto; color: #b6c3d1; font-size: 11px; }

.ini-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.ini-ancho { grid-column: span 2; }
.ini-completo { grid-column: 1 / -1; }
.ini-ver { font-size: 12.5px; font-weight: 600; color: var(--vt-blue); display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.ini-vacio { display: flex; align-items: center; gap: 8px; color: var(--vt-muted); font-size: 13px; padding: 10px 0; }
.ini-vacio .anticon { color: var(--vt-green); }
.ini-vacio-grande { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; padding: 14px 8px 6px; }
.ini-vacio-grande > span { --c: #1a5a96; width: 48px; height: 48px; border-radius: 16px; display: grid; place-items: center; font-size: 22px; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); }
.ini-vacio-grande b { color: var(--vt-navy); }
.ini-vacio-grande small { color: var(--vt-muted); font-size: 12.5px; max-width: 260px; margin-bottom: 4px; }
.ini-cuenta { min-width: 26px; height: 26px; padding: 0 8px; border-radius: 99px; display: inline-grid; place-items: center; font-weight: 700; font-size: 13px; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); }

.ini-fila { display: flex; align-items: center; gap: 10px; padding: 8px 6px; border-radius: 10px; color: inherit; }
.ini-fila + .ini-fila { border-top: 1px solid #eef2f6; }
a.ini-fila:hover { background: #f6f9fc; color: inherit; }
.ini-fila b, .ini-cola-item b, .ini-cita b, .ini-seg b, .ini-visita b, .ini-insumo b { display: block; font-size: 13.5px; color: var(--vt-navy); }
.ini-fila small, .ini-cola-item small, .ini-cita small, .ini-seg small, .ini-visita small, .ini-insumo small { display: block; font-size: 12px; color: var(--vt-muted); }
.ini-fila-ic { flex: none; width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); }
.ini-monto { color: #d9363e !important; white-space: nowrap; }
.ini-scroll { max-height: 330px; overflow-y: auto; margin-right: -6px; padding-right: 6px; }

/* Avatar con iniciales y mini icono del tipo */
.ini-avatar {
    --c: #1a5a96; position: relative; flex: none; width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center;
    font-weight: 700; font-size: 13.5px; color: var(--c); background: color-mix(in srgb, var(--c) 14%, #fff); border: 2px solid color-mix(in srgb, var(--c) 30%, #fff);
}
.ini-avatar.sm { width: 32px; height: 32px; font-size: 12px; border-width: 1.5px; }
.ini-avatar i { position: absolute; right: -4px; bottom: -4px; width: 18px; height: 18px; border-radius: 50%; display: grid; place-items: center; font-size: 10px; color: #fff; background: var(--c); border: 2px solid #fff; font-style: normal; }

/* Condómino: visitas */
.ini-visitas { display: flex; flex-direction: column; gap: 8px; }
.ini-visita { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 14px; border: 1px solid var(--vt-line); border-left: 4px solid var(--c); color: inherit; transition: all 0.2s; }
.ini-visita:hover { box-shadow: 0 6px 16px rgba(15, 42, 71, 0.08); transform: translateX(2px); color: inherit; }
.ini-visita-hora { margin-top: 2px; color: var(--vt-navy) !important; font-weight: 500; }
.ini-visita-hora .anticon { color: var(--c); }
.ini-pill { flex: none; font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 99px; color: var(--c); background: color-mix(in srgb, var(--c) 12%, #fff); white-space: nowrap; }

/* Condómino: reportes y solicitudes con avance */
.ini-seg { display: block; padding: 11px 12px; border-radius: 14px; border: 1px solid var(--vt-line); border-left: 4px solid var(--c); color: inherit; transition: all 0.2s; }
.ini-seg + .ini-seg { margin-top: 8px; }
.ini-seg:hover { box-shadow: 0 6px 16px rgba(15, 42, 71, 0.08); color: inherit; }
.ini-seg-top { display: flex; align-items: center; gap: 10px; }
.ini-pasos { display: flex; margin-top: 12px; }
.ini-pasos > span { flex: 1; position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; min-width: 0; }
.ini-pasos > span::before { content: ''; position: absolute; top: 5px; right: 50%; width: 100%; height: 2px; background: #e1e8f0; }
.ini-pasos > span:first-child::before { display: none; }
.ini-pasos > span.hecho::before { background: var(--c); }
.ini-pasos i { position: relative; z-index: 1; width: 12px; height: 12px; border-radius: 50%; background: #fff; border: 2px solid #cfd9e4; }
.ini-pasos .hecho i { background: var(--c); border-color: var(--c); }
.ini-pasos small { font-size: 10.5px !important; margin-top: 4px; line-height: 1.15; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.ini-pasos .hecho small { color: var(--vt-navy) !important; font-weight: 600; }
.ini-seg-pie { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--vt-line); font-size: 12px; color: var(--vt-muted); }
.ini-seg-pie span { display: inline-flex; align-items: center; gap: 5px; }

/* Agenda de 3 columnas */
.ini-agenda { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.ini-dia { border-radius: 14px; background: #f7f9fc; border: 1px solid var(--vt-line); padding: 10px; min-height: 120px; }
.ini-dia-head { display: flex; align-items: baseline; gap: 8px; padding: 2px 4px 10px; border-bottom: 3px solid var(--c); margin-bottom: 8px; }
.ini-dia-head b { color: var(--vt-navy); font-size: 15px; }
.ini-dia-head small { color: var(--vt-muted); font-size: 12px; flex: 1; }
.ini-dia-head span { min-width: 24px; height: 22px; padding: 0 7px; border-radius: 99px; display: grid; place-items: center; font-size: 12px; font-weight: 700; color: #fff; background: var(--c); }
.ini-dia-vacio { color: #9aa9b9; font-size: 12.5px; text-align: center; padding: 18px 0; }
.ini-cita { display: flex; align-items: flex-start; gap: 10px; padding: 9px 10px; border-radius: 12px; background: #fff; border: 1px solid var(--vt-line); color: inherit; transition: all 0.2s; }
.ini-cita + .ini-cita { margin-top: 7px; }
.ini-cita:hover { border-color: var(--c); box-shadow: 0 6px 14px rgba(15, 42, 71, 0.07); color: inherit; }
.ini-cita.prioridad { border-left: 4px solid var(--p); }
.ini-cita-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px; }
.ini-cita-tags .vt-tag { font-size: 11px; padding: 1px 8px; }
.ini-cita-est { font-size: 11px; padding: 1px 8px; flex: none; }
.ini-cita-fecha { flex: none; font-weight: 600; color: var(--c) !important; }
.ini-solo-movil { display: none; }

/* Colas numeradas */
.ini-cola { list-style: none; margin: 0; padding: 0; max-height: 330px; overflow-y: auto; }
.ini-cola li + li { border-top: 1px solid #eef2f6; }
.ini-cola-item { display: flex; align-items: center; gap: 10px; padding: 8px 4px; border-radius: 10px; color: inherit; }
.ini-cola-item:hover { background: #f6f9fc; color: inherit; }
.ini-turno { flex: none; width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; font-size: 12.5px; font-weight: 700; color: #fff; background: #1a5a96; }
.ini-tiempo { flex: none; display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: #1a5a96; background: #e6eef8; padding: 3px 8px; border-radius: 99px; }
.ini-tiempo.gris { color: var(--vt-muted); background: #eef2f6; }

/* Insumos */
.ini-insumos { display: flex; flex-direction: column; }
.ini-insumo { display: grid; grid-template-columns: 30px minmax(0, 1.6fr) 150px minmax(80px, 1fr) 110px; align-items: center; gap: 12px; padding: 9px 6px; border-radius: 10px; color: inherit; }
a.ini-insumo:hover { background: #f6f9fc; color: inherit; }
.ini-insumo + .ini-insumo { border-top: 1px solid #eef2f6; }
.ini-insumo-head { font-size: 11.5px; font-weight: 700; color: var(--vt-muted); text-transform: uppercase; letter-spacing: 0.03em; padding-top: 0; }
.ini-insumo-cant { font-size: 13px; color: var(--vt-muted); white-space: nowrap; }
.ini-insumo-cant b { display: inline !important; font-size: 15px !important; }
.ini-insumo-cant small { display: inline !important; }
.ini-insumo .vt-tag { justify-self: start; font-size: 11.5px; }
.ini-nivel { height: 8px; border-radius: 99px; background: #eef2f6; overflow: hidden; }
.ini-nivel span { display: block; height: 100%; border-radius: 99px; }

/* Barras */
.ini-leyenda { font-size: 12px; color: var(--vt-muted); display: inline-flex; align-items: center; gap: 6px; }
.ini-leyenda i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; margin-left: 6px; }
.ini-barras { display: flex; align-items: stretch; gap: 10px; height: 170px; }
.ini-barra-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; min-width: 0; }
.ini-barra-col small { font-size: 11.5px; color: var(--vt-muted); }
.ini-barra-par { flex: 1; width: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 4px; border-bottom: 1px solid var(--vt-line); }
.ini-barra { width: min(22px, 40%); border-radius: 6px 6px 0 0; min-height: 2px; transition: height 0.5s ease; }
.ini-barras-hora { gap: 4px; height: 140px; }
.ini-barras-hora .ini-barra { width: 70%; }

/* Dona */
.ini-dona-wrap { display: flex; align-items: center; gap: 18px; }
.ini-dona { flex: none; width: 150px; height: 150px; }
.ini-dona-seg { transition: stroke-width 0.2s, opacity 0.2s; cursor: pointer; }
.ini-dona-seg.apagado { opacity: 0.35; }
.ini-dona-num { font-size: 8px; font-weight: 800; fill: var(--vt-navy); }
.ini-dona-txt { font-size: 2.8px; fill: var(--vt-muted); text-transform: uppercase; }
.ini-dona-ley { list-style: none; margin: 0; padding: 0; flex: 1; min-width: 0; }
.ini-dona-ley li { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; font-size: 13.5px; cursor: default; transition: background 0.15s; }
.ini-dona-ley li.on { background: #f3f6fa; }
.ini-dona-ley i { flex: none; width: 10px; height: 10px; border-radius: 3px; }
.ini-dona-ley b { color: var(--vt-navy); }
.ini-dona-ley small { width: 36px; text-align: right; color: var(--vt-muted); font-size: 12px; }

@media (max-width: 1280px) {
    .ini-dona-wrap { flex-direction: column; }
    .ini-dona-ley { width: 100%; }
}
@media (max-width: 1100px) {
    .ini-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ini-insumo { grid-template-columns: 30px minmax(0, 1fr) 140px 110px; }
    .ini-insumo .ini-nivel { display: none; }
    .ini-insumo-head span:nth-child(4) { display: none; }
}
@media (max-width: 767px) {
    .ini-grid { grid-template-columns: minmax(0, 1fr); }
    .ini-ancho { grid-column: auto; }
    .ini-accesos { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ini-casa-saldo { text-align: left; }
    .ini-barras { gap: 6px; }
    .ini-barras-hora .ini-barra-col:nth-child(2n) small { visibility: hidden; }
    .ini-dona-wrap { flex-direction: row; }
    /* Agenda: un día a la vez con selector */
    .ini-solo-movil { display: inline-flex; }
    .vt-card-title:has(.ini-solo-movil) { flex-wrap: wrap; }
    .ini-agenda { grid-template-columns: minmax(0, 1fr); }
    .ini-dia { display: none; }
    .ini-dia.visible { display: block; }
    .ini-insumo { grid-template-columns: 26px minmax(0, 1fr) auto; }
    .ini-insumo .ini-insumo-cant { grid-column: 2; grid-row: 2; }
    .ini-insumo .vt-tag { grid-column: 3; grid-row: 1 / span 2; }
    .ini-insumo-head { display: none; }
}
</style>
