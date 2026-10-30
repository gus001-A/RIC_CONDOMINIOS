<template>
    <input 
        class="border-2 border-gray-300 focus:border-[#1a3a5c] focus:ring-1 focus:ring-[#1a3a5c] focus:ring-offset-0 w-full h-10 px-3 text-sm bg-white transition-colors duration-200 outline-none" 
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        ref="input"
        v-bind="$attrs"
        :class="[
            error ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300',
            square ? 'rounded-none' : 'rounded-md'
        ]"
    >
</template>

<script setup>
defineProps({
    modelValue: {
        type: String,
        required: true
    },
    error: {
        type: Boolean,
        default: false
    },
    square: {
        type: Boolean,
        default: true
    }
});

defineEmits(['update:modelValue']);
</script>