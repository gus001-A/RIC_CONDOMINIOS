<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Mensajes" :icon="MessageOutlined"
                :subtitle="esAdministracion ? 'Conversaciones con las casas de tus condominios' : `Comunicación de tu casa ${miCasa?.identificador || ''} con la administración`">
                <template #extra>
                    <a-button type="primary" size="large" :disabled="!esAdministracion && !miCasa" @click="abrirNueva"><FormOutlined /> Nueva conversación</a-button>
                </template>
            </PageHeader>

            <div v-if="!esAdministracion && !miCasa" class="vt-card">
                <a-result status="info" title="Sin casa asignada" sub-title="Tu usuario no está ligado a una casa activa. Pide a la administración que te registre como condómino." />
            </div>

            <div v-else class="mensajeria" :class="{ 'con-chat': actual }">
                <!-- ================= BANDEJA ================= -->
                <aside class="bandeja vt-card">
                    <div class="bandeja-filtros">
                        <a-input v-model:value="filtros.buscar" allow-clear placeholder="Buscar asunto, casa o texto…" @change="buscarConRetraso">
                            <template #prefix><SearchOutlined class="vt-muted" /></template>
                        </a-input>
                        <a-segmented v-model:value="filtros.estatus" block size="small" @change="cargarBandeja"
                            :options="[{ value: '', label: 'Todas' }, { value: 'ABIERTA', label: 'Abiertas' }, { value: 'CERRADA', label: 'Cerradas' }]" />
                        <div class="flex gap-2 items-center">
                            <a-select v-model:value="filtros.categoria" allow-clear placeholder="Categoría" size="small" class="flex-1"
                                :options="Object.entries(categorias).map(([value, label]) => ({ value, label }))" @change="cargarBandeja" />
                            <a-select v-if="condominios.length > 1" v-model:value="filtros.id_empresa" allow-clear placeholder="Condominio" size="small" class="flex-1"
                                show-search option-filter-prop="label" :options="condominios.map((c) => ({ value: c.id, label: c.nombre_empresa }))" @change="cargarBandeja" />
                            <a-checkbox v-model:checked="filtros.sin_leer" class="whitespace-nowrap" @change="cargarBandeja">Sin leer</a-checkbox>
                        </div>
                    </div>

                    <div class="bandeja-lista">
                        <div v-if="cargandoBandeja && !conversaciones.length" class="p-4"><a-skeleton active avatar :paragraph="{ rows: 2 }" /></div>
                        <a-empty v-else-if="!conversaciones.length" class="mt-8" description="No hay conversaciones." />
                        <button v-for="c in conversaciones" :key="c.id" type="button" class="item"
                            :class="{ activo: actual?.id === c.id, 'sin-leer': c.no_leidos, cerrada: c.estatus === 'CERRADA' }" @click="abrir(c.id)">
                            <a-avatar :size="40" class="item-av" :style="{ background: c.no_leidos ? '#1a5a96' : '#94a3b8' }"><HomeOutlined /></a-avatar>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="item-casa">Casa {{ c.casa }}</span>
                                    <span class="item-fecha">{{ cuando(c.ultimo_mensaje_at) }}</span>
                                </div>
                                <div class="item-asunto">{{ c.asunto }}</div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="item-texto"><b v-if="c.ultimo_autor">{{ c.ultimo_autor }}:</b> {{ c.ultimo_texto }}</span>
                                    <span v-if="c.no_leidos" class="globo">{{ c.no_leidos }}</span>
                                    <LockOutlined v-else-if="c.estatus === 'CERRADA'" class="vt-muted" />
                                </div>
                            </div>
                        </button>
                    </div>
                </aside>

                <!-- ================= CHAT ================= -->
                <section class="chat vt-card">
                    <div v-if="!actual" class="chat-vacio">
                        <MessageOutlined />
                        <p>Selecciona una conversación o inicia una nueva.</p>
                    </div>

                    <template v-else>
                        <header class="chat-header">
                            <a-button class="volver" type="text" @click="cerrarChat"><ArrowLeftOutlined /></a-button>
                            <span class="vt-head-icon" style="width: 40px; height: 40px; font-size: 18px"><MessageOutlined /></span>
                            <div class="min-w-0 flex-1">
                                <div class="chat-asunto truncate">{{ actual.asunto }}</div>
                                <div class="flex gap-1 flex-wrap items-center mt-1">
                                    <span class="vt-tag" style="background: #e8f0fa; color: #1a5a96"><HomeOutlined /> Casa {{ actual.casa?.identificador }}</span>
                                    <span v-if="esAdministracion" class="vt-small vt-muted">{{ actual.condominio?.nombre_empresa }}</span>
                                    <span class="vt-tag" style="background: #eef2f7; color: #44566c">{{ categorias[actual.categoria] || actual.categoria }}</span>
                                    <span class="vt-tag solido" :style="{ background: actual.estatus === 'ABIERTA' ? '#1faf5c' : '#94a3b8' }">{{ actual.estatus === 'ABIERTA' ? 'Abierta' : 'Cerrada' }}</span>
                                </div>
                            </div>
                            <a-button v-if="actual.estatus === 'ABIERTA' || esAdministracion" :loading="enviando" :type="actual.estatus === 'ABIERTA' ? 'default' : 'primary'" @click="cambiarEstatus">
                                <component :is="actual.estatus === 'ABIERTA' ? LockOutlined : UnlockOutlined" /> {{ actual.estatus === 'ABIERTA' ? 'Cerrar' : 'Reabrir' }}
                            </a-button>
                        </header>

                        <div class="chat-cuerpo">
                            <div ref="contenedorMensajes" class="chat-mensajes" @scroll="alDesplazar">
                                <div class="chat-inicio">
                                    Iniciada por <b>{{ actual.inicio?.nombre_usuario }}</b>
                                    {{ actual.iniciada_por_administracion ? '(Administración)' : `(Casa ${actual.casa?.identificador})` }}
                                    · {{ fechaHora(actual.created_at) }}
                                </div>

                                <template v-for="item in itemsChat" :key="item.clave">
                                    <div v-if="item.separador" class="separador"><span>{{ item.separador }}</span></div>
                                    <div v-else class="burbuja-fila" :class="{ mio: esMio(item.m), seguido: item.seguido }">
                                        <div class="burbuja" :class="{ admin: item.m.de_administracion, error: item.m.estado === 'error' }">
                                            <div v-if="!item.seguido" class="burbuja-autor">
                                                {{ item.m.de_administracion ? 'Administración' : `Casa ${actual.casa?.identificador}` }} · {{ item.m.usuario?.nombre_usuario }}
                                            </div>
                                            <p v-if="item.m.texto" class="whitespace-pre-line">{{ item.m.texto }}</p>
                                            <img v-if="item.m.adjunto_url && item.m.adjunto_es_imagen" :src="item.m.adjunto_url" :alt="item.m.adjunto_nombre" class="burbuja-img"
                                                @load="siEstabaAbajo" @click="verAdjunto(item.m.adjunto_url, item.m.adjunto_nombre)" />
                                            <button v-else-if="item.m.adjunto_url" type="button" class="burbuja-archivo" @click="verAdjunto(item.m.adjunto_url, item.m.adjunto_nombre)">
                                                <component :is="ICONO_TIPO[tipoArchivo(item.m.adjunto_nombre)]" :style="{ color: COLOR_TIPO[tipoArchivo(item.m.adjunto_nombre)] }" /> {{ item.m.adjunto_nombre }}
                                            </button>
                                            <img v-else-if="item.m.adjunto_preview" :src="item.m.adjunto_preview" class="burbuja-img" style="opacity: 0.7" alt="" />
                                            <span v-else-if="item.m.adjunto_local" class="burbuja-archivo"><PaperClipOutlined /> {{ item.m.adjunto_local }}</span>
                                            <div class="burbuja-hora">
                                                {{ hora(item.m.created_at) }}
                                                <template v-if="esMio(item.m)">
                                                    <ClockCircleOutlined v-if="item.m.estado === 'enviando'" class="palomita" title="Enviando…" />
                                                    <ExclamationCircleOutlined v-else-if="item.m.estado === 'error'" class="palomita" style="color: #d9363e" title="No se envió" />
                                                    <span v-else class="palomita" :class="{ visto: fueVisto(item.m) }" :title="fueVisto(item.m) ? 'Visto' : 'Enviado'">{{ fueVisto(item.m) ? '✓✓' : '✓' }}</span>
                                                </template>
                                            </div>
                                            <div v-if="item.m.estado === 'error'" class="flex gap-2 mt-1 items-center vt-small">
                                                <span style="color: #d9363e">{{ item.m.error }}</span>
                                                <a-button size="small" type="link" style="padding: 0" @click="reintentar(item.m)">Reintentar</a-button>
                                                <a-button size="small" type="link" danger style="padding: 0" @click="descartarFallido(item.m)">Descartar</a-button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div v-if="actual.estatus === 'CERRADA'" class="chat-inicio mt-2">
                                    <LockOutlined /> Cerrada por <b>{{ actual.cierre?.nombre_usuario }}</b> · {{ fechaHora(actual.fecha_cierre) }}
                                </div>
                            </div>

                            <button v-if="nuevosSinVer" type="button" class="pildora-nuevos" @click="bajar(true)">
                                <ArrowDownOutlined /> {{ nuevosSinVer }} mensaje{{ nuevosSinVer > 1 ? 's' : '' }} nuevo{{ nuevosSinVer > 1 ? 's' : '' }}
                            </button>
                        </div>

                        <div class="escribiendo" :class="{ visible: escribiendo }">
                            <span class="puntos"><i></i><i></i><i></i></span> {{ escribiendo }} está escribiendo…
                        </div>

                        <form v-if="actual.estatus === 'ABIERTA' || esAdministracion" class="chat-redactar" @submit.prevent="enviar">
                            <!-- Vista previa del adjunto antes de enviarlo -->
                            <div v-if="adjunto" class="adjunto-previo">
                                <img v-if="urlAdjunto && tipoArchivo(adjunto.name, adjunto.type) === 'imagen'" :src="urlAdjunto" alt="" @click="verAdjunto(null, adjunto.name, adjunto)" />
                                <span v-else class="adjunto-ic" :style="{ color: COLOR_TIPO[tipoArchivo(adjunto.name, adjunto.type)] }" @click="verAdjunto(null, adjunto.name, adjunto)">
                                    <component :is="ICONO_TIPO[tipoArchivo(adjunto.name, adjunto.type)]" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <b class="block truncate">{{ adjunto.name }}</b>
                                    <small class="vt-muted">{{ tamano(adjunto.size) }} · <a @click="verAdjunto(null, adjunto.name, adjunto)">Ver</a></small>
                                </div>
                                <a-tooltip title="Quitar"><a-button type="text" danger size="small" @click="quitarAdjunto()"><CloseOutlined /></a-button></a-tooltip>
                            </div>
                            <div class="flex gap-2 items-end">
                                <a-dropdown :trigger="['click']" placement="topLeft">
                                    <a-button size="large" shape="circle" title="Adjuntar foto o documento"><PaperClipOutlined /></a-button>
                                    <template #overlay>
                                        <a-menu>
                                            <a-menu-item key="archivo" @click="inputAdjunto?.click()"><FolderOpenOutlined style="color: #1a5a96" /> Elegir archivo</a-menu-item>
                                            <a-menu-item key="camara" @click="camaraAbierta = true"><CameraOutlined style="color: #7c4ddb" /> Tomar fotografía</a-menu-item>
                                        </a-menu>
                                    </template>
                                </a-dropdown>
                                <input ref="inputAdjunto" type="file" style="display: none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" @change="elegirAdjunto($event.target.files[0])" />
                                <textarea ref="cajaTexto" v-model="texto" rows="1" class="redactar-texto"
                                    :placeholder="actual.estatus === 'CERRADA' ? 'Escribir reabre la conversación…' : (tactil ? 'Escribe un mensaje…' : 'Escribe un mensaje… (Enter envía, Shift + Enter nueva línea)')"
                                    @keydown.enter="alEnter" @input="alEscribir"></textarea>
                                <a-button type="primary" size="large" shape="circle" html-type="submit" :disabled="!texto.trim() && !adjunto" title="Enviar"><SendOutlined /></a-button>
                            </div>
                        </form>
                        <div v-else class="chat-cerrada"><LockOutlined /> Conversación cerrada. Si necesitas algo más, inicia una nueva.</div>
                    </template>
                </section>
            </div>
        </div>

        <CamaraModal v-model:open="camaraAbierta" @foto="elegirAdjunto" />
        <VisorArchivo v-model:open="visor.open" :url="visor.url" :file="visor.file" :nombre="visor.nombre" />

        <!-- ================= NUEVA CONVERSACIÓN ================= -->
        <a-modal v-model:open="dialogoNueva" width="680px" centered :confirm-loading="enviando" ok-text="Enviar" cancel-text="Cancelar" @ok="crear">
            <template #title>
                <div class="vt-modal-title"><span class="vt-head-icon"><FormOutlined /></span><div>Nueva conversación<small>Llega al momento del otro lado</small></div></div>
            </template>
            <a-form layout="vertical">
                <div class="vt-form-grid">
                    <a-form-item v-if="esAdministracion" class="full" label="Casa" required :validate-status="erroresNueva.id_casa ? 'error' : ''" :help="erroresNueva.id_casa">
                        <a-select v-model:value="nueva.id_casa" show-search option-filter-prop="label" placeholder="¿A qué casa escribes?"
                            :options="casas.map((c) => ({ value: c.id, label: c.etiqueta }))" />
                    </a-form-item>
                    <a-form-item label="Asunto" required :validate-status="erroresNueva.asunto ? 'error' : ''" :help="erroresNueva.asunto">
                        <a-input v-model:value="nueva.asunto" :maxlength="150" />
                    </a-form-item>
                    <a-form-item label="Categoría">
                        <a-select v-model:value="nueva.categoria" :options="Object.entries(categorias).map(([value, label]) => ({ value, label }))" />
                    </a-form-item>
                    <a-form-item class="full" label="Mensaje" required :validate-status="erroresNueva.texto ? 'error' : ''" :help="erroresNueva.texto">
                        <a-textarea v-model:value="nueva.texto" :rows="4" :maxlength="5000" show-count />
                    </a-form-item>
                    <a-form-item class="full" label="Adjunto (opcional)" :validate-status="erroresNueva.adjunto ? 'error' : ''" :help="erroresNueva.adjunto" style="margin-bottom: 0">
                        <SelectorArchivos v-model:archivos="nuevaArchivos" compacto texto="Arrastra o elige una foto o documento" />
                    </a-form-item>
                </div>
            </a-form>
        </a-modal>
    </AppLayout>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref, reactive, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { message } from 'ant-design-vue';
import {
    MessageOutlined, FormOutlined, SearchOutlined, HomeOutlined, LockOutlined, UnlockOutlined, ArrowLeftOutlined, ArrowDownOutlined,
    PaperClipOutlined, ClockCircleOutlined, ExclamationCircleOutlined, CloseOutlined, SendOutlined, FolderOpenOutlined, CameraOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectorArchivos from '@/Components/SelectorArchivos.vue';
import CamaraModal from '@/Components/CamaraModal.vue';
import VisorArchivo from '@/Components/VisorArchivo.vue';
import { fechaHora } from '@/composables/useFechaHora';
import { tipoArchivo, ICONO_TIPO, COLOR_TIPO, tamano } from '@/composables/archivos';
import { pulso } from '@/composables/usePulso';

const props = defineProps({
    categorias: Object,
    esAdministracion: Boolean,
    condominios: Array,
    casas: Array,
    miCasa: Object,
    abrir: Number,
});

const page = usePage();
const notify = { error: (t) => message.error(t) };
const yo = page.props.auth?.user?.id_usuario;

// Sin websockets: sondeo. El chat abierto pregunta sólo por lo nuevo, rápido
// mientras hay actividad y más espaciado en reposo o con la pestaña oculta.
const CHAT_ACTIVO_MS = 2000;   // hubo mensajes / se escribió en el último minuto
const CHAT_REPOSO_MS = 5000;
const CHAT_OCULTO_MS = 15000;
const ACTIVIDAD_MS = 60000;
const CADA_BANDEJA_MS = 15000; // además se refresca en cuanto el pulso global detecta mensajes nuevos

const tactil = window.matchMedia?.('(pointer: coarse)').matches;
const esMio = (m) => m.id_usuario === yo;
const hora = (f) => (f ? new Date(f).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : '');
const cuando = (f) => {
    if (!f) return '';
    const d = new Date(f);
    const hoy = new Date();
    return d.toDateString() === hoy.toDateString()
        ? d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
};
const errorDe = (e, porDefecto) => Object.values(e?.response?.data?.errors || {})[0]?.[0] || e?.response?.data?.message || porDefecto;

// ================= BANDEJA =================
const conversaciones = ref([]);
const cargandoBandeja = ref(false);
const filtros = reactive({ buscar: '', estatus: '', categoria: null, id_empresa: null, sin_leer: false });

const cargarBandeja = async () => {
    cargandoBandeja.value = true;
    try {
        const params = Object.fromEntries(Object.entries(filtros).filter(([, v]) => v !== '' && v !== false && v != null));
        const { data } = await axios.get(route('mensajes.conversaciones'), { params });
        conversaciones.value = data.conversaciones;
        // Mantener al día el globo del menú (sólo si la lista no está filtrada).
        if (!filtros.sin_leer && !filtros.buscar && !filtros.estatus && !filtros.categoria && !filtros.id_empresa) {
            pulso.mensajesNoLeidos = data.no_leidos;
        }
    } catch (e) {
        if (!conversaciones.value.length) notify.error(errorDe(e, 'No se pudieron cargar las conversaciones.'));
    } finally {
        cargandoBandeja.value = false;
    }
};

let tBuscar = null;
const buscarConRetraso = () => { clearTimeout(tBuscar); tBuscar = setTimeout(cargarBandeja, 300); };

// ================= CHAT =================
const actual = ref(null);
const contenedorMensajes = ref(null);
const cajaTexto = ref(null);
const texto = ref('');
const adjunto = ref(null);
const inputAdjunto = ref(null);
const enviando = ref(false);       // cerrar / reabrir
const escribiendo = ref(null);     // nombre de quien del otro lado está tecleando
const vistoHasta = ref(null);      // hasta dónde leyó el otro lado
const nuevosSinVer = ref(0);       // llegaron mientras el usuario leía más arriba
let ultimaActividad = Date.now();
let ultimoAvisoEscribiendo = 0;
let abriendo = 0;                  // descarta respuestas de una conversación que ya se cambió

const ultimoId = () => {
    const confirmados = (actual.value?.mensajes || []).filter((m) => typeof m.id === 'number');
    return confirmados.length ? confirmados[confirmados.length - 1].id : 0;
};

// Separadores por día y burbujas "seguidas" (mismo autor en menos de 5 min: sin repetir el nombre).
const DIA = 86400000;
const etiquetaDia = (f) => {
    const d = new Date(f); d.setHours(0, 0, 0, 0);
    const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
    if (d.getTime() === hoy.getTime()) return 'Hoy';
    if (hoy.getTime() - d.getTime() === DIA) return 'Ayer';
    return d.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
};
const itemsChat = computed(() => {
    const items = [];
    let dia = null;
    let previo = null;
    for (const m of actual.value?.mensajes || []) {
        const d = etiquetaDia(m.created_at);
        if (d !== dia) {
            items.push({ clave: `d-${d}-${m.id}`, separador: d });
            dia = d;
            previo = null;
        }
        const seguido = previo && previo.id_usuario === m.id_usuario
            && new Date(m.created_at) - new Date(previo.created_at) < 5 * 60000;
        items.push({ clave: `m-${m.id}`, m, seguido });
        previo = m;
    }
    return items;
});

// Visto: el otro lado leyó después de que se envió el mensaje.
const fueVisto = (m) => !!vistoHasta.value && new Date(vistoHasta.value) >= new Date(m.created_at);

// ----- Desplazamiento: sólo baja solo si el usuario ya estaba abajo -----
const estaAbajo = () => {
    const el = contenedorMensajes.value;
    return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80;
};
const bajar = async (suave = false) => {
    await nextTick();
    const el = contenedorMensajes.value;
    if (el) el.scrollTo({ top: el.scrollHeight, behavior: suave ? 'smooth' : 'auto' });
    nuevosSinVer.value = 0;
};
const alDesplazar = () => { if (estaAbajo()) nuevosSinVer.value = 0; };
const siEstabaAbajo = () => { if (!nuevosSinVer.value) bajar(); };

const aplicarEstado = (data) => {
    escribiendo.value = data.escribiendo || null;
    vistoHasta.value = data.visto_hasta || null;
    if (actual.value && data.estatus && data.estatus !== actual.value.estatus) {
        actual.value.estatus = data.estatus;
        cargarBandeja();
    }
};

const marcarFilaLeida = (id) => {
    const fila = conversaciones.value.find((c) => c.id === id);
    if (fila?.no_leidos) {
        fila.no_leidos = 0;
        pulso.mensajesNoLeidos = Math.max(0, pulso.mensajesNoLeidos - 1);
    }
};

const abrir = async (id) => {
    const turno = ++abriendo;
    try {
        const { data } = await axios.get(route('mensajes.show', id));
        if (turno !== abriendo) return;
        actual.value = data.conversacion;
        aplicarEstado(data);
        pulso.conversacionAbierta = id;
        marcarFilaLeida(id);
        texto.value = '';
        quitarAdjunto();
        nuevosSinVer.value = 0;
        ultimaActividad = Date.now();
        history.replaceState(history.state, '', route('mensajes.index', { conversacion: id }));
        bajar();
        if (!tactil) nextTick(() => cajaTexto.value?.focus());
        programarChat(0);
    } catch (e) {
        notify.error(errorDe(e, 'No se pudo abrir la conversación.'));
    }
};

/** Sondeo del chat abierto: sólo trae los mensajes con id mayor al último que ya tiene. */
let consultandoChat = false;
const refrescarActual = async () => {
    if (!actual.value || consultandoChat) return;
    consultandoChat = true;
    const id = actual.value.id;
    try {
        const { data } = await axios.get(route('mensajes.show', id), {
            params: { desde: ultimoId(), leer: document.hidden ? 0 : 1 },
        });
        if (!actual.value || actual.value.id !== id) return;
        aplicarEstado(data);

        const conocidos = new Set(actual.value.mensajes.map((m) => m.id));
        const nuevos = data.nuevos.filter((m) => !conocidos.has(m.id));
        if (nuevos.length) {
            const abajo = estaAbajo();
            // Si el sondeo trae un mensaje mío antes de que termine el envío, reemplaza a su versión "enviando".
            let lista = actual.value.mensajes;
            for (const n of nuevos.filter(esMio)) {
                const pendiente = lista.find((m) => m.estado === 'enviando' && (m.texto || '').trim() === (n.texto || '').trim());
                if (pendiente) lista = lista.filter((m) => m !== pendiente);
            }
            actual.value.mensajes = [...lista, ...nuevos];
            ultimaActividad = Date.now();
            const ajenos = nuevos.filter((m) => !esMio(m)).length;
            if (abajo) bajar(true);
            else nuevosSinVer.value += ajenos;
            if (ajenos) cargarBandeja();
        }
    } catch (e) { /* se reintenta en el siguiente ciclo */ } finally {
        consultandoChat = false;
    }
};

let tChat = null;
const programarChat = (espera = null) => {
    clearTimeout(tChat);
    if (!actual.value) return;
    const ms = espera ?? (document.hidden ? CHAT_OCULTO_MS : (Date.now() - ultimaActividad < ACTIVIDAD_MS ? CHAT_ACTIVO_MS : CHAT_REPOSO_MS));
    tChat = setTimeout(async () => {
        await refrescarActual();
        programarChat();
    }, ms);
};

const cerrarChat = () => {
    actual.value = null;
    pulso.conversacionAbierta = null;
    clearTimeout(tChat);
    history.replaceState(history.state, '', route('mensajes.index'));
};

// ----- Adjunto con vista previa (archivo del equipo o fotografía) -----
const urlAdjunto = ref(null);
const camaraAbierta = ref(false);
const visor = reactive({ open: false, url: null, file: null, nombre: '' });
const verAdjunto = (url, nombre, file = null) => Object.assign(visor, { open: true, url, file, nombre });

const elegirAdjunto = (f) => {
    if (!f) return;
    if (f.size > 10 * 1024 * 1024) {
        message.error(`“${f.name}” supera los 10 MB.`);
        return;
    }
    quitarAdjunto();
    adjunto.value = f;
    urlAdjunto.value = URL.createObjectURL(f);
    if (inputAdjunto.value) inputAdjunto.value.value = '';
};
// La URL local no se libera si la burbuja "enviando" todavía la muestra.
const quitarAdjunto = (liberar = true) => {
    if (liberar && urlAdjunto.value) URL.revokeObjectURL(urlAdjunto.value);
    urlAdjunto.value = null;
    adjunto.value = null;
    if (inputAdjunto.value) inputAdjunto.value.value = '';
};

// ----- Teclado: Enter envía (en celular, Enter es salto de línea) -----
const alEnter = (e) => {
    if (e.shiftKey || tactil || e.isComposing) return;
    e.preventDefault();
    enviar();
};

// Avisa "escribiendo…" como máximo cada 3 s mientras se teclea.
const alEscribir = () => {
    ultimaActividad = Date.now();
    const el = cajaTexto.value;
    if (el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 140) + 'px'; }
    if (!actual.value || !texto.value.trim() || Date.now() - ultimoAvisoEscribiendo < 3000) return;
    ultimoAvisoEscribiendo = Date.now();
    axios.post(route('mensajes.escribiendo', actual.value.id)).catch(() => {});
};

// ----- Envío optimista: el mensaje aparece al instante y se confirma después -----
const mandar = async (pendiente) => {
    const conversacionId = actual.value.id;
    pendiente.estado = 'enviando';
    pendiente.error = null;
    try {
        const fd = new FormData();
        if (pendiente.texto) fd.append('texto', pendiente.texto);
        if (pendiente.archivo) fd.append('adjunto', pendiente.archivo);
        const { data } = await axios.post(route('mensajes.responder', conversacionId), fd);
        if (actual.value?.id !== conversacionId) return;
        aplicarEstado(data);
        const lista = actual.value.mensajes;
        const yaLlego = lista.some((m) => m.id === data.mensaje.id); // el sondeo pudo traerlo antes
        actual.value.mensajes = yaLlego
            ? lista.filter((m) => m !== pendiente)
            : lista.map((m) => (m === pendiente ? data.mensaje : m));
        cargarBandeja();
    } catch (e) {
        pendiente.estado = 'error';
        pendiente.error = errorDe(e, 'No se pudo enviar.');
    }
};

const enviar = () => {
    if (!actual.value || (!texto.value.trim() && !adjunto.value)) return;
    const pendiente = reactive({
        id: `tmp-${Date.now()}`,
        id_usuario: yo,
        de_administracion: props.esAdministracion,
        usuario: { nombre_usuario: page.props.auth?.user?.nombre_usuario },
        texto: texto.value.trim() ? texto.value : null,
        archivo: adjunto.value,
        adjunto_local: adjunto.value?.name,
        adjunto_preview: adjunto.value?.type?.startsWith('image/') ? urlAdjunto.value : null,
        created_at: new Date().toISOString(),
        estado: 'enviando',
    });
    actual.value.mensajes.push(pendiente);
    texto.value = '';
    quitarAdjunto(!pendiente.adjunto_preview);
    if (cajaTexto.value) cajaTexto.value.style.height = 'auto';
    ultimaActividad = Date.now();
    bajar(true);
    mandar(pendiente);
    programarChat(); // retoma el ritmo rápido
};

const reintentar = (m) => mandar(m);
const descartarFallido = (m) => { actual.value.mensajes = actual.value.mensajes.filter((x) => x !== m); };

const cambiarEstatus = async () => {
    enviando.value = true;
    try {
        const { data } = await axios.post(route('mensajes.estatus', actual.value.id));
        actual.value = data.conversacion;
        aplicarEstado(data);
        bajar();
        cargarBandeja();
    } catch (e) {
        notify.error(errorDe(e, 'No se pudo cambiar el estatus.'));
    } finally {
        enviando.value = false;
    }
};

// ================= NUEVA =================
const dialogoNueva = ref(false);
const nueva = reactive({ id_casa: null, asunto: '', categoria: 'GENERAL', texto: '' });
const nuevaArchivos = ref([]);
const erroresNueva = ref({});

const abrirNueva = () => {
    Object.assign(nueva, { id_casa: null, asunto: '', categoria: 'GENERAL', texto: '' });
    nuevaArchivos.value = [];
    erroresNueva.value = {};
    dialogoNueva.value = true;
};

const crear = async () => {
    enviando.value = true;
    erroresNueva.value = {};
    try {
        const fd = new FormData();
        for (const [k, v] of Object.entries(nueva)) if (v !== null && v !== '') fd.append(k, v);
        if (nuevaArchivos.value[0]) fd.append('adjunto', nuevaArchivos.value[0]);
        const { data } = await axios.post(route('mensajes.store'), fd);
        dialogoNueva.value = false;
        await cargarBandeja();
        abrir(data.id);
    } catch (e) {
        const errores = e?.response?.data?.errors;
        if (errores) {
            erroresNueva.value = Object.fromEntries(Object.entries(errores).map(([k, v]) => [k, v[0]]));
        } else {
            notify.error(errorDe(e, 'No se pudo crear la conversación.'));
        }
    } finally {
        enviando.value = false;
    }
};

// ================= CICLO DE CONSULTA =================
let tBandeja = null;

// El pulso global (cada pocos segundos en toda la app) avisa de mensajes en
// otras conversaciones: en cuanto cambia, se refresca la bandeja.
watch(() => pulso.mensajesNoLeidos, () => cargarBandeja());

// Al volver a la pestaña: consultar ya (y marcar como leído lo que llegó).
const alVolver = () => {
    if (document.hidden) return;
    programarChat(0);
    cargarBandeja();
};

onMounted(async () => {
    if (!props.esAdministracion && !props.miCasa) return;
    await cargarBandeja();
    if (props.abrir) abrir(props.abrir);
    tBandeja = setInterval(() => { if (!document.hidden) cargarBandeja(); }, CADA_BANDEJA_MS);
    document.addEventListener('visibilitychange', alVolver);
    window.addEventListener('focus', alVolver);
});
onBeforeUnmount(() => {
    clearInterval(tBandeja);
    clearTimeout(tChat);
    pulso.conversacionAbierta = null;
    document.removeEventListener('visibilitychange', alVolver);
    window.removeEventListener('focus', alVolver);
});
</script>

<style scoped>
/* Alto fijo: el scroll es dentro de la bandeja y del chat, nunca de la página. */
.mensajeria { display: grid; grid-template-columns: 360px 1fr; gap: 14px; height: calc(100vh - 205px); min-height: 460px; }
.bandeja, .chat { padding: 0 !important; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }

.bandeja-filtros { padding: 12px; border-bottom: 1px solid var(--vt-line); display: flex; flex-direction: column; gap: 8px; background: #fbfcfe; }
.bandeja-lista { flex: 1; overflow-y: auto; }

.item { width: 100%; text-align: left; padding: 11px 14px; border-bottom: 1px solid #eef2f7; display: flex; gap: 10px; align-items: center; transition: background 0.15s; position: relative; }
.item:hover { background: #f5f9fe; }
.item.activo { background: #e8f0fa; }
.item.activo::before { content: ''; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px; border-radius: 0 3px 3px 0; background: var(--vt-blue); }
.item.cerrada { opacity: 0.7; }
.item-av { flex: none; }
.item-casa { font-size: 11.5px; font-weight: 700; color: var(--vt-blue); }
.item-fecha { font-size: 11px; color: #94a3b8; white-space: nowrap; }
.item-asunto { font-size: 13.5px; font-weight: 600; color: var(--vt-navy); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.item.sin-leer .item-asunto { font-weight: 800; }
.item-texto { font-size: 12px; color: var(--vt-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.globo { background: #d9363e; color: #fff; border-radius: 999px; font-size: 10.5px; font-weight: 700; min-width: 19px; height: 19px; padding: 0 5px; display: inline-flex; align-items: center; justify-content: center; }

.chat-vacio { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; gap: 8px; }
.chat-vacio .anticon { font-size: 46px; color: #c3d0de; }
.chat-header { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid var(--vt-line); }
.chat-asunto { font: 700 15.5px 'Bricolage Grotesque', Figtree, sans-serif; color: var(--vt-navy); }
.volver { display: none; }
.chat-cuerpo { flex: 1; min-height: 0; position: relative; display: flex; flex-direction: column; }
.chat-mensajes { flex: 1; overflow-y: auto; padding: 14px 16px; background: #f3f6fa; display: flex; flex-direction: column; gap: 8px; }
.chat-inicio { text-align: center; font-size: 11.5px; color: var(--vt-muted); }
.burbuja-fila.seguido { margin-top: -5px; }
.separador { display: flex; justify-content: center; margin: 6px 0; position: sticky; top: 0; z-index: 1; }
.separador span { background: #fff; color: #44566c; font-size: 11px; font-weight: 600; padding: 3px 12px; border-radius: 999px; text-transform: capitalize; box-shadow: 0 1px 3px rgba(15, 42, 71, 0.1); }
.pildora-nuevos { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); background: var(--vt-navy); color: #fff; font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 999px; box-shadow: 0 6px 18px rgba(15, 42, 71, 0.35); display: inline-flex; align-items: center; gap: 6px; }
.escribiendo { height: 0; overflow: hidden; padding: 0 16px; font-size: 11.5px; color: var(--vt-muted); font-style: italic; transition: height 0.2s ease; display: flex; align-items: center; gap: 6px; background: #f3f6fa; }
.escribiendo.visible { height: 24px; }
.puntos { display: inline-flex; gap: 3px; }
.puntos i { width: 5px; height: 5px; border-radius: 50%; background: #94a3b8; animation: punto 1.2s infinite ease-in-out; }
.puntos i:nth-child(2) { animation-delay: 0.15s; }
.puntos i:nth-child(3) { animation-delay: 0.3s; }
@keyframes punto { 0%, 60%, 100% { opacity: 0.3; transform: translateY(0); } 30% { opacity: 1; transform: translateY(-3px); } }
.palomita { margin-left: 4px; font-size: 10px; letter-spacing: -2px; color: #94a3b8; }
.palomita.visto { color: #1a5a96; font-weight: 700; }

.burbuja-fila { display: flex; }
.burbuja-fila.mio { justify-content: flex-end; }
.burbuja { max-width: 75%; background: #fff; border-radius: 14px 14px 14px 4px; padding: 8px 12px; font-size: 13.5px; color: var(--vt-navy); box-shadow: 0 1px 2px rgba(15, 42, 71, 0.08); }
.burbuja p { margin: 0; }
.burbuja.admin { background: #e8f0fa; }
.mio .burbuja { border-radius: 14px 14px 4px 14px; background: #d9f2e4; }
.mio .burbuja.admin { background: #d6e6f8; }
.burbuja.error { background: #fdecec !important; }
.burbuja-autor { font-size: 10.5px; font-weight: 700; color: var(--vt-blue); letter-spacing: 0.2px; margin-bottom: 2px; }
.burbuja-hora { font-size: 10px; color: #8193a6; text-align: right; margin-top: 3px; }
.burbuja-img { max-width: 240px; max-height: 200px; border-radius: 10px; margin-top: 6px; display: block; cursor: zoom-in; }
.burbuja-archivo { display: inline-flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 12.5px; color: var(--vt-blue); font-weight: 600; background: rgba(255, 255, 255, 0.7); border-radius: 8px; padding: 5px 9px; }

.chat-redactar { border-top: 1px solid var(--vt-line); padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; }
/* Crece con el texto hasta 140 px (ver alEscribir). */
.redactar-texto { height: 40px; min-height: 40px; max-height: 140px; padding: 9px 14px; resize: none; flex: 1; line-height: 1.4; border: 1px solid #d5dee8; border-radius: 20px; font-size: 14px; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
.redactar-texto:focus { border-color: var(--vt-blue); box-shadow: 0 0 0 3px rgba(26, 90, 150, 0.12); }
.adjunto-previo { display: flex; align-items: center; gap: 10px; background: #f5f9fe; border: 1px solid var(--vt-line); border-radius: 12px; padding: 6px 8px; font-size: 12.5px; }
.adjunto-previo img { width: 54px; height: 54px; object-fit: cover; border-radius: 8px; cursor: zoom-in; }
.adjunto-ic { width: 54px; height: 54px; display: grid; place-items: center; font-size: 28px; background: #fff; border-radius: 8px; cursor: zoom-in; }
.chat-cerrada { border-top: 1px solid var(--vt-line); padding: 12px; text-align: center; font-size: 12.5px; color: var(--vt-muted); }

/* Celular: una sola columna; la bandeja o el chat. */
@media (max-width: 860px) {
    .mensajeria { grid-template-columns: 1fr; height: calc(100dvh - 250px); min-height: 380px; } /* deja lugar a la barra inferior */
    .mensajeria .chat { display: none; }
    .mensajeria.con-chat .bandeja { display: none; }
    .mensajeria.con-chat .chat { display: flex; }
    .volver { display: inline-flex; }
    .burbuja { max-width: 88%; }
}
</style>
