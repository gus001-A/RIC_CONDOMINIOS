<template>
    <div class="mapa-gps">
        <div ref="contenedor" class="mapa-gps-mapa" :style="{ height }"></div>

        <div v-if="editable" class="mapa-gps-barra">
            <span class="mapa-gps-ayuda">
                <InfoCircleOutlined />
                Haz clic en el mapa o arrastra el marcador para fijar la casa.
            </span>
            <div class="mapa-gps-acciones">
                <a-button size="small" :loading="localizando" @click="usarMiUbicacion"><AimOutlined /> Usar mi ubicación</a-button>
                <a-button v-if="tieneCoordenadas" size="small" danger @click="quitar"><CloseOutlined /> Quitar</a-button>
            </div>
        </div>

        <div class="mapa-gps-coords">
            <template v-if="tieneCoordenadas">
                <span><b>Lat:</b> {{ lat.toFixed(6) }}</span>
                <span><b>Lng:</b> {{ lng.toFixed(6) }}</span>
                <a :href="`https://www.google.com/maps?q=${lat},${lng}`" target="_blank" rel="noopener">
                    <ExportOutlined /> Abrir en Google Maps
                </a>
            </template>
            <span v-else class="mapa-gps-sin">Sin ubicación GPS registrada</span>
        </div>
        <p v-if="mensaje" style="color:#d9363e;font-size:12px;margin:0">{{ mensaje }}</p>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import L from 'leaflet';
import { InfoCircleOutlined, AimOutlined, CloseOutlined, ExportOutlined } from '@ant-design/icons-vue';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    lat: { type: Number, default: null },
    lng: { type: Number, default: null },
    editable: { type: Boolean, default: false },
    height: { type: String, default: '300px' },
    // Centro inicial cuando la casa aún no tiene GPS (p. ej. el condominio).
    // Sin centro se muestra todo México.
    centro: { type: Array, default: null },
});

const MEXICO = [23.6345, -102.5528];

const emit = defineEmits(['update:lat', 'update:lng']);

const contenedor = ref(null);
const localizando = ref(false);
const mensaje = ref('');
let mapa = null;
let marcador = null;

const tieneCoordenadas = computed(() => props.lat !== null && props.lng !== null && !Number.isNaN(props.lat));

// Marcador con divIcon: evita el problema de rutas de las imágenes
// por defecto de Leaflet al empaquetar con Vite.
const icono = L.divIcon({
    className: 'mapa-gps-pin',
    html: '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M12 3 2 12h3v8h5v-6h4v6h5v-8h3z"/></svg>',
    iconSize: [34, 34],
    iconAnchor: [17, 34],
});

const fijar = (lat, lng) => {
    emit('update:lat', Number(lat.toFixed(7)));
    emit('update:lng', Number(lng.toFixed(7)));
};

const pintarMarcador = () => {
    if (!mapa) return;
    if (!tieneCoordenadas.value) {
        if (marcador) { marcador.remove(); marcador = null; }
        return;
    }
    const pos = [props.lat, props.lng];
    if (marcador) {
        marcador.setLatLng(pos);
    } else {
        marcador = L.marker(pos, { icon: icono, draggable: props.editable }).addTo(mapa);
        marcador.on('dragend', (e) => {
            const p = e.target.getLatLng();
            fijar(p.lat, p.lng);
        });
    }
};

const usarMiUbicacion = () => {
    mensaje.value = '';
    if (!navigator.geolocation) {
        mensaje.value = 'Este navegador no permite obtener la ubicación.';
        return;
    }
    localizando.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            localizando.value = false;
            fijar(pos.coords.latitude, pos.coords.longitude);
            mapa?.setView([pos.coords.latitude, pos.coords.longitude], 18);
        },
        () => {
            localizando.value = false;
            mensaje.value = 'No se pudo obtener tu ubicación. Revisa el permiso de ubicación del navegador.';
        },
        { enableHighAccuracy: true, timeout: 15000 },
    );
};

const quitar = () => {
    emit('update:lat', null);
    emit('update:lng', null);
};

onMounted(() => {
    const [vista, zoom] = tieneCoordenadas.value
        ? [[props.lat, props.lng], 18]
        : props.centro ? [props.centro, 17] : [MEXICO, 5];
    mapa = L.map(contenedor.value, { scrollWheelZoom: false }).setView(vista, zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(mapa);

    if (props.editable) {
        mapa.on('click', (e) => fijar(e.latlng.lat, e.latlng.lng));
    }
    pintarMarcador();
});

watch(() => [props.lat, props.lng], pintarMarcador);

onBeforeUnmount(() => {
    mapa?.remove();
    mapa = null;
});
</script>

<style scoped>
.mapa-gps { display: flex; flex-direction: column; gap: 8px; }
.mapa-gps-mapa { width: 100%; border-radius: 10px; border: 1px solid var(--vt-line, #e1e8f0); z-index: 0; }
.mapa-gps-barra { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.mapa-gps-ayuda { font-size: 12px; color: #64748b; display: inline-flex; align-items: center; gap: 6px; }
.mapa-gps-acciones { display: flex; gap: 6px; }
.mapa-gps-coords { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: #334155; }
.mapa-gps-coords a { color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
.mapa-gps-coords a:hover { text-decoration: underline; }
.mapa-gps-sin { color: #94a3b8; font-style: italic; }

:deep(.mapa-gps-pin) {
    background: #1a3a5c; color: #fff; border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg); display: flex; align-items: center; justify-content: center;
    border: 2px solid #fff; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
}
:deep(.mapa-gps-pin svg) { transform: rotate(45deg); font-size: 14px; }
</style>
