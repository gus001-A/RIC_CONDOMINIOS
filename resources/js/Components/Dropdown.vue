<!-- resources/js/Components/Dropdown.vue -->
<script setup>
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: String,
        default: 'py-1 bg-white',
    },
});

const open = ref(false);
const dropdownRef = ref(null);
const triggerRef = ref(null);

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

// Cerrar al hacer click fuera
const handleClickOutside = (event) => {
    if (open.value) {
        const target = event.target;
        // Verificar si el click fue dentro del dropdown o en el trigger
        const isInsideDropdown = dropdownRef.value?.contains(target);
        const isInsideTrigger = triggerRef.value?.contains(target);
        
        if (!isInsideDropdown && !isInsideTrigger) {
            open.value = false;
        }
    }
};

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.removeEventListener('click', handleClickOutside);
});

const widthClass = computed(() => {
    return {
        '48': 'w-48',
        '72': 'w-72',
        '80': 'w-80',
    }[props.width.toString()] || 'w-48';
});

const alignmentClasses = computed(() => {
    if (props.align === 'left') {
        return 'ltr:origin-top-left rtl:origin-top-right start-0';
    } else if (props.align === 'right') {
        return 'ltr:origin-top-right rtl:origin-top-left end-0';
    } else {
        return 'origin-top';
    }
});

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

// Exponer métodos
defineExpose({
    open,
    toggle,
    close,
});
</script>

<template>
    <div class="relative inline-block">
        <div 
            ref="triggerRef"
            @click="toggle" 
            class="cursor-pointer"
        >
            <slot name="trigger" />
        </div>

        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 -translate-y-2 scale-95"
        >
            <div
                v-if="open"
                ref="dropdownRef"
                class="absolute z-50 mt-2 rounded-2xl shadow-2xl"
                :class="[widthClass, alignmentClasses]"
            >
                <div
                    class="rounded-2xl ring-1 ring-black ring-opacity-5 overflow-hidden"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.rounded-2xl {
    border-radius: 1rem;
}

.shadow-2xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.ring-1 {
    ring: 1px solid rgba(0, 0, 0, 0.05);
}
</style>