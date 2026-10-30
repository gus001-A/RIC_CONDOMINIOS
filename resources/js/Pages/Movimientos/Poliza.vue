<template>
    <AppLayout title="Póliza Fiscal (IVA 0% y 16%)">
        <template #header>
            <div class="header-wrapper">
                <div class="header-left">
                    <Link :href="route('movimientos.index')" class="btn-back">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <div>
                        <h2 class="header-title">Póliza Fiscal (IVA 0% y 16%)</h2>
                        <p class="header-subtitle">Crear una nueva póliza de ingreso/egreso con doble IVA</p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="status-badge" :class="statusClass">
                        <span v-if="hasErrors"> {{ errorCount }} errores</span>
                        <span v-else-if="isComplete">Completado</span>
                        <span v-else> {{ Math.round(progressPercentage) }}%</span>
                    </div>
                </div>
            </div>
        </template>

        <div class="page-content">
            <div class="container-custom">
                <div class="form-card">
                    <form @submit.prevent="submit" enctype="multipart/form-data">
                        <!-- ============================================ -->
                        <!-- SECCIÓN 1: DATOS GENERALES                   -->
                        <!-- ============================================ -->
                        <div class="section-block">
                            <div class="section-header">
                                <div class="section-icon icon-blue">
                                    <svg fill="none" stroke="white" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="section-title">Datos Generales</h3>
                                    <p class="section-subtitle">Configuración básica de la póliza fiscal</p>
                                </div>
                            </div>

                            <div class="form-grid">
                                <!-- Tipo de póliza -->
                                <div class="form-group">
                                    <label class="form-label">Tipo de póliza <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <select v-model="form.tipo_poliza" 
                                                class="form-input"
                                                :class="{ 'error': form.errors.tipo_poliza }">
                                            <option value="">Selecciona un tipo</option>
                                            <option value="INGRESO">Ingreso</option>
                                            <option value="EGRESO">Egreso</option>
                                        </select>
                                    </div>
                                    <div v-if="form.errors.tipo_poliza" class="error-message">{{ form.errors.tipo_poliza }}</div>
                                </div>

                                <!-- Persona -->
                                <div class="form-group">
                                    <label class="form-label">Persona</label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_persona"
                                                class="form-input"
                                                :class="{ 'error': form.errors.id_persona }">
                                            <option value="">Selecciona una persona</option>
                                            <option v-for="p in personas" :key="p.id_persona" :value="p.id_persona">
                                                {{ p.nombre_completo }}
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="form.errors.id_persona" class="error-message">{{ form.errors.id_persona }}</div>
                                </div>

                                <!-- Cuenta -->
                                <div class="form-group">
                                    <label class="form-label">Cuenta</label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_cuenta"
                                                class="form-input"
                                                :class="{ 'error': form.errors.id_cuenta }">
                                            <option value="">Selecciona una cuenta</option>
                                            <option v-for="c in cuentas" :key="c.id_cuenta" :value="c.id_cuenta">
                                                {{ c.nombre_cuenta }}
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="form.errors.id_cuenta" class="error-message">{{ form.errors.id_cuenta }}</div>
                                </div>

                                <!-- Cuenta Fondeadora -->
                                <div class="form-group">
                                    <label class="form-label">Cuenta fondeadora <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_cuenta_fondeadora"
                                                class="form-input"
                                                :class="{ 'error': form.errors.id_cuenta_fondeadora }">
                                            <option value="">Selecciona una fondeadora</option>
                                            <option v-for="c in cuentas_fondeadoras" :key="c.id_cuenta" :value="c.id_cuenta">
                                                {{ c.nombre_cuenta }} (Saldo: ${{ formatNumber(c.saldo) }})
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="form.errors.id_cuenta_fondeadora" class="error-message">{{ form.errors.id_cuenta_fondeadora }}</div>
                                    <div v-if="cuentas_fondeadoras.length === 0" class="warning-message">
                                        No hay cuentas fondeadoras configuradas. Contacta al administrador.
                                    </div>
                                </div>

                                <!-- Opciones + Fecha Vencimiento -->
                                <div class="form-group">
                                    <label class="form-label">Opciones</label>
                                    <div class="options-row">
                                        <label class="checkbox-label">
                                            <input type="checkbox" v-model="form.es_por_pagar" class="checkbox-input">
                                            <span class="checkbox-custom"></span>
                                            Por Pagar
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group" v-if="form.es_por_pagar">
                                    <label class="form-label">Fecha de vencimiento <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <input type="date" v-model="form.fecha_vencimiento"
                                               class="form-input"
                                               :class="{ 'error': form.errors.fecha_vencimiento }"
                                               :min="fechaActual">
                                    </div>
                                    <div v-if="form.errors.fecha_vencimiento" class="error-message">{{ form.errors.fecha_vencimiento }}</div>
                                </div>

                                <!-- Marcador -->
                                <div class="form-group">
                                    <label class="form-label">Marcar</label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_marcador"
                                                class="form-input">
                                            <option value="">Seleccionar marcador...</option>
                                            <option v-for="m in marcadores" :key="m.id" :value="m.id">
                                                {{ m.nombre_marcador }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- SECCIÓN 2: DESGLOSE DE IVA - CUADROS         -->
                        <!-- ============================================ -->
                        <div class="section-block">
                            <div class="section-header">
                                <div class="section-icon icon-green">
                                    <svg fill="none" stroke="white" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="section-title">Desglose de la factura por IVA</h3>
                                    <p class="section-subtitle">Esta factura contiene conceptos con IVA 0% y 16%</p>
                                </div>
                            </div>

                            <!-- CUADROS EN UNA MISMA LÍNEA -->
                            <div class="iva-cards-row">
                                <!-- Cuadro IVA 0% -->
                                <div class="iva-card card-cero">
                                    <div class="card-header">
                                        <span class="card-badge badge-cero">0%</span>
                                        <span class="card-title">Monto gravado al 0%</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="input-wrapper card-input-wrapper">
                                            <span class="input-prefix">$</span>
                                            <MoneyInput 
                                                   v-model="form.monto_iva_cero"
                                                   @input="calcularTotales"
                                                   class="form-input card-input"
                                                   :class="{ 'error': form.errors.monto_iva_cero }"
                                                   placeholder="0.00" />
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <span class="card-result">IVA 0%: ${{ formatNumber(form.monto_iva_cero) }}</span>
                                    </div>
                                </div>

                                <!-- Cuadro IVA 16% -->
                                <div class="iva-card card-dieciseis">
                                    <div class="card-header">
                                        <span class="card-badge badge-dieciseis">16%</span>
                                        <span class="card-title">Monto gravado al 16%</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="input-wrapper card-input-wrapper">
                                            <span class="input-prefix">$</span>
                                            <MoneyInput 
                                                   v-model="form.monto_iva_dieciseis"
                                                   @input="calcularTotales"
                                                   class="form-input card-input"
                                                   :class="{ 'error': form.errors.monto_iva_dieciseis }"
                                                   placeholder="0.00" />
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <span class="card-result">IVA 16%: ${{ formatNumber(ivaDieciseisCalculado) }}</span>
                                    </div>
                                </div>

                                <!-- Cuadro TOTAL -->
                                <div class="iva-card card-total">
                                    <div class="card-header">
                                        <span class="card-badge badge-total">TOTAL</span>
                                        <span class="card-title">TOTAL FACTURA (INCLUYE IVA)</span>
                                    </div>
                                    <div class="card-body total-body">
                                        <span class="total-amount">${{ formatNumber(totalFacturaCalculado) }}</span>
                                    </div>
                                    <div class="card-footer total-footer">
                                        <span class="card-result">IVA 16%: ${{ formatNumber(ivaDieciseisCalculado) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- SECCIÓN 3: DESGLOSE AUTOMÁTICO               -->
                        <!-- ============================================ -->
                        <div class="section-block" v-if="totalFacturaCalculado > 0">
                            <div class="section-header">
                                <div class="section-icon icon-orange">
                                    <svg fill="none" stroke="white" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="section-title">Desglose automático (cálculo del sistema)</h3>
                                    <p class="section-subtitle">Cálculo del sistema basado en los montos ingresados</p>
                                </div>
                            </div>

                            <div class="auto-grid">
                                <div class="auto-item">
                                    <span class="auto-label">Base gravable 0%</span>
                                    <span class="auto-value">${{ formatNumber(form.monto_iva_cero) }}</span>
                                    <span class="auto-tag">IVA 0%</span>
                                </div>
                                <div class="auto-item">
                                    <span class="auto-label">Base gravable 16%</span>
                                    <span class="auto-value">${{ formatNumber(form.monto_iva_dieciseis) }}</span>
                                    <span class="auto-tag">IVA 16%</span>
                                </div>
                                <div class="auto-item auto-highlight">
                                    <span class="auto-label">Total factura (incluye IVA)</span>
                                    <span class="auto-value total">${{ formatNumber(totalFacturaCalculado) }}</span>
                                    <span class="auto-tag">IVA 16%: ${{ formatNumber(ivaDieciseisCalculado) }}</span>
                                </div>
                            </div>

                            <!-- Barras de proporción -->
                            <div class="bars-container" v-if="totalFacturaCalculado > 0">
                                <div class="bar-row">
                                    <span class="bar-label">IVA 0%</span>
                                    <div class="bar-track">
                                        <div class="bar-fill bar-cero" :style="{ width: porcentajeCero + '%' }">
                                            {{ porcentajeCero.toFixed(0) }}%
                                        </div>
                                    </div>
                                </div>
                                <div class="bar-row">
                                    <span class="bar-label">IVA 16% (Base)</span>
                                    <div class="bar-track">
                                        <div class="bar-fill bar-dieciseis" :style="{ width: porcentajeDieciseisBase + '%' }">
                                            {{ porcentajeDieciseisBase.toFixed(0) }}%
                                        </div>
                                    </div>
                                </div>
                                <div class="bar-row">
                                    <span class="bar-label">IVA 16% (Impuesto)</span>
                                    <div class="bar-track">
                                        <div class="bar-fill bar-iva" :style="{ width: porcentajeIva + '%' }">
                                            {{ porcentajeIva.toFixed(0) }}%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- SECCIÓN 4: FACTURA Y ARCHIVOS (MEJORADA)    -->
                        <!-- ============================================ -->
                        <div class="section-block">
                            <div class="section-header">
                                <div class="section-icon icon-purple">
                                    <svg fill="none" stroke="white" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="section-title">Datos de facturación</h3>
                                    <p class="section-subtitle">Información de la factura y documentos adjuntos</p>
                                </div>
                            </div>

                            <div class="form-grid">
                                <!-- Fecha factura -->
                                <div class="form-group">
                                    <label class="form-label">Fecha de factura</label>
                                    <div class="input-wrapper">
                                        <input type="date" v-model="form.fecha_factura"
                                               class="form-input"
                                               :max="fechaActual">
                                    </div>
                                </div>

                                <!-- Número factura -->
                                <div class="form-group">
                                    <label class="form-label">Número de factura</label>
                                    <div class="input-wrapper">
                                        <input type="text" v-model="form.numero_factura"
                                               class="form-input"
                                               placeholder="Ej: A-1258">
                                    </div>
                                </div>

                                <!-- PDF - Mejorado -->
                                <div class="form-group">
                                    <label class="form-label">PDF</label>
                                    <div class="file-upload-wrapper-premium">
                                        <div class="file-upload-area-premium" 
                                             :class="{ 'has-file': archivos.pdf }"
                                             @click="$refs.pdfInput.click()">
                                            <span class="file-upload-icon-premium">
                                                <svg class="icon-svg-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                </svg>
                                            </span>
                                            <span class="file-upload-text-premium">
                                                {{ archivos.pdf ? archivos.pdf.name : 'Seleccionar archivo PDF' }}
                                            </span>
                                            <span class="file-upload-size-premium" v-if="archivos.pdf">
                                                ({{ (archivos.pdf.size / 1024).toFixed(2) }} KB)
                                            </span>
                                        </div>
                                        <input 
                                            type="file" 
                                            ref="pdfInput"
                                            @change="handleFileUpload('pdf', $event)" 
                                            accept=".pdf"
                                            class="file-input-hidden"
                                        />
                                        <button 
                                            v-if="archivos.pdf" 
                                            type="button" 
                                            @click="eliminarArchivo('pdf')" 
                                            class="file-remove-premium"
                                            title="Eliminar archivo"
                                        >

                                        </button>
                                    </div>
                                    <div class="field-hint-premium">Haz clic en el área para seleccionar un archivo PDF (máx. 5MB)</div>
                                </div>

                                <!-- XML - Mejorado -->
                                <div class="form-group">
                                    <label class="form-label">XML</label>
                                    <div class="file-upload-wrapper-premium">
                                        <div class="file-upload-area-premium" 
                                             :class="{ 'has-file': archivos.xml }"
                                             @click="$refs.xmlInput.click()">
                                            <span class="file-upload-icon-premium">
                                                <svg class="icon-svg-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </span>
                                            <span class="file-upload-text-premium">
                                                {{ archivos.xml ? archivos.xml.name : 'Seleccionar archivo XML' }}
                                            </span>
                                            <span class="file-upload-size-premium" v-if="archivos.xml">
                                                ({{ (archivos.xml.size / 1024).toFixed(2) }} KB)
                                            </span>
                                        </div>
                                        <input 
                                            type="file" 
                                            ref="xmlInput"
                                            @change="handleFileUpload('xml', $event)" 
                                            accept=".xml"
                                            class="file-input-hidden"
                                        />
                                        <button 
                                            v-if="archivos.xml" 
                                            type="button" 
                                            @click="eliminarArchivo('xml')" 
                                            class="file-remove-premium"
                                            title="Eliminar archivo"
                                        >

                                        </button>
                                    </div>
                                    <div class="field-hint-premium">Haz clic en el área para seleccionar un archivo XML (máx. 5MB)</div>
                                </div>
                            </div>

                            <!-- Archivos seleccionados - Mejorado -->
                            <div v-if="archivos.pdf || archivos.xml" class="archivos-seleccionados-premium">
                                <span class="archivos-seleccionados-title">Archivos seleccionados:</span>
                                <div class="archivos-seleccionados-list">
                                    <span v-if="archivos.pdf" class="archivo-item pdf">
                                        <span class="archivo-icon"></span>
                                        {{ archivos.pdf.name }}
                                        <span class="archivo-size">({{ (archivos.pdf.size / 1024).toFixed(2) }} KB)</span>
                                        <button type="button" @click="eliminarArchivo('pdf')" class="archivo-remove" title="Eliminar"><i class="pi pi-times"></i></button>
                                    </span>
                                    <span v-if="archivos.xml" class="archivo-item xml">
                                        <span class="archivo-icon"></span>
                                        {{ archivos.xml.name }}
                                        <span class="archivo-size">({{ (archivos.xml.size / 1024).toFixed(2) }} KB)</span>
                                        <button type="button" @click="eliminarArchivo('xml')" class="archivo-remove" title="Eliminar"><i class="pi pi-times"></i></button>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- SECCIÓN 5: OBSERVACIÓN                      -->
                        <!-- ============================================ -->
                        <div class="section-block">
                            <div class="section-header">
                                <div class="section-icon icon-teal">
                                    <svg fill="none" stroke="white" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="section-title">Observación</h3>
                                    <p class="section-subtitle">Notas adicionales sobre la póliza fiscal</p>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <div class="input-wrapper">
                                    <textarea v-model="form.nota" rows="3"
                                              class="form-textarea"
                                              placeholder="Pago de servicios profesionales correspondientes al mes de mayo 2024..."
                                              @input="clearError('nota')"></textarea>
                                </div>
                                <div v-if="form.nota" class="char-counter">{{ form.nota.length }} caracteres</div>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- BOTONES                                     -->
                        <!-- ============================================ -->
                        <div class="info-box">
                            <span class="info-icon"></span>
                            <span>Los campos con <strong class="text-danger">*</strong> son obligatorios</span>
                        </div>

                        <div class="form-actions">
                            <div class="actions-left">
                                <div class="total-box">
                                    <span class="total-label">Total</span>
                                    <span class="total-value">${{ formatNumber(totalFacturaCalculado) }}</span>
                                </div>
                            </div>
                            <div class="actions-right">
                                <Link :href="route('movimientos.index')" class="btn btn-cancel">
                                    Cancelar
                                </Link>
                                <button type="submit" 
                                        :disabled="processing || !isFormValid"
                                        class="btn btn-submit">
                                    <span v-if="processing" class="spinner"></span>
                                    <span v-else>Guardar póliza</span>
                                </button>
                            </div>
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
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, onMounted, watch } from 'vue';
import { useNotify } from '@/composables/useNotify';

const notify = useNotify();

// ============================================
// PROPS
// ============================================
const props = defineProps({
    empresa_id: { type: Number, default: null },
    cuentas_fondeadoras: { type: Array, default: () => [] },
    cuentas: { type: Array, default: () => [] },
    marcadores: { type: Array, default: () => [] },
    personas: { type: Array, default: () => [] }
});

// DEBUG
const pdfInput = ref(null);
const xmlInput = ref(null);
const fechaActual = ref(new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' }));
const processing = ref(false);
const archivos = ref({ pdf: null, xml: null });

// ============================================
// FORMULARIO
// ============================================
const form = ref({
    tipo_poliza: 'INGRESO',
    id_persona: null,
    id_cuenta: null,
    id_cuenta_fondeadora: null,
    es_por_pagar: false,
    fecha_vencimiento: null,
    id_marcador: null,
    monto_iva_cero: 0,
    monto_iva_dieciseis: 0,
    total_factura: 0,
    fecha_factura: null,
    numero_factura: null,
    nota: null,
    errors: {}
});

// ============================================
// COMPUTED
// ============================================
const ivaDieciseisCalculado = computed(() => {
    return Math.round((form.value.monto_iva_dieciseis || 0) * 0.16 * 100) / 100;
});

const totalFacturaCalculado = computed(() => {
    const base = (form.value.monto_iva_cero || 0) + (form.value.monto_iva_dieciseis || 0);
    const iva = ivaDieciseisCalculado.value;
    return Math.round((base + iva) * 100) / 100;
});

const porcentajeCero = computed(() => {
    const total = totalFacturaCalculado.value;
    if (total <= 0) return 0;
    return ((form.value.monto_iva_cero || 0) / total) * 100;
});

const porcentajeDieciseisBase = computed(() => {
    const total = totalFacturaCalculado.value;
    if (total <= 0) return 0;
    return ((form.value.monto_iva_dieciseis || 0) / total) * 100;
});

const porcentajeIva = computed(() => {
    const total = totalFacturaCalculado.value;
    if (total <= 0) return 0;
    return (ivaDieciseisCalculado.value / total) * 100;
});

const hasErrors = computed(() => Object.keys(form.value.errors).length > 0);
const errorCount = computed(() => Object.keys(form.value.errors).length);

const requiredFields = computed(() => {
    const fields = ['tipo_poliza', 'id_cuenta_fondeadora'];
    if (form.value.es_por_pagar) fields.push('fecha_vencimiento');
    return fields;
});

const progressPercentage = computed(() => {
    const total = requiredFields.value.length;
    const filled = requiredFields.value.filter(f => {
        const val = form.value[f];
        return val !== null && val !== undefined && val !== '' && val !== 0;
    }).length;
    return total > 0 ? (filled / total) * 100 : 0;
});

const isComplete = computed(() => progressPercentage.value === 100 && !hasErrors.value);

const statusClass = computed(() => {
    if (hasErrors.value) return 'status-error';
    if (isComplete.value) return 'status-success';
    return 'status-progress';
});

const isFormValid = computed(() => {
    if (!form.value.tipo_poliza) return false;
    if (!form.value.id_cuenta_fondeadora) return false;
    if (form.value.total_factura <= 0) return false;
    if (form.value.es_por_pagar && !form.value.fecha_vencimiento) return false;
    if (Object.keys(form.value.errors).length > 0) return false;
    return true;
});

// ============================================
// MÉTODOS
// ============================================
const calcularTotales = () => {
    form.value.total_factura = totalFacturaCalculado.value;
    clearError('total_factura');
};

const handleFileUpload = (tipo, event) => {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            notify.error(`El archivo ${file.name} excede el límite de 5MB.`, 'Archivo demasiado grande');
            event.target.value = '';
            return;
        }
        
        archivos.value[tipo] = file;
        
        notify.success(`Se ha seleccionado ${file.name} (${(file.size / 1024).toFixed(2)} KB)`, 'Archivo seleccionado');
    }
};

const eliminarArchivo = (tipo) => {
    archivos.value[tipo] = null;
    if (tipo === 'pdf' && pdfInput.value) {
        pdfInput.value.value = '';
    }
    if (tipo === 'xml' && xmlInput.value) {
        xmlInput.value.value = '';
    }
};

const clearError = (field) => {
    if (form.value.errors[field]) delete form.value.errors[field];
};

const formatNumber = (value) => {
    if (!value && value !== 0) return '0.00';
    return Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const submit = () => {
    processing.value = true;

    // Validaciones finales
    if (!form.value.id_cuenta_fondeadora) {
        notify.error('Selecciona una cuenta fondeadora', 'Error');
        processing.value = false;
        return;
    }
    if (form.value.total_factura <= 0) {
        notify.error('El total debe ser mayor a 0', 'Error');
        processing.value = false;
        return;
    }
    if (form.value.es_por_pagar && !form.value.fecha_vencimiento) {
        notify.error('Ingresa una fecha de vencimiento', 'Error');
        processing.value = false;
        return;
    }

    const formData = new FormData();
    
    Object.keys(form.value).forEach(key => {
        if (key === 'errors') return;
        let value = form.value[key];
        if (typeof value === 'boolean') value = value ? '1' : '0';
        if (value === null || value === undefined) value = '';
        if (value !== '' && value !== null && value !== undefined) {
            formData.append(key, value);
        }
    });

    if (archivos.value.pdf) formData.append('pdf_file', archivos.value.pdf);
    if (archivos.value.xml) formData.append('xml_file', archivos.value.xml);

    axios.post(route('movimientos.fiscal.doble.iva.store'), formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
    .then(() => {
        processing.value = false;
        notify.success('Póliza fiscal con doble IVA creada correctamente.', 'Éxito');
        setTimeout(() => {
            router.visit(route('movimientos.index'), { method: 'get', replace: true });
        }, 1500);
    })
    .catch(error => {
        processing.value = false;
        if (error.response?.data?.errors) {
            form.value.errors = error.response.data.errors;
            const firstError = Object.values(error.response.data.errors)[0];
            notify.error(Array.isArray(firstError) ? firstError[0] : firstError, 'Error de validación');
        } else {
            notify.error(error.response?.data?.message || 'Error al guardar la póliza.', 'Error');
        }
    });
};

// ============================================
// WATCHER PARA ERRORES
// ============================================
watch(
    () => form.value.errors,
    (newErrors) => {
        if (Object.keys(newErrors).length > 0) {
            const firstError = Object.values(newErrors)[0];
            if (firstError) {
                notify.error(firstError, 'Error de validación');
            }
        }
    },
    { deep: true }
);

// ============================================
// LIFECYCLE
// ============================================
onMounted(() => {
    
    // Si hay cuentas fondeadoras, seleccionar la primera
    if (props.cuentas_fondeadoras && props.cuentas_fondeadoras.length > 0) {
        form.value.id_cuenta_fondeadora = props.cuentas_fondeadoras[0].id_cuenta;
    } else {
        console.warn(' No hay cuentas fondeadoras disponibles');
    }
});
</script>

<style scoped>
/* ============================================ */
/* ===== ESTILOS PREMIUM MEJORADOS ===== */
/* ============================================ */

/* ===== HEADER ===== */
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
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255,255,255,0.8);
    border: 1px solid rgba(255,255,255,0.3);
    color: #6b7280;
    transition: all 0.3s ease;
}

.btn-back:hover {
    background: white;
    color: #1f2937;
    transform: translateX(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.header-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
    line-height: 1.3;
}

.header-subtitle {
    font-size: 0.85rem;
    color: #6b7280;
    margin: 0;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.status-success {
    background: #d1fae5;
    color: #065f46;
}

.status-error {
    background: #fecaca;
    color: #991b1b;
    animation: shake 0.5s ease;
}

.status-progress {
    background: #e0e7ff;
    color: #4338ca;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

/* ===== PAGE ===== */
.page-content {
    padding: 1.5rem 0;
}

.container-custom {
    max-width: 72rem;
    margin: 0 auto;
    padding: 0 1.5rem;
}

.form-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid #f3f4f6;
    padding: 2rem;
}

/* ===== SECTIONS ===== */
.section-block {
    margin-bottom: 2.5rem;
    padding-bottom: 2.5rem;
    border-bottom: 2px solid #f1f5f9;
}

.section-block:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 20px;
}

.section-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 14px;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 16px rgba(26, 58, 92,0.25);
}

.icon-blue { background: linear-gradient(135deg, #1a3a5c, #3d6ea5); }
.icon-green { background: linear-gradient(135deg, #10b981, #059669); }
.icon-orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
.icon-purple { background: linear-gradient(135deg, #1a3a5c, #132a44); }
.icon-teal { background: linear-gradient(135deg, #14b8a6, #0d9488); }

.section-icon svg {
    width: 22px;
    height: 22px;
}

.section-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.section-subtitle {
    font-size: 0.8rem;
    color: #94a3b8;
    margin: 0;
}

/* ===== FORM GRID ===== */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.full-width {
    grid-column: 1 / -1;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1e293b;
}

.required {
    color: #ef4444;
    font-weight: 700;
}

.input-wrapper {
    position: relative;
}

.form-input {
    width: 100%;
    padding: 10px 14px;
    font-size: 0.9rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    color: #1f2937;
    transition: all 0.3s ease;
    outline: none;
    height: 44px;
}

.form-input:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92,0.1);
}

.form-input.error {
    border-color: #ef4444;
}

.form-textarea {
    width: 100%;
    padding: 10px 14px;
    font-size: 0.9rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    color: #1f2937;
    transition: all 0.3s ease;
    outline: none;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.form-textarea:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92,0.1);
}

.input-prefix {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: 700;
    color: #64748b;
    font-size: 0.9rem;
    z-index: 1;
}

.input-prefix + .form-input {
    padding-left: 28px;
}

.error-message {
    font-size: 0.75rem;
    color: #ef4444;
    margin-top: 4px;
}

.warning-message {
    font-size: 0.75rem;
    color: #f59e0b;
    margin-top: 4px;
    padding: 6px 10px;
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 6px;
}

.char-counter {
    font-size: 0.75rem;
    color: #94a3b8;
    text-align: right;
    margin-top: 4px;
}

.field-hint-premium {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 2px;
}

/* ===== CHECKBOX ===== */
.options-row {
    display: flex;
    gap: 20px;
    padding: 6px 0;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 0.9rem;
    color: #374151;
    font-weight: 500;
}

.checkbox-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    flex-shrink: 0;
    transition: all 0.3s ease;
    position: relative;
}

.checkbox-custom::after {
    content: '';
    position: absolute;
    inset: 2px;
    background: #1a3a5c;
    border-radius: 3px;
    transform: scale(0);
    transition: all 0.3s ease;
}

.checkbox-input:checked + .checkbox-custom {
    border-color: #1a3a5c;
}

.checkbox-input:checked + .checkbox-custom::after {
    transform: scale(1);
}

/* ============================================ */
/* ===== IVA CARDS ===== */
/* ============================================ */
.iva-cards-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

.iva-card {
    background: white;
    border-radius: 14px;
    border: 2px solid #e5e7eb;
    overflow: hidden;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.iva-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

/* Card 0% */
.card-cero {
    border-color: #94a3b8;
    background: linear-gradient(180deg, #f8fafc, white);
}

.card-cero .card-header {
    background: #f1f5f9;
}

/* Card 16% */
.card-dieciseis {
    border-color: #60a5fa;
    background: linear-gradient(180deg, #eff6ff, white);
}

.card-dieciseis .card-header {
    background: #dbeafe;
}

/* Card Total */
.card-total {
    border-color: #34d399;
    background: linear-gradient(180deg, #ecfdf5, white);
    border-width: 3px;
}

.card-total .card-header {
    background: #d1fae5;
}

.card-header {
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #e5e7eb;
}

.card-badge {
    padding: 2px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
}

.badge-cero { background: #64748b; }
.badge-dieciseis { background: #3b82f6; }
.badge-total { background: #10b981; }

.card-title {
    font-weight: 600;
    font-size: 0.85rem;
    color: #1e293b;
}

.card-body {
    padding: 16px;
    flex: 1;
    display: flex;
    align-items: center;
}

.card-input-wrapper {
    width: 100%;
}

.card-input {
    height: 48px;
    font-size: 1.1rem;
}

/* Estilo especial para el input del total */
.total-body {
    justify-content: center;
    padding: 16px 20px;
}

.total-amount {
    font-size: 2rem;
    font-weight: 800;
    color: #059669;
    letter-spacing: -0.5px;
}

.card-footer {
    padding: 8px 16px 12px 16px;
    border-top: 1px solid #f1f5f9;
    text-align: center;
}

.card-result {
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
}

/* Footer especial para el total */
.total-footer {
    background: #d1fae5;
    border-top: 2px solid #34d399;
    border-radius: 0 0 12px 12px;
}

.total-footer .card-result {
    color: #065f46;
    font-weight: 700;
}

/* ===== AUTO GRID ===== */
.auto-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-top: 8px;
}

.auto-item {
    background: white;
    padding: 16px 20px;
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    text-align: center;
    transition: all 0.3s ease;
}

.auto-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.auto-highlight {
    border-color: #10b981;
    background: #f0fdf4;
}

.auto-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.auto-value {
    display: block;
    font-size: 1.3rem;
    font-weight: 700;
    color: #0f172a;
    margin: 6px 0;
}

.auto-value.total {
    color: #10b981;
    font-size: 1.5rem;
}

.auto-tag {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #64748b;
}

/* ===== BARS ===== */
.bars-container {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid #f1f5f9;
}

.bar-row {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 8px;
}

.bar-row:last-child {
    margin-bottom: 0;
}

.bar-label {
    min-width: 140px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
}

.bar-track {
    flex: 1;
    height: 24px;
    background: #f1f5f9;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}

.bar-fill {
    height: 100%;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    color: white;
    transition: width 0.6s ease;
}

.bar-cero { background: #64748b; }
.bar-dieciseis { background: #3b82f6; }
.bar-iva { background: #f59e0b; }

/* ============================================ */
/* ===== FILE UPLOAD - MEJORADO ===== */
/* ============================================ */
.file-upload-wrapper-premium {
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
}

.file-upload-area-premium {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border: 2px dashed #d1d5db;
    border-radius: 10px;
    background: #f9fafb;
    cursor: pointer;
    transition: all 0.3s ease;
    min-height: 44px;
}

.file-upload-area-premium:hover {
    border-color: #1a3a5c;
    background: linear-gradient(135deg, #f8f7ff, #eef2ff);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 58, 92, 0.1);
}

.file-upload-area-premium.has-file {
    border-color: #10b981;
    background: #ecfdf5;
}

.file-upload-area-premium.has-file:hover {
    border-color: #059669;
    background: #d1fae5;
}

.file-upload-icon-premium {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    color: #94a3b8;
}

.file-upload-text-premium {
    font-size: 0.85rem;
    color: #6b7280;
    font-weight: 500;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-upload-size-premium {
    font-size: 0.7rem;
    color: #94a3b8;
    font-weight: 400;
}

.file-input-hidden {
    position: absolute;
    opacity: 0;
    width: 0.1px;
    height: 0.1px;
    pointer-events: none;
}

.file-remove-premium {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: #fef2f2;
    border: 2px solid #fca5a5;
    border-radius: 50%;
    color: #dc2626;
    cursor: pointer;
    font-weight: 800;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.file-remove-premium:hover {
    background: #fecaca;
    transform: scale(1.1) rotate(90deg);
}

/* ===== ARCHIVOS SELECCIONADOS - MEJORADO ===== */
.archivos-seleccionados-premium {
    margin-top: 16px;
    padding: 14px 18px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.archivos-seleccionados-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    display: block;
    margin-bottom: 8px;
}

.archivos-seleccionados-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.archivo-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    position: relative;
}

.archivo-item.pdf {
    background: #fef2f2;
    color: #dc2626;
}

.archivo-item.xml {
    background: #eff6ff;
    color: #2563eb;
}

.archivo-icon {
    font-size: 0.9rem;
}

.archivo-size {
    font-size: 0.65rem;
    color: #94a3b8;
    font-weight: 400;
}

.archivo-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border: none;
    background: rgba(0,0,0,0.08);
    border-radius: 50%;
    color: #6b7280;
    cursor: pointer;
    font-size: 0.6rem;
    font-weight: 700;
    transition: all 0.3s ease;
    padding: 0;
    margin-left: 2px;
}

.archivo-remove:hover {
    background: #fecaca;
    color: #dc2626;
    transform: scale(1.2);
}

/* ===== INFO BOX ===== */
.info-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    background: #f8f7ff;
    border-radius: 12px;
    border-left: 4px solid #1a3a5c;
    font-size: 0.85rem;
    color: #4b5563;
    margin-top: 20px;
}

.info-icon {
    font-size: 1.1rem;
}

.text-danger {
    color: #ef4444;
}

/* ===== ACTIONS ===== */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 2px solid #f1f5f9;
}

.actions-left,
.actions-right {
    display: flex;
    gap: 12px;
    align-items: center;
}

.total-box {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.total-label {
    font-size: 0.7rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.total-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #0f172a;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 28px;
    font-weight: 700;
    border: none;
    border-radius: 12px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    height: 44px;
}

.btn-cancel {
    background: #f1f5f9;
    color: #64748b;
    border: 2px solid #e5e7eb;
}

.btn-cancel:hover {
    background: #fecaca;
    color: #dc2626;
}

.btn-submit {
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    color: white;
    box-shadow: 0 4px 20px rgba(26, 58, 92,0.3);
}

.btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(26, 58, 92,0.4);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.spinner {
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

/* ============================================ */
/* ===== RESPONSIVE ===== */
/* ============================================ */
@media (max-width: 992px) {
    .iva-cards-row {
        grid-template-columns: 1fr 1fr;
    }
    
    .card-total {
        grid-column: span 2;
    }
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .iva-cards-row {
        grid-template-columns: 1fr;
    }
    
    .card-total {
        grid-column: span 1;
    }

    .auto-grid {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .actions-left,
    .actions-right {
        width: 100%;
        justify-content: center;
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .header-wrapper {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .header-right {
        width: 100%;
        justify-content: flex-start;
    }

    .bar-row {
        flex-direction: column;
        align-items: stretch;
        gap: 4px;
    }

    .bar-label {
        min-width: auto;
        font-size: 0.7rem;
    }

    .container-custom {
        padding: 0 0.75rem;
    }

    .form-card {
        padding: 1.25rem;
    }

    .total-amount {
        font-size: 1.6rem;
    }

    .file-upload-wrapper-premium {
        flex-wrap: wrap;
    }
    
    .file-upload-area-premium {
        padding: 10px 14px;
        min-height: 38px;
    }
    
    .file-upload-text-premium {
        font-size: 0.75rem;
    }
    
    .archivos-seleccionados-list {
        flex-direction: column;
        gap: 4px;
    }
}

@media (max-width: 480px) {
    .form-card {
        padding: 1rem;
        border-radius: 16px;
    }

    .header-title {
        font-size: 1.1rem;
    }

    .section-header {
        gap: 10px;
    }

    .section-icon {
        width: 34px;
        height: 34px;
    }

    .section-icon svg {
        width: 18px;
        height: 18px;
    }

    .total-amount {
        font-size: 1.3rem;
    }
}
</style>