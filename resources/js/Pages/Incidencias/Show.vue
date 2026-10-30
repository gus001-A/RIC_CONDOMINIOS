<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ToolOutlined, BulbOutlined, ArrowLeftOutlined, InfoCircleOutlined, InboxOutlined, EnvironmentOutlined, UserSwitchOutlined, SaveOutlined,
    SendOutlined, CommentOutlined, PlayCircleOutlined, CheckOutlined, SafetyCertificateOutlined, StopOutlined, RedoOutlined, ArrowRightOutlined,
    PlusOutlined, DeleteOutlined, HistoryOutlined, LinkOutlined, DollarOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import MapaGps from '@/Components/MapaGps.vue';
import Registro from '@/Components/Registro.vue';
import Galeria from '@/Components/Galeria.vue';
import SelectorArchivos from '@/Components/SelectorArchivos.vue';
import { fechaHora, moneda } from '@/composables/useFechaHora';
import { colorEstatus, colorPrioridad } from '@/composables/useIncidencias';

const props = defineProps({
    incidencia: Object,
    totalGastos: Number,
    transiciones: Array,
    puedeAtender: Boolean,
    puedeSupervisar: Boolean,
    personalMantenimiento: Array,
    catalogos: Object,
    materiales: { type: Array, default: () => [] },
    insumosDisponibles: { type: Array, default: () => [] },
});

const opciones = (obj) => Object.entries(obj).map(([value, label]) => ({ value, label }));
const cantidad = (n) => Number(n).toLocaleString('es-MX', { maximumFractionDigits: 3 });
const costoMateriales = computed(() => props.materiales.reduce((s, m) => s + (-m.cantidad * (m.costo_unitario || 0)), 0));

const PASOS = ['PENDIENTE', 'EN PROCESO', 'ATENDIDA', 'SUPERVISADA'];
// SUPERVISADA es el final: todos los pasos quedan como completados.
const indicePaso = computed(() => (props.incidencia.estatus === 'SUPERVISADA' ? PASOS.length : PASOS.indexOf(props.incidencia.estatus)));
const cerrada = computed(() => ['SUPERVISADA', 'CANCELADA'].includes(props.incidencia.estatus));
const seguimientosRecientes = computed(() => [...props.incidencia.seguimientos].reverse());

const fechaPaso = (paso) => {
    const i = props.incidencia;
    const f = { PENDIENTE: i.created_at, 'EN PROCESO': i.fecha_inicio, ATENDIDA: i.fecha_atencion, SUPERVISADA: i.fecha_supervision }[paso];
    return f ? fechaHora(f) : '';
};

const ROLES = { CONDOMINO: 'Condómino', MANTENIMIENTO: 'Mantenimiento', VIGILANCIA: 'Vigilancia' };
const rol = (tipo) => ROLES[tipo] || 'Administración';

const textoTransicion = (e) => ({
    'EN PROCESO': props.incidencia.estatus === 'ATENDIDA' ? 'Regresar a proceso' : 'Tomar / en proceso',
    ATENDIDA: 'Marcar atendida',
    SUPERVISADA: 'Supervisar y cerrar',
    CANCELADA: 'Cancelar',
    PENDIENTE: 'Reabrir',
}[e] || e);
const iconoTransicion = (e) => ({
    'EN PROCESO': PlayCircleOutlined, ATENDIDA: CheckOutlined, SUPERVISADA: SafetyCertificateOutlined, CANCELADA: StopOutlined, PENDIENTE: RedoOutlined,
}[e] || ArrowRightOutlined);

// ===== Seguimiento =====
const fotos = ref([]);
const seg = useForm({ estatus_nuevo: null, comentario: '', fotos: [], gastos: [], insumos: [] });
const errorDe = (pre) => Object.entries(seg.errors).find(([k]) => k.startsWith(pre))?.[1];
const placeholderComentario = computed(() => ({
    ATENDIDA: '¿Qué se hizo para resolverlo?',
    CANCELADA: '¿Por qué se cancela?',
    SUPERVISADA: 'Comentario de la supervisión (opcional)',
}[seg.estatus_nuevo] || 'Escribe un comentario…'));

const enviarSeguimiento = () => {
    seg.transform((d) => ({ ...d, fotos: fotos.value })).post(route('incidencias.seguimiento', props.incidencia.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { seg.reset(); fotos.value = []; },
    });
};
const fotosDe = (s) => s.fotos.map((f) => ({ id: f.id, url: f.url, nombre: `Foto ${props.incidencia.folio}`, mime: 'image/jpeg' }));

// ===== Asignación =====
const asignacion = useForm({ id_usuario_asignado: props.incidencia.id_usuario_asignado, prioridad: props.incidencia.prioridad });
const asignar = () => asignacion.post(route('incidencias.asignar', props.incidencia.id), { preserveScroll: true, onSuccess: () => asignacion.defaults() });

// ===== Gastos (administración) =====
const dialogoGasto = ref(false);
const gastoSel = ref(null);
const gastoForm = useForm({ accion: 'ligar', id_poliza: null, motivo: '' });
const polizas = ref([]);
let tp = null;
const buscarPolizas = (q = '') => {
    clearTimeout(tp);
    tp = setTimeout(async () => {
        const { data } = await axios.get(route('incidencias.polizas-egreso', props.incidencia.id), { params: { q } });
        polizas.value = data;
    }, 250);
};
const abrirGasto = (g, accion) => {
    gastoSel.value = g;
    gastoForm.reset();
    gastoForm.clearErrors();
    gastoForm.accion = accion;
    gastoForm.id_poliza = g.id_poliza;
    dialogoGasto.value = true;
    if (accion === 'ligar') buscarPolizas();
};
const guardarGasto = () => gastoForm.post(route('incidencias.gasto', gastoSel.value.id), { preserveScroll: true, onSuccess: () => (dialogoGasto.value = false) });
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader :title="`${incidencia.folio} · ${incidencia.titulo}`" :icon="incidencia.tipo === 'SUGERENCIA' ? BulbOutlined : ToolOutlined"
                :subtitle="`${catalogos.categorias[incidencia.categoria]} · ${incidencia.condominio?.nombre_empresa || ''}`">
                <template #titulo><span class="vt-tag solido" :style="{ background: colorEstatus(incidencia.estatus), fontSize: '12px' }">{{ catalogos.estatus[incidencia.estatus] }}</span></template>
                <template #extra><Link :href="route('incidencias.index')"><a-button size="large"><ArrowLeftOutlined /> Incidencias</a-button></Link></template>
            </PageHeader>

            <!-- Flujo -->
            <div class="vt-card mb-4">
                <a-steps :current="Math.min(indicePaso, PASOS.length - 1)" :status="incidencia.estatus === 'CANCELADA' ? 'error' : (indicePaso >= PASOS.length ? 'finish' : 'process')" size="small" responsive>
                    <a-step v-for="paso in PASOS" :key="paso" :title="catalogos.estatus[paso]" :description="fechaPaso(paso)" />
                </a-steps>
                <div v-if="incidencia.estatus === 'CANCELADA'" class="vt-alert error mt-3 mb-0">
                    <StopOutlined /> Cancelada {{ fechaHora(incidencia.fecha_cancelacion) }}
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-5">
                <!-- ===== Izquierda: datos ===== -->
                <div class="xl:col-span-2 flex flex-col gap-4">
                    <div class="vt-card">
                        <div class="vt-card-title"><h3><InfoCircleOutlined /> Reporte</h3></div>
                        <p class="whitespace-pre-line mb-3" style="color: var(--vt-navy)">{{ incidencia.descripcion }}</p>
                        <div class="vt-grid-datos">
                            <div class="vt-dato"><span>Tipo</span><span>{{ catalogos.tipos[incidencia.tipo] }}</span></div>
                            <div class="vt-dato"><span>Prioridad</span><span><span class="vt-tag solido" :style="{ background: colorPrioridad(incidencia.prioridad) }">{{ catalogos.prioridades[incidencia.prioridad] }}</span></span></div>
                            <div class="vt-dato full"><span>Ubicación</span><span>{{ incidencia.ubicacion || '—' }}</span></div>
                            <div class="vt-dato"><span>Reportó</span><span>{{ incidencia.reporta?.nombre_usuario }}<template v-if="incidencia.casa"> · Casa {{ incidencia.casa.identificador }}</template></span></div>
                            <div class="vt-dato"><span>Fecha</span><span>{{ fechaHora(incidencia.created_at) }}</span></div>
                            <div class="vt-dato"><span>Asignado a</span><span>{{ incidencia.asignado?.nombre_completo || 'Sin asignar' }}</span></div>
                            <div class="vt-dato"><span>Gastos</span><span :style="{ fontWeight: 700, color: totalGastos ? '#a86f00' : undefined }">{{ moneda(totalGastos) }}</span></div>
                        </div>
                    </div>

                    <div v-if="materiales.length" class="vt-card">
                        <div class="vt-card-title"><h3><InboxOutlined /> Material del almacén</h3><b style="color: #a86f00">{{ moneda(costoMateriales) }}</b></div>
                        <div v-for="m in materiales" :key="m.id" class="vt-row" style="cursor: default">
                            <div class="min-w-0 flex-1">
                                <b class="block truncate">{{ m.insumo?.nombre }}</b>
                                <Registro :usuario="m.usuario?.nombre_usuario" :fecha="m.created_at" en-linea />
                            </div>
                            <div class="text-right vt-nowrap">
                                <div>{{ cantidad(-m.cantidad) }} {{ m.insumo?.unidad.toLowerCase() }}</div>
                                <b>{{ moneda(-m.cantidad * (m.costo_unitario || 0)) }}</b>
                            </div>
                        </div>
                        <p class="vt-small vt-muted mt-2 mb-0">Costo al precio promedio del almacén.</p>
                    </div>

                    <div v-if="incidencia.latitud !== null" class="vt-card">
                        <div class="vt-card-title"><h3><EnvironmentOutlined /> Ubicación</h3></div>
                        <MapaGps :lat="Number(incidencia.latitud)" :lng="Number(incidencia.longitud)" height="240px" />
                    </div>

                    <!-- Asignación (administración) -->
                    <div v-if="puedeSupervisar && !cerrada" class="vt-card">
                        <div class="vt-card-title"><h3><UserSwitchOutlined /> Asignación</h3></div>
                        <a-form layout="vertical">
                            <div class="vt-form-grid">
                                <a-form-item label="Mantenimiento" :validate-status="asignacion.errors.id_usuario_asignado ? 'error' : ''"
                                    :help="asignacion.errors.id_usuario_asignado || (!personalMantenimiento.length ? 'No hay usuarios de Mantenimiento en este condominio.' : '')">
                                    <a-select v-model:value="asignacion.id_usuario_asignado" allow-clear placeholder="Sin asignar" show-search option-filter-prop="label"
                                        :options="personalMantenimiento.map((p) => ({ value: p.id, label: p.nombre }))" />
                                </a-form-item>
                                <a-form-item label="Prioridad"><a-select v-model:value="asignacion.prioridad" :options="opciones(catalogos.prioridades)" /></a-form-item>
                            </div>
                            <div class="flex justify-end">
                                <a-button type="primary" :loading="asignacion.processing" :disabled="!asignacion.isDirty" @click="asignar"><SaveOutlined /> Guardar</a-button>
                            </div>
                        </a-form>
                    </div>
                </div>

                <!-- ===== Derecha: seguimiento ===== -->
                <div class="xl:col-span-3 flex flex-col gap-4">
                    <div v-if="!cerrada || transiciones.length" class="vt-card">
                        <div class="vt-card-title"><h3><SendOutlined /> {{ cerrada ? 'Reabrir' : 'Agregar seguimiento' }}</h3></div>
                        <a-form layout="vertical">
                            <div v-if="transiciones.length" class="flex flex-wrap gap-2 mb-3">
                                <button v-if="!cerrada" type="button" class="is-opcion" :class="{ activa: seg.estatus_nuevo === null }" style="--c: #5d7085" @click="seg.estatus_nuevo = null">
                                    <CommentOutlined /> Sólo comentar
                                </button>
                                <button v-for="e in transiciones" :key="e" type="button" class="is-opcion" :class="{ activa: seg.estatus_nuevo === e }" :style="{ '--c': colorEstatus(e) }" @click="seg.estatus_nuevo = e">
                                    <component :is="iconoTransicion(e)" /> {{ textoTransicion(e) }}
                                </button>
                            </div>

                            <a-form-item :validate-status="seg.errors.comentario || seg.errors.estatus_nuevo ? 'error' : ''" :help="seg.errors.comentario || seg.errors.estatus_nuevo">
                                <a-textarea v-model:value="seg.comentario" :rows="3" :maxlength="3000" :placeholder="placeholderComentario" />
                            </a-form-item>

                            <a-form-item label="Fotos (hasta 5)" :validate-status="errorDe('fotos') ? 'error' : ''" :help="errorDe('fotos')">
                                <SelectorArchivos v-model:archivos="fotos" :max="5" solo-imagenes :max-mb="8" compacto texto="Arrastra o elige fotos del avance" />
                            </a-form-item>

                            <!-- Gastos de la reparación -->
                            <div v-if="puedeAtender && !cerrada" class="vt-seccion mb-3">
                                <div class="vt-seccion-t justify-between">
                                    <span class="flex items-center gap-2"><DollarOutlined /> Gastos de la reparación</span>
                                    <a-button size="small" @click="seg.gastos.push({ concepto: '', monto: null, proveedor: '' })"><PlusOutlined /> Gasto</a-button>
                                </div>
                                <div v-if="!seg.gastos.length" class="vt-small vt-muted">Sin gastos en este seguimiento.</div>
                                <div v-for="(g, i) in seg.gastos" :key="i" class="is-linea">
                                    <a-input v-model:value="g.concepto" placeholder="Concepto (material, mano de obra…)" :maxlength="150" style="flex: 3" />
                                    <a-input v-model:value="g.proveedor" placeholder="Proveedor" :maxlength="150" style="flex: 2" />
                                    <a-input-number v-model:value="g.monto" :min="0.01" :precision="2" prefix="$" placeholder="Monto" style="flex: 1.3" />
                                    <a-button class="vt-ab rojo" @click="seg.gastos.splice(i, 1)"><DeleteOutlined /></a-button>
                                </div>
                                <div v-if="errorDe('gastos')" class="vt-small" style="color: #d9363e">{{ errorDe('gastos') }}</div>
                            </div>

                            <!-- Material del almacén -->
                            <div v-if="insumosDisponibles.length && !cerrada" class="vt-seccion mb-3">
                                <div class="vt-seccion-t justify-between">
                                    <span class="flex items-center gap-2"><InboxOutlined /> Material del almacén</span>
                                    <a-button size="small" @click="seg.insumos.push({ id_insumo: null, cantidad: null })"><PlusOutlined /> Material</a-button>
                                </div>
                                <div v-if="!seg.insumos.length" class="vt-small vt-muted">Se descuenta del inventario al guardar.</div>
                                <div v-for="(m, i) in seg.insumos" :key="i" class="is-linea">
                                    <a-select v-model:value="m.id_insumo" placeholder="¿Qué material?" show-search option-filter-prop="label" style="flex: 4"
                                        :options="insumosDisponibles.map((x) => ({ value: x.id, label: `${x.nombre} — hay ${cantidad(x.existencia)} ${x.unidad.toLowerCase()}` }))" />
                                    <a-input-number v-model:value="m.cantidad" :min="0.001" :step="1" placeholder="Cantidad" style="flex: 1.3" />
                                    <a-button class="vt-ab rojo" @click="seg.insumos.splice(i, 1)"><DeleteOutlined /></a-button>
                                </div>
                                <div v-if="errorDe('insumos')" class="vt-small" style="color: #d9363e">{{ errorDe('insumos') }}</div>
                            </div>

                            <div class="flex justify-end">
                                <a-button type="primary" size="large" :loading="seg.processing" @click="enviarSeguimiento">
                                    <component :is="seg.estatus_nuevo ? iconoTransicion(seg.estatus_nuevo) : SendOutlined" /> {{ seg.estatus_nuevo ? textoTransicion(seg.estatus_nuevo) : 'Comentar' }}
                                </a-button>
                            </div>
                        </a-form>
                    </div>

                    <!-- Línea de tiempo -->
                    <div class="vt-card">
                        <div class="vt-card-title"><h3><HistoryOutlined /> Seguimiento</h3></div>
                        <a-timeline>
                            <a-timeline-item v-for="s in seguimientosRecientes" :key="s.id" :color="s.estatus_nuevo ? colorEstatus(s.estatus_nuevo) : '#cbd5e1'">
                                <div class="is-evento" :class="{ cambio: s.estatus_nuevo }">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span v-if="s.estatus_nuevo" class="vt-tag solido" :style="{ background: colorEstatus(s.estatus_nuevo) }">
                                            <template v-if="s.estatus_anterior">{{ catalogos.estatus[s.estatus_anterior] }} → </template>{{ catalogos.estatus[s.estatus_nuevo] }}
                                        </span>
                                        <Registro :etiqueta="rol(s.usuario?.tipo_usuario) + ' ·'" :usuario="s.usuario?.nombre_usuario" :fecha="s.created_at" en-linea />
                                    </div>
                                    <p v-if="s.comentario" class="mt-2 mb-0 whitespace-pre-line" style="color: var(--vt-navy)">{{ s.comentario }}</p>
                                    <Galeria v-if="s.fotos.length" class="mt-2" :items="fotosDe(s)" tamano="chico" />
                                    <div v-if="s.gastos.length" class="is-gastos mt-2">
                                        <div v-for="g in s.gastos" :key="g.id" class="is-gasto" :class="{ cancelado: g.cancelado }">
                                            <div class="min-w-0 flex-1">
                                                <span class="tachable">{{ g.concepto }}<span v-if="g.proveedor" class="vt-muted"> · {{ g.proveedor }}</span></span>
                                                <div v-if="g.poliza" class="vt-small" style="color: #1a5a96"><LinkOutlined /> Póliza {{ g.poliza.folio }}</div>
                                                <div v-if="g.cancelado" class="vt-small" style="color: #d9363e">Cancelado: {{ g.motivo_cancelacion }} ({{ g.modifico?.nombre_usuario }})</div>
                                            </div>
                                            <b class="tachable vt-nowrap">{{ moneda(g.monto) }}</b>
                                            <div v-if="puedeSupervisar && !g.cancelado" class="vt-acciones-fila">
                                                <a-tooltip title="Ligar a póliza de egreso"><a-button size="small" class="vt-ab azul" @click="abrirGasto(g, 'ligar')"><LinkOutlined /></a-button></a-tooltip>
                                                <a-tooltip title="Cancelar gasto"><a-button size="small" class="vt-ab rojo" @click="abrirGasto(g, 'cancelar')"><StopOutlined /></a-button></a-tooltip>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a-timeline-item>
                        </a-timeline>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gasto: ligar póliza / cancelar -->
        <a-modal v-model:open="dialogoGasto" width="540px" centered :confirm-loading="gastoForm.processing" cancel-text="Cerrar"
            :ok-text="gastoForm.accion === 'ligar' ? 'Guardar' : 'Cancelar gasto'" :ok-button-props="{ danger: gastoForm.accion !== 'ligar' }" @ok="guardarGasto">
            <template #title>
                <div class="vt-modal-title">
                    <span class="vt-head-icon" :style="gastoForm.accion === 'ligar' ? '' : 'background: linear-gradient(135deg, #b42318, #d9363e)'"><component :is="gastoForm.accion === 'ligar' ? LinkOutlined : StopOutlined" /></span>
                    <div>{{ gastoForm.accion === 'ligar' ? 'Ligar gasto a póliza de egreso' : 'Cancelar gasto' }}<small v-if="gastoSel">{{ gastoSel.concepto }} · {{ moneda(gastoSel.monto) }}</small></div>
                </div>
            </template>
            <a-form layout="vertical">
                <a-form-item v-if="gastoForm.accion === 'ligar'" label="Póliza de egreso" :validate-status="gastoForm.errors.id_poliza ? 'error' : ''" :help="gastoForm.errors.id_poliza">
                    <a-select v-model:value="gastoForm.id_poliza" show-search allow-clear :filter-option="false" placeholder="Buscar folio o concepto"
                        :options="polizas.map((p) => ({ value: p.id, label: p.etiqueta }))" @search="buscarPolizas" />
                </a-form-item>
                <a-form-item v-else label="Motivo" required :validate-status="gastoForm.errors.motivo ? 'error' : ''" :help="gastoForm.errors.motivo">
                    <a-textarea v-model:value="gastoForm.motivo" :rows="2" :maxlength="255" />
                </a-form-item>
            </a-form>
        </a-modal>
    </AppLayout>
</template>

<style>
.is-opcion { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; border: 1.5px solid var(--vt-line); color: #44566c; background: #fff; transition: all 0.2s; }
.is-opcion:hover { border-color: var(--c); color: var(--c); }
.is-opcion.activa { border-color: var(--c); background: var(--c); color: #fff; box-shadow: 0 4px 12px rgba(15, 42, 71, 0.15); }
.is-linea { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
.is-evento { background: #fff; border: 1px solid var(--vt-line); border-radius: 12px; padding: 10px 14px; }
.is-evento.cambio { background: #fafcfe; }
.is-gastos { border-top: 1px dashed var(--vt-line); }
.is-gasto { display: flex; gap: 10px; align-items: center; padding: 6px 0; border-bottom: 1px dashed #eef2f7; font-size: 13px; }
.is-gasto.cancelado { color: #94a3b8; }
.is-gasto.cancelado .tachable { text-decoration: line-through; }
</style>
