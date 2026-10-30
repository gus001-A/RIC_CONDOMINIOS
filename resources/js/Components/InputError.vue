<template>
    <div v-if="message">
        <p class="text-sm text-red-600">{{ message }}</p>
    </div>
</template>

<script setup>
defineProps({
    message: {
        type: String,
        default: null
    }
});
</script>