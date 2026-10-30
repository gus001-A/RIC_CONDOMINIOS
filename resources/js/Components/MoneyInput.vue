<template>
    <input
        ref="inputRef"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        :value="texto"
        @input="onInput"
        @focus="enfocado = true"
        @blur="onBlur"
    >
</template>

<script setup>
/**
 * Campo de dinero con separador de miles mientras se escribe: 13,983.27
 *
 * - v-model recibe y emite un Number (o null si está vacío), nunca el texto
 *   con comas, así que los cálculos siguen funcionando igual.
 * - Máximo 2 decimales. Al salir del campo se completan a .00
 * - Un 0 se muestra vacío (para no tener que borrarlo antes de escribir),
 *   salvo con :vacio-en-cero="false".
 * - Clases, placeholder, disabled, @blur, @focus, etc. pasan directo al <input>.
 */
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null
    },
    vacioEnCero: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['update:modelValue']);

const inputRef = ref(null);
const enfocado = ref(false);

// Agrega las comas a la parte entera de un texto ya limpio ("13983.2" -> "13,983.2").
const conComas = (limpio) => {
    const [entero, decimales] = limpio.split('.');
    const enteroFmt = (entero || '').replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return decimales !== undefined ? `${enteroFmt || '0'}.${decimales}` : enteroFmt;
};

// Texto que se muestra para un valor numérico.
const formatear = (valor, completarDecimales) => {
    if (valor === null || valor === undefined || valor === '') return '';
    const n = Number(valor);
    if (Number.isNaN(n)) return '';
    if (n === 0 && props.vacioEnCero) return '';
    const base = completarDecimales
        ? n.toFixed(2)
        : String(Math.round(n * 100) / 100);
    return conComas(base);
};

const aNumero = (limpio) => {
    if (limpio === '' || limpio === '.') return null;
    const n = parseFloat(limpio);
    return Number.isNaN(n) ? null : n;
};

const texto = ref(formatear(props.modelValue, true));
let ultimoEmitido = aNumero(texto.value.replace(/,/g, ''));

// Si el valor cambia desde afuera (p. ej. un botón "Liquidar todo"), se redibuja.
watch(() => props.modelValue, (nuevo) => {
    const n = nuevo === '' || nuevo === null || nuevo === undefined ? null : Number(nuevo);
    if (n === ultimoEmitido) return;
    ultimoEmitido = n;
    texto.value = formatear(n, !enfocado.value);
});

const onInput = (event) => {
    const el = event.target;
    const crudo = el.value;
    const cursor = el.selectionStart ?? crudo.length;

    // Cuántos dígitos/punto había antes del cursor, para dejarlo en el mismo lugar.
    const significativosAntes = crudo.slice(0, cursor).replace(/[^\d.]/g, '').length;

    // Sólo dígitos y un punto, máximo 2 decimales.
    let limpio = crudo.replace(/[^\d.]/g, '');
    const punto = limpio.indexOf('.');
    if (punto !== -1) {
        limpio = limpio.slice(0, punto + 1) + limpio.slice(punto + 1).replace(/\./g, '').slice(0, 2);
    }

    const formateado = conComas(limpio);
    texto.value = formateado;
    el.value = formateado;

    let pos = 0;
    let vistos = 0;
    while (pos < formateado.length && vistos < significativosAntes) {
        if (/[\d.]/.test(formateado[pos])) vistos++;
        pos++;
    }
    el.setSelectionRange(pos, pos);

    ultimoEmitido = aNumero(limpio);
    emit('update:modelValue', ultimoEmitido);
};

const onBlur = () => {
    enfocado.value = false;
    texto.value = formatear(ultimoEmitido, true);
};

defineExpose({ focus: () => inputRef.value?.focus() });
</script>
