<template>
    <!-- Grupo del menú superior (Condominio, Finanzas…) con su lista desplegable -->
    <div ref="raiz" class="relative" @mouseleave="abierto = false">
        <button
            type="button"
            class="relative px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 flex items-center gap-2"
            :class="activo ? 'text-[#0A1628] bg-[#0A1628]/5' : 'text-gray-600 hover:text-[#0A1628] hover:bg-[#0A1628]/5'"
            @click="abierto = !abierto"
            @mouseenter="abierto = true"
        >
            <i :class="icono" class="text-sm"></i>
            {{ label }}
            <span v-if="globo" class="min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold inline-flex items-center justify-center">{{ globo }}</span>
            <DownOutlined style="font-size: 10px; opacity: 0.6" />
            <span v-if="activo" class="absolute bottom-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-[#0A1628] rounded-full"></span>
        </button>
        <transition enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition duration-100" leave-to-class="opacity-0">
            <div v-if="abierto" class="absolute left-0 top-full pt-1 z-50">
                <div class="min-w-[210px] bg-white rounded-xl shadow-xl border border-gray-100 py-1">
                    <Link
                        v-for="item in items"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm transition-colors"
                        :class="item.activo ? 'text-[#0A1628] bg-[#0A1628]/5 font-semibold' : 'text-gray-700 hover:bg-[#0A1628]/5'"
                        @click="abierto = false"
                    >
                        <i :class="item.icono" class="text-sm w-4"></i>
                        <span class="flex-1">{{ item.label }}</span>
                        <span v-if="item.globo" class="min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold inline-flex items-center justify-center">{{ item.globo }}</span>
                    </Link>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { DownOutlined } from '@ant-design/icons-vue';
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    label: String,
    icono: String,
    items: Array,
});

const abierto = ref(false);
const activo = computed(() => props.items.some((i) => i.activo));
const globo = computed(() => props.items.reduce((s, i) => s + (i.globo || 0), 0));
</script>
