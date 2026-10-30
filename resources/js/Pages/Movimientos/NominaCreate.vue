<template>
    <AppLayout :title="'Generar Pólizas de Nómina'">
        <template #header>
            <div class="header-wrapper">
                <div class="header-left">
                    <Link :href="route('movimientos.index')" class="btn-back">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <div class="header-content">
                        <h2 class="header-title">Generar Pólizas de Nómina</h2>
                        <p class="header-subtitle">Genera de manera masiva las pólizas de pago de empleados</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="page-content">
            <div class="container-custom">
                <div class="form-card">
                    <!-- Aviso: no hay empleados -->
                    <div v-if="empleados.length === 0" class="nomina-aviso">
                        <div class="nomina-aviso-icon"><i class="pi pi-exclamation-triangle"></i></div>
                        <div class="nomina-aviso-text">
                            <strong>No hay personas marcadas como empleado.</strong>
                            Puedes revisar el formulario, pero para generar la nómina primero
                            edita a cada persona en el módulo de Personas y activa
                            <em>«¿Es empleado?»</em>.
                        </div>
                        <Link :href="route('personas.index')" class="nomina-aviso-btn">
                            <i class="pi pi-users"></i> Ir a Personas
                        </Link>
                    </div>

                    <form @submit.prevent="submit" id="nominaForm" novalidate>
                        <!-- ============================================ -->
                        <!-- SECCION 1: CONFIGURACION DE LA NOMINA -->
                        <!-- ============================================ -->
                        <div class="section-block-premium">
                            <div class="section-header-premium">
                                <div class="section-icon-premium blue">
                                    <svg class="icon-svg-premium" fill="none" stroke="white" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="section-title-text">Configuración de la Nómina</h3>
                                    <p class="section-title-sub">Configura los datos generales de la nómina</p>
                                </div>
                            </div>

                            <div class="form-grid-premium">
                                <!-- Fecha de Pago -->
                                <div class="form-group-premium">
                                    <label class="form-label-premium">Fecha de Pago <span class="required-star">*</span></label>
                                    <div class="input-wrapper-premium">
                                        <input type="date" v-model="formData.fecha_pago"
                                               class="form-input-premium"
                                               disabled>
                                        <div class="input-icon-premium">
                                            <svg class="icon-svg-sm-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="field-hint-premium">Fecha del sistema</div>
                                    <div v-if="errors.fecha_pago" class="error-message-premium">{{ errors.fecha_pago }}</div>
                                </div>

                                <!-- Tipo de Poliza -->
                                <div class="form-group-premium">
                                    <label class="form-label-premium">Tipo de Póliza <span class="required-star">*</span></label>
                                    <div class="input-wrapper-premium">
                                        <select v-model="formData.tipo_poliza"
                                                class="form-input-premium form-select-premium"
                                                disabled>
                                            <option value="EGRESO">Egreso</option>
                                        </select>
                                        <div class="input-icon-premium">
                                            <svg class="icon-svg-sm-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <!-- Marcador -->
                                <div class="form-group-premium">
                                    <label class="form-label-premium">Marcador</label>
                                    <div class="input-wrapper-premium" style="display: flex; gap: 8px;">
                                        <select v-model="formData.id_marcador"
                                                class="form-input-premium form-select-premium"
                                                style="flex: 1;">
                                            <option value="">Selecciona un marcador</option>
                                            <option v-for="m in marcadores" :key="m.id" :value="m.id">
                                                {{ m.nombre_marcador }}
                                            </option>
                                        </select>
                                        <button type="button" @click="marcadorGestorVisible = true" class="btn-add-marcador-premium" title="Nuevo marcador">
                                            <svg class="icon-svg-sm-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Cuenta contable -->
                                <div class="form-group-premium">
                                    <label class="form-label-premium">Cuenta <span class="required-star">*</span></label>
                                    <div class="input-wrapper-premium">
                                        <select v-model="formData.id_cuenta"
                                                @change="clearError('id_cuenta')"
                                                class="form-input-premium form-select-premium"
                                                :class="{ 'error': errors.id_cuenta }">
                                            <option value="">Selecciona una cuenta</option>
                                            <option v-for="c in cuentasNomina" :key="c.id_cuenta" :value="c.id_cuenta">
                                                {{ c.nombre_cuenta }}
                                            </option>
                                        </select>
                                        <div class="input-icon-premium">
                                            <svg class="icon-svg-sm-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div v-if="errors.id_cuenta" class="error-message-premium">{{ errors.id_cuenta }}</div>
                                </div>

                                <!-- Observación General -->
                                <div class="form-group-premium full-width-premium">
                                    <label class="form-label-premium">Observación General <span class="required-star">*</span></label>
                                    <div class="input-wrapper-premium">
                                        <input type="text" v-model="formData.observacion_general"
                                               class="form-input-premium"
                                               placeholder="Ej: Pago de nómina quincenal"
                                               @input="replicarObservacion">
                                    </div>
                                    <div class="field-hint-premium">Esta observación se replicará en todos los empleados seleccionados</div>
                                    <div v-if="errors.observacion_general" class="error-message-premium">{{ errors.observacion_general }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- SECCION 2: LISTA DE EMPLEADOS -->
                        <!-- ============================================ -->
                        <div class="section-block-premium empleados-section">
                            <div class="section-header-premium">
                                <div class="section-icon-premium green">
                                    <svg class="icon-svg-premium" fill="none" stroke="white" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="section-title-text">Empleados</h3>
                                    <p class="section-title-sub">Selecciona los empleados a pagar y sus montos</p>
                                </div>
                            </div>

                            <!-- Barra de acciones -->
                            <div class="table-actions-premium">
                                <div class="table-actions-left">
                                    <button type="button" @click="seleccionarTodos" class="btn-action-premium btn-select-all">
                                        <svg class="icon-svg-sm-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                        Seleccionar Todos
                                    </button>
                                    <button type="button" @click="deseleccionarTodos" class="btn-action-premium btn-deselect-all">
                                        <svg class="icon-svg-sm-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Deseleccionar Todos
                                    </button>
                                </div>
                                <div class="table-actions-right">
                                    <span class="selected-count-premium">{{ empleadosSeleccionados }} seleccionados</span>
                                    <span class="total-payroll-premium">Total: ${{ formatNumber(totalNomina) }}</span>
                                </div>
                            </div>

                            <!-- Aplicar en lote -->
                            <div class="lote-bar-premium">
                                <div class="lote-bar-fila">
                                    <span class="lote-bar-titulo">
                                        <i class="pi pi-bolt"></i> Aplicar en lote:
                                    </span>
                                    <div class="lote-campo">
                                        <span class="lote-campo-prefix">$</span>
                                        <MoneyInput v-model="loteMonto"
                                               placeholder="Monto" class="form-input-premium lote-input-monto" />
                                    </div>
                                    <select v-model="loteCuentaFondeadora" class="form-input-premium form-select-premium lote-input-cuenta">
                                        <option value="">(no cambiar cuenta de fondo)</option>
                                        <option v-for="c in cuentasFondeadoras" :key="c.id_cuenta" :value="c.id_cuenta">
                                            {{ c.nombre_cuenta }}
                                        </option>
                                    </select>
                                    <button type="button" @click="aplicarEnLote"
                                            :disabled="empleadosSeleccionados === 0 || (!loteMonto && !loteCuentaFondeadora)"
                                            class="btn-action-premium btn-aplicar-lote">
                                        <svg class="icon-svg-sm-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Aplicar a {{ empleadosSeleccionados }} seleccionados
                                    </button>
                                </div>
                                <div class="lote-bar-hint">
                                    Marca a los empleados, escribe el monto y presiona "Aplicar".
                                </div>
                            </div>

                            <!-- Tabla de empleados con scroll interno -->
                            <div class="table-container-premium">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">
                                                <input type="checkbox" :checked="todosSeleccionados"
                                                       @change="toggleSeleccionarTodos" class="checkbox-input-premium">
                                            </th>
                                            <th>Empleado</th>
                                            <th style="width: 160px;">Monto a Pagar</th>
                                            <th style="width: 180px;">Cuenta de Fondo</th>
                                            <th>Observación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="empleado in empleadosPaginados" :key="empleado.id_persona">
                                            <td>
                                                <input type="checkbox" v-model="empleado.seleccionado" class="checkbox-input-premium">
                                            </td>
                                            <td>
                                                <span class="empleado-nombre">{{ empleado.nombre_completo }}</span>
                                            </td>
                                            <td>
                                                <div class="input-wrapper-premium" style="width: 100%;">
                                                    <span class="input-prefix-premium">$</span>
                                                    <MoneyInput v-model="empleado.monto"
                                                           class="form-input-premium monto-input" placeholder="0.00" />
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-wrapper-premium" style="width: 100%;">
                                                    <select v-model="empleado.id_cuenta_fondeadora"
                                                            class="form-input-premium form-select-premium" style="width: 100%;">
                                                        <option value="">Seleccionar</option>
                                                        <option v-for="c in cuentasFondeadoras" :key="c.id_cuenta" :value="c.id_cuenta">
                                                            {{ c.nombre_cuenta }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-wrapper-premium" style="width: 100%;">
                                                    <input type="text" v-model="empleado.observacion"
                                                           class="form-input-premium" placeholder="Observación">
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="empleadosFiltrados.length === 0">
                                            <td colspan="5" class="empty-state-premium">
                                                <i class="pi pi-users empty-icon"></i>
                                                <span>{{ filtroEmpleado ? 'No hay coincidencias con el filtro aplicado' : 'No hay empleados disponibles' }}</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Filtro y paginación en la parte inferior -->
                            <div class="table-footer-premium">
                                <div class="footer-filtro-col">
                                    <input type="text" v-model="filtroEmpleado"
                                           placeholder="Buscar empleado..."
                                           class="footer-filtro-input">
                                </div>
                                <div class="footer-paginacion-col">
                                    <div v-if="empleadosFiltrados.length > 0" class="footer-paginacion">
                                        <span class="pagination-info">
                                            Mostrando {{ inicioPagina + 1 }} - {{ finPagina }} de {{ empleadosFiltrados.length }}
                                        </span>
                                        <div class="pagination-controls">
                                            <button type="button" @click="paginaActual = 1" :disabled="paginaActual === 1"
                                                    class="pagination-btn" title="Primera página">
                                                <i class="pi pi-angle-double-left"></i>
                                            </button>
                                            <button type="button" @click="paginaActual--" :disabled="paginaActual === 1"
                                                    class="pagination-btn" title="Anterior">
                                                <i class="pi pi-angle-left"></i>
                                            </button>
                                            <span class="pagination-page">{{ paginaActual }} / {{ totalPaginas }}</span>
                                            <button type="button" @click="paginaActual++" :disabled="paginaActual === totalPaginas"
                                                    class="pagination-btn" title="Siguiente">
                                                <i class="pi pi-angle-right"></i>
                                            </button>
                                            <button type="button" @click="paginaActual = totalPaginas" :disabled="paginaActual === totalPaginas"
                                                    class="pagination-btn" title="Última página">
                                                <i class="pi pi-angle-double-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Info box -->
                        <div class="info-box-premium">
                            <svg class="info-icon-premium" fill="none" stroke="#1a3a5c" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Los campos con <strong class="text-danger-premium">*</strong> son obligatorios</span>
                            <span>Total a pagar: <strong>${{ formatNumber(totalNomina) }}</strong></span>
                            <span>Empleados: <strong>{{ empleadosSeleccionados }}</strong></span>
                        </div>

                        <div class="form-actions-premium">
                            <div class="actions-left-premium">
                                <Link :href="route('movimientos.index')" class="btn-premium btn-cancel-premium">
                                    <svg class="btn-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Cancelar
                                </Link>
                            </div>
                            <div class="actions-right-premium">
                                <button type="submit" :disabled="processing || !isFormValid"
                                        class="btn-premium btn-submit-premium">
                                    <span v-if="processing" class="spinner-border-premium"></span>
                                    <svg v-else class="btn-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                    </svg>
                                    {{ processing ? 'Generando...' : 'Generar Pólizas' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Marcador -->
        <MarcadoresModal v-model="marcadorGestorVisible" :marcadores="marcadores" @changed="(l) => marcadores = l" />

        <div v-if="modalMarcadorVisible" class="modal-overlay-premium" @click.self="cerrarModalMarcador">
            <div class="modal-container-premium">
                <div class="modal-header-premium">
                    <h3 class="modal-title-premium">Nuevo Marcador</h3>
                    <button type="button" @click="cerrarModalMarcador" class="modal-close-premium">
                        <svg class="icon-svg-sm-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body-premium">
                    <form @submit.prevent="guardarMarcador">
                        <div class="form-group-premium">
                            <label class="form-label-premium">Nombre <span class="required-star">*</span></label>
                            <div class="input-wrapper-premium">
                                <input type="text" v-model="nuevoMarcador.nombre" class="form-input-premium"
                                       placeholder="Ej: Urgente, Importante">
                            </div>
                        </div>
                        <div class="form-group-premium" style="margin-top: 16px;">
                            <label class="form-label-premium">Descripción</label>
                            <div class="input-wrapper-premium">
                                <textarea v-model="nuevoMarcador.descripcion" class="form-textarea-premium"
                                          rows="2" placeholder="Breve descripción..."></textarea>
                            </div>
                        </div>
                        <div class="modal-actions-premium">
                            <button type="button" @click="cerrarModalMarcador" class="btn-premium btn-cancel-premium">Cancelar</button>
                            <button type="submit" :disabled="guardandoMarcador" class="btn-premium btn-submit-premium">
                                {{ guardandoMarcador ? 'Guardando...' : 'Crear Marcador' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import MarcadoresModal from '@/Components/MarcadoresModal.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, onMounted, watch, reactive } from 'vue';
import { useNotify } from '@/composables/useNotify';

const notify = useNotify();

// ============================================
// PROPS
// ============================================
const props = defineProps({
    empresa_id: { type: Number, default: null },
    empleados: { type: Array, default: () => [] },
    cuentas_fondeadoras: { type: Array, default: () => [] },
    cuentas_nomina: { type: Array, default: () => [] },
    marcadores: { type: Array, default: () => [] },
    sin_empleados: { type: Boolean, default: false }
});

// ============================================
// REFS
// ============================================
const modalMarcadorVisible = ref(false);
const marcadorGestorVisible = ref(false);
const guardandoMarcador = ref(false);
const nuevoMarcador = ref({ nombre: '', descripcion: '' });
const processing = ref(false);

// Paginación
const paginaActual = ref(1);
const porPagina = 10;
const filtroEmpleado = ref('');

// ============================================
// DATOS REACTIVOS
// ============================================
const cuentasFondeadoras = ref(props.cuentas_fondeadoras || []);
const cuentasNomina = ref(props.cuentas_nomina || []);
const marcadores = ref(props.marcadores || []);
const empleados = ref([]);

// ============================================
// FORMULARIO
// ============================================
const formData = reactive({
    fecha_pago: '',
    id_cuenta: props.cuentas_nomina?.length > 0 ? props.cuentas_nomina[0].id_cuenta : null,
    id_cuenta_fondeadora: props.cuentas_fondeadoras?.length > 0 ? props.cuentas_fondeadoras[0].id_cuenta : null,
    tipo_poliza: 'EGRESO',
    id_marcador: null,
    observacion_general: '',
});

const errors = reactive({});

// ============================================
// APLICAR EN LOTE
// ============================================
const loteMonto = ref(null);
const loteCuentaFondeadora = ref('');

// ============================================
// COMPUTED - FILTRO Y PAGINACIÓN
// ============================================
const empleadosFiltrados = computed(() => {
    const filtro = filtroEmpleado.value.toLowerCase().trim();
    if (!filtro) return empleados.value;
    return empleados.value.filter(e =>
        (e.nombre_completo || '').toLowerCase().includes(filtro)
    );
});

const totalPaginas = computed(() => {
    return Math.max(1, Math.ceil(empleadosFiltrados.value.length / porPagina));
});

const inicioPagina = computed(() => {
    return (paginaActual.value - 1) * porPagina;
});

const finPagina = computed(() => {
    return Math.min(inicioPagina.value + porPagina, empleadosFiltrados.value.length);
});

const empleadosPaginados = computed(() => {
    return empleadosFiltrados.value.slice(inicioPagina.value, finPagina.value);
});

// Resetear página cuando cambia el filtro
watch(filtroEmpleado, () => {
    paginaActual.value = 1;
});

// Ajustar página si queda fuera de rango
watch(totalPaginas, (nuevoTotal) => {
    if (paginaActual.value > nuevoTotal) {
        paginaActual.value = nuevoTotal;
    }
});

// ============================================
// COMPUTED
// ============================================
const empleadosSeleccionados = computed(() => {
    return empleados.value.filter(e => e.seleccionado).length;
});

const totalNomina = computed(() => {
    return empleados.value
        .filter(e => e.seleccionado)
        .reduce((sum, e) => sum + (parseFloat(e.monto) || 0), 0);
});

const todosSeleccionados = computed({
    get: () => {
        if (empleados.value.length === 0) return false;
        return empleados.value.every(e => e.seleccionado);
    },
    set: (value) => {
        empleados.value.forEach(e => e.seleccionado = value);
    }
});

const isFormValid = computed(() => {
    if (!formData.fecha_pago) return false;
    if (!formData.id_cuenta) return false;
    if (!formData.id_cuenta_fondeadora) return false;
    if (!formData.observacion_general || formData.observacion_general.trim() === '') return false;
    if (empleadosSeleccionados.value === 0) return false;

    const empleadosConMonto = empleados.value
        .filter(e => e.seleccionado)
        .every(e => e.monto > 0);
    if (!empleadosConMonto) return false;

    const empleadosConCuenta = empleados.value
        .filter(e => e.seleccionado)
        .every(e => e.id_cuenta_fondeadora);
    if (!empleadosConCuenta) return false;

    return true;
});

// ============================================
// METODOS
// ============================================
const clearError = (field) => {
    if (errors[field]) delete errors[field];
};

const formatNumber = (value) => {
    if (!value && value !== 0) return '0.00';
    return Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const seleccionarTodos = () => {
    empleados.value.forEach(e => e.seleccionado = true);
};

const deseleccionarTodos = () => {
    empleados.value.forEach(e => e.seleccionado = false);
};

const toggleSeleccionarTodos = (event) => {
    const checked = event.target.checked;
    empleados.value.forEach(e => e.seleccionado = checked);
};

const aplicarEnLote = () => {
    const seleccionados = empleados.value.filter(e => e.seleccionado);
    if (seleccionados.length === 0) {
        notify.error('Selecciona primero a los empleados a los que quieres aplicar el monto/cuenta.', 'Nada seleccionado');
        return;
    }
    if (!loteMonto.value && !loteCuentaFondeadora.value) {
        notify.error('Escribe un monto o elige una cuenta de fondo para aplicar.', 'Nada que aplicar');
        return;
    }

    seleccionados.forEach(e => {
        if (loteMonto.value) {
            e.monto = loteMonto.value;
        }
        if (loteCuentaFondeadora.value) {
            e.id_cuenta_fondeadora = loteCuentaFondeadora.value;
        }
    });

    notify.success(`Se aplicó a ${seleccionados.length} empleado(s) seleccionado(s).`, 'Aplicado');
};

const replicarObservacion = () => {
    const observacion = formData.observacion_general;
    empleados.value.forEach(e => {
        if (e.seleccionado) {
            e.observacion = observacion;
        }
    });
};

// ============================================
// MODAL MARCADOR
// ============================================
const abrirModalMarcador = () => {
    nuevoMarcador.value = { nombre: '', descripcion: '' };
    modalMarcadorVisible.value = true;
};

const cerrarModalMarcador = () => {
    modalMarcadorVisible.value = false;
    nuevoMarcador.value = { nombre: '', descripcion: '' };
};

const guardarMarcador = async () => {
    guardandoMarcador.value = true;
    try {
        const res = await axios.post(route('movimientos.marcadores.store'), {
            nombre_marcador: nuevoMarcador.value.nombre,
            descripcion: nuevoMarcador.value.descripcion
        });

        if (res.data.success) {
            marcadores.value.push(res.data.data);
            formData.id_marcador = res.data.data.id;
            cerrarModalMarcador();
            notify.success('El marcador se ha creado y seleccionado correctamente.', 'Marcador creado');
        }
    } catch (error) {
        notify.error(error.response?.data?.message || 'Error al crear el marcador', 'Error');
    } finally {
        guardandoMarcador.value = false;
    }
};

// ============================================
// SUBMIT
// ============================================
const submit = () => {
    processing.value = true;
    Object.keys(errors).forEach(key => delete errors[key]);

    if (!formData.fecha_pago) {
        errors.fecha_pago = 'La fecha de pago no se ha calculado correctamente';
        processing.value = false;
        return;
    }

    if (!formData.id_cuenta) {
        errors.id_cuenta = 'Selecciona una cuenta para la nómina';
        processing.value = false;
        return;
    }

    if (!formData.id_cuenta_fondeadora) {
        errors.id_cuenta_fondeadora = 'Selecciona una caja fondo';
        processing.value = false;
        return;
    }

    if (!formData.observacion_general || formData.observacion_general.trim() === '') {
        errors.observacion_general = 'Ingresa una observación general para la nómina';
        processing.value = false;
        return;
    }

    const empleadosSeleccionados = empleados.value.filter(e => e.seleccionado);
    if (empleadosSeleccionados.length === 0) {
        notify.error('Selecciona al menos un empleado para generar la póliza', 'Error');
        processing.value = false;
        return;
    }

    const empleadosSinMonto = empleadosSeleccionados.filter(e => e.monto <= 0);
    if (empleadosSinMonto.length > 0) {
        const nombres = empleadosSinMonto.map(e => e.nombre_completo).join(', ');
        notify.error(`Los siguientes empleados no tienen un monto válido: ${nombres}`, 'Error');
        processing.value = false;
        return;
    }

    const empleadosSinCuenta = empleadosSeleccionados.filter(e => !e.id_cuenta_fondeadora);
    if (empleadosSinCuenta.length > 0) {
        const nombres = empleadosSinCuenta.map(e => e.nombre_completo).join(', ');
        notify.error(`Los siguientes empleados no tienen una cuenta de fondo seleccionada: ${nombres}`, 'Error');
        processing.value = false;
        return;
    }

    const data = {
        fecha_pago: formData.fecha_pago,
        id_cuenta: formData.id_cuenta,
        id_cuenta_fondeadora: formData.id_cuenta_fondeadora,
        tipo_poliza: formData.tipo_poliza,
        id_marcador: formData.id_marcador,
        observacion_general: formData.observacion_general,
        empleados: empleadosSeleccionados.map(e => ({
            id_persona: e.id_persona,
            monto: e.monto,
            id_cuenta_fondeadora: e.id_cuenta_fondeadora,
            observacion: e.observacion || formData.observacion_general
        }))
    };

    axios.post(route('movimientos.nomina.store'), data)
        .then(() => {
            processing.value = false;
            notify.success('Las pólizas de nómina se han generado correctamente.', 'Exito');
            setTimeout(() => {
                const fp = formData.fecha_pago ? String(formData.fecha_pago).slice(0, 10) : null;
                const params = fp ? { fecha_desde: fp, fecha_hasta: fp } : { sin_fecha: 1 };
                router.visit(route('movimientos.index', params), {
                    method: 'get',
                    replace: true
                });
            }, 1500);
        })
        .catch(error => {
            processing.value = false;
            console.error('Error:', error);

            if (error.response?.data?.errors) {
                const err = error.response.data.errors;
                Object.keys(err).forEach(key => {
                    errors[key] = Array.isArray(err[key]) ? err[key][0] : err[key];
                });
                const firstError = Object.values(errors)[0];
                notify.error(firstError, 'Error de validación');
            } else {
                notify.error(error.response?.data?.message || 'Error al generar las pólizas. Intenta nuevamente.', 'Error');
            }
        });
};

// ============================================
// WATCHERS
// ============================================
watch(
    () => empleados.value.map(e => e.seleccionado),
    () => {
        const observacion = formData.observacion_general;
        empleados.value.forEach(e => {
            if (e.seleccionado) {
                e.observacion = observacion;
            }
        });
    },
    { deep: true }
);

// ============================================
// MOUNTED
// ============================================
onMounted(() => {
    const hoy = new Date();
    const year = hoy.getFullYear();
    const month = String(hoy.getMonth() + 1).padStart(2, '0');
    const day = String(hoy.getDate()).padStart(2, '0');
    formData.fecha_pago = `${year}-${month}-${day}`;

    if (props.cuentas_nomina && props.cuentas_nomina.length > 0) {
        cuentasNomina.value = props.cuentas_nomina;
        formData.id_cuenta = props.cuentas_nomina[0].id_cuenta;
    }

    if (props.cuentas_fondeadoras && props.cuentas_fondeadoras.length > 0) {
        cuentasFondeadoras.value = props.cuentas_fondeadoras;
        formData.id_cuenta_fondeadora = props.cuentas_fondeadoras[0].id_cuenta;
    }

    if (props.marcadores && props.marcadores.length > 0) {
        marcadores.value = props.marcadores;
    }

    if (props.empleados && props.empleados.length > 0) {
        empleados.value = props.empleados.map(e => ({
            ...e,
            seleccionado: false,
            monto: 0,
            id_cuenta_fondeadora: props.cuentas_fondeadoras?.length > 0 ? props.cuentas_fondeadoras[0].id_cuenta : null,
            observacion: ''
        }));
    } else {
        console.warn('No hay empleados en props');
    }
});
</script>

<style scoped>
/* ========== FIJAR PANTALLA SIN SCROLL GLOBAL ========== */
.page-content {
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 0;
}

.container-custom {
    max-width: 80rem;
    margin: 0 auto;
    padding: 0 1.5rem;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.form-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    padding: 1rem 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#nominaForm {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    gap: 0.75rem;
}

/* ========== HEADER ========== */
.header-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 4px 0;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.btn-back {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #6b7280;
    transition: all 0.3s ease;
}

.btn-back:hover {
    background: white;
    color: #1f2937;
    transform: translateX(-2px) scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.header-content {
    display: flex;
    flex-direction: column;
}

.header-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    line-height: 1.3;
}

.header-subtitle {
    font-size: 0.85rem;
    color: #6b7280;
    margin: 0;
}

/* ========== SECTIONS ========== */
.section-block-premium {
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f1f5f9;
}

.section-block-premium:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.empleados-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}

.section-header-premium {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    flex-shrink: 0;
}

.section-icon-premium {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.25);
}

.section-icon-premium.blue { background: linear-gradient(135deg, #1a3a5c, #3d6ea5); }
.section-icon-premium.green { background: linear-gradient(135deg, #10b981, #059669); }

.icon-svg-premium {
    width: 20px;
    height: 20px;
}

.section-title-text {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.section-title-sub {
    font-size: 0.8rem;
    color: #94a3b8;
    margin: 0;
}

/* ========== FORM GRID (4 columnas en una línea) ========== */
.form-grid-premium {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.full-width-premium {
    grid-column: 1 / -1;
}

.form-group-premium {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.form-label-premium {
    font-size: 0.78rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 6px;
}

.required-star {
    color: #ef4444;
    font-weight: 700;
}

/* ========== INPUTS ========== */
.input-wrapper-premium {
    position: relative;
}

.form-input-premium {
    width: 100%;
    padding: 8px 14px;
    font-size: 0.85rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    color: #1f2937;
    transition: all 0.3s ease;
    outline: none;
    height: 38px;
}

.form-input-premium:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92, 0.1);
}

.form-input-premium.error {
    border-color: #ef4444;
    animation: shake 0.5s ease;
}

.form-input-premium:disabled {
    background: #f3f4f6;
    cursor: not-allowed;
    opacity: 0.7;
}

.form-select-premium {
    appearance: none;
    cursor: pointer;
    padding-right: 40px;
}

.form-select-premium:disabled {
    background: #f3f4f6;
    cursor: not-allowed;
}

.input-prefix-premium {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: 700;
    color: #64748b;
    font-size: 0.9rem;
    z-index: 1;
}

.input-prefix-premium + .form-input-premium {
    padding-left: 28px;
}

.input-icon-premium {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

.icon-svg-sm-premium {
    width: 18px;
    height: 18px;
}

.field-hint-premium {
    font-size: 0.68rem;
    color: #94a3b8;
    margin-top: 1px;
}

.error-message-premium {
    font-size: 0.72rem;
    color: #ef4444;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
    animation: slideDown 0.3s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

/* ========== TABLE ========== */
.table-actions-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 8px;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.table-actions-left {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-action-premium {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border: none;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    color: #64748b;
    border: 1px solid #e5e7eb;
}

.btn-action-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.btn-select-all { color: #2563eb; }
.btn-select-all:hover { background: #eff6ff; border-color: #2563eb; }

.btn-deselect-all { color: #dc2626; }
.btn-deselect-all:hover { background: #fef2f2; border-color: #dc2626; }

.table-actions-right {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.selected-count-premium {
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
}

.total-payroll-premium {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
    padding: 3px 12px;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border-radius: 8px;
    border: 1px solid #a7f3d0;
}

/* ========== APLICAR EN LOTE ========== */
.lote-bar-premium {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 8px;
    padding: 8px 14px;
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 1px solid #fde68a;
    border-radius: 10px;
    flex-shrink: 0;
}

.lote-bar-fila {
    display: flex;
    flex-wrap: nowrap;
    gap: 10px;
    align-items: center;
}

.lote-bar-titulo {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.82rem;
    font-weight: 700;
    color: #92400e;
    white-space: nowrap;
    flex-shrink: 0;
}

.lote-campo {
    display: flex;
    align-items: center;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 0 10px;
    height: 36px;
    flex-shrink: 0;
}

.lote-campo:focus-within {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92, 0.1);
}

.lote-campo-prefix {
    color: #64748b;
    font-weight: 600;
    margin-right: 4px;
}

.lote-input-monto {
    border: none !important;
    padding: 0 !important;
    height: 32px !important;
    width: 100px;
}

.lote-input-monto:focus {
    outline: none;
    box-shadow: none !important;
}

.lote-input-cuenta {
    flex: 1;
    min-width: 140px;
}

.btn-aplicar-lote {
    background: linear-gradient(135deg, #f59e0b, #d97706) !important;
    color: white !important;
    border: none !important;
    white-space: nowrap;
    flex-shrink: 0;
    font-size: 0.75rem;
}

.btn-aplicar-lote:hover:not(:disabled) {
    filter: brightness(1.05);
}

.btn-aplicar-lote:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.lote-bar-hint {
    font-size: 0.68rem;
    color: #92400e;
    opacity: 0.85;
}

@media (max-width: 720px) {
    .lote-bar-fila { flex-wrap: wrap; }
    .lote-input-cuenta { min-width: 100%; }
    .btn-aplicar-lote { width: 100%; justify-content: center; }
}

/* ========== TABLA CON SCROLL INTERNO ========== */
.table-container-premium {
    overflow-y: auto;
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    flex: 1;
    min-height: 0;
}

.table-premium {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.table-premium thead {
    background: linear-gradient(135deg, #f8fafc, #eef2ff);
    border-bottom: 2px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 2;
}

.table-premium th {
    padding: 8px 12px;
    text-align: left;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #475569;
    white-space: nowrap;
}

.table-premium td {
    padding: 6px 12px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.table-premium tbody tr:hover {
    background: #f8fafc;
}

.table-premium tbody tr:last-child td {
    border-bottom: none;
}

.empleado-nombre {
    font-weight: 600;
    color: #0f172a;
    font-size: 0.82rem;
}

.monto-input {
    height: 34px;
    font-size: 0.82rem;
    padding: 4px 10px 4px 28px;
}

.checkbox-input-premium {
    width: 17px;
    height: 17px;
    accent-color: #1a3a5c;
    cursor: pointer;
    border-radius: 4px;
}

.empty-state-premium {
    text-align: center;
    padding: 30px 20px;
    color: #94a3b8;
    font-weight: 500;
}

.empty-icon {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 8px;
}

/* ========== FOOTER DE TABLA: FILTRO ALINEADO CON COLUMNA EMPLEADO ========== */
.table-footer-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    padding: 10px 0 2px;
    flex-shrink: 0;
    flex-wrap: wrap;
}

/* La columna del filtro replica el ancho de la columna "Empleado".
   La columna Empleado empieza tras: 50px (checkbox) + 12px padding de la celda.
   El input va justo debajo del título "Empleado". */
.footer-filtro-col {
    display: flex;
    align-items: center;
    padding-left: 62px; /* 50px checkbox + 12px padding-left de la celda */
    flex: 0 0 auto;
    min-width: 380px;
}

.footer-filtro-input {
    width: 380px;
    max-width: 100%;
    padding: 6px 12px;
    font-size: 0.8rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    color: #1f2937;
    outline: none;
    transition: all 0.2s ease;
    height: 34px;
}

.footer-filtro-input:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92, 0.1);
}

.footer-filtro-input::placeholder {
    color: #9ca3af;
}

/* La paginación ocupa el resto a la derecha */
.footer-paginacion-col {
    display: flex;
    justify-content: flex-end;
    flex: 1;
    min-width: 0;
}

.footer-paginacion {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.pagination-info {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 500;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pagination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    color: #475569;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.2s ease;
}

.pagination-btn:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #1a3a5c;
    color: #1a3a5c;
}

.pagination-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.pagination-page {
    font-size: 0.78rem;
    font-weight: 700;
    color: #1e293b;
    padding: 0 10px;
    min-width: 60px;
    text-align: center;
}

/* ========== BOTONES ========== */
.btn-premium {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 24px;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    height: 38px;
}

.btn-icon-premium {
    width: 18px;
    height: 18px;
}

.btn-cancel-premium {
    background: #f1f5f9;
    color: #64748b;
    border: 2px solid #e5e7eb;
}

.btn-cancel-premium:hover {
    background: #fecaca;
    color: #dc2626;
    border-color: #fca5a5;
}

.btn-submit-premium {
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    color: white;
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.25);
}

.btn-submit-premium:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(26, 58, 92, 0.35);
}

.btn-submit-premium:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-add-marcador-premium {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    background: white;
    color: #1a3a5c;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.btn-add-marcador-premium:hover {
    border-color: #1a3a5c;
    background: linear-gradient(135deg, #f8f7ff, #eef2ff);
    transform: scale(1.05) rotate(90deg);
}

/* ========== FORM ACTIONS ========== */
.form-actions-premium {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding-top: 10px;
    border-top: 2px solid #f1f5f9;
    flex-shrink: 0;
}

.actions-left-premium,
.actions-right-premium {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* ========== INFO BOX ========== */
.info-box-premium {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: linear-gradient(135deg, rgba(26, 58, 92, 0.06), rgba(118, 75, 162, 0.06));
    border-radius: 10px;
    border-left: 4px solid #1a3a5c;
    font-size: 0.78rem;
    color: #4b5563;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.info-icon-premium {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.text-danger-premium {
    color: #ef4444;
}

/* ========== SPINNER ========== */
.spinner-border-premium {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 0.2em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner 0.75s linear infinite;
}

@keyframes spinner {
    to { transform: rotate(360deg); }
}

/* ========== MODAL ========== */
.modal-overlay-premium {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}

.modal-container-premium {
    background: white;
    border-radius: 20px;
    width: 100%;
    max-width: 450px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.2);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-header-premium {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #f3f4f6;
}

.modal-title-premium {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.modal-close-premium {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    background: #f3f4f6;
    border-radius: 50%;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modal-close-premium:hover {
    background: #fecaca;
    color: #dc2626;
}

.modal-body-premium {
    padding: 24px;
}

.modal-actions-premium {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #f3f4f6;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 992px) {
    .form-grid-premium {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .form-grid-premium {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .full-width-premium {
        grid-column: 1;
    }

    .header-wrapper {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .form-actions-premium {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .actions-left-premium,
    .actions-right-premium {
        width: 100%;
        justify-content: center;
        flex-direction: column;
    }

    .btn-premium {
        width: 100%;
        justify-content: center;
        padding: 8px 20px;
        height: 40px;
    }

    .container-custom {
        padding: 0 0.75rem;
    }

    .table-actions-premium {
        flex-direction: column;
        align-items: stretch;
    }

    .table-actions-left {
        justify-content: center;
    }

    .table-actions-right {
        justify-content: center;
    }

    .section-icon-premium {
        width: 38px;
        height: 38px;
    }

    .icon-svg-premium {
        width: 18px;
        height: 18px;
    }

    .section-title-text {
        font-size: 0.95rem;
    }

    .table-premium {
        font-size: 0.78rem;
    }

    .table-premium th,
    .table-premium td {
        padding: 6px 8px;
    }

    .info-box-premium {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .modal-container-premium {
        margin: 16px;
        max-height: 80vh;
    }

    .table-footer-premium {
        flex-direction: column;
        align-items: stretch;
    }

    .footer-filtro-col {
        padding-left: 0;
        min-width: 0;
    }

    .footer-filtro-input {
        width: 100%;
        max-width: 100%;
    }

    .footer-paginacion-col {
        justify-content: space-between;
    }
}

@media (max-width: 480px) {
    .form-card {
        padding: 0.75rem 1rem;
        border-radius: 16px;
    }

    .header-title {
        font-size: 1.1rem;
    }

    .section-header-premium {
        gap: 10px;
    }

    .section-icon-premium {
        width: 34px;
        height: 34px;
    }

    .icon-svg-premium {
        width: 16px;
        height: 16px;
    }

    .table-premium th,
    .table-premium td {
        padding: 4px 6px;
        font-size: 0.72rem;
    }

    .table-actions-premium {
        padding: 8px 10px;
    }

    .btn-action-premium {
        font-size: 0.68rem;
        padding: 4px 10px;
    }

    .total-payroll-premium {
        font-size: 0.78rem;
        padding: 3px 8px;
    }

    .selected-count-premium {
        font-size: 0.72rem;
    }

    .footer-paginacion {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }

    .pagination-controls {
        justify-content: center;
    }
}

/* ===== Estado vacío de nómina (sin empleados) ===== */
.nomina-aviso {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 18px;
    margin-bottom: 16px;
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 1px solid #fcd34d;
    border-left: 4px solid #d97706;
    border-radius: 12px;
}

.nomina-aviso-icon {
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: rgba(217, 119, 6, 0.15);
    color: #b45309;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
}

.nomina-aviso-text {
    flex: 1;
    font-size: 0.82rem;
    line-height: 1.45;
    color: #78350f;
}

.nomina-aviso-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #1a3a5c;
    color: #fff;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 650;
    text-decoration: none;
    white-space: nowrap;
    transition: filter 0.15s ease;
}

.nomina-aviso-btn:hover { filter: brightness(1.1); }

@media (max-width: 640px) {
    .nomina-aviso { flex-direction: column; align-items: flex-start; }
}
</style>