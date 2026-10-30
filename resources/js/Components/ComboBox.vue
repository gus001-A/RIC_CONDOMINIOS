<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';

/**
 * Combo editable: input de texto + lista desplegable con estilo propio.
 *
 *  - Al hacer clic / enfocar se abre con TODAS las opciones (como un <select>).
 *  - Sólo se filtra mientras el usuario escribe.
 *  - Enter NUNCA envía el formulario: elige la opción resaltada / la única
 *    coincidencia, o simplemente cierra conservando lo escrito.
 *  - Si se escribe algo que no está en la lista, al hacer clic fuera se conserva.
 *  - La lista se renderiza con <Teleport to="body"> y posición fija, para que
 *    NO la recorte el `overflow:hidden` de la tarjeta del formulario
 *    (igual que el desplegable nativo de un <select>).
 *
 *   <ComboBox v-model="form.municipio" :options="municipios" placeholder="Municipio" />
 */
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },        // ['Cuernavaca', ...] ó [{ label, value }]
    placeholder: { type: String, default: '' },
    inputClass: { type: [String, Array, Object], default: '' },
    disabled: { type: Boolean, default: false },
    emptyText: { type: String, default: 'Sin coincidencias' },
    hint: {
        type: String,
        default: 'Elige una opción de la lista, o haz clic fuera del recuadro para conservar lo que escribiste.',
    },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const activeIndex = ref(-1);
const userTyped = ref(false); // true sólo mientras el usuario escribe -> controla el filtrado
const rootEl = ref(null);
const inputEl = ref(null);
const listEl = ref(null);
const panelEl = ref(null);
const panelStyle = ref({});

const normalize = (s) =>
    String(s ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '');

const asItem = (o) => (typeof o === 'object' && o !== null ? o : { label: String(o), value: o });

const allItems = computed(() => props.options.map(asItem));

const filtered = computed(() => {
    // Al abrir con clic/foco se muestran TODAS las opciones (como un <select>).
    // Sólo se filtra cuando el usuario está escribiendo activamente.
    if (!userTyped.value) return allItems.value.slice(0, 300);
    const q = normalize(props.modelValue);
    if (!q) return allItems.value.slice(0, 300);
    return allItems.value.filter((i) => normalize(i.label).includes(q)).slice(0, 300);
});

// ── Posicionamiento del panel (teleport a <body>, position: fixed) ──────────
const actualizarPosicion = () => {
    const el = inputEl.value;
    if (!el) return;
    const r = el.getBoundingClientRect();
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const espacioAbajo = vh - r.bottom;
    const abrirArriba = espacioAbajo < 260 && r.top > espacioAbajo;
    const maxH = Math.max(160, Math.min(300, (abrirArriba ? r.top : espacioAbajo) - 16));

    const width = Math.min(Math.max(r.width, 240), vw - 16);
    let left = r.left;
    if (left + width > vw - 8) left = vw - 8 - width;
    if (left < 8) left = 8;

    panelStyle.value = {
        position: 'fixed',
        left: `${Math.round(left)}px`,
        width: `${Math.round(width)}px`,
        maxHeight: `${Math.round(maxH)}px`,
        ...(abrirArriba
            ? { bottom: `${Math.round(vh - r.top + 6)}px` }
            : { top: `${Math.round(r.bottom + 6)}px` }),
    };
};

const scrollActiveIntoView = () => {
    nextTick(() => {
        requestAnimationFrame(() => {
            const cont = listEl.value;
            if (!cont) return;
            const el = cont.querySelector('.combo-item.is-active') || cont.querySelector('.combo-item.is-selected');
            if (el) el.scrollIntoView({ block: 'nearest' });
        });
    });
};

const openList = () => {
    if (props.disabled) return;
    // Al abrir desde cerrado (clic / foco / caret) se muestra la lista COMPLETA,
    // como un <select>. Si ya estaba abierta (el usuario escribe) no se reinicia.
    if (!open.value) userTyped.value = false;
    open.value = true;
    actualizarPosicion();
    const idx = filtered.value.findIndex((i) => String(i.value) === String(props.modelValue));
    activeIndex.value = idx;
    scrollActiveIntoView();
};

const closeList = () => {
    open.value = false;
    activeIndex.value = -1;
};

const toggleList = () => {
    if (props.disabled) return;
    if (open.value) {
        closeList();
    } else {
        inputEl.value?.focus();
        openList();
    }
};

const onInput = (e) => {
    userTyped.value = true;
    emit('update:modelValue', e.target.value);
    open.value = true;
    activeIndex.value = -1;
    nextTick(actualizarPosicion);
};

const pick = (item) => {
    emit('update:modelValue', item.value);
    userTyped.value = false;
    closeList();
    nextTick(() => inputEl.value?.blur());
};

const onKeydown = (e) => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!open.value) {
            openList();
            return;
        }
        const n = filtered.value.length;
        if (!n) return;
        activeIndex.value = e.key === 'ArrowDown'
            ? (activeIndex.value + 1) % n
            : (activeIndex.value - 1 + n) % n;
        scrollActiveIntoView();
        return;
    }

    if (e.key === 'Enter') {
        // NUNCA enviar el formulario desde el combo: Enter = completar.
        e.preventDefault();
        if (!open.value) {
            openList();
            return;
        }
        if (activeIndex.value >= 0 && filtered.value[activeIndex.value]) {
            pick(filtered.value[activeIndex.value]);
        } else if (filtered.value.length === 1) {
            pick(filtered.value[0]);
        } else {
            // conserva lo escrito y cierra
            closeList();
            nextTick(() => inputEl.value?.blur());
        }
        return;
    }

    if (e.key === 'Escape') {
        closeList();
        return;
    }

    if (e.key === 'Tab') {
        closeList(); // al salir con Tab conserva lo escrito
    }
};

const onBlur = () => {
    // pequeño delay para permitir el click en un item de la lista
    setTimeout(closeList, 150);
};

// Cerrar (conservando lo escrito) al hacer clic FUERA del combo y de su panel.
const onDocPointerDown = (e) => {
    if (!open.value) return;
    const dentroRoot = rootEl.value && rootEl.value.contains(e.target);
    const dentroPanel = panelEl.value && panelEl.value.contains(e.target);
    if (!dentroRoot && !dentroPanel) closeList();
};

const onReposition = () => {
    if (open.value) actualizarPosicion();
};

onMounted(() => {
    document.addEventListener('mousedown', onDocPointerDown, true);
    window.addEventListener('scroll', onReposition, true);
    window.addEventListener('resize', onReposition);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocPointerDown, true);
    window.removeEventListener('scroll', onReposition, true);
    window.removeEventListener('resize', onReposition);
});
</script>

<template>
    <div ref="rootEl" class="combo" :class="{ 'combo-open': open }">
        <input
            ref="inputEl"
            type="text"
            :value="modelValue"
            :placeholder="placeholder"
            :class="inputClass"
            :disabled="disabled"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open"
            @input="onInput"
            @focus="openList"
            @click="openList"
            @blur="onBlur"
            @keydown="onKeydown"
        />
        <button
            type="button"
            class="combo-caret"
            tabindex="-1"
            :disabled="disabled"
            aria-hidden="true"
            @mousedown.prevent="toggleList"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Teleport to="body">
            <div v-if="open" ref="panelEl" class="combo-panel" :style="panelStyle">
                <ul ref="listEl" class="combo-list" role="listbox">
                    <li v-if="!filtered.length" class="combo-empty">{{ emptyText }}</li>
                    <li
                        v-for="(item, i) in filtered"
                        :key="item.value ?? i"
                        class="combo-item"
                        :class="{
                            'is-active': i === activeIndex,
                            'is-selected': String(item.value) === String(modelValue),
                        }"
                        role="option"
                        :aria-selected="String(item.value) === String(modelValue)"
                        @mousedown.prevent="pick(item)"
                        @mouseenter="activeIndex = i"
                    >
                        <span class="combo-item-label">{{ item.label }}</span>
                        <svg v-if="String(item.value) === String(modelValue)" class="combo-item-check" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                    </li>
                </ul>
                <p v-if="hint" class="combo-hint">{{ hint }}</p>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.combo {
    position: relative;
    width: 100%;
}

.combo input {
    width: 100%;
    padding: 9px 34px 9px 14px;
    font-size: 0.85rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    color: #1f2937;
    outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.combo input::placeholder {
    color: #9ca3af;
}

.combo input:hover:not(:focus):not(:disabled) {
    border-color: #cbd5e1;
}

.combo input:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.12);
}

.combo input:disabled {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
}

.combo input.error {
    border-color: #ef4444;
}

.combo-caret {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 6px;
    transition: transform 0.18s ease, color 0.15s ease, background 0.15s ease;
}

.combo-caret svg {
    width: 16px;
    height: 16px;
}

.combo-caret:hover:not(:disabled) {
    color: #1a3a5c;
    background: rgba(26, 58, 92, 0.08);
}

.combo-caret:disabled {
    cursor: not-allowed;
}

.combo-open .combo-caret {
    transform: translateY(-50%) rotate(180deg);
}
</style>

<style>
/* NO scoped: el panel se teletransporta a <body>. Clases suficientemente
   específicas para no colisionar. */
.combo-panel {
    z-index: 3000;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 16px 40px -12px rgba(15, 23, 42, 0.28), 0 0 0 1px rgba(15, 23, 42, 0.03);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.combo-panel .combo-list {
    margin: 0;
    padding: 6px;
    list-style: none;
    overflow-y: auto;
    flex: 1 1 auto;
    min-height: 0;
}

.combo-panel .combo-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px 12px;
    border-radius: 9px;
    font-size: 0.85rem;
    color: #334155;
    cursor: pointer;
    transition: background 0.12s ease, color 0.12s ease;
}

.combo-panel .combo-item.is-active {
    background: rgba(26, 58, 92, 0.08);
    color: #0f2136;
}

.combo-panel .combo-item.is-selected {
    color: #1a3a5c;
    font-weight: 600;
}

.combo-panel .combo-item-label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.combo-panel .combo-item-check {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    color: #1a3a5c;
}

.combo-panel .combo-empty {
    padding: 12px;
    text-align: center;
    font-size: 0.82rem;
    color: #94a3b8;
}

.combo-panel .combo-hint {
    margin: 0;
    padding: 8px 12px;
    border-top: 1px solid #eef2f7;
    background: #f8fafc;
    font-size: 0.72rem;
    line-height: 1.35;
    color: #64748b;
    flex-shrink: 0;
}

.combo-panel .combo-list::-webkit-scrollbar { width: 6px; }
.combo-panel .combo-list::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
</style>
