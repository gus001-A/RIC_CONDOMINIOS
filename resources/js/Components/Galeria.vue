<script setup>
/**
 * Miniaturas de fotos y archivos ya guardados; clic = visor en modal (zoom,
 * giro, descarga), como en SURVICOS.
 *   items: [{ id, url, nombre, mime, detalle, etiqueta, eliminable }]
 */
import { ref } from 'vue';
import { EyeOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import VisorArchivo from './VisorArchivo.vue';
import { tipoArchivo, ICONO_TIPO, COLOR_TIPO } from '@/composables/archivos';

defineProps({
    items: { type: Array, default: () => [] },
    tamano: { type: String, default: 'normal' }, // normal | chico
    vacio: { type: String, default: '' },
});
const emit = defineEmits(['eliminar']);

const visor = ref({ open: false, url: null, nombre: '', mime: null, detalle: null });
const ver = (i) => (visor.value = { open: true, url: i.url, nombre: i.nombre || 'Archivo', mime: i.mime || null, detalle: i.detalle || null });
</script>

<template>
    <div v-if="items.length" class="gal" :class="tamano">
        <div v-for="i in items" :key="i.id ?? i.url" class="gal-item">
            <div class="gal-media" @click="ver(i)">
                <img v-if="tipoArchivo(i.nombre || i.url, i.mime) === 'imagen'" :src="i.url" :alt="i.nombre" loading="lazy" />
                <span v-else class="gal-ic" :style="{ color: COLOR_TIPO[tipoArchivo(i.nombre || i.url, i.mime)] }"><component :is="ICONO_TIPO[tipoArchivo(i.nombre || i.url, i.mime)]" /></span>
                <span class="gal-ver"><EyeOutlined /></span>
                <span v-if="i.etiqueta" class="gal-tag">{{ i.etiqueta }}</span>
            </div>
            <div v-if="i.detalle || i.eliminable || $slots.pie" class="gal-pie">
                <span class="flex-1 min-w-0"><slot name="pie" :item="i"><small class="truncate block">{{ i.detalle }}</small></slot></span>
                <a-tooltip v-if="i.eliminable" title="Eliminar"><a-button type="text" danger size="small" @click="emit('eliminar', i)"><DeleteOutlined /></a-button></a-tooltip>
            </div>
        </div>
    </div>
    <div v-else-if="vacio" class="vt-empty">{{ vacio }}</div>

    <VisorArchivo v-model:open="visor.open" :url="visor.url" :nombre="visor.nombre" :mime="visor.mime" :detalle="visor.detalle" />
</template>

<style>
.gal { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
.gal.chico { grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); gap: 8px; }
.gal-item { border: 1px solid var(--vt-line); border-radius: 12px; overflow: hidden; background: #fff; transition: box-shadow 0.15s, transform 0.15s; }
.gal-item:hover { box-shadow: 0 8px 20px rgba(15, 42, 71, 0.12); transform: translateY(-2px); }
.gal-media { position: relative; height: 112px; background: #f4f7fb; display: grid; place-items: center; cursor: zoom-in; }
.gal.chico .gal-media { height: 78px; }
.gal-media img { width: 100%; height: 100%; object-fit: cover; }
.gal-ic { font-size: 40px; }
.gal-ver { position: absolute; right: 6px; bottom: 6px; width: 24px; height: 24px; border-radius: 50%; background: rgba(15, 42, 71, 0.72); color: #fff; display: grid; place-items: center; font-size: 12px; opacity: 0; transition: opacity 0.15s; }
.gal-item:hover .gal-ver { opacity: 1; }
.gal-tag { position: absolute; left: 6px; top: 6px; background: rgba(255, 255, 255, 0.92); color: var(--vt-navy); font-size: 10.5px; font-weight: 700; padding: 1px 8px; border-radius: 99px; }
.gal-pie { display: flex; align-items: center; gap: 4px; padding: 5px 8px; font-size: 11.5px; color: var(--vt-muted); }
</style>
