<template>
    <AppLayout title="RIC - Personas">
        <template #header>
            <div class="header-premium">
                <div class="header-content-premium">
                    <div class="header-left-premium">
                        <div class="header-icon-wrapper">
                            <svg class="header-icon-svg" fill="none" stroke="white" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="header-title-premium">Gestión de Personas
                            </h2>
                            <p class="header-subtitle-premium">
                                <span class="subtitle-line"></span>
                                Administra las personas físicas y morales
                            </p>
                        </div>
                    </div>
                    <Link v-if="permisos?.puede_crear_personas" :href="route('personas.create')">
                        <button type="button" class="btn-nueva-persona-premium">
                            <i class="pi pi-plus"></i>
                            Nueva Persona
                        </button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-full px-4 sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="stats-grid-personas mb-6">
                    <div class="stats-card-enhanced">
                        <div class="stats-card-enhanced-content">
                            <div class="stats-card-enhanced-left">
                                <span class="stats-card-enhanced-label">Total Personas</span>
                                <span class="stats-card-enhanced-value">{{ stats?.total || 0 }}</span>
                            </div>
                            <div class="stats-card-enhanced-icon" style="background: linear-gradient(135deg, rgba(26, 58, 92, 0.1), rgba(26, 58, 92, 0.05));">
                                <svg class="stats-card-enhanced-svg" style="color: #1a3a5c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-enhanced-progress">
                            <div class="stats-card-enhanced-progress-bar" style="width: 100%; background: linear-gradient(90deg, #1a3a5c, #2c5282);"></div>
                        </div>
                    </div>

                    <div class="stats-card-enhanced">
                        <div class="stats-card-enhanced-content">
                            <div class="stats-card-enhanced-left">
                                <span class="stats-card-enhanced-label">Físicas</span>
                                <span class="stats-card-enhanced-value" style="color: #2563eb;">{{ stats?.fisicas || 0 }}</span>
                            </div>
                            <div class="stats-card-enhanced-icon" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(37, 99, 235, 0.05));">
                                <svg class="stats-card-enhanced-svg" style="color: #2563eb;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-enhanced-progress">
                            <div class="stats-card-enhanced-progress-bar" :style="{ width: stats?.total ? Math.round((stats.fisicas / stats.total) * 100) + '%' : '0%', background: 'linear-gradient(90deg, #2563eb, #3b82f6)' }"></div>
                        </div>
                    </div>

                    <div class="stats-card-enhanced">
                        <div class="stats-card-enhanced-content">
                            <div class="stats-card-enhanced-left">
                                <span class="stats-card-enhanced-label">Morales</span>
                                <span class="stats-card-enhanced-value" style="color: #132a44;">{{ stats?.morales || 0 }}</span>
                            </div>
                            <div class="stats-card-enhanced-icon" style="background: linear-gradient(135deg, rgba(26, 58, 92, 0.1), rgba(26, 58, 92, 0.05));">
                                <svg class="stats-card-enhanced-svg" style="color: #132a44;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-enhanced-progress">
                            <div class="stats-card-enhanced-progress-bar" :style="{ width: stats?.total ? Math.round((stats.morales / stats.total) * 100) + '%' : '0%', background: 'linear-gradient(90deg, #132a44, #1a3a5c)' }"></div>
                        </div>
                    </div>

                    <div class="stats-card-enhanced">
                        <div class="stats-card-enhanced-content">
                            <div class="stats-card-enhanced-left">
                                <span class="stats-card-enhanced-label">Activas / Inactivas</span>
                                <span class="stats-card-enhanced-value" style="color: #0f172a; font-size: 22px;">{{ stats?.activas || 0 }} / {{ stats?.inactivas || 0 }}</span>
                            </div>
                            <div class="stats-card-enhanced-icon" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(239, 68, 68, 0.05));">
                                <svg class="stats-card-enhanced-svg" style="color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-enhanced-progress">
                            <div class="stats-card-enhanced-progress-bar" :style="{ width: stats?.total ? Math.round((stats.activas / stats.total) * 100) + '%' : '0%', background: 'linear-gradient(90deg, #10b981, #34d399)' }"></div>
                        </div>
                    </div>
                </div>

                <!-- Tabla Premium -->
                <div class="table-wrapper-premium">
                    <!-- Header de la tabla -->
                    <div class="table-header-ultra">
                        <div class="table-header-left-ultra">
                            <span class="table-title-ultra">Listado de Personas</span>
                            <span v-if="filtrosActivos" class="filter-tag-ultra">
                                <span class="filter-dot-active"></span>
                                Filtros activos
                            </span>
                            <span v-if="personas.total > 0" class="resultados-count-ultra">
                                {{ personas.total }} resultado{{ personas.total !== 1 ? 's' : '' }}
                            </span>
                        </div>
                        <div class="table-header-right-ultra">
                            <button v-if="filtrosActivos" type="button" @click="limpiarFiltros" class="btn-limpiar-ultra">
                                <i class="pi pi-times"></i>
                                Limpiar filtros
                            </button>
                        </div>
                    </div>

                    <!-- Contenedor de tabla -->
                    <div class="table-scroll-container-full">
                        <DataTable
                            :value="personas.data"
                            :loading="loading"
                            data-key="id_persona"
                            scrollable
                            scroll-height="460px"
                            row-hover
                            table-style="min-width: 70rem"
                            class="persona-table-ultra"
                        >
                            <template #empty>
                                <div class="tabla-vacia">No se encontraron personas con los filtros actuales.</div>
                            </template>

                            <Column header="Persona" style="width: 18%" frozen>
                                <template #body="{ data }">
                                    <span class="persona-nombre-ultra">{{ data.nombre_completo || 'Sin nombre' }}</span>
                                </template>
                            </Column>

                            <Column header="Tipo" style="width: 8%">
                                <template #body="{ data }">
                                    <span class="tipo-badge" :class="data.tipo_persona === 'MORAL' ? 'moral' : 'fisica'">
                                        {{ data.tipo_persona === 'MORAL' ? 'Moral' : 'Física' }}
                                    </span>
                                </template>
                            </Column>

                            <Column header="RFC" style="width: 11%">
                                <template #body="{ data }">
                                    <span class="rfc-text-ultra">{{ data.rfc || '-' }}</span>
                                </template>
                            </Column>

                            <Column header="Correo" style="width: 16%">
                                <template #body="{ data }">
                                    <a v-if="data.email" :href="`mailto:${data.email}`" class="contacto-email-ultra">
                                        <i class="pi pi-envelope contacto-icon-ultra"></i>
                                        <span>{{ data.email }}</span>
                                    </a>
                                    <span v-else class="contacto-vacio-ultra">—</span>
                                </template>
                            </Column>

                            <Column header="Teléfono" style="width: 12%">
                                <template #body="{ data }">
                                    <span v-if="data.telefono_particular || data.telefono_trabajo" class="contacto-telefono-ultra">
                                        <i class="pi pi-phone contacto-icon-ultra"></i>
                                        <span>{{ data.telefono_particular || data.telefono_trabajo }}</span>
                                    </span>
                                    <span v-else class="contacto-vacio-ultra">—</span>
                                </template>
                            </Column>

                            <Column header="Representante" style="width: 13%">
                                <template #body="{ data }">
                                    <span v-if="data.representante_nombre_completo" class="representante-nombre-ultra">
                                        {{ data.representante_nombre_completo }}
                                    </span>
                                    <span v-else class="representante-vacio-ultra">-</span>
                                </template>
                            </Column>

                            <Column header="Estado" style="width: 8%">
                                <template #body="{ data }">
                                    <div class="estado-badge-ultra" :class="data.activo ? 'activo' : 'inactivo'">
                                        <span class="estado-dot-ultra"></span>
                                        <span>{{ data.activo ? 'Activo' : 'Inactivo' }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column header="Acciones" style="width: 10%" align-frozen="right" frozen>
                                <template #body="{ data }">
                                    <div class="acciones-ultra">
                                        <Link :href="route('personas.show', data.id_persona)">
                                            <button v-tooltip.top="'Ver detalles'" class="btn-action-ultra btn-view-ultra">
                                                <i class="pi pi-eye"></i>
                                            </button>
                                        </Link>

                                        <Link v-if="permisos?.puede_editar_personas" :href="route('personas.edit', data.id_persona)">
                                            <button v-tooltip.top="'Editar'" class="btn-action-ultra btn-edit-ultra">
                                                <i class="pi pi-pencil"></i>
                                            </button>
                                        </Link>

                                        <button
                                            v-if="!data.activo && permisos?.puede_editar_personas"
                                            v-tooltip.top="'Activar persona'"
                                            class="btn-action-ultra btn-activate-ultra"
                                            @click="confirmarActivar(data)"
                                        >
                                            <i class="pi pi-check-circle"></i>
                                        </button>

                                        <button
                                            v-if="data.activo && permisos?.puede_editar_personas"
                                            v-tooltip.top="'Desactivar'"
                                            class="btn-action-ultra btn-delete-ultra"
                                            @click="confirmarDesactivar(data)"
                                        >
                                            <i class="pi pi-trash"></i>
                                        </button>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <!-- FILTROS INFERIOR -->
                    <div class="filtros-ultra-full">
                        <div class="filtros-grid-ultra-full">
                            <div class="filtro-item-ultra">
                                <InputLabel>Persona</InputLabel>
                                <TextInput
                                    v-model="filtros.search"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    square
                                />
                            </div>

                            <div class="filtro-item-ultra">
                                <InputLabel>Tipo</InputLabel>
                                <Select
                                    v-model="filtros.tipo_persona"
                                    :options="tipoOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Todos"
                                    show-clear
                                    class="filtro-select-ultra"
                                    @change="aplicarFiltros"
                                />
                            </div>

                            <div class="filtro-item-ultra">
                                <InputLabel>RFC</InputLabel>
                                <TextInput
                                    v-model="filtros.rfc"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    square
                                />
                            </div>

                            <div class="filtro-item-ultra">
                                <InputLabel>Correo / Teléfono</InputLabel>
                                <TextInput
                                    v-model="filtros.contacto"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    square
                                />
                            </div>

                            <div class="filtro-item-ultra">
                                <InputLabel>Representante</InputLabel>
                                <TextInput
                                    v-model="filtros.representante"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    square
                                />
                            </div>

                            <div class="filtro-item-ultra">
                                <InputLabel>Estado</InputLabel>
                                <Select
                                    v-model="filtros.estado"
                                    :options="estadoOptions"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Todos"
                                    show-clear
                                    class="filtro-select-ultra"
                                    @change="aplicarFiltros"
                                />
                            </div>

                            <div class="filtro-item-ultra filtro-actions">
                                <InputLabel>Acciones</InputLabel>
                                <button
                                    v-if="filtrosActivos"
                                    type="button"
                                    @click="limpiarFiltros"
                                    class="btn-clear-ultra"
                                >
                                    <i class="pi pi-times"></i>
                                    Limpiar filtros
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    disabled
                                    class="btn-no-filtros-ultra"
                                >
                                    <span class="no-filtros-text-ultra">Sin filtros</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div class="pagination-ultra">
                        <span class="pagination-info-ultra">Mostrando <span class="pagination-highlight-ultra">{{ personas.from || 0 }}</span> a
                            <span class="pagination-highlight-ultra">{{ personas.to || 0 }}</span> de
                            <span class="pagination-highlight-ultra">{{ personas.total || 0 }}</span> resultados
                        </span>
                        <Pagination :links="personas.links" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Select from 'primevue/select';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { useNotify } from '@/composables/useNotify';

// ============================================
// PERMISOS
// ============================================
const page = usePage();
const permisos = computed(() => page.props.permisos || {});
const notify = useNotify();

const props = defineProps({
    personas: Object,
    stats: Object,
    filtros: Object,
    flash: Object,
});

const loading = ref(false);

// ============================================
// OPCIONES DE FILTROS
// ============================================
const tipoOptions = [
    { label: 'Física', value: 'FISICA' },
    { label: 'Moral', value: 'MORAL' },
];

const estadoOptions = [
    { label: 'Activo', value: 'activo' },
    { label: 'Inactivo', value: 'inactivo' },
];

// ============================================
// FILTROS
// ============================================
const filtros = ref({
    search: props.filtros?.search || '',
    tipo_persona: props.filtros?.tipo_persona || null,
    rfc: props.filtros?.rfc || '',
    contacto: props.filtros?.contacto || '',
    representante: props.filtros?.representante || '',
    estado: props.filtros?.estado || null,
});

const filtrosActivos = computed(() => {
    return Object.values(filtros.value).some(value => value !== '' && value !== null && value !== undefined);
});

let timeoutId = null;
const aplicarFiltros = () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
        loading.value = true;
        const params = {};
        for (const [key, value] of Object.entries(filtros.value)) {
            if (value !== '' && value !== null && value !== undefined) {
                params[key] = value;
            }
        }

        router.get(route('personas.index'), params, {
            preserveState: true,
            replace: true,
            onFinish: () => {
                loading.value = false;
            },
        });
    }, 300);
};

const limpiarFiltros = () => {
    filtros.value = {
        search: '',
        tipo_persona: null,
        rfc: '',
        contacto: '',
        representante: '',
        estado: null,
    };
    aplicarFiltros();
};

// ============================================
// ACTIVAR / DESACTIVAR
// ============================================
const confirmarDesactivar = (persona) => {
    const nombre = persona.razon_social_display || persona.nombre_completo || 'Persona';
    notify.confirmDelete({
        header: 'Desactivar persona',
        message: `La persona "${nombre}" quedará inactiva. Podrás reactivarla más tarde desde el listado.`,
        acceptLabel: 'Sí, desactivar',
        accept: () => procesarCambioEstado(persona, false),
    });
};

const confirmarActivar = (persona) => {
    const nombre = persona.razon_social_display || persona.nombre_completo || 'Persona';
    notify.confirmAction({
        header: 'Activar persona',
        message: `La persona "${nombre}" volverá a estar activa.`,
        acceptLabel: 'Sí, activar',
        acceptSeverity: 'success',
        icon: 'pi pi-check-circle',
        accept: () => procesarCambioEstado(persona, true),
    });
};

const procesarCambioEstado = (persona) => {
    // El controlador responde con redirect()->back()->with('success'|'error', ...);
    // hay que usar router.post (Inertia) para que el flash — de éxito O de error —
    // se procese correctamente. El toast lo dispara el manejador global.
    loading.value = true;
    router.post(route('personas.toggle-active', persona.id_persona), {}, {
        preserveScroll: true,
        onFinish: () => { loading.value = false; },
    });
};
</script>

<style scoped>
/* ===== HEADER PREMIUM ===== */
.header-premium {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 20px;
    padding: 24px 28px;
    margin-bottom: 8px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
    border: 1px solid #f0f2f5;
}

.header-content-premium {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

@media (min-width: 640px) {
    .header-content-premium {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}

.header-left-premium {
    display: flex;
    align-items: center;
    gap: 16px;
}

.header-icon-wrapper {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(26, 58, 92, 0.2);
}

.header-icon-svg {
    width: 28px;
    height: 28px;
    stroke: white;
}

.header-title-premium {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.5px;
}

.header-subtitle-premium {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #64748b;
    font-size: 14px;
    margin: 4px 0 0 0;
}

.subtitle-line {
    width: 24px;
    height: 2px;
    background: linear-gradient(90deg, #1a3a5c, transparent);
    border-radius: 2px;
}

/* ===== BOTÓN NUEVA PERSONA ===== */
.btn-nueva-persona-premium {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #1a3a5c 0%, #2c5282 100%);
    border: none;
    color: #fff;
    font-weight: 700;
    padding: 0 32px;
    height: 50px;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.3);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 15px;
    letter-spacing: 0.3px;
}

.btn-nueva-persona-premium:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 30px rgba(26, 58, 92, 0.4);
    background: linear-gradient(135deg, #2c5282 0%, #1a3a5c 100%);
}

/* ===== STATS CARDS ===== */
.stats-grid-personas {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

@media (max-width: 992px) {
    .stats-grid-personas {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 560px) {
    .stats-grid-personas {
        grid-template-columns: 1fr;
    }
}

.stats-card-enhanced {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #f0f2f5;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.stats-card-enhanced:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    border-color: #d0d7de;
}

.stats-card-enhanced-content {
    padding: 20px 20px 16px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.stats-card-enhanced-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stats-card-enhanced-label {
    font-size: 13px;
    font-weight: 500;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stats-card-enhanced-value {
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}

.stats-card-enhanced-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.stats-card-enhanced:hover .stats-card-enhanced-icon {
    transform: scale(1.1) rotate(-5deg);
}

.stats-card-enhanced-svg {
    width: 26px;
    height: 26px;
}

.stats-card-enhanced-progress {
    height: 4px;
    background: #f1f5f9;
}

.stats-card-enhanced-progress-bar {
    height: 100%;
    transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== TABLA ===== */
.table-wrapper-premium {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #f0f2f5;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    padding: 20px;
}

.table-header-ultra {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}

@media (min-width: 640px) {
    .table-header-ultra {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}

.table-header-left-ultra {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.table-title-ultra {
    font-size: 17px;
    font-weight: 700;
    color: #0f172a;
}

.resultados-count-ultra {
    font-size: 13px;
    font-weight: 500;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 2px 12px;
    border-radius: 20px;
}

.table-header-right-ultra {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.filter-tag-ultra {
    border-radius: 30px;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    color: #1a3a5c;
    font-weight: 600;
    font-size: 12px;
    padding: 4px 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.filter-dot-active {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #1a3a5c;
    display: inline-block;
    animation: pulse 2s infinite;
}

.btn-limpiar-ultra {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    border: 2px solid #d1d5db;
    transition: all 0.3s ease;
    height: 40px;
    padding: 0 16px;
    font-weight: 600;
    background: #ffffff;
    cursor: pointer;
}

.btn-limpiar-ultra:hover {
    color: #1a3a5c;
    border-color: #1a3a5c;
    background: #f8faff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 58, 92, 0.1);
}

/* ===== CONTENEDOR DE TABLA ===== */
.table-scroll-container-full {
    border-radius: 8px;
    border: 1px solid #f1f5f9;
    overflow: hidden;
}

.tabla-vacia {
    padding: 32px 16px;
    text-align: center;
    color: #94a3b8;
    font-size: 14px;
}

/* ===== DATATABLE PRIMEVUE ===== */
.persona-table-ultra :deep(.p-datatable-thead > tr > th) {
    background: linear-gradient(135deg, #f1f5f9, #e8edf2);
    font-weight: 700;
    color: #1e293b;
    border-bottom: 2px solid #d1d5db;
    padding: 10px 12px;
    font-size: 11px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    white-space: nowrap;
}

.persona-table-ultra :deep(.p-datatable-tbody > tr) {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.persona-table-ultra :deep(.p-datatable-tbody > tr:hover) {
    background: linear-gradient(90deg, #f8faff, #f0f7ff);
}

.persona-table-ultra :deep(.p-datatable-tbody > tr:last-child > td) {
    border-bottom: none;
}

.persona-table-ultra :deep(.p-datatable-tbody > tr > td) {
    padding: 8px 10px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}

.persona-table-ultra :deep(.p-datatable-tbody > tr > td.p-frozen-column),
.persona-table-ultra :deep(.p-datatable-thead > tr > th.p-frozen-column) {
    background: #ffffff;
}

/* ===== CELDAS ===== */
.persona-nombre-ultra {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
}

.tipo-badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.tipo-badge.fisica {
    background: #dbeafe;
    color: #1e40af;
}

.tipo-badge.moral {
    background: #fce7f3;
    color: #9d174d;
}

.rfc-text-ultra {
    font-size: 13px;
    font-weight: 500;
    color: #1a3a5c;
    font-family: 'Courier New', monospace;
}

.ubicacion-text-ultra {
    font-size: 12px;
    color: #475569;
}

.contacto-cell-ultra {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.contacto-email-ultra {
    color: #3b82f6;
    font-size: 12px;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.contacto-email-ultra:hover {
    color: #2563eb;
    text-decoration: underline;
}

.contacto-telefono-ultra {
    color: #475569;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.contacto-icon-ultra {
    font-size: 13px;
}

.contacto-vacio-ultra {
    color: #cbd5e1;
    font-size: 12px;
    font-style: italic;
}

.representante-nombre-ultra {
    font-size: 12px;
    color: #0f172a;
    font-weight: 500;
}

.representante-vacio-ultra {
    color: #cbd5e1;
    font-size: 12px;
}

.estado-badge-ultra {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.estado-badge-ultra.activo {
    background: #dcfce7;
    color: #166534;
}

.estado-badge-ultra.inactivo {
    background: #fee2e2;
    color: #991b1b;
}

.estado-dot-ultra {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.estado-badge-ultra.activo .estado-dot-ultra {
    background: #22c55e;
    animation: pulse 2s infinite;
}

.estado-badge-ultra.inactivo .estado-dot-ultra {
    background: #ef4444;
}

/* ===== ACCIONES ===== */
.acciones-ultra {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.btn-action-ultra {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: transparent;
}

.btn-action-ultra:hover {
    transform: translateY(-2px) scale(1.05);
}

.btn-view-ultra {
    color: #3b82f6;
}

.btn-view-ultra:hover {
    background: #eff6ff;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.btn-edit-ultra {
    color: #eab308;
}

.btn-edit-ultra:hover {
    background: #fefce8;
    box-shadow: 0 4px 12px rgba(234, 179, 8, 0.2);
}

.btn-delete-ultra {
    color: #ef4444;
}

.btn-delete-ultra:hover {
    background: #fef2f2;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
}

.btn-activate-ultra {
    color: #10b981;
}

.btn-activate-ultra:hover {
    background: #dcfce7;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

/* ===== FILTROS INFERIOR ===== */
.filtros-ultra-full {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 2px solid #f1f5f9;
}

.filtros-grid-ultra-full {
    display: grid;
    /* 7 filtros para las 8 columnas de la tabla (Persona 18% | Tipo 8% | RFC 11%
       | Correo 16% + Teléfono 12% | Representante 13% | Estado 8% | Acciones 10%)
       — el filtro "Correo / Teléfono" es uno solo y ocupa el ancho combinado de
       esas dos columnas para quedar alineado debajo de ambas. Antes tenía 8
       valores que no correspondían ni en ancho ni en cantidad a las columnas
       reales, así que los filtros no quedaban bajo su columna. */
    grid-template-columns: 18% 8% 11% 28% 13% 8% 10%;
    gap: 4px;
    align-items: end;
    width: 100%;
}

@media (max-width: 1400px) {
    .filtros-grid-ultra-full {
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 6px;
    }
    .filtro-actions {
        grid-column: 1 / -1;
    }
}

@media (max-width: 992px) {
    .filtros-grid-ultra-full {
        grid-template-columns: 1fr 1fr 1fr;
        gap: 6px;
    }
    .filtro-actions {
        grid-column: 1 / -1;
    }
}

@media (max-width: 768px) {
    .filtros-grid-ultra-full {
        grid-template-columns: 1fr 1fr;
        gap: 6px;
    }
    .filtro-actions {
        grid-column: 1 / -1;
    }
}

@media (max-width: 480px) {
    .filtros-grid-ultra-full {
        grid-template-columns: 1fr;
        gap: 4px;
    }
    .filtro-actions {
        grid-column: 1;
    }
}

.filtro-item-ultra {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.filtro-item-ultra :deep(.input-label) {
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    margin-bottom: 0 !important;
}

.filtro-item-ultra :deep(.text-input) {
    height: 36px !important;
    font-size: 12px !important;
    padding: 0 10px !important;
    border-radius: 0px !important;
    border: 2px solid #d1d5db !important;
    width: 100% !important;
}

.filtro-item-ultra :deep(.text-input:focus) {
    border-color: #1a3a5c !important;
    box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1) !important;
}

.filtro-actions {
    min-width: 90px;
}

/* ===== SELECT PRIMEVUE ===== */
.filtro-select-ultra {
    width: 100% !important;
}

.filtro-select-ultra :deep(.p-select) {
    width: 100%;
}

.filtro-select-ultra.p-select {
    border-radius: 0px;
    border: 2px solid #d1d5db;
    background: #ffffff;
    height: 36px;
    box-shadow: none;
    transition: all 0.3s ease;
}

.filtro-select-ultra.p-select:hover {
    border-color: #1a3a5c;
    background: #fafbfc;
}

.filtro-select-ultra.p-select.p-focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1);
}

.filtro-select-ultra :deep(.p-select-label) {
    font-size: 12px;
    padding: 0 10px;
    display: flex;
    align-items: center;
}

.btn-clear-ultra {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    border-radius: 0px;
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    border: none;
    color: white;
    height: 36px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(26, 58, 92, 0.2);
}

.btn-clear-ultra:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.3);
}

.btn-no-filtros-ultra {
    width: 100%;
    border-radius: 0px;
    background: #f8fafc;
    border: 2px dashed #d1d5db;
    cursor: not-allowed;
    height: 36px;
    font-size: 12px;
}

.no-filtros-text-ultra {
    color: #94a3b8;
    font-weight: 600;
}

/* ===== PAGINACIÓN ===== */
.pagination-ultra {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 2px solid #f1f5f9;
}

@media (min-width: 640px) {
    .pagination-ultra {
        flex-direction: row;
    }
}

.pagination-info-ultra {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.pagination-highlight-ultra {
    font-weight: 700;
    color: #1a3a5c;
    padding: 2px 8px;
    background: #f1f4f9;
    border-radius: 4px;
}

/* ===== ANIMACIONES ===== */
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}
</style>
