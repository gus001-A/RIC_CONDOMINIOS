<!-- resources/js/Components/DropdownLink.vue -->
<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
    method: {
        type: String,
        default: 'get',
    },
    as: {
        type: String,
        default: 'a',
    },
});
</script>

<template>
    <Link
        :href="href"
        :method="method"
        :as="as"
        class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-[#0A1628]/5 transition-all duration-200 group w-full text-left"
    >
        <slot />
    </Link>
</template>

<style scoped>
.group:hover {
    background-color: rgba(10, 22, 40, 0.05);
}
</style>