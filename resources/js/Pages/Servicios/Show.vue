<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ShopOutlined, ArrowLeftOutlined, LockOutlined, InfoCircleOutlined, DollarOutlined, PlusOutlined, CheckOutlined, CloseOutlined,
    PaperClipOutlined, FileTextOutlined, FilePdfOutlined, FileAddOutlined, RightOutlined, SendOutlined, CommentOutlined, HistoryOutlined,
    PhoneOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import SelectorArchivos from '@/Components/SelectorArchivos.vue';
import VisorArchivo from '@/Components/VisorArchivo.vue';
import GenerarDocumento from '@/Components/GenerarDocumento.vue';
import { confirmar } from '@/composables/confirmar';
import { fechaHora, moneda } from '@/composables/useFechaHora';
import { colorServicio, colorCotizacion } from '@/composables/useServicios';

const props = defineProps({
    solicitud: Object,
    esAdministracion: Boolean,
    catalogos: Object,
    plantillas: Array,
});

const ESTATUS_COT = { PROPUESTA: 'Propuesta', ACEPTADA: 'Aceptada', RECHAZADA: 'Rechazada' };
const fecha = (f) => (f ? String(f).slice(0, 10).split('-').reverse().join('/') : '');
const cerrada = computed(() => ['CONCLUIDA', 'CANCELADA'].includes(props.solicitud.estatus));
const seguimientosRecientes = computed(() => [...props.solicitud.seguimientos].reverse());

// La administración mueve a cualquier estatus; la casa sólo cancela antes de que inicie el trabajo.
const opcionesEstatus = computed(() => {
    if (props.esAdministracion) return Object.keys(props.catalogos.estatus).filter((e) => e !== props.solicitud.estatus && e !== 'NUEVA');
    return ['NUEVA', 'EN_REVISION', 'COTIZADA'].includes(props.solicitud.estatus) ? ['CANCELADA'] : [];
});

const visor = reactive({ open: false, url: null, nombre: '' });
const ver = (url, nombre) => Object.assign(visor, { open: true, url, nombre });

// ===== Seguimiento =====
const adjunto = ref([]);
const seg = useForm({ estatus_nuevo: null, comentario: '', adjunto: null });
const enviarSeguimiento = () => seg.transform((d) => ({ ...d, adjunto: adjunto.value[0] || null })).post(route('servicios.seguimiento', props.solicitud.id), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { seg.reset(); adjunto.value = []; },
});

// ===== Cotizaciones =====
const dialogoCotizacion = ref(false);
const archivoCot = ref([]);
const cot = useForm({ proveedor: '', contacto_proveedor: '', concepto: '', monto: null, notas: '', archivo: null });
const errCot = (k) => ({ 'validate-status': cot.errors[k] ? 'error' : '', help: cot.errors[k] });
const abrirCotizacion = () => { cot.reset(); cot.clearErrors(); archivoCot.value = []; dialogoCotizacion.value = true; };
const guardarCotizacion = () => cot.transform((d) => ({ ...d, archivo: archivoCot.value[0] || null })).post(route('servicios.cotizar', props.solicitud.id), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => (dialogoCotizacion.value = false),
});

const procesando = ref(false);
const responderCotizacion = async (c, accion) => {
    const aceptar = accion === 'aceptar';
    const ok = await confirmar({
        tipo: aceptar ? 'success' : 'danger',
        titulo: aceptar ? '¿Aceptar la cotización?' : '¿Rechazar la cotización?',
        texto: aceptar
            ? `Aceptarás la cotización de ${c.proveedor} por ${moneda(c.monto)}. Las demás se rechazarán y la administración coordinará el servicio.`
            : `Rechazarás la cotización de ${c.proveedor}.`,
        confirmar: aceptar ? 'Sí, aceptar' : 'Sí, rechazar',
    });
    if (!ok) return;
    procesando.value = true;
    router.post(route('servicios.cotizacion.responder', c.id), { accion }, { preserveScroll: true, onFinish: () => (procesando.value = false) });
};

const dialogoDocumento = ref(false);
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader :title="`${solicitud.folio} · ${solicitud.titulo}`" :icon="ShopOutlined">
                <template #titulo><span class="vt-tag solido" :style="{ background: colorServicio(solicitud.estatus), fontSize: '12px' }">{{ catalogos.estatus[solicitud.estatus] }}</span></template>
                <template #subtitulo><LockOutlined /> Privada · Casa {{ solicitud.casa?.identificador }}<template v-if="esAdministracion"> · {{ solicitud.condominio?.nombre_empresa }}</template></template>
                <template #extra><Link :href="route('servicios.index')"><a-button size="large"><ArrowLeftOutlined /> Servicios</a-button></Link></template>
            </PageHeader>

            <div class="grid gap-4 xl:grid-cols-5">
                <!-- ===== Izquierda ===== -->
                <div class="xl:col-span-2 flex flex-col gap-4">
                    <div class="vt-card">
                        <div class="vt-card-title"><h3><InfoCircleOutlined /> Solicitud</h3></div>
                        <p class="whitespace-pre-line mb-3" style="color: var(--vt-navy)">{{ solicitud.descripcion }}</p>
                        <div class="vt-grid-datos">
                            <div class="vt-dato"><span>Tipo</span><span>{{ catalogos.tipos[solicitud.tipo] }}</span></div>
                            <div v-if="solicitud.categoria" class="vt-dato"><span>Servicio</span><span>{{ catalogos.categorias[solicitud.categoria] }}</span></div>
                            <div v-if="solicitud.precio_deseado" class="vt-dato"><span>{{ solicitud.tipo === 'RENTA' ? 'Renta deseada' : 'Precio deseado' }}</span><span>{{ moneda(solicitud.precio_deseado) }}</span></div>
                            <div v-if="solicitud.fecha_disponible" class="vt-dato"><span>Disponible desde</span><span>{{ fecha(solicitud.fecha_disponible) }}</span></div>
                            <div class="vt-dato"><span>Atiende</span><span>{{ solicitud.atiende?.nombre_usuario || 'Sin asignar' }}</span></div>
                        </div>
                        <a-divider style="margin: 12px 0 8px" />
                        <Registro etiqueta="Solicitó" :usuario="solicitud.solicita?.nombre_usuario" :fecha="solicitud.created_at" en-linea />
                    </div>

                    <!-- Cotizaciones -->
                    <div class="vt-card">
                        <div class="vt-card-title">
                            <h3><DollarOutlined /> Cotizaciones de proveedores</h3>
                            <a-button v-if="esAdministracion && !cerrada" size="small" type="primary" @click="abrirCotizacion"><PlusOutlined /> Cotización</a-button>
                        </div>
                        <div v-if="!solicitud.cotizaciones.length" class="vt-empty">
                            {{ esAdministracion ? 'Agrega cotizaciones de proveedores para que la casa elija.' : 'La administración te enviará aquí las cotizaciones.' }}
                        </div>
                        <div class="flex flex-col gap-2">
                            <div v-for="c in solicitud.cotizaciones" :key="c.id" class="sv-cot" :class="c.estatus.toLowerCase()" :style="{ '--c': colorCotizacion(c.estatus) }">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <b style="color: var(--vt-navy)">{{ c.proveedor }}</b>
                                        <div v-if="c.contacto_proveedor" class="vt-small vt-muted"><PhoneOutlined /> {{ c.contacto_proveedor }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold" style="color: var(--vt-navy); font-size: 15px">{{ moneda(c.monto) }}</div>
                                        <span class="vt-tag solido" :style="{ background: colorCotizacion(c.estatus) }">{{ ESTATUS_COT[c.estatus] }}</span>
                                    </div>
                                </div>
                                <div class="mt-1">{{ c.concepto }}</div>
                                <div v-if="c.notas" class="vt-small vt-muted mt-1 whitespace-pre-line">{{ c.notas }}</div>
                                <a-button v-if="c.archivo_url" size="small" class="mt-2" @click="ver(c.archivo_url, c.archivo_nombre)"><PaperClipOutlined /> {{ c.archivo_nombre }}</a-button>
                                <div class="flex gap-4 flex-wrap mt-2">
                                    <Registro etiqueta="Propuso" :usuario="c.registro?.nombre_usuario" :fecha="c.created_at" en-linea />
                                    <Registro v-if="c.respuesta" etiqueta="Respondió" :usuario="c.respuesta.nombre_usuario" :fecha="c.fecha_respuesta" en-linea />
                                </div>
                                <div v-if="!esAdministracion && c.estatus === 'PROPUESTA' && !cerrada" class="flex gap-2 mt-2">
                                    <a-button type="primary" size="small" :loading="procesando" @click="responderCotizacion(c, 'aceptar')"><CheckOutlined /> Aceptar</a-button>
                                    <a-button danger size="small" :disabled="procesando" @click="responderCotizacion(c, 'rechazar')"><CloseOutlined /> Rechazar</a-button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documentos -->
                    <div class="vt-card">
                        <div class="vt-card-title">
                            <h3><FileTextOutlined /> Documentos</h3>
                            <a-button v-if="esAdministracion" size="small" :disabled="!plantillas.length" @click="dialogoDocumento = true"><FileAddOutlined /> Generar</a-button>
                        </div>
                        <div v-if="!solicitud.documentos.length" class="vt-empty">
                            {{ esAdministracion ? (plantillas.length ? 'Genera contratos o convenios con los datos de esta casa.' : 'No hay plantillas. Créalas en Documentos.') : 'Aquí aparecerán los contratos o convenios que te prepare la administración.' }}
                        </div>
                        <Link v-for="d in solicitud.documentos" :key="d.id" :href="route('documentos.show', d.id)" class="vt-row">
                            <FilePdfOutlined style="color: #d9363e; font-size: 20px" />
                            <div class="min-w-0 flex-1">
                                <b class="block truncate">{{ d.titulo }}</b>
                                <Registro :usuario="d.registro?.nombre_usuario" :fecha="d.created_at" en-linea />
                            </div>
                            <RightOutlined class="vt-muted" />
                        </Link>
                    </div>
                </div>

                <!-- ===== Derecha: conversación ===== -->
                <div class="xl:col-span-3 flex flex-col gap-4">
                    <div v-if="!cerrada || esAdministracion" class="vt-card">
                        <div class="vt-card-title"><h3><SendOutlined /> {{ esAdministracion ? 'Responder a la casa' : 'Escribir a la administración' }}</h3></div>
                        <div v-if="opcionesEstatus.length" class="flex flex-wrap gap-2 mb-3">
                            <button type="button" class="is-opcion" :class="{ activa: seg.estatus_nuevo === null }" style="--c: #5d7085" @click="seg.estatus_nuevo = null"><CommentOutlined /> Sólo comentar</button>
                            <button v-for="e in opcionesEstatus" :key="e" type="button" class="is-opcion" :class="{ activa: seg.estatus_nuevo === e }" :style="{ '--c': colorServicio(e) }" @click="seg.estatus_nuevo = e">
                                {{ e === 'CANCELADA' ? 'Cancelar solicitud' : catalogos.estatus[e] }}
                            </button>
                        </div>
                        <a-form layout="vertical">
                            <a-form-item :validate-status="seg.errors.comentario || seg.errors.estatus_nuevo ? 'error' : ''" :help="seg.errors.comentario || seg.errors.estatus_nuevo">
                                <a-textarea v-model:value="seg.comentario" :rows="3" :maxlength="3000" :placeholder="seg.estatus_nuevo === 'CANCELADA' ? 'Motivo de la cancelación' : 'Escribe tu mensaje…'" />
                            </a-form-item>
                            <a-form-item label="Adjunto (opcional)" :validate-status="seg.errors.adjunto ? 'error' : ''" :help="seg.errors.adjunto">
                                <SelectorArchivos v-model:archivos="adjunto" accept="image/*,.pdf,.doc,.docx" ayuda="Imagen, PDF o Word · máx. 10 MB" compacto />
                            </a-form-item>
                            <div class="flex justify-end"><a-button type="primary" size="large" :loading="seg.processing" @click="enviarSeguimiento"><SendOutlined /> Enviar</a-button></div>
                        </a-form>
                    </div>
                    <div v-else class="vt-card"><div class="vt-empty"><LockOutlined /> Solicitud cerrada.</div></div>

                    <div class="vt-card">
                        <div class="vt-card-title"><h3><HistoryOutlined /> Conversación y seguimiento</h3></div>
                        <div class="sv-chat">
                            <div v-for="s in seguimientosRecientes" :key="s.id" class="sv-fila" :class="{ admin: s.de_administracion }">
                                <div class="sv-burbuja" :class="{ admin: s.de_administracion }">
                                    <div class="flex items-center gap-2 flex-wrap mb-1">
                                        <span class="sv-autor">{{ s.de_administracion ? 'Administración' : `Casa ${solicitud.casa?.identificador}` }} · {{ s.usuario?.nombre_usuario }}</span>
                                        <span v-if="s.estatus_nuevo" class="vt-tag solido" :style="{ background: colorServicio(s.estatus_nuevo) }">
                                            <template v-if="s.estatus_anterior">{{ catalogos.estatus[s.estatus_anterior] }} → </template>{{ catalogos.estatus[s.estatus_nuevo] }}
                                        </span>
                                    </div>
                                    <p v-if="s.comentario" class="whitespace-pre-line mb-0">{{ s.comentario }}</p>
                                    <img v-if="s.adjunto_url && s.adjunto_es_imagen" :src="s.adjunto_url" class="sv-img" :alt="s.adjunto_nombre" @click="ver(s.adjunto_url, s.adjunto_nombre)" />
                                    <a-button v-else-if="s.adjunto_url" size="small" class="mt-1" @click="ver(s.adjunto_url, s.adjunto_nombre)"><PaperClipOutlined /> {{ s.adjunto_nombre }}</a-button>
                                    <div class="sv-hora">{{ fechaHora(s.created_at) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <VisorArchivo v-model:open="visor.open" :url="visor.url" :nombre="visor.nombre" />

        <!-- Nueva cotización -->
        <a-modal v-model:open="dialogoCotizacion" width="680px" centered :confirm-loading="cot.processing" ok-text="Enviar a la casa" cancel-text="Cancelar" @ok="guardarCotizacion">
            <template #title>
                <div class="vt-modal-title"><span class="vt-head-icon"><DollarOutlined /></span><div>Nueva cotización<small>{{ solicitud.folio }} · Casa {{ solicitud.casa?.identificador }}</small></div></div>
            </template>
            <a-form layout="vertical">
                <div class="vt-form-grid">
                    <a-form-item label="Proveedor" required v-bind="errCot('proveedor')"><a-input v-model:value="cot.proveedor" :maxlength="150" /></a-form-item>
                    <a-form-item label="Contacto"><a-input v-model:value="cot.contacto_proveedor" :maxlength="150" placeholder="Teléfono / correo" /></a-form-item>
                    <a-form-item label="Concepto" required v-bind="errCot('concepto')"><a-input v-model:value="cot.concepto" :maxlength="255" /></a-form-item>
                    <a-form-item label="Monto" required v-bind="errCot('monto')"><a-input-number v-model:value="cot.monto" :min="0.01" :precision="2" prefix="$" style="width: 100%" /></a-form-item>
                    <a-form-item class="full" label="Notas"><a-textarea v-model:value="cot.notas" :rows="2" :maxlength="2000" placeholder="Tiempo de entrega, garantía, anticipo…" /></a-form-item>
                    <a-form-item class="full" label="Archivo de la cotización" v-bind="errCot('archivo')" style="margin-bottom: 0">
                        <SelectorArchivos v-model:archivos="archivoCot" accept="image/*,.pdf" ayuda="Imagen o PDF · máx. 10 MB" />
                    </a-form-item>
                </div>
            </a-form>
        </a-modal>

        <GenerarDocumento v-if="esAdministracion" v-model:visible="dialogoDocumento" :plantillas="plantillas"
            :id-casa="solicitud.id_casa" :id-empresa="solicitud.id_empresa" :id-solicitud="solicitud.id" />
    </AppLayout>
</template>

<style>
.is-opcion { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; border: 1.5px solid var(--vt-line); color: #44566c; background: #fff; transition: all 0.2s; }
.is-opcion:hover { border-color: var(--c); color: var(--c); }
.is-opcion.activa { border-color: var(--c); background: var(--c); color: #fff; }
.sv-cot { border: 1px solid color-mix(in srgb, var(--c) 35%, #fff); background: color-mix(in srgb, var(--c) 5%, #fff); border-radius: 14px; padding: 12px 14px; }
.sv-cot.rechazada { opacity: 0.7; }
.sv-chat { display: flex; flex-direction: column; gap: 8px; background: #f3f6fa; border-radius: 12px; padding: 12px; max-height: 60vh; overflow-y: auto; }
.sv-fila { display: flex; }
.sv-fila.admin { justify-content: flex-end; }
.sv-burbuja { max-width: 85%; background: #fff; border-radius: 14px 14px 14px 4px; padding: 8px 12px; color: var(--vt-navy); box-shadow: 0 1px 2px rgba(15, 42, 71, 0.08); }
.sv-burbuja.admin { background: #e8f0fa; border-radius: 14px 14px 4px 14px; }
.sv-autor { font-size: 10.5px; font-weight: 700; color: var(--vt-blue); }
.sv-hora { font-size: 10px; color: #8193a6; text-align: right; margin-top: 4px; }
.sv-img { max-width: 240px; max-height: 200px; border-radius: 10px; margin-top: 6px; cursor: zoom-in; display: block; }
</style>
