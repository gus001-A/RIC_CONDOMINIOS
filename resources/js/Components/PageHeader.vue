<script setup>
/**
 * Encabezado de las pantallas de condominios (estilo VenTrack / SURVICOS):
 * icono, título, subtítulo y acciones a la derecha. Va DENTRO del contenedor
 * .vt-wrap, así título, tarjetas y formularios tienen exactamente el mismo ancho.
 */
import { Head } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
});
</script>

<template>
    <Head :title="title" />
    <div class="vt-page-head">
        <div class="min-w-0">
            <h1>
                <span v-if="icon" class="vt-head-icon"><component :is="icon" /></span>
                <span class="truncate">{{ title }}</span>
                <slot name="titulo" />
            </h1>
            <p v-if="subtitle || $slots.subtitulo"><slot name="subtitulo">{{ subtitle }}</slot></p>
        </div>
        <div class="flex gap-2 flex-wrap"><slot name="extra" /></div>
    </div>
</template>
