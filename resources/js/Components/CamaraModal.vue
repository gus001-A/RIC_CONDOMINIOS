<script setup>
/**
 * Toma fotografías directamente con la cámara del dispositivo (computadora o teléfono).
 * Si el navegador no permite usar la cámara (p. ej. sin HTTPS), abre la cámara nativa del teléfono.
 */
import { onBeforeUnmount, ref, watch } from 'vue';
import { message } from 'ant-design-vue';
import { CameraOutlined, RetweetOutlined, CheckOutlined, ReloadOutlined, SwapOutlined } from '@ant-design/icons-vue';

const open = defineModel('open', { default: false });
const props = defineProps({
    titulo: { type: String, default: 'Tomar fotografía' },
    subtitulo: { type: String, default: 'Usa la cámara del dispositivo' },
    // 'placa': muestra un recuadro para encuadrar la placa del vehículo.
    guia: { type: String, default: null },
});
const emit = defineEmits(['foto']);

const video = ref(null);
const nativo = ref(null);
const stream = ref(null);
const captura = ref(null);
const blob = ref(null);
const frontal = ref(false);
const iniciando = ref(false);
const error = ref(null);

function detener() {
    stream.value?.getTracks().forEach((t) => t.stop());
    stream.value = null;
}

async function iniciar() {
    detener();
    error.value = null;
    if (!navigator.mediaDevices?.getUserMedia) {
        error.value = 'Este navegador no permite usar la cámara aquí (se requiere HTTPS). Usa la cámara del teléfono.';
        return;
    }
    iniciando.value = true;
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: frontal.value ? 'user' : 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } },
            audio: false,
        });
        if (video.value) {
            video.value.srcObject = stream.value;
            await video.value.play();
        }
    } catch (e) {
        error.value = e?.name === 'NotAllowedError'
            ? 'Permite el acceso a la cámara en el navegador para tomar la foto.'
            : 'No se encontró una cámara disponible.';
    } finally {
        iniciando.value = false;
    }
}

function tomar() {
    const v = video.value;
    if (!v || !v.videoWidth) return;
    const c = document.createElement('canvas');
    c.width = v.videoWidth;
    c.height = v.videoHeight;
    c.getContext('2d').drawImage(v, 0, 0);
    c.toBlob((b) => {
        if (!b) return;
        blob.value = b;
        captura.value = URL.createObjectURL(b);
        detener();
    }, 'image/jpeg', 0.9);
}

function repetir() {
    if (captura.value) URL.revokeObjectURL(captura.value);
    captura.value = null;
    blob.value = null;
    iniciar();
}

function usar() {
    if (!blob.value) return;
    const nombre = `foto-${new Date().toISOString().replace(/[:.]/g, '-')}.jpg`;
    emit('foto', new File([blob.value], nombre, { type: 'image/jpeg' }));
    open.value = false;
}

function cambiarCamara() {
    frontal.value = !frontal.value;
    iniciar();
}

function desdeNativo(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    if (!f.type.startsWith('image/')) return message.error('El archivo debe ser una imagen');
    emit('foto', f);
    open.value = false;
}

watch(open, (o) => {
    if (o) {
        captura.value = null;
        blob.value = null;
        setTimeout(iniciar, 50);
    } else {
        detener();
        if (captura.value) URL.revokeObjectURL(captura.value);
    }
});
onBeforeUnmount(detener);
</script>

<template>
    <a-modal v-model:open="open" :footer="null" width="min(720px, 96vw)" centered destroy-on-close>
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" style="background: linear-gradient(135deg, #7c4ddb, #e0457b)"><CameraOutlined /></span>
                <div>{{ props.titulo }}<small>{{ props.subtitulo }}</small></div>
            </div>
        </template>

        <div class="vt-cam">
            <img v-if="captura" :src="captura" alt="Foto tomada" />
            <video v-show="!captura && !error" ref="video" playsinline muted></video>
            <div v-if="props.guia === 'placa' && !captura && !error && stream" class="vt-cam-guia"><span>Encuadra la placa aquí</span></div>
            <div v-if="error" class="vt-cam-error">
                <CameraOutlined style="font-size: 40px" />
                <p>{{ error }}</p>
                <a-button type="primary" size="large" @click="nativo?.click()"><CameraOutlined /> Abrir cámara del teléfono</a-button>
                <a-button size="large" @click="iniciar"><ReloadOutlined /> Reintentar</a-button>
            </div>
            <a-spin v-if="iniciando" class="vt-cam-spin" />
        </div>
        <input ref="nativo" type="file" accept="image/*" capture="environment" style="display: none" @change="desdeNativo" />

        <div class="flex justify-center gap-3 mt-4 flex-wrap">
            <template v-if="!captura">
                <a-button size="large" :disabled="!!error" @click="cambiarCamara"><SwapOutlined /> Cambiar cámara</a-button>
                <a-button type="primary" size="large" class="vt-cam-shot" :disabled="!stream" @click="tomar"><CameraOutlined /> Tomar foto</a-button>
            </template>
            <template v-else>
                <a-button size="large" @click="repetir"><RetweetOutlined /> Tomar otra</a-button>
                <a-button type="primary" size="large" @click="usar"><CheckOutlined /> Usar esta foto</a-button>
            </template>
        </div>
    </a-modal>
</template>

<style>
.vt-cam {
    position: relative;
    background: #0a1f44;
    border-radius: 14px;
    overflow: hidden;
    min-height: 360px;
    display: grid;
    place-items: center;
    margin-top: 10px;
}
.vt-cam video,
.vt-cam img {
    width: 100%;
    max-height: 62vh;
    object-fit: contain;
    display: block;
}
.vt-cam-error {
    color: #d6e3f5;
    text-align: center;
    padding: 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}
.vt-cam-guia {
    position: absolute;
    left: 15%;
    width: 70%;
    top: 34%;
    height: 32%;
    border: 3px dashed rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    box-shadow: 0 0 0 9999px rgba(10, 31, 68, 0.35);
    pointer-events: none;
}
.vt-cam-guia span {
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    color: #fff;
    font-weight: 700;
    font-size: 13px;
    white-space: nowrap;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6);
}
.vt-cam-spin {
    position: absolute;
}
.vt-cam-shot {
    min-width: 180px;
}
</style>
