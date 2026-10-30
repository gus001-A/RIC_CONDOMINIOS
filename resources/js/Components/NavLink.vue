<template>
    <Link :href="href" class="nav-link" :class="{ 'nav-link-active': active }">
        <slot />
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true
    },
    active: {
        type: Boolean,
        default: false
    }
});
</script>

<style scoped>
.nav-link {
    @apply px-3.5 py-2 rounded-xl text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100/80 transition-all duration-200;
}

.nav-link-active {
    @apply text-blue-700 bg-blue-50/80 hover:text-blue-800 hover:bg-blue-100/80;
}
</style>