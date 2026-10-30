<template>
    <input type="checkbox" 
           class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
           :checked="checked"
           @change="$emit('update:checked', $event.target.checked)">
</template>

<script setup>
defineProps({
    checked: {
        type: Boolean,
        default: false
    }
});

defineEmits(['update:checked']);
</script>