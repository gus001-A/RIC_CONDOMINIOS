<!-- resources/js/Components/ColumnSelector.vue -->
<template>
    <div class="column-selector-wrapper" ref="wrapperRef">
        <button 
            @click.stop="toggleDropdown" 
            class="btn-column-selector"
            :title="'Seleccionar columnas'"
        >
            <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            <span class="btn-text">Columnas</span>
            <span v-if="columnasActivasCount > 0" class="badge-count">{{ columnasActivasCount }}</span>
        </button>

        <!-- Dropdown -->
        <div v-if="showDropdown" class="dropdown-panel" ref="dropdownRef">
            <div class="dropdown-header">
                <span class="dropdown-title">Mostrar columnas</span>
                <div class="dropdown-actions">
                    <button @click.stop="seleccionarTodas" class="dropdown-action-btn">Todas</button>
                    <button @click.stop="deseleccionarTodas" class="dropdown-action-btn">Ninguna</button>
                </div>
            </div>
            
            <div class="dropdown-body">
                <div 
                    v-for="columna in columnasDisponibles" 
                    :key="columna.key"
                    class="dropdown-item"
                >
                    <label class="dropdown-label">
                        <input 
                            type="checkbox" 
                            :checked="columnasActivas.includes(columna.key)"
                            @change="toggleColumna(columna.key)"
                            class="dropdown-checkbox"
                            :disabled="columna.required"
                        />
                        <span class="dropdown-item-label">{{ columna.title }}</span>
                        <span v-if="columna.required" class="badge-required">Requerida</span>
                    </label>
                </div>
            </div>

            <div class="dropdown-footer">
                <button @click.stop="resetColumnas" class="dropdown-reset-btn">
                    <svg class="reset-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Restaurar predeterminado
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    columnas: {
        type: Array,
        required: true
    },
    modelValue: {
        type: Array,
        default: () => []
    },
    storageKey: {
        type: String,
        default: 'columnas_visibles'
    },
    vista: {
        type: String,
        default: 'normal'
    }
});

const emit = defineEmits(['update:modelValue']);

// ============================================
// REFS
// ============================================
const showDropdown = ref(false);
const dropdownRef = ref(null);
const wrapperRef = ref(null);
const columnasActivas = ref([]);

// ============================================
// COMPUTED
// ============================================
const columnasDisponibles = computed(() => props.columnas);

const columnasActivasCount = computed(() => {
    return columnasActivas.value.length;
});

// ============================================
// MÉTODOS
// ============================================
const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value;
};

const toggleColumna = (key) => {
    const columna = columnasDisponibles.value.find(c => c.key === key);
    if (columna?.required) return;
    
    const index = columnasActivas.value.indexOf(key);
    if (index > -1) {
        columnasActivas.value.splice(index, 1);
    } else {
        columnasActivas.value.push(key);
    }
    guardarYEmmitir();
};

const seleccionarTodas = () => {
    columnasActivas.value = columnasDisponibles.value.map(col => col.key);
    guardarYEmmitir();
};

const deseleccionarTodas = () => {
    const requiredKeys = columnasDisponibles.value
        .filter(col => col.required)
        .map(col => col.key);
    columnasActivas.value = [...requiredKeys];
    guardarYEmmitir();
};

const resetColumnas = () => {
    const defaultKeys = columnasDisponibles.value
        .filter(col => col.visibleByDefault !== false)
        .map(col => col.key);
    columnasActivas.value = defaultKeys;
    guardarYEmmitir();
};

const guardarYEmmitir = () => {
    // Guardar en localStorage
    const storageKey = `${props.storageKey}_${props.vista}`;
    localStorage.setItem(storageKey, JSON.stringify(columnasActivas.value));
    
    // Emitir al padre
    emit('update:modelValue', [...columnasActivas.value]);
};

const cargarDesdeStorage = () => {
    const storageKey = `${props.storageKey}_${props.vista}`;
    const saved = localStorage.getItem(storageKey);
    
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            const validKeys = columnasDisponibles.value.map(col => col.key);
            const filtered = parsed.filter(key => validKeys.includes(key));
            const requiredKeys = columnasDisponibles.value
                .filter(col => col.required)
                .map(col => col.key);
            columnasActivas.value = [...new Set([...requiredKeys, ...filtered])];
            return;
        } catch (e) {
            console.warn('Error al cargar columnas guardadas:', e);
        }
    }
    
    // Valores predeterminados
    const defaultKeys = columnasDisponibles.value
        .filter(col => col.visibleByDefault !== false)
        .map(col => col.key);
    columnasActivas.value = defaultKeys;
};

// ============================================
// CIERRE DEL DROPDOWN
// ============================================
const handleClickOutside = (event) => {
    if (wrapperRef.value && !wrapperRef.value.contains(event.target)) {
        showDropdown.value = false;
    }
};

// ============================================
// LIFECYCLE
// ============================================
onMounted(() => {
    cargarDesdeStorage();
    emit('update:modelValue', [...columnasActivas.value]);
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Watcher para cuando cambia la vista
watch(() => props.vista, () => {
    cargarDesdeStorage();
    emit('update:modelValue', [...columnasActivas.value]);
});

// Watcher para cuando cambian las columnas desde fuera
watch(() => props.columnas, () => {
    cargarDesdeStorage();
    emit('update:modelValue', [...columnasActivas.value]);
}, { deep: true });
</script>

<style scoped>
.column-selector-wrapper {
    position: relative;
    display: inline-block;
}

.btn-column-selector {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 16px;
    height: 36px;
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    color: #475569;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    white-space: nowrap;
}

.btn-column-selector:hover {
    border-color: #1a3a5c;
    color: #1a3a5c;
    background: #f8faff;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(26, 58, 92, 0.15);
}

.btn-column-selector:active {
    transform: translateY(0px);
}

.btn-icon {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    flex-shrink: 0;
}

.btn-text {
    font-size: 13px;
    font-weight: 600;
}

.badge-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    color: white;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
}

.dropdown-panel {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 260px;
    max-width: 340px;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    z-index: 1000;
    overflow: hidden;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.dropdown-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.dropdown-title {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}

.dropdown-actions {
    display: flex;
    gap: 6px;
}

.dropdown-action-btn {
    padding: 2px 10px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: #ffffff;
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}

.dropdown-action-btn:hover {
    border-color: #1a3a5c;
    color: #1a3a5c;
    background: #eff6ff;
}

.dropdown-body {
    padding: 8px 0;
    max-height: 280px;
    overflow-y: auto;
}

.dropdown-body::-webkit-scrollbar {
    width: 4px;
}

.dropdown-body::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.dropdown-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.dropdown-item {
    padding: 2px 16px;
}

.dropdown-label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
}

.dropdown-label:hover {
    background: #f1f5f9;
}

.dropdown-checkbox {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #1a3a5c;
    flex-shrink: 0;
}

.dropdown-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.dropdown-item-label {
    font-size: 13px;
    color: #0f172a;
    flex: 1;
}

.badge-required {
    font-size: 9px;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 1px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    white-space: nowrap;
}

.dropdown-footer {
    padding: 10px 16px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.dropdown-reset-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 6px 12px;
    border: 1px dashed #d1d5db;
    border-radius: 6px;
    background: transparent;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}

.dropdown-reset-btn:hover {
    border-color: #1a3a5c;
    color: #1a3a5c;
    background: #eff6ff;
}

.reset-icon {
    width: 14px;
    height: 14px;
    stroke: currentColor;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .dropdown-panel {
        right: -20px;
        min-width: 220px;
        max-width: 280px;
    }
}

@media (max-width: 480px) {
    .btn-column-selector {
        padding: 0 12px;
        font-size: 12px;
        height: 32px;
    }
    
    .btn-text {
        font-size: 12px;
    }
    
    .dropdown-panel {
        right: -40px;
        min-width: 200px;
        max-width: 240px;
    }
    
    .dropdown-item-label {
        font-size: 12px;
    }
}
</style>