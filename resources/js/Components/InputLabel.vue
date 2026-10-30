<template>
    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>

<script setup>
defineProps({
    value: {
        type: String,
        default: null
    }
});
</script>