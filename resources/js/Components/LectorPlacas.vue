<script setup>
/**
 * Botón "Leer placa": toma (o elige) la foto del vehículo, lee la placa con
 * OCR en el navegador y la regresa para llenar el formulario.
 *   <LectorPlacas @leida="({ placa, foto, guardarFoto }) => …" />
 */
import { ref, onBeforeUnmount } from 'vue';
import { message } from 'ant-design-vue';
import { ScanOutlined, CameraOutlined, PictureOutlined, CheckOutlined, RetweetOutlined, WarningOutlined } from '@ant-design/icons-vue';
import CamaraModal from './CamaraModal.vue';
import { leerPlaca, progreso } from '@/composables/lectorPlacas';

defineProps({ bloque: { type: Boolean, default: false } });
const emit = defineEmits(['leida']);

const abierto = ref(false);
const camara = ref(false);
const elegir = ref(null);
const foto = ref(null);
const vista = ref(null);
const leyendo = ref(false);
const placa = ref('');
const candidatos = ref([]);
const sinResultado = ref(false);
const guardarFoto = ref(true);

const limpiar = () => {
    if (vista.value) URL.revokeObjectURL(vista.value);
    foto.value = null;
    vista.value = null;
    placa.value = '';
    candidatos.value = [];
    sinResultado.value = false;
};
onBeforeUnmount(limpiar);

const abrir = () => { limpiar(); abierto.value = true; camara.value = true; };

async function procesar(f) {
    if (!f) return;
    if (!f.type.startsWith('image/')) return message.error('El archivo debe ser una imagen.');
    limpiar();
    foto.value = f;
    vista.value = URL.createObjectURL(f);
    leyendo.value = true;
    try {
        const r = await leerPlaca(f);
        candidatos.value = r.candidatos;
        placa.value = r.placa || '';
        sinResultado.value = !r.placa;
    } catch (e) {
        sinResultado.value = true;
        message.error('No se pudo cargar el lector de placas.');
    } finally {
        leyendo.value = false;
    }
}

const desdeArchivo = (e) => { procesar(e.target.files?.[0]); e.target.value = ''; };

const usar = () => {
    const limpia = placa.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (limpia.length < 4) return message.warning('Escribe o corrige la placa antes de usarla.');
    emit('leida', { placa: limpia, foto: foto.value, guardarFoto: guardarFoto.value });
    message.success(`Placa ${limpia} capturada.`);
    abierto.value = false;
};
</script>

<template>
    <a-tooltip title="Tomar foto de la placa y llenarla automáticamente">
        <a-button :block="bloque" @click="abrir"><ScanOutlined style="color: #7c4ddb" /> Leer placa</a-button>
    </a-tooltip>

    <CamaraModal v-model:open="camara" guia="placa" titulo="Foto de la placa" subtitulo="Encuadra la placa dentro del recuadro" @foto="procesar" />
    <input ref="elegir" type="file" accept="image/*" style="display: none" @change="desdeArchivo" />

    <a-modal v-model:open="abierto" width="560px" centered :footer="null">
        <template #title>
            <div class="vt-modal-title">
                <span class="vt-head-icon" style="background: linear-gradient(135deg, #7c4ddb, #1a5a96)"><ScanOutlined /></span>
                <div>Leer placa<small>Se lee en este equipo; la foto no sale a internet</small></div>
            </div>
        </template>

        <div v-if="!foto" class="lp-vacio">
            <ScanOutlined style="font-size: 40px; color: #7c4ddb" />
            <p>Toma una foto de la placa del vehículo o elige una ya tomada.</p>
            <div class="flex gap-2 flex-wrap justify-center">
                <a-button type="primary" size="large" @click="camara = true"><CameraOutlined /> Tomar foto</a-button>
                <a-button size="large" @click="elegir?.click()"><PictureOutlined /> Elegir foto</a-button>
            </div>
        </div>

        <template v-else>
            <div class="lp-foto"><img :src="vista" alt="Foto de la placa" /></div>

            <div v-if="leyendo" class="lp-leyendo">
                <a-progress :percent="progreso" status="active" :show-info="false" />
                <span class="vt-small vt-muted">Leyendo la placa… (la primera vez tarda un poco más mientras se carga el lector)</span>
            </div>

            <template v-else>
                <div v-if="sinResultado" class="vt-alert mt-3 mb-2">
                    <WarningOutlined /> <span>No se distinguió la placa. Escríbela abajo o toma otra foto más de cerca y sin reflejos.</span>
                </div>
                <a-form layout="vertical" class="mt-3">
                    <a-form-item label="Placa" :help="candidatos.length > 1 ? 'Si no es correcta, elige otra lectura o corrígela.' : 'Revisa que coincida con la foto; puedes corregirla.'">
                        <a-input v-model:value="placa" size="large" class="lp-placa" :maxlength="10" placeholder="ABC123" @press-enter="usar" />
                    </a-form-item>
                    <div v-if="candidatos.length > 1" class="flex gap-2 flex-wrap mb-3">
                        <a-tag v-for="c in candidatos" :key="c" class="lp-opcion" :color="c === placa ? 'blue' : undefined" @click="placa = c">{{ c }}</a-tag>
                    </div>
                    <a-checkbox v-model:checked="guardarFoto">Guardar esta foto como foto de la visita</a-checkbox>
                </a-form>
                <div class="flex justify-end gap-2 mt-4">
                    <a-button size="large" @click="camara = true"><RetweetOutlined /> Tomar otra</a-button>
                    <a-button type="primary" size="large" @click="usar"><CheckOutlined /> Usar placa</a-button>
                </div>
            </template>
        </template>
    </a-modal>
</template>

<style>
.lp-vacio { text-align: center; padding: 24px 12px; display: flex; flex-direction: column; align-items: center; gap: 12px; color: var(--vt-muted); }
.lp-foto { background: #0a1f44; border-radius: 12px; overflow: hidden; display: grid; place-items: center; }
.lp-foto img { width: 100%; max-height: 260px; object-fit: contain; }
.lp-leyendo { margin-top: 14px; display: flex; flex-direction: column; gap: 4px; }
.lp-placa input, input.lp-placa { font: 800 24px 'Bricolage Grotesque', Figtree, sans-serif !important; letter-spacing: 3px; text-align: center; text-transform: uppercase; }
.lp-opcion { cursor: pointer; font-weight: 700; letter-spacing: 1px; padding: 3px 10px; font-size: 13px; }
</style>
