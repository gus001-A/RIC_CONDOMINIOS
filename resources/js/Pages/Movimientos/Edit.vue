<template>
    <AppLayout :title="tituloPagina">
        <!-- Los flash messages se muestran de forma global (AppLayout useFlashMessages) -->

        <template #header>
            <div class="header-wrapper">
                <div class="header-left">
                    <Link :href="route('movimientos.index')" class="btn-back">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <div class="header-content">
                        <h2 class="header-title">{{ tituloPagina }}</h2>
                        <p class="header-subtitle">{{ subtituloPagina }}</p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="status-badge" :class="statusClass">
                        <span v-if="hasErrors">! {{ errorCount }} errores</span>
                        <span v-else-if="isComplete">Completado</span>
                        <span v-else>{{ Math.round(progressPercentage) }}%</span>
                    </div>
                    <span v-if="movimiento.estatus" class="status-badge" :class="getStatusClass(movimiento.estatus)">
                        {{ movimiento.estatus }}
                    </span>
                </div>
            </div>
        </template>

        <div class="page-content">
            <div class="container-custom">
                <div class="form-card">
                    <!-- Selector de Tipo de Poliza -->
                    <div class="tipo-selector-premium">
                        <button 
                            type="button"
                            @click="seleccionarTipo('INGRESO_EGRESO')"
                            class="tipo-btn-premium"
                            :class="{ active: tipoPolizaSeleccionado === 'INGRESO_EGRESO' }"
                            :disabled="bloqueado"
                        >
                            <div class="tipo-btn-content">
                                <span class="tipo-btn-icon">
                                    <svg class="icon-svg-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                    </svg>
                                </span>
                                <span class="tipo-btn-label">Ingreso / Egreso</span>
                            </div>
                            <div class="tipo-btn-glow"></div>
                        </button>
                        <button 
                            type="button"
                            @click="seleccionarTipo('TRASPASO')"
                            class="tipo-btn-premium"
                            :class="{ active: tipoPolizaSeleccionado === 'TRASPASO' }"
                            :disabled="bloqueado"
                        >
                            <div class="tipo-btn-content">
                                <span class="tipo-btn-icon">
                                    <svg class="icon-svg-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                    </svg>
                                </span>
                                <span class="tipo-btn-label">Traspaso</span>
                            </div>
                            <div class="tipo-btn-glow"></div>
                        </button>
                    </div>

                    <form @submit.prevent="submit" id="polizaForm" novalidate enctype="multipart/form-data">
                        <!-- ============================================ -->
                        <!-- INGRESO / EGRESO -->
                        <!-- ============================================ -->
                        <div v-if="tipoPolizaSeleccionado === 'INGRESO_EGRESO'" class="fade-slide">
                            <!-- FILA 1: TIPO, PERSONA, CUENTA -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Tipo <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="form.tipo_poliza" 
                                                class="form-input"
                                                :class="{ error: form.errors.tipo_poliza }"
                                                @change="onTipoPolizaChange"
                                                :disabled="bloqueado">
                                            <option value="">Selecciona un tipo</option>
                                            <option value="INGRESO">Ingreso</option>
                                            <option value="EGRESO">Egreso</option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.tipo_poliza" class="error-text">{{ form.errors.tipo_poliza }}</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Persona <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_persona"
                                                @change="onPersonaChange"
                                                class="form-input"
                                                :class="{ error: form.errors.id_persona }"
                                                :disabled="bloqueado">
                                            <option value="">Selecciona una persona</option>
                                            <option v-for="p in personas" :key="p.id_persona" :value="p.id_persona">
                                                {{ p.nombre_completo }}
                                            </option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.id_persona" class="error-text">{{ form.errors.id_persona }}</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Cuenta <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_cuenta"
                                                class="form-input"
                                                :class="{ error: form.errors.id_cuenta }"
                                                :disabled="bloqueado">
                                            <option value="">Selecciona una cuenta</option>
                                            <optgroup v-if="form.tipo_poliza === 'EGRESO'" label="Cuentas para Egresos">
                                                <option v-for="c in cuentasEgreso" :key="c.id_cuenta" :value="c.id_cuenta">
                                                    {{ c.nombre_cuenta }}
                                                </option>
                                            </optgroup>
                                            <optgroup v-else-if="form.tipo_poliza === 'INGRESO'" label="Cuentas para Ingresos">
                                                <option v-for="c in cuentasIngreso" :key="c.id_cuenta" :value="c.id_cuenta">
                                                    {{ c.nombre_cuenta }}
                                                </option>
                                            </optgroup>
                                            <optgroup v-else label="Selecciona un tipo de poliza primero">
                                                <option value="" disabled>Selecciona INGRESO o EGRESO arriba</option>
                                            </optgroup>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.id_cuenta" class="error-text">{{ form.errors.id_cuenta }}</div>
                                </div>
                            </div>

                            <!-- FILA 2: MONTO + IVA + FECHA -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Monto <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper input-with-prefix">
                                        <span class="input-prefix">$</span>
                                        <MoneyInput v-model="form.monto_directo"
                                               @input="onMontoChange"
                                               class="form-input"
                                               :class="{ error: form.errors.monto_directo }"
                                               placeholder="0.00"
                                               :disabled="bloqueado" />
                                    </div>
                                    <div v-if="form.errors.monto_directo" class="error-text">{{ form.errors.monto_directo }}</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">IVA <span class="opcional-label">(Opcional)</span></label>
                                    <div class="iva-selector">
                                        <button 
                                            type="button"
                                            v-for="iva in tiposIva" 
                                            :key="iva.id"
                                            class="iva-btn"
                                            :class="{ 
                                                active: ivasSeleccionados.includes(iva.id),
                                                disabled: ivasSeleccionados.length >= 2 && !ivasSeleccionados.includes(iva.id)
                                            }"
                                            @click="toggleIva(iva.id)"
                                            :disabled="bloqueado"
                                        >
                                            {{ iva.porcentaje }}%
                                            <span class="iva-check" v-if="ivasSeleccionados.includes(iva.id)"><i class="pi pi-check"></i></span>
                                        </button>
                                    </div>
                                    <div class="hint-text">Selecciona hasta 2 tipos de IVA (opcional)</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Fecha <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper input-date-wrapper">
                                        <input type="date" v-model="form.fecha_poliza"
                                               class="form-input input-date"
                                               :class="{ error: form.errors.fecha_poliza }"
                                               :max="fechaActual"
                                               :disabled="bloqueado">
                                        <svg class="input-icon input-date-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.fecha_poliza" class="error-text">{{ form.errors.fecha_poliza }}</div>
                                </div>
                            </div>

                            <!-- DETALLE DE IVA (SOLO SI HAY IVAS SELECCIONADOS) -->
                            <div v-if="ivasSeleccionados.length > 0" class="iva-detail">
                                <div class="iva-detail-grid">
                                    <div v-for="ivaId in ivasSeleccionados" :key="ivaId" class="iva-detail-item">
                                        <span class="iva-detail-badge" :class="getIvaPorcentaje(ivaId) === 0 ? 'badge-cero' : 'badge-dieciseis'">
                                            {{ getIvaPorcentaje(ivaId) }}%
                                        </span>
                                        <div class="input-wrapper input-with-prefix iva-input-wrap">
                                            <span class="input-prefix">$</span>
                                            <MoneyInput 
                                                v-model="form.ivas[ivaId].monto"
                                                @input="onIvaMontoChange"
                                                class="form-input iva-input"
                                                placeholder="0.00"
                                                :disabled="bloqueado" />
                                        </div>
                                        <span class="iva-detail-result">IVA: ${{ formatNumber(calcularIvaMonto(ivaId)) }}</span>
                                        <button type="button" @click="quitarIva(ivaId)" class="iva-remove-btn" :disabled="bloqueado"><i class="pi pi-times"></i></button>
                                    </div>
                                    <div class="iva-total">
                                        <span>Total: <strong>${{ formatNumber(totalConIvaCalculado) }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- VALIDACION DE IVA VS MONTO -->
                            <div v-if="form.monto_directo > 0 && ivasSeleccionados.length > 0" 
                                 class="validacion-iva"
                                 :class="totalConIvaCalculado > form.monto_directo ? 'iva-invalido' : 'iva-valido'">
                                <span class="validacion-icon"><i class="pi" :class="(totalConIvaCalculado > form.monto_directo) ? 'pi-exclamation-triangle' : 'pi-check'"></i></span>
                                <span class="validacion-texto">
                                    {{ totalConIvaCalculado > form.monto_directo 
                                        ? `La suma del desglose ($${formatNumber(totalConIvaCalculado)}) excede el monto ($${formatNumber(form.monto_directo)})` 
                                        : `Desglose correcto: $${formatNumber(form.monto_directo - totalConIvaCalculado)} restante` }}
                                </span>
                            </div>

                            <!-- FILA 3: CUENTA FONDEADORA, OPCIONES, MARCADOR -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Cuenta Fondeadora
                                        <span v-if="!form.es_por_pagar" class="required-star">*</span>
                                        <span v-else class="opcional-label">(Opcional)</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_cuenta_fondeadora"
                                                @change="cambiarCuentaFondeadora"
                                                class="form-input"
                                                :class="{ error: form.errors.id_cuenta_fondeadora }"
                                                :disabled="bloqueado">
                                            <option value="">Selecciona una fondeadora</option>
                                            <option v-for="c in cuentasFondeadoras" :key="c.id_cuenta" :value="c.id_cuenta">
                                                {{ c.nombre_cuenta }}
                                            </option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.id_cuenta_fondeadora" class="error-text">{{ form.errors.id_cuenta_fondeadora }}</div>
                                    <div v-if="cuentaFondeadoraSeleccionada" class="saldo-disponible">Saldo disponible: <strong>${{ formatNumber(saldoFondeadoraEfectivo) }}</strong>
                                    </div>
                                    <div v-if="form.es_por_pagar" class="hint-text" style="color: #92400e;">
                                        Póliza diferida - no requiere cuenta fondeadora
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Opciones</label>
                                    <div class="options-grid">
                                        <label class="checkbox">
                                            <input type="checkbox" v-model="form.es_por_pagar" @change="onEsPorPagarChange" class="checkbox-input" :disabled="bloqueado">
                                            <span class="checkbox-custom"></span>
                                            <span class="checkbox-text">Por Pagar</span>
                                        </label>
                                        <label class="checkbox">
                                            <input type="checkbox" v-model="form.es_fiscal" @change="toggleFiscal" class="checkbox-input" :disabled="bloqueado">
                                            <span class="checkbox-custom"></span>
                                            <span class="checkbox-text">Fiscal</span>
                                        </label>
                                    </div>
                                    <div v-if="form.es_por_pagar" class="vencimiento-wrapper">
                                        <label class="vencimiento-label">Fecha Vencimiento</label>
                                        <input type="date" v-model="form.fecha_vencimiento"
                                               class="form-input vencimiento-input input-date"
                                               :min="fechaActual"
                                               :class="{ error: form.errors.fecha_vencimiento }"
                                               :disabled="bloqueado">
                                        <div v-if="form.errors.fecha_vencimiento" class="error-text">{{ form.errors.fecha_vencimiento }}</div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Marcador</label>
                                    <div class="marcador-wrapper">
                                        <div class="input-wrapper" style="flex: 1;">
                                            <select v-model="form.id_marcador"
                                                    class="form-input"
                                                    :class="{ error: form.errors.id_marcador }"
                                                    :disabled="bloqueado">
                                                <option value="">Selecciona un marcador</option>
                                                <option v-for="m in marcadores" :key="m.id" :value="m.id">
                                                    {{ m.nombre_marcador }}
                                                </option>
                                            </select>
                                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                        </div>
                                        <button type="button" @click="marcadorGestorVisible = true" class="btn-add-marcador" title="Nuevo marcador" :disabled="bloqueado">
                                            <svg class="icon-svg-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-if="form.errors.id_marcador" class="error-text">{{ form.errors.id_marcador }}</div>
                                </div>
                            </div>

                            <!-- SECCION FISCAL -->
                            <div v-if="form.es_fiscal" class="fiscal-section fade-slide">
                                <div class="fiscal-header">
                                    <span class="fiscal-title">Datos de Facturacion</span>
                                    <span class="fiscal-badge">Fiscal</span>
                                </div>
                                <div class="fiscal-grid">
                                    <div class="form-group">
                                        <label class="form-label">Fecha Factura</label>
                                        <div class="input-wrapper input-date-wrapper">
                                            <input type="date" v-model="form.fecha_factura"
                                                   class="form-input input-date"
                                                   :class="{ error: form.errors.fecha_factura }"
                                                   :max="fechaActual"
                                                   :disabled="bloqueado">
                                            <svg class="input-icon input-date-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div v-if="form.errors.fecha_factura" class="error-text">{{ form.errors.fecha_factura }}</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Numero Factura</label>
                                        <div class="input-wrapper">
                                            <input type="text" v-model="form.numero_factura"
                                                   class="form-input"
                                                   :class="{ error: form.errors.numero_factura }"
                                                   placeholder="Ej: A-1258"
                                                   :disabled="bloqueado">
                                        </div>
                                        <div v-if="form.errors.numero_factura" class="error-text">{{ form.errors.numero_factura }}</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">PDF</label>
                                        <div class="file-upload">
                                            <div class="file-upload-area" @click="$refs.pdfInput.click()">
                                                <svg class="file-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                </svg>
                                                <span>{{ archivos.pdf ? archivos.pdf.name : (movimiento.nombre_pdf || 'Seleccionar PDF') }}</span>
                                            </div>
                                            <input type="file" ref="pdfInput" @change="handleFileUpload('pdf', $event)" accept=".pdf" class="file-input-hidden" :disabled="bloqueado">
                                            <button v-if="archivos.pdf || movimiento.pdf_existente" type="button" @click="eliminarArchivo('pdf')" class="file-remove"><i class="pi pi-times"></i></button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">XML</label>
                                        <div class="file-upload">
                                            <div class="file-upload-area" @click="$refs.xmlInput.click()">
                                                <svg class="file-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <span>{{ archivos.xml ? archivos.xml.name : (movimiento.nombre_xml || 'Seleccionar XML') }}</span>
                                            </div>
                                            <input type="file" ref="xmlInput" @change="handleFileUpload('xml', $event)" accept=".xml" class="file-input-hidden" :disabled="bloqueado">
                                            <button v-if="archivos.xml || movimiento.xml_existente" type="button" @click="eliminarArchivo('xml')" class="file-remove"><i class="pi pi-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OBSERVACIONES -->
                            <div class="form-row observaciones-row">
                                <div class="form-group full-width">
                                    <label class="form-label">Observaciones</label>
                                    <div class="input-wrapper">
                                        <textarea v-model="form.nota" rows="2"
                                                  class="form-textarea"
                                                  :class="{ error: form.errors.nota }"
                                                  placeholder="Agrega notas o comentarios..."
                                                  :disabled="bloqueado"></textarea>
                                    </div>
                                    <div v-if="form.errors.nota" class="error-text">{{ form.errors.nota }}</div>
                                </div>
                            </div>

                            <!-- VALIDACION DE SALDO -->
                            <div v-if="!form.es_por_pagar && form.tipo_poliza === 'EGRESO' && totalConIvaCalculado > 0 && cuentaFondeadoraSeleccionada"
                                 class="validacion-saldo"
                                 :class="totalConIvaCalculado > saldoFondeadoraEfectivo ? 'saldo-insuficiente' : 'saldo-suficiente'">
                                <span class="validacion-icon"><i class="pi" :class="(totalConIvaCalculado > saldoFondeadoraEfectivo) ? 'pi-exclamation-triangle' : 'pi-check'"></i></span>
                                <span class="validacion-texto">
                                    {{ totalConIvaCalculado > saldoFondeadoraEfectivo
                                        ? `El monto ($${formatNumber(totalConIvaCalculado)}) excede el saldo disponible ($${formatNumber(saldoFondeadoraEfectivo)})`
                                        : `Saldo suficiente: $${formatNumber(saldoFondeadoraEfectivo - totalConIvaCalculado)} restante` }}
                                </span>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- TRASPASO -->
                        <!-- ============================================ -->
                        <div v-if="tipoPolizaSeleccionado === 'TRASPASO'" class="fade-slide">
                            <!-- FILA 1: TIPO, FECHA, MARCADOR -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Tipo <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="formTraspaso.tipo_poliza"
                                                class="form-input"
                                                :class="{ error: formTraspaso.errors.tipo_poliza }"
                                                disabled>
                                            <option value="TRASPASO">Traspaso</option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                    <div v-if="formTraspaso.errors.tipo_poliza" class="error-text">{{ formTraspaso.errors.tipo_poliza }}</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Fecha <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper input-date-wrapper">
                                        <input type="date" v-model="formTraspaso.fecha_poliza"
                                               class="form-input input-date"
                                               :class="{ error: formTraspaso.errors.fecha_poliza }"
                                               :max="fechaActual"
                                               :disabled="bloqueado">
                                        <svg class="input-icon input-date-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div v-if="formTraspaso.errors.fecha_poliza" class="error-text">{{ formTraspaso.errors.fecha_poliza }}</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Marcador</label>
                                    <div class="marcador-wrapper">
                                        <div class="input-wrapper" style="flex: 1;">
                                            <select v-model="formTraspaso.id_marcador"
                                                    class="form-input"
                                                    :class="{ error: formTraspaso.errors.id_marcador }"
                                                    :disabled="bloqueado">
                                                <option value="">Selecciona un marcador</option>
                                                <option v-for="m in marcadores" :key="m.id" :value="m.id">
                                                    {{ m.nombre_marcador }}
                                                </option>
                                            </select>
                                            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                        </div>
                                        <button type="button" @click="marcadorGestorVisible = true" class="btn-add-marcador" title="Nuevo marcador" :disabled="bloqueado">
                                            <svg class="icon-svg-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-if="formTraspaso.errors.id_marcador" class="error-text">{{ formTraspaso.errors.id_marcador }}</div>
                                </div>
                            </div>

                            <!-- FILA 2: CUENTA ORIGEN, CUENTA DESTINO, MONTO -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Cuenta Origen <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="formTraspaso.id_cuenta_origen"
                                                @change="onCuentaOrigenChange"
                                                class="form-input"
                                                :class="{ error: formTraspaso.errors.id_cuenta_origen }"
                                                :disabled="bloqueado">
                                            <option value="">Selecciona una cuenta</option>
                                            <option v-for="c in cuentasOrigenTraspaso" :key="c.id_cuenta" :value="c.id_cuenta">
                                                {{ c.nombre_cuenta }}
                                            </option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div v-if="formTraspaso.errors.id_cuenta_origen" class="error-text">{{ formTraspaso.errors.id_cuenta_origen }}</div>
                                    <div v-if="formTraspaso.id_cuenta_origen" class="saldo-disponible">Saldo: <strong>${{ formatNumber(saldoOrigenTraspasoEfectivo) }}</strong>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Cuenta Destino <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="formTraspaso.id_cuenta_destino"
                                                @change="onCuentaDestinoChange"
                                                class="form-input"
                                                :class="{ error: formTraspaso.errors.id_cuenta_destino }"
                                                :disabled="bloqueado">
                                            <option value="">Selecciona una cuenta</option>
                                            <option v-for="c in cuentasDestinoTraspaso" :key="c.id_cuenta" :value="c.id_cuenta">
                                                {{ c.nombre_cuenta }}
                                            </option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div v-if="formTraspaso.errors.id_cuenta_destino" class="error-text">{{ formTraspaso.errors.id_cuenta_destino }}</div>
                                    <div v-if="formTraspaso.id_cuenta_destino" class="saldo-disponible">Saldo: <strong>${{ formatNumber(saldoCuentaDestino) }}</strong>
                                        <span v-if="totalConIvaTraspasoCalculado > 0" class="nuevo-saldo">
                                             Nuevo: ${{ formatNumber(nuevoSaldoDestino) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Monto <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper input-with-prefix">
                                        <span class="input-prefix">$</span>
                                        <MoneyInput v-model="formTraspaso.monto_directo"
                                               @input="onMontoTraspasoChange"
                                               class="form-input"
                                               :class="{ error: formTraspaso.errors.monto_directo }"
                                               placeholder="0.00"
                                               :disabled="bloqueado" />
                                    </div>
                                    <div v-if="formTraspaso.errors.monto_directo" class="error-text">{{ formTraspaso.errors.monto_directo }}</div>
                                </div>
                            </div>

                            <!-- IVA + DETALLE (OPCIONAL) -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">IVA <span class="opcional-label">(Opcional)</span></label>
                                    <div class="iva-selector">
                                        <button 
                                            type="button"
                                            v-for="iva in tiposIva" 
                                            :key="iva.id"
                                            class="iva-btn"
                                            :class="{ 
                                                active: ivasSeleccionadosTraspaso.includes(iva.id),
                                                disabled: ivasSeleccionadosTraspaso.length >= 2 && !ivasSeleccionadosTraspaso.includes(iva.id)
                                            }"
                                            @click="toggleIvaTraspaso(iva.id)"
                                            :disabled="bloqueado"
                                        >
                                            {{ iva.porcentaje }}%
                                            <span class="iva-check" v-if="ivasSeleccionadosTraspaso.includes(iva.id)"><i class="pi pi-check"></i></span>
                                        </button>
                                    </div>
                                    <div class="hint-text">Selecciona hasta 2 tipos de IVA (opcional)</div>
                                </div>

                                <div v-if="ivasSeleccionadosTraspaso.length > 0" class="iva-detail" style="grid-column: span 2;">
                                    <div class="iva-detail-grid">
                                        <div v-for="ivaId in ivasSeleccionadosTraspaso" :key="ivaId" class="iva-detail-item">
                                            <span class="iva-detail-badge" :class="getIvaPorcentaje(ivaId) === 0 ? 'badge-cero' : 'badge-dieciseis'">
                                                {{ getIvaPorcentaje(ivaId) }}%
                                            </span>
                                            <div class="input-wrapper input-with-prefix iva-input-wrap">
                                                <span class="input-prefix">$</span>
                                                <MoneyInput 
                                                    v-model="formTraspaso.ivas[ivaId].monto"
                                                    @input="onIvaTraspasoChange"
                                                    class="form-input iva-input"
                                                    placeholder="0.00"
                                                    :disabled="bloqueado" />
                                            </div>
                                            <span class="iva-detail-result">IVA: ${{ formatNumber(calcularIvaMontoTraspaso(ivaId)) }}</span>
                                            <button type="button" @click="quitarIvaTraspaso(ivaId)" class="iva-remove-btn" :disabled="bloqueado"><i class="pi pi-times"></i></button>
                                        </div>
                                        <div class="iva-total">
                                            <span>Total: <strong>${{ formatNumber(totalConIvaTraspasoCalculado) }}</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- VALIDACION DE IVA VS MONTO TRASPASO -->
                            <div v-if="formTraspaso.monto_directo > 0 && ivasSeleccionadosTraspaso.length > 0" 
                                 class="validacion-iva"
                                 :class="totalConIvaTraspasoCalculado > formTraspaso.monto_directo ? 'iva-invalido' : 'iva-valido'">
                                <span class="validacion-icon"><i class="pi" :class="(totalConIvaTraspasoCalculado > formTraspaso.monto_directo) ? 'pi-exclamation-triangle' : 'pi-check'"></i></span>
                                <span class="validacion-texto">
                                    {{ totalConIvaTraspasoCalculado > formTraspaso.monto_directo 
                                        ? `La suma del desglose ($${formatNumber(totalConIvaTraspasoCalculado)}) excede el monto ($${formatNumber(formTraspaso.monto_directo)})` 
                                        : `Desglose correcto: $${formatNumber(formTraspaso.monto_directo - totalConIvaTraspasoCalculado)} restante` }}
                                </span>
                            </div>

                            <!-- OPCIONES + FISCAL -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Opciones</label>
                                    <div class="options-grid">
                                        <label class="checkbox">
                                            <input type="checkbox" v-model="formTraspaso.es_fiscal" @change="toggleFiscalTraspaso" class="checkbox-input" :disabled="bloqueado">
                                            <span class="checkbox-custom"></span>
                                            <span class="checkbox-text">Fiscal</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- FISCAL TRASPASO -->
                                <div v-if="formTraspaso.es_fiscal" class="fiscal-section" style="grid-column: span 2;">
                                    <div class="fiscal-header">
                                        <span class="fiscal-title">Datos de Facturacion</span>
                                        <span class="fiscal-badge">Fiscal</span>
                                    </div>
                                    <div class="fiscal-grid">
                                        <div class="form-group">
                                            <label class="form-label">Fecha Factura</label>
                                            <div class="input-wrapper input-date-wrapper">
                                                <input type="date" v-model="formTraspaso.fecha_factura"
                                                       class="form-input input-date"
                                                       :class="{ error: formTraspaso.errors.fecha_factura }"
                                                       :max="fechaActual"
                                                       :disabled="bloqueado">
                                                <svg class="input-icon input-date-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div v-if="formTraspaso.errors.fecha_factura" class="error-text">{{ formTraspaso.errors.fecha_factura }}</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Numero Factura</label>
                                            <div class="input-wrapper">
                                                <input type="text" v-model="formTraspaso.numero_factura"
                                                       class="form-input"
                                                       :class="{ error: formTraspaso.errors.numero_factura }"
                                                       placeholder="Ej: A-1258"
                                                       :disabled="bloqueado">
                                            </div>
                                            <div v-if="formTraspaso.errors.numero_factura" class="error-text">{{ formTraspaso.errors.numero_factura }}</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">PDF</label>
                                            <div class="file-upload">
                                                <div class="file-upload-area" @click="$refs.pdfInputTraspaso.click()">
                                                    <svg class="file-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                    </svg>
                                                    <span>{{ archivosTraspaso.pdf ? archivosTraspaso.pdf.name : (movimiento.nombre_pdf || 'Seleccionar PDF') }}</span>
                                                </div>
                                                <input type="file" ref="pdfInputTraspaso" @change="handleFileUploadTraspaso('pdf', $event)" accept=".pdf" class="file-input-hidden" :disabled="bloqueado">
                                                <button v-if="archivosTraspaso.pdf || movimiento.pdf_existente" type="button" @click="eliminarArchivoTraspaso('pdf')" class="file-remove"><i class="pi pi-times"></i></button>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">XML</label>
                                            <div class="file-upload">
                                                <div class="file-upload-area" @click="$refs.xmlInputTraspaso.click()">
                                                    <svg class="file-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <span>{{ archivosTraspaso.xml ? archivosTraspaso.xml.name : (movimiento.nombre_xml || 'Seleccionar XML') }}</span>
                                                </div>
                                                <input type="file" ref="xmlInputTraspaso" @change="handleFileUploadTraspaso('xml', $event)" accept=".xml" class="file-input-hidden" :disabled="bloqueado">
                                                <button v-if="archivosTraspaso.xml || movimiento.xml_existente" type="button" @click="eliminarArchivoTraspaso('xml')" class="file-remove"><i class="pi pi-times"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OBSERVACIONES TRASPASO -->
                            <div class="form-row observaciones-row">
                                <div class="form-group full-width">
                                    <label class="form-label">Observaciones</label>
                                    <div class="input-wrapper">
                                        <textarea v-model="formTraspaso.nota" rows="2"
                                                  class="form-textarea"
                                                  :class="{ error: formTraspaso.errors.nota }"
                                                  placeholder="Agrega notas o comentarios..."
                                                  :disabled="bloqueado"></textarea>
                                    </div>
                                    <div v-if="formTraspaso.errors.nota" class="error-text">{{ formTraspaso.errors.nota }}</div>
                                </div>
                            </div>

                            <!-- VALIDACION DE SALDO TRASPASO -->
                            <div v-if="formTraspaso.id_cuenta_origen && totalConIvaTraspasoCalculado > 0"
                                 class="validacion-saldo"
                                 :class="totalConIvaTraspasoCalculado > saldoOrigenTraspasoEfectivo ? 'saldo-insuficiente' : 'saldo-suficiente'">
                                <span class="validacion-icon"><i class="pi" :class="(totalConIvaTraspasoCalculado > saldoOrigenTraspasoEfectivo) ? 'pi-exclamation-triangle' : 'pi-check'"></i></span>
                                <span class="validacion-texto">
                                    {{ totalConIvaTraspasoCalculado > saldoOrigenTraspasoEfectivo
                                        ? `El monto ($${formatNumber(totalConIvaTraspasoCalculado)}) excede el saldo de origen ($${formatNumber(saldoOrigenTraspasoEfectivo)})`
                                        : `Saldo suficiente en origen` }}
                                </span>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- BOTONES -->
                        <!-- ============================================ -->
                        <div class="info-box">
                            <span>Los campos con <strong class="text-danger">*</strong> son obligatorios.</span>
                            <span v-if="ivasSeleccionados.length === 0 && tipoPolizaSeleccionado === 'INGRESO_EGRESO'" class="info-sin-iva">Sin IVA</span>
                            <span v-if="ivasSeleccionadosTraspaso.length === 0 && tipoPolizaSeleccionado === 'TRASPASO'" class="info-sin-iva">Sin IVA</span>
                            <span v-else-if="tipoPolizaSeleccionado === 'INGRESO_EGRESO' && ivasSeleccionados.length > 0" class="info-con-iva">Con IVA</span>
                            <span v-else-if="tipoPolizaSeleccionado === 'TRASPASO' && ivasSeleccionadosTraspaso.length > 0" class="info-con-iva">Con IVA</span>
                            <span v-if="movimiento.id" class="info-id">ID: {{ movimiento.id }}</span>
                            <span v-if="movimiento.estatus" class="info-id" :style="{ background: getStatusColor(movimiento.estatus) }">
                                {{ movimiento.estatus }}
                            </span>
                        </div>

                        <div class="form-actions">
                            <div class="actions-left">
                                <div class="total-card">
                                    <span class="total-label">Total</span>
                                    <span class="total-value">${{ formatNumber(tipoPolizaSeleccionado === 'INGRESO_EGRESO' ? form.monto_directo : formTraspaso.monto_directo) }}</span>
                                </div>
                            </div>
                            <div class="actions-right">
                                <Link :href="route('movimientos.index')" class="btn btn-cancel">
                                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Cancelar
                                </Link>
                                <button type="submit"
                                        :disabled="processing"
                                        class="btn btn-submit">
                                    <span v-if="processing" class="spinner-border"></span>
                                    <svg v-else class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                    </svg>
                                    {{ processing ? 'Guardando...' : 'Actualizar Póliza' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Marcador -->
        <MarcadoresModal v-model="marcadorGestorVisible" :marcadores="marcadores" @changed="(l) => marcadores = l" />

        <div v-if="modalMarcadorVisible" class="modal-overlay" @click.self="cerrarModalMarcador">
            <div class="modal-container">
                <div class="modal-header">
                    <h3 class="modal-title">Nuevo Marcador</h3>
                    <button type="button" @click="cerrarModalMarcador" class="modal-close">
                        <svg class="icon-svg-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="guardarMarcador">
                        <div class="form-group">
                            <label class="form-label">Nombre <span class="required-star">*</span></label>
                            <div class="input-wrapper">
                                <input type="text" v-model="nuevoMarcador.nombre"
                                       class="form-input"
                                       placeholder="Ej: Urgente, Importante">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 16px;">
                            <label class="form-label">Descripcion</label>
                            <div class="input-wrapper">
                                <textarea v-model="nuevoMarcador.descripcion"
                                          class="form-textarea"
                                          rows="2"
                                          placeholder="Breve descripcion..."></textarea>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" @click="cerrarModalMarcador" class="btn btn-cancel">Cancelar</button>
                            <button type="submit" :disabled="guardandoMarcador" class="btn btn-submit">
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
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, onMounted, watch } from 'vue';
import { useNotify } from '@/composables/useNotify';

const notify = useNotify();

// ============================================
// PROPS
// ============================================
const props = defineProps({
    movimiento: {
        type: Object,
        required: true
    },
    empresa_id: { type: Number, default: null },
    cuentas_fondeadoras: { type: Array, default: () => [] },
    cuentas_egreso: { type: Array, default: () => [] },
    cuentas_ingreso: { type: Array, default: () => [] },
    tipos_iva: { type: Array, default: () => [] },
    marcadores: { type: Array, default: () => [] },
    personas: { type: Array, default: () => [] },
    es_traspaso: { type: Boolean, default: false }
});

// Sólo una póliza CERRADA queda bloqueada para edición (a pedido del negocio:
// "puedo modificarle todo"). Antes se bloqueaban también AUTORIZADO/ABONADO/LIQUIDADO.
const bloqueado = computed(() => props.movimiento?.estatus === 'CERRADO');

// ============================================
// REFS
// ============================================
const pdfInput = ref(null);
const xmlInput = ref(null);
const pdfInputTraspaso = ref(null);
const xmlInputTraspaso = ref(null);
const personas = ref(props.personas || []);
const cuentaFondeadoraSeleccionada = ref(null);
const fechaActual = ref(new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' }));
const modalMarcadorVisible = ref(false);
const marcadorGestorVisible = ref(false);
const guardandoMarcador = ref(false);
const nuevoMarcador = ref({ nombre: '', descripcion: '' });
const processing = ref(false);
const archivos = ref({ pdf: null, xml: null });
const archivosTraspaso = ref({ pdf: null, xml: null });
const cargandoPoliza = ref(false);
const polizaCargada = ref(false);
const modoIva = ref('SIN_IVA');

// SALDOS DE TRASPASO
const saldoCuentaOrigen = ref(0);
const saldoCuentaDestino = ref(0);

// DATOS REACTIVOS
const tiposIva = ref(props.tipos_iva || []);
const cuentasFondeadoras = ref(props.cuentas_fondeadoras || []);
const cuentasEgreso = ref(props.cuentas_egreso || []);
const cuentasIngreso = ref(props.cuentas_ingreso || []);
const marcadores = ref(props.marcadores || []);

// DETERMINAR TIPO DE PÓLIZA
const tipoPolizaSeleccionado = ref(
    props.es_traspaso === true || props.movimiento?.tipo_poliza === 'TRASPASO' 
        ? 'TRASPASO' 
        : 'INGRESO_EGRESO'
);

// IVAs seleccionados
const ivasSeleccionados = ref([]);
const ivasSeleccionadosTraspaso = ref([]);

// ============================================
// COMPUTED - FILTROS DE TRASPASO
// ============================================
const cuentasOrigenTraspaso = computed(() => {
    return cuentasFondeadoras.value.filter(cuenta => {
        if (!cuenta.Naturaleza) return true;
        return cuenta.Naturaleza === 'DEUDORA';
    });
});

const cuentasDestinoTraspaso = computed(() => {
    return cuentasFondeadoras.value.filter(cuenta => {
        if (!cuenta.Naturaleza) return true;
        return cuenta.Naturaleza === 'ACREEDORA';
    });
});

// ============================================
// SALDO "EFECTIVO" PARA EDITAR
// El saldo que trae la cuenta/fondeadora del servidor ya incluye el efecto
// de ESTA MISMA póliza (porque ya está guardada en la BD). Si comparamos el
// monto nuevo contra ese saldo tal cual, una póliza que ya consumió casi todo
// el saldo de su propia fondeadora queda imposible de volver a guardar
// -aunque no cambies nada- y el botón "Actualizar" se ve deshabilitado.
// Por eso, antes de validar, le "devolvemos" a la cuenta lo que esta póliza
// ya le había restado/sumado (equivalente a revertirla y volver a aplicarla).
// ============================================
const montoAnteriorFirmado = computed(() => {
    if (props.movimiento?.es_por_pagar) return 0; // diferida: nunca tocó saldos
    const signo = props.movimiento?.tipo_poliza === 'EGRESO' ? -1 : 1;
    return signo * (Number(props.movimiento?.monto_abs) || 0);
});

const saldoFondeadoraEfectivo = computed(() => {
    const base = cuentaFondeadoraSeleccionada.value?.saldo || 0;
    if (String(form.id_cuenta_fondeadora || '') === String(props.movimiento?.id_cuenta_fondeadora || '')) {
        return base - montoAnteriorFirmado.value;
    }
    return base;
});

const saldoOrigenTraspasoEfectivo = computed(() => {
    const base = saldoCuentaOrigen.value || 0;
    if (String(formTraspaso.id_cuenta_origen || '') === String(props.movimiento?.id_cuenta_origen || '')) {
        const montoAnteriorTraspaso = Number(props.movimiento?.monto_traspaso) || Number(props.movimiento?.monto_abs) || 0;
        return base + montoAnteriorTraspaso;
    }
    return base;
});

// ============================================
// COMPUTED - VALIDACIONES
// ============================================
const isIvaValid = computed(() => {
    if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
        if (ivasSeleccionados.value.length === 0) return true;
        if (form.monto_directo > 0) {
            return totalConIvaCalculado.value <= form.monto_directo;
        }
        return true;
    } else {
        if (ivasSeleccionadosTraspaso.value.length === 0) return true;
        if (formTraspaso.monto_directo > 0) {
            return totalConIvaTraspasoCalculado.value <= formTraspaso.monto_directo;
        }
        return true;
    }
});

const isFormValid = computed(() => {
    if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
        if (!form.fecha_poliza) return false;
        if (!form.id_persona) return false;
        if (!form.tipo_poliza) return false;
        if (!form.id_cuenta) return false;
        if (!form.monto_directo || form.monto_directo <= 0) return false;
        if (ivasSeleccionados.value.length > 0 && totalConIvaCalculado.value > form.monto_directo) return false;
        if (!form.es_por_pagar && form.tipo_poliza === 'EGRESO' && cuentaFondeadoraSeleccionada.value) {
            const montoTotal = ivasSeleccionados.value.length > 0 ? totalConIvaCalculado.value : form.monto_directo;
            if (montoTotal > saldoFondeadoraEfectivo.value) return false;
        }
        return true;
    } else {
        if (!formTraspaso.fecha_poliza) return false;
        if (!formTraspaso.id_cuenta_origen) return false;
        if (!formTraspaso.id_cuenta_destino) return false;
        if (formTraspaso.id_cuenta_origen === formTraspaso.id_cuenta_destino) return false;
        if (!formTraspaso.monto_directo || formTraspaso.monto_directo <= 0) return false;
        if (ivasSeleccionadosTraspaso.value.length > 0 && totalConIvaTraspasoCalculado.value > formTraspaso.monto_directo) return false;
        const montoTotal = ivasSeleccionadosTraspaso.value.length > 0 ? totalConIvaTraspasoCalculado.value : formTraspaso.monto_directo;
        if (montoTotal > saldoOrigenTraspasoEfectivo.value) return false;
        return true;
    }
});

const hasErrors = computed(() => {
    const formErrors = Object.keys(form.errors).length;
    const traspasoErrors = Object.keys(formTraspaso.errors).length;
    return formErrors > 0 || traspasoErrors > 0 || !isIvaValid.value;
});

const errorCount = computed(() => {
    let count = Object.keys(form.errors).length + Object.keys(formTraspaso.errors).length;
    if (!isIvaValid.value) count++;
    return count;
});

const requiredFields = computed(() => {
    if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
        const fields = ['fecha_poliza', 'id_persona', 'tipo_poliza', 'id_cuenta', 'monto_directo'];
        if (!form.es_por_pagar) {
            fields.push('id_cuenta_fondeadora');
        }
        return fields;
    } else {
        return ['fecha_poliza', 'id_cuenta_origen', 'id_cuenta_destino', 'monto_directo'];
    }
});

const progressPercentage = computed(() => {
    const total = requiredFields.value.length;
    const filled = requiredFields.value.filter(f => {
        if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
            const val = form[f];
            return val !== null && val !== undefined && val !== '' && val !== 0;
        } else {
            const val = formTraspaso[f];
            return val !== null && val !== undefined && val !== '' && val !== 0;
        }
    }).length;
    return total > 0 ? (filled / total) * 100 : 0;
});

const isComplete = computed(() => {
    return progressPercentage.value === 100 && !hasErrors.value && isFormValid.value;
});

const statusClass = computed(() => {
    if (hasErrors.value) return 'status-error';
    if (isComplete.value) return 'status-success';
    return 'status-progress';
});

// ============================================
// CALCULO DE IVA
// ============================================
const totalConIvaCalculado = computed(() => {
    let total = 0;
    ivasSeleccionados.value.forEach(ivaId => {
        total += form.ivas[ivaId]?.monto || 0;
    });
    return Math.round(total * 100) / 100;
});

const totalBaseCalculado = computed(() => {
    let totalBase = 0;
    ivasSeleccionados.value.forEach(ivaId => {
        const montoConIva = form.ivas[ivaId]?.monto || 0;
        const iva = tiposIva.value.find(i => i.id === ivaId);
        if (iva && iva.porcentaje > 0) {
            const base = montoConIva / (1 + (iva.porcentaje / 100));
            totalBase += base;
        } else {
            totalBase += montoConIva;
        }
    });
    return Math.round(totalBase * 100) / 100;
});

const totalIvaCalculado = computed(() => {
    const totalConIva = totalConIvaCalculado.value;
    const base = totalBaseCalculado.value;
    return Math.round((totalConIva - base) * 100) / 100;
});

const totalConIvaTraspasoCalculado = computed(() => {
    let total = 0;
    ivasSeleccionadosTraspaso.value.forEach(ivaId => {
        total += formTraspaso.ivas[ivaId]?.monto || 0;
    });
    return Math.round(total * 100) / 100;
});

const totalBaseTraspasoCalculado = computed(() => {
    let totalBase = 0;
    ivasSeleccionadosTraspaso.value.forEach(ivaId => {
        const montoConIva = formTraspaso.ivas[ivaId]?.monto || 0;
        const iva = tiposIva.value.find(i => i.id === ivaId);
        if (iva && iva.porcentaje > 0) {
            const base = montoConIva / (1 + (iva.porcentaje / 100));
            totalBase += base;
        } else {
            totalBase += montoConIva;
        }
    });
    return Math.round(totalBase * 100) / 100;
});

const totalIvaTraspasoCalculado = computed(() => {
    const totalConIva = totalConIvaTraspasoCalculado.value;
    const base = totalBaseTraspasoCalculado.value;
    return Math.round((totalConIva - base) * 100) / 100;
});

const nuevoSaldoDestino = computed(() => {
    const montoTotal = ivasSeleccionadosTraspaso.value.length > 0 ? totalConIvaTraspasoCalculado.value : formTraspaso.monto_directo;
    return saldoCuentaDestino.value + montoTotal;
});

// ============================================
// COMPUTED - TITULOS
// ============================================
const tituloPagina = computed(() => {
    return tipoPolizaSeleccionado.value === 'INGRESO_EGRESO' 
        ? 'Editar Póliza de Ingreso/Egreso'
        : 'Editar Póliza de Traspaso';
});

const subtituloPagina = computed(() => {
    return tipoPolizaSeleccionado.value === 'INGRESO_EGRESO'
        ? 'Modifica los datos de la póliza de ingreso o egreso'
        : 'Modifica los datos del traspaso entre cuentas fondeadoras';
});

// ============================================
// METODOS DE UTILIDAD
// ============================================
const formatNumber = (value) => {
    if (!value && value !== 0) return '0.00';
    return Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getIvaPorcentaje = (ivaId) => {
    const iva = tiposIva.value.find(i => i.id === ivaId);
    return iva ? iva.porcentaje : 0;
};

const calcularIvaMonto = (ivaId) => {
    const montoConIva = form.ivas[ivaId]?.monto || 0;
    const iva = tiposIva.value.find(i => i.id === ivaId);
    if (!iva || montoConIva === 0) return 0;
    const base = montoConIva / (1 + (iva.porcentaje / 100));
    return Math.round((montoConIva - base) * 100) / 100;
};

const calcularIvaMontoTraspaso = (ivaId) => {
    const montoConIva = formTraspaso.ivas[ivaId]?.monto || 0;
    const iva = tiposIva.value.find(i => i.id === ivaId);
    if (!iva || montoConIva === 0) return 0;
    const base = montoConIva / (1 + (iva.porcentaje / 100));
    return Math.round((montoConIva - base) * 100) / 100;
};

const getStatusClass = (estatus) => {
    const map = {
        'CAPTURADO': 'status-capturado',
        'REVISADO': 'status-revisado',
        'AUTORIZADO': 'status-autorizado',
        'ABONADO': 'status-abonado',
        'LIQUIDADO': 'status-liquidado',
        'PENDIENTE': 'status-pendiente',
        'CERRADO': 'status-cerrado'
    };
    return map[estatus] || 'status-default';
};

const getStatusColor = (estatus) => {
    const map = {
        'CAPTURADO': '#3b82f6',
        'REVISADO': '#f59e0b',
        'AUTORIZADO': '#10b981',
        'ABONADO': '#1a3a5c',
        'LIQUIDADO': '#059669',
        'PENDIENTE': '#f59e0b',
        'CERRADO': '#6b7280'
    };
    return map[estatus] || '#6b7280';
};

// ============================================
// METODOS DE IVA
// ============================================
const toggleIva = (ivaId) => {
    const index = ivasSeleccionados.value.indexOf(ivaId);
    if (index > -1) {
        ivasSeleccionados.value.splice(index, 1);
        delete form.ivas[ivaId];
    } else {
        if (ivasSeleccionados.value.length >= 2) {
            notify.warn('Solo puedes seleccionar hasta 2 tipos de IVA', 'Limite alcanzado');
            return;
        }
        ivasSeleccionados.value.push(ivaId);
        if (!form.ivas[ivaId]) {
            form.ivas[ivaId] = { monto: 0 };
        }
    }
    modoIva.value = ivasSeleccionados.value.length > 0 ? 'CON_IVA' : 'SIN_IVA';
};

const quitarIva = (ivaId) => {
    const index = ivasSeleccionados.value.indexOf(ivaId);
    if (index > -1) {
        ivasSeleccionados.value.splice(index, 1);
        delete form.ivas[ivaId];
    }
    modoIva.value = ivasSeleccionados.value.length > 0 ? 'CON_IVA' : 'SIN_IVA';
};

const toggleIvaTraspaso = (ivaId) => {
    const index = ivasSeleccionadosTraspaso.value.indexOf(ivaId);
    if (index > -1) {
        ivasSeleccionadosTraspaso.value.splice(index, 1);
        delete formTraspaso.ivas[ivaId];
    } else {
        if (ivasSeleccionadosTraspaso.value.length >= 2) {
            notify.warn('Solo puedes seleccionar hasta 2 tipos de IVA', 'Limite alcanzado');
            return;
        }
        ivasSeleccionadosTraspaso.value.push(ivaId);
        if (!formTraspaso.ivas[ivaId]) {
            formTraspaso.ivas[ivaId] = { monto: 0 };
        }
    }
};

const quitarIvaTraspaso = (ivaId) => {
    const index = ivasSeleccionadosTraspaso.value.indexOf(ivaId);
    if (index > -1) {
        ivasSeleccionadosTraspaso.value.splice(index, 1);
        delete formTraspaso.ivas[ivaId];
    }
};

const onIvaMontoChange = () => {
    modoIva.value = ivasSeleccionados.value.length > 0 ? 'CON_IVA' : 'SIN_IVA';
};

const onIvaTraspasoChange = () => {};

const onMontoChange = () => {};

const onMontoTraspasoChange = () => {};

// ============================================
// METODOS GENERALES
// ============================================
const seleccionarTipo = (tipo) => {
    if (bloqueado.value) {
        return;
    }
    tipoPolizaSeleccionado.value = tipo;
    form.clearErrors();
    formTraspaso.clearErrors();
    saldoCuentaOrigen.value = 0;
    saldoCuentaDestino.value = 0;
    form.id_cuenta = null;
};

const onTipoPolizaChange = () => {
    form.id_cuenta = null;
    form.clearErrors('id_cuenta');
};

const cambiarCuentaFondeadora = async () => {
    if (!form.id_cuenta_fondeadora) {
        cuentaFondeadoraSeleccionada.value = null;
        return;
    }

    const cuentaEncontrada = cuentasFondeadoras.value.find(
        c => c.id_cuenta === form.id_cuenta_fondeadora
    );

    // Saldo REAL (el mismo que valida el servidor al guardar). El "saldo" que
    // viene en la lista de fondeadoras es sólo el saldo inicial, y con él se
    // bloqueaban egresos que sí tenían fondos.
    try {
        const res = await axios.get(route('movimientos.saldo.cuenta'), {
            params: { id: form.id_cuenta_fondeadora }
        });
        cuentaFondeadoraSeleccionada.value = res.data;
    } catch (error) {
        console.error('Error al obtener saldo:', error);
        cuentaFondeadoraSeleccionada.value = {
            id_cuenta: form.id_cuenta_fondeadora,
            nombre_cuenta: cuentaEncontrada?.nombre_cuenta || 'Cuenta',
            saldo: cuentaEncontrada?.saldo || 0
        };
    }
};

const obtenerSaldoCuenta = (idCuenta) => {
    if (!idCuenta) return 0;
    const cuenta = cuentasFondeadoras.value.find(c => c.id_cuenta === idCuenta);
    return cuenta ? (cuenta.saldo || 0) : 0;
};

const onPersonaChange = async () => {};

const onCuentaOrigenChange = () => {
    saldoCuentaOrigen.value = obtenerSaldoCuenta(formTraspaso.id_cuenta_origen);
    if (formTraspaso.id_cuenta_origen === formTraspaso.id_cuenta_destino) {
        notify.error('La cuenta de origen y destino no pueden ser la misma.', 'Error');
        formTraspaso.id_cuenta_origen = null;
    }
};

const onCuentaDestinoChange = () => {
    saldoCuentaDestino.value = obtenerSaldoCuenta(formTraspaso.id_cuenta_destino);
    if (formTraspaso.id_cuenta_origen === formTraspaso.id_cuenta_destino) {
        notify.error('La cuenta de origen y destino no pueden ser la misma.', 'Error');
        formTraspaso.id_cuenta_destino = null;
    }
};

const toggleFiscal = () => {
    if (!form.es_fiscal) {
        form.fecha_factura = null;
        form.numero_factura = null;
        archivos.value.pdf = null;
        archivos.value.xml = null;
    }
};

const toggleFiscalTraspaso = () => {
    if (!formTraspaso.es_fiscal) {
        formTraspaso.fecha_factura = null;
        formTraspaso.numero_factura = null;
        archivosTraspaso.value.pdf = null;
        archivosTraspaso.value.xml = null;
    }
};

const onEsPorPagarChange = () => {
    if (form.es_por_pagar) {
        form.id_cuenta_fondeadora = null;
        cuentaFondeadoraSeleccionada.value = null;
    } else {
        if (cuentasFondeadoras.value.length > 0) {
            form.id_cuenta_fondeadora = cuentasFondeadoras.value[0].id_cuenta;
            cambiarCuentaFondeadora();
        }
    }
};

// ============================================
// MANEJO DE ARCHIVOS
// ============================================
const handleFileUpload = (tipo, event) => {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            notify.error(`El archivo ${file.name} excede el limite de 5MB.`, 'Archivo demasiado grande');
            event.target.value = '';
            return;
        }
        archivos.value[tipo] = file;
    }
};

const eliminarArchivo = (tipo) => {
    archivos.value[tipo] = null;
    if (tipo === 'pdf' && pdfInput.value) pdfInput.value.value = '';
    if (tipo === 'xml' && xmlInput.value) xmlInput.value.value = '';
    if (tipo === 'pdf' && movimiento.pdf_existente) {
        form.eliminar_pdf = true;
    }
    if (tipo === 'xml' && movimiento.xml_existente) {
        form.eliminar_xml = true;
    }
};

const handleFileUploadTraspaso = (tipo, event) => {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            notify.error(`El archivo ${file.name} excede el limite de 5MB.`, 'Archivo demasiado grande');
            event.target.value = '';
            return;
        }
        archivosTraspaso.value[tipo] = file;
    }
};

const eliminarArchivoTraspaso = (tipo) => {
    archivosTraspaso.value[tipo] = null;
    if (tipo === 'pdf' && pdfInputTraspaso.value) pdfInputTraspaso.value.value = '';
    if (tipo === 'xml' && xmlInputTraspaso.value) xmlInputTraspaso.value.value = '';
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
    if (!nuevoMarcador.value.nombre.trim()) {
        notify.error('El nombre del marcador es obligatorio', 'Error');
        return;
    }
    guardandoMarcador.value = true;
    try {
        const res = await axios.post(route('movimientos.marcadores.store'), {
            nombre_marcador: nuevoMarcador.value.nombre,
            descripcion: nuevoMarcador.value.descripcion
        });
        if (res.data.success) {
            marcadores.value.push(res.data.data);
            if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
                form.id_marcador = res.data.data.id;
            } else {
                formTraspaso.id_marcador = res.data.data.id;
            }
            cerrarModalMarcador();
            notify.success('El marcador se ha creado y seleccionado correctamente.', 'Marcador creado');
        }
    } catch (error) {
        notify.error(error.response?.data?.message || 'Error al crear el marcador', 'Error');
    } finally { guardandoMarcador.value = false; }
};

// ============================================
// FORMULARIOS
// ============================================
const form = useForm({
    tipo_poliza: props.movimiento?.tipo_poliza || 'INGRESO',
    fecha_poliza: props.movimiento?.fecha_poliza || null,
    id_persona: props.movimiento?.id_persona || null,
    id_cuenta: props.movimiento?.id_cuenta || null,
    es_por_pagar: props.movimiento?.es_por_pagar || false,
    fecha_vencimiento: props.movimiento?.fecha_vencimiento || null,
    es_fiscal: props.movimiento?.es_fiscal || false,
    id_cuenta_fondeadora: props.movimiento?.id_cuenta_fondeadora || null,
    id_marcador: props.movimiento?.id_marcador || null,
    total_factura: props.movimiento?.monto_abs || 0,
    fecha_factura: props.movimiento?.fecha_factura || null,
    numero_factura: props.movimiento?.numero_factura || null,
    nota: props.movimiento?.nota || null,
    referencia: props.movimiento?.referencia || null,
    monto_directo: props.movimiento?.monto_abs || 0,
    ivas: {},
    eliminar_pdf: false,
    eliminar_xml: false,
});

const formTraspaso = useForm({
    tipo_poliza: 'TRASPASO',
    fecha_poliza: props.movimiento?.fecha_poliza || null,
    id_cuenta_origen: props.movimiento?.id_cuenta_origen || null,
    id_cuenta_destino: props.movimiento?.id_cuenta_destino || null,
    monto_directo: props.movimiento?.monto_abs || 0,
    es_fiscal: props.movimiento?.es_fiscal || false,
    id_marcador: props.movimiento?.id_marcador || null,
    fecha_factura: props.movimiento?.fecha_factura || null,
    numero_factura: props.movimiento?.numero_factura || null,
    nota: props.movimiento?.nota || null,
    ivas: {},
});

// ============================================
// SUBMIT - CORREGIDO CON AXIOS PARA ARCHIVOS
// ============================================
const submit = () => {

    if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
        // VALIDACIONES
        // El botón ya no se deshabilita solo (se valida aquí, al querer guardar,
        // con los datos reales de la cuenta — no con una condición reactiva que
        // podía dejarlo gris sin explicar por qué).
        if (!form.tipo_poliza) {
            notify.error('Selecciona si es Ingreso o Egreso.', 'Tipo requerido');
            return;
        }

        if (!form.fecha_poliza) {
            notify.error('La fecha de la póliza es obligatoria.', 'Fecha requerida');
            return;
        }

        if (!form.id_cuenta) {
            notify.error('Selecciona la cuenta de la póliza.', 'Cuenta requerida');
            return;
        }

        if (!form.monto_directo || form.monto_directo <= 0) {
            notify.error('Captura un monto mayor a $0.', 'Monto requerido');
            return;
        }

        if (!form.es_por_pagar && !form.id_cuenta_fondeadora) {
            notify.error('Selecciona la cuenta fondeadora.', 'Fondeadora requerida');
            return;
        }

        if (ivasSeleccionados.value.length > 0 && totalConIvaCalculado.value > form.monto_directo) {
            notify.error(`La suma del desglose ($${formatNumber(totalConIvaCalculado.value)}) excede el monto ($${formatNumber(form.monto_directo)}).`, 'Error en desglose de IVA');
            return;
        }

        if (form.fecha_poliza > fechaActual.value) {
            notify.error('La fecha de la poliza no puede ser futura.', 'Fecha invalida');
            return;
        }

        if (!form.id_persona) {
            notify.error('Debes seleccionar una persona para la poliza.', 'Persona requerida');
            return;
        }

        if (!form.es_por_pagar && form.tipo_poliza === 'EGRESO' && cuentaFondeadoraSeleccionada.value) {
            const montoTotal = ivasSeleccionados.value.length > 0 ? totalConIvaCalculado.value : form.monto_directo;
            if (montoTotal > saldoFondeadoraEfectivo.value) {
                notify.error(`El monto total ($${formatNumber(montoTotal)}) excede el saldo disponible ($${formatNumber(saldoFondeadoraEfectivo.value)}).`, 'Saldo insuficiente');
                return;
            }
        }

        processing.value = true;

        // CONSTRUIR FORM DATA CON AXIOS
        const formData = new FormData();
        
        // Agregar _method para Laravel
        formData.append('_method', 'PUT');

        // Agregar todos los campos del formulario
        const campos = {
            tipo_poliza: form.tipo_poliza,
            fecha_poliza: form.fecha_poliza,
            id_persona: form.id_persona ? parseInt(form.id_persona) : null,
            id_cuenta: form.id_cuenta ? parseInt(form.id_cuenta) : null,
            es_por_pagar: form.es_por_pagar ? 'true' : 'false',
            fecha_vencimiento: form.fecha_vencimiento || null,
            es_fiscal: form.es_fiscal ? 'true' : 'false',
            id_cuenta_fondeadora: form.id_cuenta_fondeadora ? parseInt(form.id_cuenta_fondeadora) : null,
            id_marcador: form.id_marcador ? parseInt(form.id_marcador) : null,
            total_factura: parseFloat(form.monto_directo) || 0,
            fecha_factura: form.fecha_factura || null,
            numero_factura: form.numero_factura || null,
            nota: form.nota || null,
            referencia: form.referencia || null,
            monto_directo: parseFloat(form.monto_directo) || 0,
            modo_iva: ivasSeleccionados.value.length > 0 ? 'CON_IVA' : 'SIN_IVA',
            eliminar_pdf: form.eliminar_pdf ? 'true' : 'false',
            eliminar_xml: form.eliminar_xml ? 'true' : 'false',
        };

        // Agregar campos al FormData
        Object.keys(campos).forEach(key => {
            const value = campos[key];
            if (value !== null && value !== undefined && value !== '') {
                formData.append(key, value);
            }
        });

        // Agregar IVAs
        const ivasArray = ivasSeleccionados.value.map(ivaId => ({
            id: parseInt(ivaId),
            monto: parseFloat(form.ivas[ivaId]?.monto) || 0
        }));

        ivasArray.forEach((iva, index) => {
            formData.append(`ivas[${index}][id]`, iva.id);
            formData.append(`ivas[${index}][monto]`, iva.monto);
        });

        // AGREGAR EL PDF - ESTO ES LO IMPORTANTE
        if (archivos.value.pdf) {
            formData.append('pdf_file', archivos.value.pdf);
        }


        // ENVIAR CON AXIOS
        axios.post(route('movimientos.update', props.movimiento.id), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            processing.value = false;
            
            notify.success('La póliza se ha actualizado correctamente.', 'Éxito');

            setTimeout(() => {
                const fp = form.fecha_poliza ? String(form.fecha_poliza).slice(0, 10) : null;
                const params = fp ? { fecha_desde: fp, fecha_hasta: fp } : { sin_fecha: 1 };
                router.visit(route('movimientos.index', params), { method: 'get', replace: true });
            }, 1500);
        })
        .catch(error => {
            processing.value = false;
            console.error(' Error completo:', error);
            
            if (error.response?.data?.errors) {
                const errors = error.response.data.errors;
                const firstError = Object.values(errors)[0];
                notify.error(Array.isArray(firstError) ? firstError[0] : firstError, 'Error de validación');
                Object.keys(errors).forEach(key => {
                    form.errors[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                });
            } else if (error.response?.data?.message) {
                notify.error(error.response.data.message, 'Error');
            } else {
                notify.error('Error al actualizar la póliza. Intenta nuevamente.', 'Error');
            }
        });

    } else {
        // ============================================
        // TRASPASO - MISMO PATRÓN
        // ============================================
        if (!formTraspaso.fecha_poliza) {
            notify.error('La fecha de la póliza es obligatoria.', 'Fecha requerida');
            return;
        }

        if (!formTraspaso.monto_directo || formTraspaso.monto_directo <= 0) {
            notify.error('Captura un monto mayor a $0.', 'Monto requerido');
            return;
        }

        if (ivasSeleccionadosTraspaso.value.length > 0 && totalConIvaTraspasoCalculado.value > formTraspaso.monto_directo) {
            notify.error(`La suma del desglose ($${formatNumber(totalConIvaTraspasoCalculado.value)}) excede el monto ($${formatNumber(formTraspaso.monto_directo)}).`, 'Error en desglose de IVA');
            return;
        }

        if (formTraspaso.fecha_poliza > fechaActual.value) {
            notify.error('La fecha de la poliza no puede ser futura.', 'Fecha invalida');
            return;
        }

        if (!formTraspaso.id_cuenta_origen) {
            notify.error('Debes seleccionar una cuenta de origen.', 'Cuenta Origen requerida');
            return;
        }

        if (!formTraspaso.id_cuenta_destino) {
            notify.error('Debes seleccionar una cuenta de destino.', 'Cuenta Destino requerida');
            return;
        }

        if (formTraspaso.id_cuenta_origen === formTraspaso.id_cuenta_destino) {
            notify.error('La cuenta de origen y destino no pueden ser la misma.', 'Cuentas invalidas');
            return;
        }

        const montoTotalTraspaso = ivasSeleccionadosTraspaso.value.length > 0 ? totalConIvaTraspasoCalculado.value : formTraspaso.monto_directo;
        if (montoTotalTraspaso > saldoOrigenTraspasoEfectivo.value) {
            notify.error(`El monto a transferir ($${formatNumber(montoTotalTraspaso)}) excede el saldo de la cuenta de origen ($${formatNumber(saldoOrigenTraspasoEfectivo.value)}).`, 'Saldo insuficiente en origen');
            return;
        }

        processing.value = true;

        // CONSTRUIR FORM DATA PARA TRASPASO
        const traspasoFormData = new FormData();
        traspasoFormData.append('_method', 'PUT');

        const camposTraspaso = {
            tipo_poliza: formTraspaso.tipo_poliza,
            fecha_poliza: formTraspaso.fecha_poliza,
            id_cuenta_origen: formTraspaso.id_cuenta_origen ? parseInt(formTraspaso.id_cuenta_origen) : null,
            id_cuenta_destino: formTraspaso.id_cuenta_destino ? parseInt(formTraspaso.id_cuenta_destino) : null,
            monto_directo: parseFloat(formTraspaso.monto_directo) || 0,
            es_fiscal: formTraspaso.es_fiscal ? 'true' : 'false',
            id_marcador: formTraspaso.id_marcador ? parseInt(formTraspaso.id_marcador) : null,
            fecha_factura: formTraspaso.fecha_factura || null,
            numero_factura: formTraspaso.numero_factura || null,
            nota: formTraspaso.nota || null,
            modo_iva: ivasSeleccionadosTraspaso.value.length > 0 ? 'CON_IVA' : 'SIN_IVA',
        };

        Object.keys(camposTraspaso).forEach(key => {
            const value = camposTraspaso[key];
            if (value !== null && value !== undefined && value !== '') {
                traspasoFormData.append(key, value);
            }
        });

        const ivasArrayTraspaso = ivasSeleccionadosTraspaso.value.map(ivaId => ({
            id: parseInt(ivaId),
            monto: parseFloat(formTraspaso.ivas[ivaId]?.monto) || 0
        }));

        ivasArrayTraspaso.forEach((iva, index) => {
            traspasoFormData.append(`ivas[${index}][id]`, iva.id);
            traspasoFormData.append(`ivas[${index}][monto]`, iva.monto);
        });

        // AGREGAR PDF PARA TRASPASO
        if (archivosTraspaso.value.pdf) {
            traspasoFormData.append('pdf_file', archivosTraspaso.value.pdf);
        }

        // ENVIAR CON AXIOS
        axios.post(route('movimientos.update', props.movimiento.id), traspasoFormData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(() => {
            processing.value = false;
            notify.success('El traspaso se ha actualizado correctamente.', 'Éxito');
            setTimeout(() => {
                const fp = formTraspaso.fecha_poliza ? String(formTraspaso.fecha_poliza).slice(0, 10) : null;
                const params = { vista: 'traspasos', ...(fp ? { fecha_desde: fp, fecha_hasta: fp } : { sin_fecha: 1 }) };
                router.visit(route('movimientos.index', params), { method: 'get', replace: true });
            }, 1500);
        })
        .catch(error => {
            processing.value = false;
            console.error(' Error completo:', error);
            
            if (error.response?.data?.errors) {
                const errors = error.response.data.errors;
                const firstError = Object.values(errors)[0];
                notify.error(Array.isArray(firstError) ? firstError[0] : firstError, 'Error de validación');
                Object.keys(errors).forEach(key => {
                    formTraspaso.errors[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                });
            } else if (error.response?.data?.message) {
                notify.error(error.response.data.message, 'Error');
            } else {
                notify.error('Error al actualizar el traspaso. Intenta nuevamente.', 'Error');
            }
        });
    }
};
// ============================================
// WATCHERS
// ============================================
watch(() => form.tipo_poliza, () => {
    form.id_cuenta = null;
});

// ============================================
// MOUNTED
// ============================================
onMounted(() => {
    
    const hoy = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' });
    
    if (props.movimiento?.ivas && Array.isArray(props.movimiento.ivas) && props.movimiento.ivas.length > 0) {
        if (tipoPolizaSeleccionado.value === 'TRASPASO') {
            props.movimiento.ivas.forEach(iva => {
                if (iva.id && iva.monto && iva.monto > 0) {
                    const ivaId = iva.id;
                    ivasSeleccionadosTraspaso.value.push(ivaId);
                    formTraspaso.ivas[ivaId] = { monto: iva.monto };
                }
            });
        } else {
            props.movimiento.ivas.forEach(iva => {
                if (iva.id && iva.monto && iva.monto > 0) {
                    const ivaId = iva.id;
                    ivasSeleccionados.value.push(ivaId);
                    form.ivas[ivaId] = { monto: iva.monto };
                }
            });
        }
    }

    if (props.movimiento?.es_fiscal) {
        form.es_fiscal = true;
        form.fecha_factura = props.movimiento.fecha_factura || null;
        form.numero_factura = props.movimiento.numero_factura || '';
    }

    if (form.id_cuenta_fondeadora) {
        cambiarCuentaFondeadora();
    }

    if (formTraspaso.id_cuenta_origen) {
        saldoCuentaOrigen.value = obtenerSaldoCuenta(formTraspaso.id_cuenta_origen);
    }
    if (formTraspaso.id_cuenta_destino) {
        saldoCuentaDestino.value = obtenerSaldoCuenta(formTraspaso.id_cuenta_destino);
    }

    if (props.tipos_iva && props.tipos_iva.length > 0) tiposIva.value = props.tipos_iva;
    if (props.cuentas_fondeadoras && props.cuentas_fondeadoras.length > 0) cuentasFondeadoras.value = props.cuentas_fondeadoras;
    if (props.cuentas_egreso && props.cuentas_egreso.length > 0) cuentasEgreso.value = props.cuentas_egreso;
    if (props.cuentas_ingreso && props.cuentas_ingreso.length > 0) cuentasIngreso.value = props.cuentas_ingreso;
    if (props.marcadores && props.marcadores.length > 0) marcadores.value = props.marcadores;
    if (props.personas && props.personas.length > 0) personas.value = props.personas;
});
</script>

<style scoped>
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
    gap: 12px;
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

.header-content { display: flex; flex-direction: column; }
.header-title { font-size: 1.2rem; font-weight: 700; color: #0f172a; margin: 0; line-height: 1.3; }
.header-subtitle { font-size: 0.8rem; color: #64748b; margin: 0; }

.header-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.status-badge {
    padding: 4px 14px;
    border-radius: 18px;
    font-size: 0.7rem;
    font-weight: 700;
    transition: all 0.3s ease;
}
.status-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.status-error { background: #fecaca; color: #991b1b; border: 1px solid #f87171; animation: shake 0.4s ease; }
.status-progress { background: #e0e7ff; color: #4338ca; border: 1px solid #818cf8; }
.status-capturado { background: #dbeafe; color: #1e40af; border: 1px solid #60a5fa; }
.status-revisado { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }
.status-autorizado { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
.status-abonado { background: #ede9fe; color: #5b21b6; border: 1px solid #1a3a5c; }
.status-liquidado { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
.status-pendiente { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }
.status-cerrado { background: #f3f4f6; color: #4b5563; border: 1px solid #9ca3af; }
.status-default { background: #f3f4f6; color: #4b5563; border: 1px solid #9ca3af; }

/* ========== FLASH ========== */
.flash-container { margin: 0 0 8px 0; padding: 0 1.5rem; }
.flash-message {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    border-radius: 8px;
    animation: slideDown 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 4px;
    font-size: 0.85rem;
}
.flash-success { background: #ecfdf5; border-left: 3px solid #10b981; }
.flash-error { background: #fef2f2; border-left: 3px solid #dc2626; }
.flash-info { background: #eff6ff; border-left: 3px solid #3b82f6; }
.flash-text { font-weight: 500; color: #1f2937; flex: 1; }
.flash-close {
    background: none;
    border: none;
    color: #6b7280;
    font-size: 1rem;
    cursor: pointer;
    padding: 0 4px;
    border-radius: 4px;
    transition: all 0.3s ease;
}
.flash-close:hover { background: rgba(0, 0, 0, 0.05); color: #dc2626; }

/* ========== PAGE ========== */
.page-content { padding: 0.5rem 0; }
.container-custom { max-width: 80rem; margin: 0 auto; padding: 0 1.5rem; }

/* ========== FORM CARD ========== */
.form-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    padding: 1.25rem 1.5rem;
}

/* ========== TIPO SELECTOR ========== */
.tipo-selector-premium {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    padding: 4px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.tipo-btn-premium {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    background: transparent;
    color: #64748b;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.tipo-btn-premium:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.tipo-btn-content {
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 2;
}

.tipo-btn-premium .tipo-btn-glow {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    opacity: 0;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.tipo-btn-premium.active {
    color: white;
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.25);
}
.tipo-btn-premium.active .tipo-btn-glow { opacity: 1; }

.tipo-btn-icon { display: flex; align-items: center; }
.tipo-btn-label { font-weight: 700; font-size: 0.85rem; }

/* ========== FORM ROWS ========== */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 14px;
    margin-bottom: 12px;
}
.form-row.observaciones-row {
    grid-template-columns: 1fr;
    margin-bottom: 8px;
}

/* ========== FORM GROUP ========== */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.form-group.full-width { grid-column: 1 / -1; }

.form-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 4px;
}

.required-star { color: #ef4444; font-weight: 700; }
.opcional-label { color: #6b7280; font-weight: 400; font-size: 0.7rem; }

/* ========== INPUTS ========== */
.input-wrapper { position: relative; }
.input-date-wrapper { position: relative; }
.input-date {
    padding-right: 40px !important;
    -webkit-appearance: none;
    appearance: none;
}
.input-date-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: #94a3b8;
    pointer-events: none;
    z-index: 2;
}

.form-input {
    width: 100%;
    padding: 8px 36px 8px 14px;
    font-size: 0.85rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    color: #1f2937;
    transition: all 0.3s ease;
    outline: none;
    height: 40px;
    appearance: none;
    -webkit-appearance: none;
}

.form-input:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92, 0.1);
}

.form-input.error {
    border-color: #ef4444;
    animation: shake 0.3s ease;
}
.form-input.error:focus { box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1); }

.form-input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: #f3f4f6;
}

.form-textarea {
    width: 100%;
    padding: 8px 12px;
    font-size: 0.85rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    color: #1f2937;
    transition: all 0.3s ease;
    outline: none;
    resize: vertical;
    min-height: 50px;
    max-height: 100px;
    font-family: inherit;
}

.form-textarea:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92, 0.1);
}
.form-textarea.error { border-color: #ef4444; }
.form-textarea:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: #f3f4f6;
}

.input-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: #94a3b8;
    pointer-events: none;
}

.input-with-prefix .form-input { padding-left: 32px; }
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

/* ========== ERROR TEXT ========== */
.error-text {
    font-size: 0.7rem;
    color: #dc2626;
    font-weight: 500;
    margin-top: 2px;
}
.hint-text {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 2px;
}

/* ========== IVA SELECTOR ========== */
.iva-selector {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.iva-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 14px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    background: white;
    color: #6b7280;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 34px;
}
.iva-btn:hover:not(.disabled):not(:disabled) {
    border-color: #1a3a5c;
    color: #1a3a5c;
}
.iva-btn.active {
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    border-color: #1a3a5c;
    color: white;
    box-shadow: 0 2px 10px rgba(26, 58, 92, 0.2);
}
.iva-btn.disabled { opacity: 0.4; cursor: not-allowed; }
.iva-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.iva-check { font-size: 0.6rem; font-weight: 700; }

/* ========== IVA DETAIL ========== */
.iva-detail {
    background: #f8fafc;
    border-radius: 8px;
    padding: 8px 12px;
    margin: 2px 0 8px 0;
    border: 1px solid #e5e7eb;
}
.iva-detail-grid {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.iva-detail-item {
    display: flex;
    align-items: center;
    gap: 6px;
    background: white;
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}
.iva-detail-badge {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.6rem;
    font-weight: 700;
    color: white;
}
.iva-detail-badge.badge-cero { background: #64748b; }
.iva-detail-badge.badge-dieciseis { background: #3b82f6; }

.iva-input-wrap { width: 100px; }
.iva-input {
    height: 32px !important;
    padding: 2px 8px 2px 24px !important;
    font-size: 0.8rem !important;
}
.iva-detail-result {
    font-size: 0.7rem;
    font-weight: 600;
    color: #059669;
    white-space: nowrap;
}
.iva-remove-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: none;
    background: #fef2f2;
    border-radius: 50%;
    color: #dc2626;
    cursor: pointer;
    font-size: 0.6rem;
    font-weight: 700;
    transition: all 0.3s ease;
}
.iva-remove-btn:hover:not(:disabled) {
    background: #fecaca;
    transform: rotate(90deg) scale(1.1);
}
.iva-remove-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.iva-total {
    display: flex;
    gap: 12px;
    font-size: 0.8rem;
    color: #1f2937;
    padding-left: 6px;
}
.iva-total strong { color: #059669; font-size: 0.9rem; }
.iva-breakdown { font-size: 0.7rem; color: #94a3b8; }

/* ========== VALIDACION DE IVA ========== */
.validacion-iva {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.8rem;
    margin-top: 4px;
    margin-bottom: 8px;
}
.validacion-iva.iva-valido {
    background: #ecfdf5;
    border: 1px solid #6ee7b7;
    color: #065f46;
}
.validacion-iva.iva-invalido {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #991b1b;
    animation: shake 0.3s ease;
}
.validacion-icon { font-size: 1rem; flex-shrink: 0; }
.validacion-texto { font-weight: 500; flex: 1; }

/* ========== OPTIONS ========== */
.options-grid {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}
.checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    color: #1e293b;
    position: relative;
}
.checkbox-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.checkbox-custom {
    width: 18px;
    height: 18px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    flex-shrink: 0;
    transition: all 0.3s ease;
    position: relative;
}
.checkbox-custom::after {
    content: '';
    position: absolute;
    inset: 3px;
    background: #1a3a5c;
    border-radius: 2px;
    transform: scale(0);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.checkbox-input:checked + .checkbox-custom {
    border-color: #1a3a5c;
}
.checkbox-input:checked + .checkbox-custom::after {
    transform: scale(1);
}
.checkbox-input:disabled + .checkbox-custom {
    opacity: 0.5;
    cursor: not-allowed;
}
.checkbox-text { font-weight: 500; }

/* ========== VENCIMIENTO ========== */
.vencimiento-wrapper {
    margin-top: 4px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.vencimiento-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748b;
}
.vencimiento-input {
    height: 34px !important;
    font-size: 0.8rem !important;
    padding: 4px 10px !important;
}

/* ========== MARCADOR ========== */
.marcador-wrapper {
    display: flex;
    gap: 6px;
    align-items: center;
}
.btn-add-marcador {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    background: white;
    color: #1a3a5c;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0;
}
.btn-add-marcador:hover:not(:disabled) {
    border-color: #1a3a5c;
    background: #f8f7ff;
    transform: scale(1.05);
}
.btn-add-marcador:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ========== SALDO ========== */
.saldo-disponible {
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.saldo-disponible strong { color: #059669; }
.nuevo-saldo {
    font-size: 0.7rem;
    color: #2563eb;
    font-weight: 600;
}

/* ========== FISCAL ========== */
.fiscal-section {
    grid-column: 1 / -1;
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px 14px;
    border: 1px solid #e5e7eb;
    margin: 2px 0 4px 0;
}
.fiscal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.fiscal-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
}
.fiscal-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 14px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.fiscal-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 12px;
}

/* ========== FILE UPLOAD ========== */
.file-upload {
    display: flex;
    align-items: center;
    gap: 6px;
}
.file-upload-area {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px;
    border: 2px dashed #d1d5db;
    border-radius: 6px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 36px;
    font-size: 0.8rem;
    color: #6b7280;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.file-upload-area:hover {
    border-color: #1a3a5c;
    background: #f8f7ff;
}
.file-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    color: #94a3b8;
}
.file-input-hidden {
    position: absolute;
    opacity: 0;
    width: 0.1px;
    height: 0.1px;
    pointer-events: none;
}
.file-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: #fef2f2;
    border: 1.5px solid #fca5a5;
    border-radius: 50%;
    color: #dc2626;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.7rem;
    transition: all 0.3s ease;
    flex-shrink: 0;
}
.file-remove:hover {
    background: #fecaca;
    transform: rotate(90deg) scale(1.1);
}

/* ========== VALIDACION DE SALDO ========== */
.validacion-saldo {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.8rem;
    margin-top: 4px;
}
.validacion-saldo.saldo-suficiente {
    background: #ecfdf5;
    border: 1px solid #6ee7b7;
    color: #065f46;
}
.validacion-saldo.saldo-insuficiente {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #991b1b;
    animation: shake 0.3s ease;
}
.validacion-icon { font-size: 1rem; flex-shrink: 0; }
.validacion-texto { font-weight: 500; flex: 1; }

/* ========== INFO BOX ========== */
.info-box {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 4px solid #1a3a5c;
    font-size: 0.8rem;
    color: #4b5563;
    margin-top: 8px;
    flex-wrap: wrap;
}
.text-danger { color: #ef4444; }
.info-sin-iva {
    background: #dbeafe;
    color: #1e40af;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.7rem;
}
.info-con-iva {
    background: #d1fae5;
    color: #065f46;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.7rem;
}
.info-id {
    background: #e0e7ff;
    color: #4338ca;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.7rem;
}

/* ========== FORM ACTIONS ========== */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 2px solid #f1f5f9;
}
.actions-left,
.actions-right {
    display: flex;
    gap: 10px;
    align-items: center;
}

.total-card {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 18px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}
.total-card.total-card-iva {
    background: #fef3c7;
    border-color: #fcd34d;
}
.total-label {
    font-size: 0.65rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.total-value {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}
.total-card-iva .total-value { color: #d97706; }

.btn {
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
    height: 40px;
}
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}
.btn-icon {
    width: 18px;
    height: 18px;
}
.btn-cancel {
    background: #f1f5f9;
    color: #64748b;
    border: 1.5px solid #e5e7eb;
}
.btn-cancel:hover:not(:disabled) {
    background: #fecaca;
    color: #dc2626;
    border-color: #fca5a5;
}
.btn-submit {
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    color: white;
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.25);
}
.btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(26, 58, 92, 0.35);
}
.spinner-border {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 0.2em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner 0.75s linear infinite;
}

/* ========== MODAL ========== */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease;
}
.modal-container {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 420px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 24px 48px rgba(0, 0, 0, 0.15);
    animation: slideUp 0.3s ease;
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
}
.modal-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.modal-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    background: #f3f4f6;
    border-radius: 50%;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
}
.modal-close:hover {
    background: #fecaca;
    color: #dc2626;
}
.modal-body { padding: 20px; }
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

/* ========== ANIMATIONS ========== */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(15px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes spinner {
    to { transform: rotate(360deg); }
}
.fade-slide {
    animation: fadeSlide 0.3s ease;
}
@keyframes fadeSlide {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ========== RESPONSIVE ========== */
@media (max-width: 992px) {
    .form-row { grid-template-columns: 1fr 1fr; }
    .fiscal-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 640px) {
    .form-row { grid-template-columns: 1fr; gap: 10px; margin-bottom: 10px; }
    .form-card { padding: 1rem; }
    .container-custom { padding: 0 0.75rem; }
    .header-wrapper {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    .header-right { width: 100%; justify-content: flex-start; }
    .tipo-selector-premium { flex-direction: column; }
    .form-actions {
        flex-direction: column-reverse;
        align-items: stretch;
        gap: 8px;
    }
    .actions-left,
    .actions-right {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }
    .btn { flex: 1; justify-content: center; padding: 6px 16px; height: 36px; font-size: 0.8rem; }
    .fiscal-grid { grid-template-columns: 1fr; gap: 8px; }
    .fiscal-section { padding: 10px; }
    .options-grid { flex-direction: column; align-items: flex-start; gap: 4px; }
    .iva-detail-item { flex-wrap: wrap; }
    .iva-input-wrap { width: 100%; }
    .modal-container { margin: 12px; }
    .total-card { padding: 4px 12px; }
    .total-value { font-size: 0.9rem; }
}
</style>