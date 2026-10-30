<script setup>
/**
 * Visor de documentos dentro de un modal: PDF, imágenes (con zoom y giro), Word (.docx) y Excel (.xls/.xlsx).
 * Incluye descargar, zoom y abrir en una pestaña nueva; las imágenes ajustan el modal a su tamaño.
 * Acepta una URL del servidor o un File local (vista previa antes de subir).
 */
import { computed, nextTick, ref, watch, onBeforeUnmount } from 'vue';
import {
    DownloadOutlined, FileUnknownOutlined, ZoomInOutlined, ZoomOutOutlined, RotateRightOutlined, CompressOutlined,
    CloseOutlined, LoadingOutlined, TableOutlined, ExportOutlined,
} from '@ant-design/icons-vue';
import { tipoArchivo, ICONO_TIPO, COLOR_TIPO, tamano as fmtTamano } from '@/composables/archivos';

const props = defineProps({
    url: { type: String, default: null },
    file: { type: [File, Object], default: null },
    nombre: { type: String, default: '' },
    mime: { type: String, default: null },
    descarga: { type: String, default: null },
    tamano: { type: Number, default: null },
    detalle: { type: String, default: null },
});
const open = defineModel('open', { default: false });

const tipo = computed(() => tipoArchivo(props.nombre, props.mime || props.file?.type));
const ETIQUETA = { pdf: 'PDF', word: 'Word', excel: 'Excel', powerpoint: 'PowerPoint', imagen: 'Imagen', video: 'Video', otro: 'Archivo' };
const fuente = ref(null);
const cargando = ref(false);
const error = ref(null);
const contenedor = ref(null);
const hojas = ref([]);
const hoja = ref(0);
const zoom = ref(1);
const giro = ref(0);
const natural = ref(null);

async function blob() {
    if (props.file) return props.file;
    const r = await fetch(props.url, { credentials: 'same-origin' });
    if (!r.ok) throw new Error('No se pudo cargar el archivo');
    return r.blob();
}

async function preparar() {
    error.value = null;
    hojas.value = [];
    hoja.value = 0;
    zoom.value = 1;
    giro.value = 0;
    natural.value = null;
    if (fuente.value?.startsWith('blob:')) URL.revokeObjectURL(fuente.value);
    fuente.value = props.file ? URL.createObjectURL(props.file) : props.url ?? null;

    if (tipo.value === 'word' && /\.docx$/i.test(props.nombre)) {
        cargando.value = true;
        try {
            const { renderAsync } = await import('docx-preview');
            await nextTick();
            if (contenedor.value) {
                contenedor.value.innerHTML = '';
                await renderAsync(await blob(), contenedor.value, undefined, { inWrapper: true, ignoreLastRenderedPageBreak: true });
            }
        } catch {
            error.value = 'No se pudo generar la vista previa del documento.';
        } finally {
            cargando.value = false;
        }
    } else if (tipo.value === 'excel') {
        cargando.value = true;
        try {
            const XLSX = await import('xlsx');
            const wb = XLSX.read(await (await blob()).arrayBuffer());
            hojas.value = wb.SheetNames.map((n) => ({ nombre: n, html: XLSX.utils.sheet_to_html(wb.Sheets[n], { header: '', footer: '' }) }));
        } catch {
            error.value = 'No se pudo leer la hoja de cálculo.';
        } finally {
            cargando.value = false;
        }
    }
}

watch(open, (o) => o && preparar());
onBeforeUnmount(() => fuente.value?.startsWith('blob:') && URL.revokeObjectURL(fuente.value));

const previsualizable = computed(
    () => ['pdf', 'imagen', 'excel', 'video'].includes(tipo.value) || (tipo.value === 'word' && /\.docx$/i.test(props.nombre)),
);
const peso = computed(() => props.tamano ?? props.file?.size ?? null);
const zoomar = (d) => (zoom.value = Math.min(4, Math.max(0.25, +(zoom.value + d).toFixed(2))));
const conZoom = computed(() => !['powerpoint', 'otro', 'video'].includes(tipo.value) && previsualizable.value && !error.value);

// Descargar y abrir en pestaña nueva (sirve también para la vista previa local)
const enlace = computed(() => props.descarga || fuente.value || props.url || null);
const nombreDescarga = computed(() => {
    if (/\.[a-z0-9]{2,5}$/i.test(props.nombre)) return props.nombre;
    const ext = (props.url || '').split('?')[0].split('.').pop();
    return ext && ext.length <= 5 ? `${props.nombre}.${ext}` : props.nombre;
});
const pestanaNueva = () => fuente.value && window.open(fuente.value, '_blank', 'noopener');

// PDF: el zoom lo aplica el visor del navegador; Word y Excel se escalan con CSS
const srcPdf = computed(() => (fuente.value ? `${fuente.value}#zoom=${Math.round(zoom.value * 100)}` : ''));
const estiloZoom = computed(() => ({ zoom: String(zoom.value) }));

// Imágenes: el modal toma el tamaño de la imagen (con límites de pantalla)
function alCargarImagen(e) {
    const img = e.target;
    natural.value = { w: img.naturalWidth, h: img.naturalHeight };
}
const anchoModal = computed(() => {
    if (tipo.value !== 'imagen') return 'min(1320px, 97vw)';
    if (!natural.value) return 'min(720px, 97vw)';
    // Ancho que ocupa la imagen al caber en el 78% del alto de la pantalla
    const alto = Math.min(natural.value.h, window.innerHeight * 0.78 - 48);
    const ancho = Math.max(460, Math.round((natural.value.w * alto) / natural.value.h) + 48);
    return `min(${ancho}px, 97vw)`;
});
</script>

<template>
    <a-modal v-model:open="open" :footer="null" :closable="false" :width="anchoModal" centered destroy-on-close wrap-class-name="vt-visor-wrap">
        <div class="vt-visor">
            <!-- Barra superior -->
            <header class="vt-visor-bar">
                <span class="vt-file-ic" :style="{ background: COLOR_TIPO[tipo], color: '#fff' }"><component :is="ICONO_TIPO[tipo]" /></span>
                <div class="min-w-0 flex-1">
                    <div class="vt-visor-nombre">{{ nombre }}</div>
                    <div class="vt-visor-meta">
                        <span class="vt-visor-chip" :style="{ background: COLOR_TIPO[tipo] }">{{ ETIQUETA[tipo] }}</span>
                        <span v-if="peso">{{ fmtTamano(peso) }}</span>
                        <span v-if="detalle">· {{ detalle }}</span>
                        <span v-if="file" class="vt-visor-chip" style="background: #f0a202">Vista previa · sin guardar</span>
                    </div>
                </div>
                <div class="vt-visor-tools">
                    <template v-if="conZoom">
                        <a-tooltip title="Alejar"><button class="vt-tool" @click="zoomar(-0.25)"><ZoomOutOutlined /></button></a-tooltip>
                        <span class="vt-tool-zoom">{{ Math.round(zoom * 100) }}%</span>
                        <a-tooltip title="Acercar"><button class="vt-tool" @click="zoomar(0.25)"><ZoomInOutlined /></button></a-tooltip>
                        <a-tooltip v-if="tipo === 'imagen'" title="Girar"><button class="vt-tool" @click="giro = (giro + 90) % 360"><RotateRightOutlined /></button></a-tooltip>
                        <a-tooltip title="Tamaño original"><button class="vt-tool" @click="(zoom = 1), (giro = 0)"><CompressOutlined /></button></a-tooltip>
                        <span class="vt-tool-sep"></span>
                    </template>
                    <a-tooltip v-if="fuente" title="Abrir en pestaña nueva"><button class="vt-tool" @click="pestanaNueva"><ExportOutlined /></button></a-tooltip>
                    <a-tooltip v-if="enlace" title="Descargar"><a class="vt-tool primario" :href="enlace" :download="nombreDescarga"><DownloadOutlined /></a></a-tooltip>
                    <a-tooltip title="Cerrar"><button class="vt-tool" @click="open = false"><CloseOutlined /></button></a-tooltip>
                </div>
            </header>

            <!-- Contenido -->
            <div class="vt-visor-stage" :class="tipo">
                <div v-if="cargando" class="vt-visor-cargando"><LoadingOutlined /> Preparando vista previa…</div>

                <div v-if="tipo === 'imagen' && fuente" class="vt-visor-img-wrap">
                    <img :src="fuente" :alt="nombre" class="vt-visor-img" :style="{ transform: `scale(${zoom}) rotate(${giro}deg)` }" @load="alCargarImagen" />
                </div>
                <div v-else-if="tipo === 'video' && fuente" class="vt-visor-img-wrap"><video :src="fuente" controls autoplay class="vt-visor-img"></video></div>
                <iframe v-else-if="tipo === 'pdf' && fuente" :key="srcPdf" :src="srcPdf" class="vt-visor-pdf" :title="nombre"></iframe>
                <div v-else-if="tipo === 'word' && previsualizable" ref="contenedor" class="vt-visor-docx" :style="estiloZoom"></div>
                <div v-else-if="tipo === 'excel' && hojas.length" class="vt-visor-excel">
                    <div class="vt-visor-hojas">
                        <button v-for="(h, i) in hojas" :key="i" :class="{ on: hoja === i }" @click="hoja = i"><TableOutlined /> {{ h.nombre }}</button>
                    </div>
                    <div class="vt-visor-xlsx" :style="estiloZoom" v-html="hojas[hoja].html"></div>
                </div>

                <div v-if="(!previsualizable || error) && !cargando" class="vt-visor-none">
                    <span class="vt-visor-none-ic" :style="{ color: COLOR_TIPO[tipo] }"><FileUnknownOutlined /></span>
                    <h3>Vista previa no disponible</h3>
                    <p>{{ error || 'Este formato (PowerPoint o Word 97-2003) no se puede mostrar en el navegador.' }}</p>
                    <a-button v-if="enlace" type="primary" size="large" :href="enlace" :download="nombreDescarga"><DownloadOutlined /> Descargar archivo</a-button>
                </div>
            </div>
        </div>
    </a-modal>
</template>

<style>
.vt-visor-wrap .ant-modal-content {
    padding: 0 !important;
    overflow: hidden;
    border-radius: 18px !important;
    background: #111827 !important;
}
.vt-visor-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    background: #0a1f44;
    color: #fff;
}
.vt-visor-nombre {
    font: 700 16px 'Bricolage Grotesque', sans-serif;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.vt-visor-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #a9bad3;
    margin-top: 2px;
}
.vt-visor-chip {
    color: #fff;
    font-weight: 700;
    font-size: 11px;
    padding: 1px 8px;
    border-radius: 99px;
}
.vt-visor-tools {
    display: flex;
    align-items: center;
    gap: 6px;
}
.vt-tool {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    border: 0;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.08);
    color: #fff !important;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.15s, transform 0.15s;
}
.vt-tool:hover {
    background: rgba(255, 255, 255, 0.18);
    transform: translateY(-2px);
}
.vt-tool:active {
    transform: scale(0.92);
}
.vt-tool.primario {
    background: #1faf5c;
}
.vt-tool-zoom {
    min-width: 46px;
    text-align: center;
    font-size: 12.5px;
    font-weight: 600;
    color: #cfdbec;
}
.vt-tool-sep {
    width: 1px;
    height: 24px;
    background: rgba(255, 255, 255, 0.2);
    margin: 0 4px;
}
.vt-visor-stage {
    position: relative;
    height: 78vh;
    overflow: auto;
    background: radial-gradient(circle at 50% 30%, #1f2b3d, #111827);
}
.vt-visor-stage.pdf,
.vt-visor-stage.word,
.vt-visor-stage.excel,
.vt-visor-stage.powerpoint,
.vt-visor-stage.otro {
    background: #e9edf3;
}
.vt-visor-stage.imagen {
    height: auto;
    max-height: 78vh;
}
.vt-visor-stage.imagen .vt-visor-img-wrap {
    padding: 24px;
}
.vt-visor-cargando {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    color: #6b7a90;
    font-size: 15px;
    z-index: 2;
}
.vt-visor-img-wrap {
    min-height: 100%;
    display: grid;
    place-items: center;
    padding: 24px;
}
.vt-visor-img {
    max-width: 100%;
    max-height: calc(78vh - 48px);
    object-fit: contain;
    border-radius: 6px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
    transition: transform 0.25s ease;
}
.vt-visor-pdf {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
}
.vt-visor-docx .docx-wrapper {
    background: #e9edf3 !important;
    padding: 24px !important;
}
.vt-visor-docx .docx-wrapper > section.docx {
    box-shadow: 0 8px 24px rgba(10, 31, 68, 0.15) !important;
    border-radius: 4px;
}
.vt-visor-excel {
    padding: 16px;
}
.vt-visor-hojas {
    display: flex;
    gap: 6px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.vt-visor-hojas button {
    border: 1px solid #c9d4e0;
    background: #fff;
    border-radius: 8px;
    padding: 5px 12px;
    font: 600 13px Figtree, sans-serif;
    color: #44566c;
    cursor: pointer;
}
.vt-visor-hojas button.on {
    background: #1d8a4e;
    border-color: #1d8a4e;
    color: #fff;
}
.vt-visor-xlsx {
    background: #fff;
    border-radius: 10px;
    overflow: auto;
    box-shadow: 0 6px 18px rgba(10, 31, 68, 0.08);
}
.vt-visor-xlsx table {
    border-collapse: collapse;
    font-size: 13px;
    min-width: 100%;
}
.vt-visor-xlsx td {
    border: 1px solid #e1e8f0;
    padding: 6px 10px;
    white-space: nowrap;
}
.vt-visor-xlsx tr:first-child td {
    background: #1d8a4e;
    color: #fff;
    font-weight: 700;
    position: sticky;
    top: 0;
}
.vt-visor-xlsx tr:nth-child(even) td {
    background: #f6f9fc;
}
.vt-visor-none {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px;
    color: #44566c;
}
.vt-visor-none-ic {
    font-size: 64px;
    margin-bottom: 8px;
}
.vt-visor-none h3 {
    font-size: 20px;
    margin: 0 0 6px;
    color: var(--vt-navy);
}
.vt-file-ic {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    font-size: 19px;
    flex: none;
}
</style>
