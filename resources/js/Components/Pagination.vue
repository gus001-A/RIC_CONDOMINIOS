<template>
    <div v-if="links.length > 3" class="flex items-center justify-between">
        <!-- Versión móvil -->
        <div class="flex-1 flex justify-between sm:hidden">
            <Link 
                v-if="links[0].url" 
                :href="links[0].url" 
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200"
            >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Anterior
            </Link>
            <Link 
                v-if="links[links.length - 1].url" 
                :href="links[links.length - 1].url" 
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200"
            >
                Siguiente
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>
        </div>

        <!-- Versión desktop -->
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <nav class="relative z-0 inline-flex rounded-lg shadow-sm" style="gap: 4px;">
                    <!-- Botón Anterior con ícono -->
                    <Link 
                        v-if="links[0].url" 
                        :href="links[0].url" 
                        class="relative inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-l-lg text-gray-700 bg-white hover:bg-gray-50 hover:text-gray-900 transition-all duration-200"
                        style="border-radius: 8px 0 0 8px;"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        <span class="sr-only">Anterior</span>
                    </Link>
                    <Link 
                        v-else 
                        disabled
                        class="relative inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-l-lg text-gray-400 bg-gray-50 cursor-not-allowed"
                        style="border-radius: 8px 0 0 8px;"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        <span class="sr-only">Anterior</span>
                    </Link>

                    <!-- Números de página -->
                    <template v-for="(link, key) in pageLinks" :key="key">
                        <Link 
                            v-if="link.url" 
                            :href="link.url" 
                            :class="[
                                link.active 
                                    ? 'z-10 bg-gradient-to-br from-blue-600 to-blue-700 border-blue-600 text-white shadow-md' 
                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400',
                                'relative inline-flex items-center px-4 py-2 border text-sm font-medium transition-all duration-200'
                            ]"
                            style="border-radius: 6px; margin: 0 2px;"
                            v-html="link.label"
                        />
                        <span 
                            v-else 
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-400 cursor-default"
                            style="border-radius: 6px; margin: 0 2px;"
                            v-html="link.label"
                        />
                    </template>

                    <!-- Botón Siguiente con ícono -->
                    <Link 
                        v-if="links[links.length - 1].url" 
                        :href="links[links.length - 1].url" 
                        class="relative inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-r-lg text-gray-700 bg-white hover:bg-gray-50 hover:text-gray-900 transition-all duration-200"
                        style="border-radius: 0 8px 8px 0;"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="sr-only">Siguiente</span>
                    </Link>
                    <Link 
                        v-else 
                        disabled
                        class="relative inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-r-lg text-gray-400 bg-gray-50 cursor-not-allowed"
                        style="border-radius: 0 8px 8px 0;"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="sr-only">Siguiente</span>
                    </Link>
                </nav>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    links: {
        type: Array,
        required: true
    }
});

// Calcular el primer y último elemento mostrado
const firstItem = computed(() => {
    if (!props.links || props.links.length < 2) return 0;
    // Buscar el primer enlace que no sea prev/next y tenga URL
    const firstLink = props.links.find(link => link.url && !link.label.includes('Previous') && !link.label.includes('Next'));
    return firstLink ? firstLink.label : 0;
});

const lastItem = computed(() => {
    if (!props.links || props.links.length < 2) return 0;
    // Buscar el último enlace que no sea prev/next y tenga URL
    const reversedLinks = [...props.links].reverse();
    const lastLink = reversedLinks.find(link => link.url && !link.label.includes('Previous') && !link.label.includes('Next'));
    return lastLink ? lastLink.label : 0;
});

const totalItems = computed(() => {
    // Extraer el total de los enlaces (asumiendo que viene en el meta)
    // Si no está disponible, usar el último número de página
    const lastPageLink = props.links[props.links.length - 1];
    if (lastPageLink && lastPageLink.url) {
        return lastPageLink.label;
    }
    return '...';
});

// Filtrar enlaces para mostrar solo números de página (excluyendo prev/next)
const pageLinks = computed(() => {
    return props.links.filter(link => {
        return !link.label.includes('Previous') && 
               !link.label.includes('Next') && 
               link.label !== 'Anterior' && 
               link.label !== 'Siguiente';
    });
});
</script>

<style scoped>
/* Estilos premium para la paginación */
:deep(.relative.inline-flex.items-center) {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Estilo para el botón activo */
:deep(.bg-gradient-to-br.from-blue-600.to-blue-700) {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

:deep(.bg-gradient-to-br.from-blue-600.to-blue-700:hover) {
    transform: scale(1.08);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
}

/* Hover en números de página */
:deep(.bg-white.border-gray-300:hover:not(.bg-gradient-to-br)) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

/* Hover en botones de navegación */
:deep(.rounded-l-lg:hover:not(.cursor-not-allowed)),
:deep(.rounded-r-lg:hover:not(.cursor-not-allowed)) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

/* Efecto de escala en los íconos al hacer hover */
:deep(.rounded-l-lg:hover:not(.cursor-not-allowed) svg),
:deep(.rounded-r-lg:hover:not(.cursor-not-allowed) svg) {
    transform: scale(1.2);
    transition: transform 0.2s ease;
}

:deep(.rounded-l-lg svg),
:deep(.rounded-r-lg svg) {
    transition: transform 0.2s ease;
}

/* Puntos suspensivos (ellipsis) */
:deep(.cursor-default) {
    color: #9ca3af;
    min-width: 40px;
    text-align: center;
}

/* Animación de entrada */
:deep(.relative.inline-flex.items-center) {
    animation: fadeInUp 0.3s ease forwards;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive - versión móvil */
@media (max-width: 640px) {
    :deep(.flex-1.flex.justify-between.sm\:hidden) {
        padding: 8px 0;
        gap: 12px;
    }
    
    :deep(.flex-1.flex.justify-between.sm\:hidden a) {
        flex: 1;
        justify-content: center;
        padding: 10px 16px;
        border-radius: 8px;
        font-weight: 600;
    }
}
</style>