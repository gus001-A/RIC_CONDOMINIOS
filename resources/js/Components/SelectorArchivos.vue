<script setup>
/**
 * Selector de archivos con VISTA PREVIA antes de subir (estilo VenTrack / SURVICOS):
 *  - arrastrar o elegir del equipo
 *  - tomar fotografía con la cámara del dispositivo
 *  - miniaturas de lo elegido (imagen, PDF o icono por tipo); clic = visor en modal
 *
 *   <SelectorArchivos v-model:archivos="form.fotos" :max="5" solo-imagenes />
 * Siempre entrega un arreglo de File (aunque sea de uno).
 */
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { message } from 'ant-design-vue';
import { CloudUploadOutlined, CameraOutlined, CloseOutlined, EyeOutlined, FolderOpenOutlined } from '@ant-design/icons-vue';
import CamaraModal from './CamaraModal.vue';
import VisorArchivo from './VisorArchivo.vue';
import { tipoArchivo, ICONO_TIPO, COLOR_TIPO, tamano } from '@/composables/archivos';

const archivos = defineModel('archivos', { type: Array, default: () => [] });
const props = defineProps({
    max: { type: Number, default: 1 },
    maxMb: { type: Number, default: 10 },
    soloImagenes: { type: Boolean, default: false },
    accept: { type: String, default: '' },          // si no se indica: imágenes, PDF y Office
    camara: { type: Boolean, default: true },
    texto: { type: String, default: '' },
    ayuda: { type: String, default: '' },
    compacto: { type: Boolean, default: false },     // dragger bajo (para formularios largos)
});

const aceptados = computed(() => props.accept || (props.soloImagenes ? 'image/*' : 'image/*,.pdf,.doc,.docx,.xls,.xlsx'));
const textoAyuda = computed(() => props.ayuda || `${props.soloImagenes ? 'Imágenes' : 'Imágenes, PDF, Word o Excel'} · máx. ${props.maxMb} MB${props.max > 1 ? ` · hasta ${props.max}` : ''}`);
const caben = computed(() => archivos.value.length < props.max);

// URLs locales para las miniaturas (se liberan al quitar el archivo)
const urls = ref(new Map());
const urlDe = (f) => {
    if (!urls.value.has(f)) urls.value.set(f, URL.createObjectURL(f));
    return urls.value.get(f);
};
const liberar = (f) => {
    const u = urls.value.get(f);
    if (u) URL.revokeObjectURL(u);
    urls.value.delete(f);
};
watch(archivos, (nuevos) => {
    for (const f of [...urls.value.keys()]) if (!nuevos.includes(f)) liberar(f);
});
onBeforeUnmount(() => [...urls.value.keys()].forEach(liberar));

function agregar(f) {
    if (props.soloImagenes && !f.type.startsWith('image/')) {
        message.error('Sólo se aceptan imágenes.');
        return false;
    }
    if (f.size > props.maxMb * 1024 * 1024) {
        message.error(`“${f.name}” supera los ${props.maxMb} MB.`);
        return false;
    }
    if (props.max === 1) {
        archivos.value = [f];
    } else if (caben.value) {
        archivos.value = [...archivos.value, f];
    } else {
        message.warning(`Máximo ${props.max} archivos.`);
    }
    return false; // evita que a-upload lo suba solo
}

const quitar = (f) => (archivos.value = archivos.value.filter((x) => x !== f));

const camaraAbierta = ref(false);
const visor = ref({ open: false, file: null, nombre: '' });
const ver = (f) => (visor.value = { open: true, file: f, nombre: f.name });
</script>

<template>
    <div class="sa">
        <template v-if="caben">
            <!-- Toda la zona abre el explorador; "Tomar fotografía" no (detiene el clic). -->
            <a-upload-dragger :accept="aceptados" :show-upload-list="false" :before-upload="agregar" :multiple="max > 1" class="sa-drag" :class="{ compacto }">
                <div class="sa-zona">
                    <span class="sa-zona-ic"><CloudUploadOutlined /></span>
                    <div class="sa-zona-txt">
                        <b>{{ texto || (max > 1 ? 'Arrastra tus archivos aquí' : 'Arrastra tu archivo aquí') }}</b>
                        <small>{{ textoAyuda }}</small>
                    </div>
                    <div class="sa-zona-btns">
                        <a-button type="primary"><FolderOpenOutlined /> Seleccionar archivo</a-button>
                        <a-button v-if="camara" class="sa-btn-cam" @click.stop="camaraAbierta = true"><CameraOutlined /> Tomar fotografía</a-button>
                    </div>
                </div>
            </a-upload-dragger>
        </template>

        <div v-if="archivos.length" class="sa-lista" :class="{ uno: max === 1 }">
            <div v-for="f in archivos" :key="f.name + f.size + f.lastModified" class="sa-item">
                <div class="sa-media" @click="ver(f)">
                    <img v-if="tipoArchivo(f.name, f.type) === 'imagen'" :src="urlDe(f)" :alt="f.name" />
                    <iframe v-else-if="tipoArchivo(f.name, f.type) === 'pdf' && max === 1" :src="urlDe(f) + '#toolbar=0&view=FitH'" title="Vista previa"></iframe>
                    <span v-else class="sa-ic" :style="{ color: COLOR_TIPO[tipoArchivo(f.name, f.type)] }"><component :is="ICONO_TIPO[tipoArchivo(f.name, f.type)]" /></span>
                    <span class="sa-ver"><EyeOutlined /> Ver</span>
                </div>
                <div class="sa-pie">
                    <span class="truncate flex-1" :title="f.name"><b>{{ f.name }}</b><br /><small>{{ tamano(f.size) }}</small></span>
                    <a-tooltip title="Quitar"><a-button type="text" danger size="small" @click="quitar(f)"><CloseOutlined /></a-button></a-tooltip>
                </div>
            </div>
        </div>

        <CamaraModal v-model:open="camaraAbierta" @foto="agregar" />
        <VisorArchivo v-model:open="visor.open" :file="visor.file" :nombre="visor.nombre" />
    </div>
</template>

<style>
/* ===== Zona para arrastrar / seleccionar ===== */
.sa .sa-drag.ant-upload-wrapper .ant-upload-drag,
.sa .sa-drag .ant-upload-drag {
    background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
    border: 1.5px dashed #b9cde6;
    border-radius: 16px;
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
}
.sa .sa-drag .ant-upload-drag:hover,
.sa .sa-drag .ant-upload-drag.ant-upload-drag-hover {
    border-color: var(--vt-blue);
    background: linear-gradient(180deg, #f2f8ff 0%, #e4effb 100%);
    box-shadow: 0 0 0 4px rgba(26, 90, 150, 0.08);
}
.sa .sa-drag .ant-upload-btn { padding: 0 !important; }
.sa-zona { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 20px 16px; text-align: center; }
.sa-zona-ic {
    width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; font-size: 26px; color: #fff;
    background: linear-gradient(135deg, #1a3a5c, #1a5a96); box-shadow: 0 8px 18px rgba(26, 90, 150, 0.28);
}
.sa-zona-txt { display: flex; flex-direction: column; gap: 2px; line-height: 1.3; }
.sa-zona-txt b { color: var(--vt-navy); font-size: 14.5px; font-weight: 700; }
.sa-zona-txt small { color: var(--vt-muted); font-size: 12.5px; }
.sa-zona-btns { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
.sa-btn-cam .anticon { color: #7c4ddb; }

/* Compacto: todo en una fila (formularios largos) */
.sa .sa-drag.compacto .sa-zona { flex-direction: row; text-align: left; padding: 12px 14px; gap: 12px; }
.sa .sa-drag.compacto .sa-zona-ic { width: 40px; height: 40px; font-size: 20px; border-radius: 12px; flex: none; }
.sa .sa-drag.compacto .sa-zona-txt { flex: 1; min-width: 0; }
.sa .sa-drag.compacto .sa-zona-btns { justify-content: flex-end; }
@media (max-width: 640px) {
    .sa .sa-drag.compacto .sa-zona { flex-direction: column; text-align: center; }
}

/* ===== Vista previa de lo elegido ===== */
.sa-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-top: 10px; }
.sa-lista.uno { grid-template-columns: 1fr; }
.sa-item { border: 1px solid var(--vt-line); border-radius: 14px; overflow: hidden; background: #fff; box-shadow: 0 2px 8px rgba(15, 42, 71, 0.05); }
.sa-media { position: relative; height: 110px; background: #f4f7fb; display: grid; place-items: center; cursor: zoom-in; }
.sa-lista.uno .sa-media { height: 190px; }
.sa-media img { width: 100%; height: 100%; object-fit: cover; }
.sa-lista.uno .sa-media img { object-fit: contain; }
.sa-media iframe { width: 100%; height: 100%; border: 0; pointer-events: none; }
.sa-ic { font-size: 44px; }
.sa-ver { position: absolute; right: 8px; bottom: 8px; display: inline-flex; align-items: center; gap: 4px; background: rgba(15, 42, 71, 0.8); color: #fff; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 99px; }
.sa-pie { display: flex; align-items: center; gap: 6px; padding: 8px 10px; font-size: 12.5px; line-height: 1.3; }
.sa-pie b { color: var(--vt-navy); }
.sa-pie small { color: var(--vt-muted); }
</style>
