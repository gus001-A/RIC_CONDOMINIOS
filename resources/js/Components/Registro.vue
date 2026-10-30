<script setup>
/** Quién hizo algo y cuándo (usuario, fecha y hora), en una o dos líneas. */
import { UserOutlined, ClockCircleOutlined } from '@ant-design/icons-vue';
import { fechaHora } from '@/composables/useFechaHora';

defineProps({
    usuario: { type: String, default: null },
    fecha: { type: String, default: null },
    etiqueta: { type: String, default: '' },
    enLinea: { type: Boolean, default: false },
});
</script>

<template>
    <div class="vt-reg" :class="{ linea: enLinea }">
        <b class="vt-reg-u"><span v-if="etiqueta" class="vt-reg-et">{{ etiqueta }}</span><UserOutlined v-if="enLinea" /> {{ usuario || 'Sistema' }}</b>
        <span v-if="fecha" class="vt-reg-f"><ClockCircleOutlined /> {{ fechaHora(fecha) }}</span>
    </div>
</template>

<style>
.vt-reg { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
.vt-reg-u { font-size: 12.5px; color: var(--vt-navy); font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.vt-reg-et { font-weight: 500; color: var(--vt-muted); margin-right: 4px; }
.vt-reg-f { font-size: 11.5px; color: var(--vt-muted); white-space: nowrap; }
.vt-reg-f .anticon { color: var(--vt-teal); font-size: 10.5px; }
.vt-reg.linea { flex-direction: row; align-items: center; gap: 10px; flex-wrap: wrap; }
.vt-reg.linea .vt-reg-u .anticon { color: var(--vt-blue); }
</style>
