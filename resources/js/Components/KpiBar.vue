<script setup>
/** Fila de tarjetas de indicadores con icono de color (opcionalmente clicables como filtro). */
defineProps({
    items: { type: Array, required: true }, // [{ key, icon, valor, label, color, activo }]
    clicables: { type: Boolean, default: false },
});
const emit = defineEmits(['elegir']);
</script>

<template>
    <div class="vt-kpi-row">
        <component :is="clicables ? 'button' : 'div'" v-for="(k, i) in items" :key="k.key" type="button" class="vt-kpi-c" :class="{ on: k.activo }"
            :style="{ '--k': k.color, animationDelay: i * 0.04 + 's' }" @click="clicables && emit('elegir', k.key)">
            <span class="vt-kpi-ic"><component :is="k.icon" /></span>
            <span class="min-w-0"><b>{{ k.valor }}</b><small>{{ k.label }}</small></span>
        </component>
    </div>
</template>
