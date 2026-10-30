<script setup>
import { ref, watch, computed, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { useNotify } from '@/composables/useNotify';

/**
 * Gestor de marcadores: crear, editar y eliminar desde un modal.
 *
 *   <MarcadoresModal v-model="modalVisible" :marcadores="marcadores" @changed="onMarcadoresChanged" />
 *
 * Emite `changed` con la lista actualizada cada vez que algo cambia.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    marcadores: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'changed']);

const notify = useNotify();

const lista = ref([]);
const busy = ref(false);

// Formulario de alta
const nuevo = ref({ nombre_marcador: '', descripcion: '' });
const errorNuevo = ref('');

// Edición en línea
const editandoId = ref(null);
const editForm = ref({ nombre_marcador: '', descripcion: '' });
const errorEdit = ref('');

const buscar = ref('');
const filtrados = computed(() => {
    const q = buscar.value.trim().toLowerCase();
    if (!q) return lista.value;
    return lista.value.filter((m) => (m.nombre_marcador || '').toLowerCase().includes(q));
});

watch(
    () => props.modelValue,
    (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            lista.value = [...props.marcadores];
            recargar();
            resetAlta();
            editandoId.value = null;
        }
    },
);

const cerrar = () => emit('update:modelValue', false);

onBeforeUnmount(() => { document.body.style.overflow = ''; });

const recargar = async () => {
    try {
        const { data } = await axios.get(route('movimientos.marcadores.index'));
        if (data.success) {
            lista.value = data.data;
            emit('changed', data.data);
        }
    } catch (e) {
        /* se conserva la lista actual */
    }
};

const resetAlta = () => {
    nuevo.value = { nombre_marcador: '', descripcion: '' };
    errorNuevo.value = '';
};

const crear = async () => {
    if (!nuevo.value.nombre_marcador.trim()) {
        errorNuevo.value = 'Escribe un nombre para el marcador.';
        return;
    }
    busy.value = true;
    errorNuevo.value = '';
    try {
        const { data } = await axios.post(route('movimientos.marcadores.store'), {
            nombre_marcador: nuevo.value.nombre_marcador.trim(),
            descripcion: nuevo.value.descripcion?.trim() || null,
        });
        if (data.success) {
            notify.success('Marcador creado.');
            resetAlta();
            await recargar();
        }
    } catch (e) {
        errorNuevo.value = e.response?.data?.errors?.nombre_marcador?.[0]
            || e.response?.data?.message
            || 'No se pudo crear el marcador.';
    } finally {
        busy.value = false;
    }
};

const iniciarEdicion = (m) => {
    editandoId.value = m.id;
    editForm.value = { nombre_marcador: m.nombre_marcador, descripcion: m.descripcion || '' };
    errorEdit.value = '';
};

const cancelarEdicion = () => {
    editandoId.value = null;
    errorEdit.value = '';
};

const guardarEdicion = async (m) => {
    if (!editForm.value.nombre_marcador.trim()) {
        errorEdit.value = 'El nombre no puede quedar vacío.';
        return;
    }
    busy.value = true;
    errorEdit.value = '';
    try {
        const { data } = await axios.put(route('movimientos.marcadores.update', m.id), {
            nombre_marcador: editForm.value.nombre_marcador.trim(),
            descripcion: editForm.value.descripcion?.trim() || null,
        });
        if (data.success) {
            notify.success('Marcador actualizado.');
            editandoId.value = null;
            await recargar();
        }
    } catch (e) {
        errorEdit.value = e.response?.data?.errors?.nombre_marcador?.[0]
            || e.response?.data?.message
            || 'No se pudo actualizar.';
    } finally {
        busy.value = false;
    }
};

const eliminar = (m) => {
    notify.confirmDelete({
        header: 'Eliminar marcador',
        message: `El marcador "${m.nombre_marcador}" se eliminará. Si está en uso, sólo se archivará.`,
        acceptLabel: 'Sí, eliminar',
        accept: async () => {
            busy.value = true;
            try {
                const { data } = await axios.delete(route('movimientos.marcadores.destroy', m.id));
                notify[data.archived ? 'warn' : 'success'](data.message || 'Marcador eliminado.');
                await recargar();
            } catch (e) {
                notify.error(e.response?.data?.message || 'No se pudo eliminar el marcador.');
            } finally {
                busy.value = false;
            }
        },
    });
};
</script>

<template>
    <transition name="mm-fade">
        <div v-if="modelValue" class="mm-overlay" @click.self="cerrar">
            <div class="mm-modal">
                <header class="mm-head">
                    <div class="mm-head-info">
                        <span class="mm-head-icon"><i class="pi pi-bookmark"></i></span>
                        <div>
                            <h3 class="mm-title">Marcadores</h3>
                            <p class="mm-sub">Crea, edita o elimina los marcadores de pólizas</p>
                        </div>
                    </div>
                    <button type="button" class="mm-close" @click="cerrar"><i class="pi pi-times"></i></button>
                </header>

                <div class="mm-body">
                    <!-- Alta -->
                    <form class="mm-nuevo" @submit.prevent="crear">
                        <div class="mm-nuevo-row">
                            <input
                                v-model="nuevo.nombre_marcador"
                                type="text"
                                class="mm-input"
                                placeholder="Nombre del nuevo marcador"
                                maxlength="100"
                            >
                            <input
                                v-model="nuevo.descripcion"
                                type="text"
                                class="mm-input"
                                placeholder="Descripción (opcional)"
                                maxlength="255"
                            >
                            <button type="submit" class="mm-btn mm-btn-primary" :disabled="busy">
                                <i class="pi pi-plus"></i> Agregar
                            </button>
                        </div>
                        <p v-if="errorNuevo" class="mm-error">{{ errorNuevo }}</p>
                    </form>

                    <div class="mm-search" v-if="lista.length > 6">
                        <i class="pi pi-search"></i>
                        <input v-model="buscar" type="text" class="mm-input" placeholder="Buscar marcador...">
                    </div>

                    <!-- Lista -->
                    <ul class="mm-list">
                        <li v-if="!filtrados.length" class="mm-empty">
                            {{ lista.length ? 'Sin coincidencias' : 'Todavía no hay marcadores' }}
                        </li>

                        <li v-for="m in filtrados" :key="m.id" class="mm-item" :class="{ editing: editandoId === m.id }">
                            <template v-if="editandoId === m.id">
                                <div class="mm-item-edit">
                                    <input v-model="editForm.nombre_marcador" type="text" class="mm-input" maxlength="100">
                                    <input v-model="editForm.descripcion" type="text" class="mm-input" placeholder="Descripción" maxlength="255">
                                </div>
                                <div class="mm-item-actions">
                                    <button type="button" class="mm-icon-btn ok" title="Guardar" :disabled="busy" @click="guardarEdicion(m)">
                                        <i class="pi pi-check"></i>
                                    </button>
                                    <button type="button" class="mm-icon-btn" title="Cancelar" @click="cancelarEdicion">
                                        <i class="pi pi-times"></i>
                                    </button>
                                </div>
                            </template>

                            <template v-else>
                                <div class="mm-item-info">
                                    <span class="mm-item-name">{{ m.nombre_marcador }}</span>
                                    <span v-if="m.descripcion" class="mm-item-desc">{{ m.descripcion }}</span>
                                </div>
                                <div class="mm-item-actions">
                                    <button type="button" class="mm-icon-btn" title="Editar" @click="iniciarEdicion(m)">
                                        <i class="pi pi-pencil"></i>
                                    </button>
                                    <button type="button" class="mm-icon-btn danger" title="Eliminar" :disabled="busy" @click="eliminar(m)">
                                        <i class="pi pi-trash"></i>
                                    </button>
                                </div>
                            </template>
                        </li>
                    </ul>
                    <p v-if="errorEdit" class="mm-error">{{ errorEdit }}</p>
                </div>

                <footer class="mm-foot">
                    <button type="button" class="mm-btn" @click="cerrar">Cerrar</button>
                </footer>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.mm-overlay {
    position: fixed;
    inset: 0;
    z-index: 1200;
    background: rgba(11, 20, 38, 0.5);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.mm-modal {
    width: 100%;
    max-width: 560px;
    max-height: min(88vh, 720px);
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 30px 70px -14px rgba(11, 20, 38, 0.4), 0 0 0 1px rgba(11, 20, 38, 0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: mm-in 0.24s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes mm-in {
    from { opacity: 0; transform: translateY(14px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.mm-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid #eef2f6;
    background: linear-gradient(180deg, #fbfcfe, #fff);
    flex-shrink: 0;
}

.mm-head-info { display: flex; align-items: center; gap: 12px; }

.mm-head-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(26, 58, 92, 0.1);
    color: #1a3a5c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
}

.mm-title { margin: 0; font-size: 1.05rem; font-weight: 750; color: #0b1426; letter-spacing: -0.015em; }
.mm-sub { margin: 1px 0 0; font-size: 0.75rem; color: #94a3b8; }

.mm-close {
    width: 32px;
    height: 32px;
    border: none;
    background: #f1f5f9;
    border-radius: 9px;
    color: #64748b;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.mm-close:hover { background: #fee2e2; color: #dc2626; }

.mm-body {
    padding: 18px 22px;
    overflow-y: auto;
    flex: 1;
}

.mm-nuevo-row {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 8px;
}

.mm-input {
    width: 100%;
    padding: 9px 12px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.85rem;
    color: #1f2937;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.mm-input:focus { border-color: #1a3a5c; box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.12); }

.mm-btn {
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 0.83rem;
    font-weight: 650;
    border: 1.5px solid #d8dee6;
    background: #fff;
    color: #475569;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
}
.mm-btn:hover { background: #f8fafc; }

.mm-btn-primary {
    background: #1a3a5c;
    border-color: #1a3a5c;
    color: #fff;
}
.mm-btn-primary:hover { filter: brightness(1.08); background: #1a3a5c; }
.mm-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.mm-error { margin: 6px 2px 0; font-size: 0.76rem; color: #dc2626; }

.mm-search {
    position: relative;
    margin-top: 14px;
}
.mm-search .pi { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 0.75rem; color: #94a3b8; }
.mm-search .mm-input { padding-left: 30px; }

.mm-list {
    list-style: none;
    margin: 14px 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.mm-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #eef2f6;
    border-radius: 12px;
    background: #fbfcfe;
}
.mm-item.editing { border-color: #c7d6e6; background: #fff; }

.mm-item-info { display: flex; flex-direction: column; min-width: 0; }
.mm-item-name { font-size: 0.86rem; font-weight: 650; color: #0f2136; }
.mm-item-desc { font-size: 0.76rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.mm-item-edit {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    flex: 1;
}

.mm-item-actions { display: flex; gap: 6px; flex-shrink: 0; }

.mm-icon-btn {
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 8px;
    background: #eef2f6;
    color: #64748b;
    cursor: pointer;
    font-size: 0.78rem;
    transition: all 0.15s ease;
}
.mm-icon-btn:hover { background: #e2e8f0; color: #0f2136; }
.mm-icon-btn.ok { background: rgba(22, 163, 74, 0.12); color: #16a34a; }
.mm-icon-btn.ok:hover { background: rgba(22, 163, 74, 0.2); }
.mm-icon-btn.danger:hover { background: #fee2e2; color: #dc2626; }
.mm-icon-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.mm-empty { padding: 20px; text-align: center; font-size: 0.82rem; color: #94a3b8; }

.mm-foot {
    padding: 14px 22px;
    border-top: 1px solid #eef2f6;
    background: #f8fafc;
    display: flex;
    justify-content: flex-end;
    flex-shrink: 0;
}

.mm-fade-enter-active, .mm-fade-leave-active { transition: opacity 0.18s ease; }
.mm-fade-enter-from, .mm-fade-leave-to { opacity: 0; }

@media (max-width: 560px) {
    .mm-nuevo-row { grid-template-columns: 1fr; }
    .mm-item-edit { grid-template-columns: 1fr; }
}
</style>
