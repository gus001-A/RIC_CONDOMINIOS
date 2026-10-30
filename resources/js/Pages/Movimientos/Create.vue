<template>
    <AppLayout :title="tituloPagina">
        <!-- Los flash messages se muestran de forma global (AppLayout useFlashMessages) -->

        <template #header>
            <div class="header-wrapper">
                <div class="header-left">
                    <Link :href="route('movimientos.index')" class="btn-back">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18" />
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
                </div>
            </div>
        </template>

        <div class="page-content">
            <div class="container-custom">
                <div class="form-card">
                    <!-- Selector de Tipo de Poliza -->
                    <div class="tipo-selector-premium">
                        <button type="button" @click="seleccionarTipo('INGRESO_EGRESO')" class="tipo-btn-premium"
                            :class="{ active: tipoPolizaSeleccionado === 'INGRESO_EGRESO' }">
                            <div class="tipo-btn-content">
                                <span class="tipo-btn-icon">
                                    <svg class="icon-svg-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                    </svg>
                                </span>
                                <span class="tipo-btn-label">Ingreso / Egreso</span>
                            </div>
                            <div class="tipo-btn-glow"></div>
                        </button>
                        <button type="button" @click="seleccionarTipo('TRASPASO')" class="tipo-btn-premium"
                            :class="{ active: tipoPolizaSeleccionado === 'TRASPASO' }">
                            <div class="tipo-btn-content">
                                <span class="tipo-btn-icon">
                                    <svg class="icon-svg-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
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
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                        </svg>
                                        Tipo <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="form.tipo_poliza" class="form-input"
                                            :class="{ error: form.errors.tipo_poliza }" @change="onTipoPolizaChange">
                                            <option value="">Selecciona un tipo</option>
                                            <option value="INGRESO">Ingreso</option>
                                            <option value="EGRESO">Egreso</option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.tipo_poliza" class="error-text">{{ form.errors.tipo_poliza }}
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Persona <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_persona" @change="onPersonaChange" class="form-input"
                                            :class="{ error: form.errors.id_persona }">
                                            <option value="">Selecciona una persona</option>
                                            <option v-for="p in personas" :key="p.id_persona" :value="p.id_persona">
                                                {{ p.nombre_completo }}
                                            </option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.id_persona" class="error-text">{{ form.errors.id_persona }}
                                    </div>
                                    <div v-if="cargandoPoliza" class="hint-text">
                                        <svg class="hint-icon spin" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Cargando datos de la última póliza...
                                    </div>
                                    <div v-if="polizaCargada" class="hint-text success">
                                        <svg class="hint-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                        Datos cargados automáticamente
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        Cuenta <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_cuenta" class="form-input"
                                            :class="{ error: form.errors.id_cuenta }">
                                            <option value="">Selecciona una cuenta</option>
                                            <optgroup v-if="form.tipo_poliza === 'EGRESO'" label="Cuentas para Egresos">
                                                <option v-for="c in cuentasEgreso" :key="c.id_cuenta"
                                                    :value="c.id_cuenta">
                                                    {{ c.nombre_cuenta }}
                                                </option>
                                            </optgroup>
                                            <optgroup v-else-if="form.tipo_poliza === 'INGRESO'"
                                                label="Cuentas para Ingresos">
                                                <option v-for="c in cuentasIngreso" :key="c.id_cuenta"
                                                    :value="c.id_cuenta">
                                                    {{ c.nombre_cuenta }}
                                                </option>
                                            </optgroup>
                                            <optgroup v-else label="Selecciona un tipo de póliza primero">
                                                <option value="" disabled>Selecciona INGRESO o EGRESO arriba</option>
                                            </optgroup>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.id_cuenta" class="error-text">{{ form.errors.id_cuenta }}
                                    </div>
                                </div>
                            </div>

                            <!-- FILA 2: MONTO + IVA + FECHA -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Monto <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper input-with-prefix">
                                        <span class="input-prefix">$</span>
                                        <MoneyInput v-model="form.monto_directo" class="form-input"
                                            :class="{ error: form.errors.monto_directo }" placeholder="0.00" />
                                    </div>
                                    <div v-if="form.errors.monto_directo" class="error-text">{{
                                        form.errors.monto_directo }}
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                        </svg>
                                        IVA <span class="opcional-label">(Opcional)</span>
                                    </label>
                                    <div class="iva-selector">
                                        <button type="button" v-for="iva in tiposIva" :key="iva.id" class="iva-btn"
                                            :class="{
                                                active: ivasSeleccionados.includes(iva.id),
                                                disabled: ivasSeleccionados.length >= 2 && !ivasSeleccionados.includes(iva.id)
                                            }" @click="toggleIva(iva.id)">
                                            {{ iva.porcentaje }}%
                                            <span class="iva-check" v-if="ivasSeleccionados.includes(iva.id)"><i class="pi pi-check"></i></span>
                                        </button>
                                    </div>
                                    <div class="hint-text">Selecciona hasta 2 tipos de IVA (opcional)</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Fecha póliza (del gasto) <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper input-date-wrapper">
                                        <input type="date" v-model="form.fecha_poliza" class="form-input input-date"
                                            :class="{ error: form.errors.fecha_poliza }" :max="fechaActual">
                                        <svg class="input-icon input-date-icon" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.fecha_poliza" class="error-text">{{ form.errors.fecha_poliza
                                    }}</div>
                                    <div class="registro-auto" title="Fecha de registro: la asigna el sistema al guardar">
                                        Registro: <strong>{{ usuarioActual }}</strong> · {{ fechaHoraSistema }}
                                    </div>
                                </div>
                            </div>

                            <!-- DETALLE DE IVA -->
                            <div v-if="ivasSeleccionados.length > 0" class="iva-detail">
                                <div class="iva-detail-grid">
                                    <div v-for="ivaId in ivasSeleccionados" :key="ivaId" class="iva-detail-item">
                                        <span class="iva-detail-badge"
                                            :class="getIvaPorcentaje(ivaId) === 0 ? 'badge-cero' : 'badge-dieciseis'">
                                            {{ getIvaPorcentaje(ivaId) }}%
                                        </span>
                                        <div class="input-wrapper input-with-prefix iva-input-wrap">
                                            <span class="input-prefix">$</span>
                                            <MoneyInput v-model="form.ivas[ivaId].monto"
                                                class="form-input iva-input" placeholder="0.00" />
                                        </div>
                                        <span class="iva-detail-result">IVA: ${{
                                            formatNumberConComas(calcularIvaMonto(ivaId))
                                        }}</span>
                                        <button type="button" @click="quitarIva(ivaId)"
                                            class="iva-remove-btn"><i class="pi pi-times"></i></button>
                                    </div>
                                    <div class="iva-total">
                                        <span>Total: <strong>${{ formatNumberConComas(totalConIvaCalculado)
                                        }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- VALIDACION DE IVA -->
                            <div v-if="form.monto_directo > 0 && ivasSeleccionados.length > 0" class="validacion-iva"
                                :class="totalConIvaCalculado > form.monto_directo ? 'iva-invalido' : 'iva-valido'">
                                <span class="validacion-icon">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path v-if="totalConIvaCalculado >
                                            form.monto_directo" stroke-linecap="round"
                                            stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                <span class="validacion-texto">
                                    {{ totalConIvaCalculado > form.monto_directo
                                        ? `La suma del desglose ($${formatNumberConComas(totalConIvaCalculado)}) excede el
                                    monto
                                    ($${formatNumberConComas(form.monto_directo)})`
                                        : `Desglose correcto: $${formatNumberConComas(form.monto_directo -
                                            totalConIvaCalculado)}
                                    restante` }}
                                </span>
                            </div>

                            <!-- FILA 3: CUENTA FONDEADORA, OPCIONES, MARCADOR -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        Cuenta Fondeadora <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="form.id_cuenta_fondeadora" @change="cambiarCuentaFondeadora"
                                            class="form-input" :class="{ error: form.errors.id_cuenta_fondeadora }">
                                            <option value="">Selecciona una fondeadora</option>
                                            <option v-for="c in cuentasFondeadoras" :key="c.id_cuenta"
                                                :value="c.id_cuenta">
                                                {{ c.nombre_cuenta }}
                                            </option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div v-if="form.errors.id_cuenta_fondeadora" class="error-text">{{
                                        form.errors.id_cuenta_fondeadora }}</div>
                                    <div v-if="cuentaFondeadoraSeleccionada" class="saldo-disponible">
                                        <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Saldo disponible: <strong>${{
                                            formatNumberConComas(cuentaFondeadoraSeleccionada.saldo ||
                                                0) }}</strong>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Opciones
                                    </label>
                                    <div class="options-grid">
                                        <label class="checkbox">
                                            <input type="checkbox" v-model="form.es_por_pagar"
                                                @change="onEsPorPagarChange" class="checkbox-input">
                                            <span class="checkbox-custom"></span>
                                            <span class="checkbox-text">Por Pagar</span>
                                        </label>
                                        <label class="checkbox">
                                            <input type="checkbox" v-model="form.es_fiscal" @change="toggleFiscal"
                                                class="checkbox-input">
                                            <span class="checkbox-custom"></span>
                                            <span class="checkbox-text">Fiscal</span>
                                        </label>
                                    </div>
                                    <div v-if="form.es_por_pagar" class="vencimiento-wrapper">
                                        <label class="vencimiento-label">
                                            <svg class="inline-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            Fecha Vencimiento
                                        </label>
                                        <input type="date" v-model="form.fecha_vencimiento"
                                            class="form-input vencimiento-input input-date" :min="fechaActual"
                                            :class="{ error: form.errors.fecha_vencimiento }">
                                        <div v-if="form.errors.fecha_vencimiento" class="error-text">{{
                                            form.errors.fecha_vencimiento }}</div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                        </svg>
                                        Marcador
                                    </label>
                                    <div class="marcador-wrapper">
                                        <div class="input-wrapper" style="flex: 1;">
                                            <select v-model="form.id_marcador" class="form-input"
                                                :class="{ error: form.errors.id_marcador }">
                                                <option value="">Selecciona un marcador</option>
                                                <option v-for="m in marcadores" :key="m.id" :value="m.id">
                                                    {{ m.nombre_marcador }}
                                                </option>
                                            </select>
                                            <svg class="input-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                            </svg>
                                        </div>
                                        <button type="button" @click="marcadorGestorVisible = true" class="btn-add-marcador"
                                            title="Gestionar marcadores">
                                            <svg class="icon-svg-sm" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-if="form.errors.id_marcador" class="error-text">{{ form.errors.id_marcador }}
                                    </div>
                                </div>
                            </div>

                            <!-- SECCION FISCAL -->
                            <div v-if="form.es_fiscal" class="fiscal-section fade-slide">
                                <div class="fiscal-header">
                                    <span class="fiscal-title">
                                        <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                        </svg>
                                        Datos de Facturación
                                    </span>
                                    <span class="fiscal-badge">Fiscal</span>
                                </div>
                                <div class="fiscal-grid">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <svg class="inline-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            Fecha Factura
                                        </label>
                                        <div class="input-wrapper input-date-wrapper">
                                            <input type="date" v-model="form.fecha_factura"
                                                class="form-input input-date"
                                                :class="{ error: form.errors.fecha_factura }" :max="fechaActual">
                                            <svg class="input-icon input-date-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div v-if="form.errors.fecha_factura" class="error-text">{{
                                            form.errors.fecha_factura }}
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">
                                            <svg class="inline-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            Número Factura
                                        </label>
                                        <div class="input-wrapper">
                                            <input type="text" v-model="form.numero_factura" class="form-input"
                                                :class="{ error: form.errors.numero_factura }" placeholder="Ej: A-1258">
                                        </div>
                                        <div v-if="form.errors.numero_factura" class="error-text">{{
                                            form.errors.numero_factura
                                        }}</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">
                                            <svg class="inline-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            PDF
                                        </label>
                                        <div class="file-upload">
                                            <div class="file-upload-area" @click="$refs.pdfInput.click()">
                                                <svg class="file-icon" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                <span>{{ archivos.pdf ? archivos.pdf.name : 'Seleccionar PDF' }}</span>
                                            </div>
                                            <input type="file" ref="pdfInput" @change="handleFileUpload('pdf', $event)"
                                                accept=".pdf" class="file-input-hidden">
                                            <button v-if="archivos.pdf" type="button" @click="eliminarArchivo('pdf')"
                                                class="file-remove"><i class="pi pi-times"></i></button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">
                                            <svg class="inline-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            XML
                                        </label>
                                        <div class="file-upload">
                                            <div class="file-upload-area" @click="$refs.xmlInput.click()">
                                                <svg class="file-icon" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span>{{ archivos.xml ? archivos.xml.name : 'Seleccionar XML' }}</span>
                                            </div>
                                            <input type="file" ref="xmlInput" @change="handleFileUpload('xml', $event)"
                                                accept=".xml" class="file-input-hidden">
                                            <button v-if="archivos.xml" type="button" @click="eliminarArchivo('xml')"
                                                class="file-remove"><i class="pi pi-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OBSERVACIONES -->
                            <div class="form-row observaciones-row">
                                <div class="form-group full-width">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Observaciones
                                    </label>
                                    <div class="input-wrapper">
                                        <textarea v-model="form.nota" rows="2" class="form-textarea"
                                            :class="{ error: form.errors.nota }"
                                            placeholder="Agrega notas o comentarios..."></textarea>
                                    </div>
                                    <div v-if="form.errors.nota" class="error-text">{{ form.errors.nota }}</div>
                                </div>
                            </div>

                            <!-- VALIDACION DE SALDO -->
                            <!-- FIX: se agregó "&& !form.es_por_pagar" — cuando la póliza es
                                 EGRESO y "Es por pagar" está marcado, el saldo de la fondeadora NO
                                 se toca hasta que se registre un abono, así que no tiene sentido
                                 mostrarle al usuario una advertencia de saldo insuficiente (o
                                 "suficiente") que no aplica en este caso. -->
                            <div v-if="form.tipo_poliza === 'EGRESO' && !form.es_por_pagar && totalMontoTotal > 0 && cuentaFondeadoraSeleccionada"
                                class="validacion-saldo"
                                :class="totalMontoTotal > (cuentaFondeadoraSeleccionada.saldo || 0) ? 'saldo-insuficiente' : 'saldo-suficiente'">
                                <span class="validacion-icon">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path v-if="totalMontoTotal > (cuentaFondeadoraSeleccionada.saldo || 0)"
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                <span class="validacion-texto">
                                    {{ totalMontoTotal > (cuentaFondeadoraSeleccionada.saldo || 0)
                                        ? `El monto ($${formatNumberConComas(totalMontoTotal)}) excede el saldo disponible
                                    ($${formatNumberConComas(cuentaFondeadoraSeleccionada.saldo || 0)})`
                                        : `Saldo suficiente: $${formatNumberConComas((cuentaFondeadoraSeleccionada.saldo ||
                                            0) -
                                            totalMontoTotal)} restante` }}
                                </span>
                            </div>

                            <!-- Aviso alterno cuando es diferida: explica por qué no se valida saldo -->
                            <div v-if="form.tipo_poliza === 'EGRESO' && form.es_por_pagar && totalMontoTotal > 0"
                                class="validacion-saldo saldo-suficiente">
                                <span class="validacion-icon">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                <span class="validacion-texto">
                                    Póliza diferida (por pagar): el saldo de la cuenta fondeadora no se afecta hasta que
                                    registres un abono.
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
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                        </svg>
                                        Tipo <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="formTraspaso.tipo_poliza" class="form-input"
                                            :class="{ error: formTraspaso.errors.tipo_poliza }">
                                            <option value="TRASPASO">Traspaso</option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                    <div v-if="formTraspaso.errors.tipo_poliza" class="error-text">{{
                                        formTraspaso.errors.tipo_poliza }}</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Fecha póliza <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper input-date-wrapper">
                                        <input type="date" v-model="formTraspaso.fecha_poliza"
                                            class="form-input input-date"
                                            :class="{ error: formTraspaso.errors.fecha_poliza }" :max="fechaActual">
                                        <svg class="input-icon input-date-icon" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div v-if="formTraspaso.errors.fecha_poliza" class="error-text">{{
                                        formTraspaso.errors.fecha_poliza }}</div>
                                    <div class="registro-auto" title="Fecha de registro: la asigna el sistema al guardar">
                                        Registro: <strong>{{ usuarioActual }}</strong> · {{ fechaHoraSistema }}
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                        </svg>
                                        Marcador
                                    </label>
                                    <div class="marcador-wrapper">
                                        <div class="input-wrapper" style="flex: 1;">
                                            <select v-model="formTraspaso.id_marcador" class="form-input"
                                                :class="{ error: formTraspaso.errors.id_marcador }">
                                                <option value="">Selecciona un marcador</option>
                                                <option v-for="m in marcadores" :key="m.id" :value="m.id">
                                                    {{ m.nombre_marcador }}
                                                </option>
                                            </select>
                                            <svg class="input-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                            </svg>
                                        </div>
                                        <button type="button" @click="marcadorGestorVisible = true" class="btn-add-marcador"
                                            title="Gestionar marcadores">
                                            <svg class="icon-svg-sm" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-if="formTraspaso.errors.id_marcador" class="error-text">{{
                                        formTraspaso.errors.id_marcador }}</div>
                                </div>
                            </div>

                            <!-- FILA 2: CUENTA ORIGEN, CUENTA DESTINO, MONTO -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        Cuenta Origen <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="formTraspaso.id_cuenta_origen" @change="onCuentaOrigenChange"
                                            class="form-input" :class="{ error: formTraspaso.errors.id_cuenta_origen }">
                                            <option value="">Selecciona una cuenta</option>
                                            <option v-for="c in cuentasFondeadoras" :key="c.id_cuenta"
                                                :value="c.id_cuenta">
                                                {{ c.nombre_cuenta }}
                                            </option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div v-if="formTraspaso.errors.id_cuenta_origen" class="error-text">{{
                                        formTraspaso.errors.id_cuenta_origen }}</div>
                                    <div v-if="formTraspaso.id_cuenta_origen" class="saldo-disponible">
                                        <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Saldo: <strong>${{ formatNumberConComas(saldoCuentaOrigen) }}</strong>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        Cuenta Destino <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <select v-model="formTraspaso.id_cuenta_destino" @change="onCuentaDestinoChange"
                                            class="form-input"
                                            :class="{ error: formTraspaso.errors.id_cuenta_destino }">
                                            <option value="">Selecciona una cuenta</option>
                                            <option v-for="c in cuentasFondeadoras" :key="c.id_cuenta"
                                                :value="c.id_cuenta">
                                                {{ c.nombre_cuenta }}
                                            </option>
                                        </select>
                                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div v-if="formTraspaso.errors.id_cuenta_destino" class="error-text">{{
                                        formTraspaso.errors.id_cuenta_destino }}</div>
                                    <div v-if="formTraspaso.id_cuenta_destino" class="saldo-disponible">
                                        <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Saldo: <strong>${{ formatNumberConComas(saldoCuentaDestino) }}</strong>
                                        <span v-if="totalMontoTraspaso > 0" class="nuevo-saldo">
                                             Nuevo: ${{ formatNumberConComas(nuevoSaldoDestino) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Monto <span class="required-star">*</span>
                                    </label>
                                    <div class="input-wrapper input-with-prefix">
                                        <span class="input-prefix">$</span>
                                        <MoneyInput v-model="formTraspaso.monto_directo"
                                            class="form-input" :class="{ error: formTraspaso.errors.monto_directo }"
                                            placeholder="0.00" />
                                    </div>
                                    <div v-if="formTraspaso.errors.monto_directo" class="error-text">{{
                                        formTraspaso.errors.monto_directo }}</div>
                                </div>
                            </div>

                            <!-- FILA 3: IVA + OPCIONES (FISCAL) + OBSERVACIONES -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                        </svg>
                                        IVA <span class="opcional-label">(Opcional)</span>
                                    </label>
                                    <div class="iva-selector">
                                        <button type="button" v-for="iva in tiposIva" :key="iva.id" class="iva-btn"
                                            :class="{
                                                active: ivasSeleccionadosTraspaso.includes(iva.id),
                                                disabled: ivasSeleccionadosTraspaso.length >= 2 && !ivasSeleccionadosTraspaso.includes(iva.id)
                                            }" @click="toggleIvaTraspaso(iva.id)">
                                            {{ iva.porcentaje }}%
                                            <span class="iva-check"
                                                v-if="ivasSeleccionadosTraspaso.includes(iva.id)"><i class="pi pi-check"></i></span>
                                        </button>
                                    </div>
                                    <div class="hint-text">Selecciona hasta 2 tipos de IVA (opcional)</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Opciones
                                    </label>
                                    <div class="options-grid">
                                        <label class="checkbox">
                                            <input type="checkbox" v-model="formTraspaso.es_fiscal"
                                                @change="toggleFiscalTraspaso" class="checkbox-input">
                                            <span class="checkbox-custom"></span>
                                            <span class="checkbox-text">Fiscal</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <svg class="label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Observaciones
                                    </label>
                                    <div class="input-wrapper">
                                        <textarea v-model="formTraspaso.nota" rows="2" class="form-textarea"
                                            :class="{ error: formTraspaso.errors.nota }"
                                            placeholder="Agrega notas o comentarios..."></textarea>
                                    </div>
                                    <div v-if="formTraspaso.errors.nota" class="error-text">{{ formTraspaso.errors.nota
                                    }}</div>
                                </div>
                            </div>

                            <!-- DETALLE DE IVA TRASPASO -->
                            <div v-if="ivasSeleccionadosTraspaso.length > 0" class="iva-detail">
                                <div class="iva-detail-grid">
                                    <div v-for="ivaId in ivasSeleccionadosTraspaso" :key="ivaId"
                                        class="iva-detail-item">
                                        <span class="iva-detail-badge"
                                            :class="getIvaPorcentaje(ivaId) === 0 ? 'badge-cero' : 'badge-dieciseis'">
                                            {{ getIvaPorcentaje(ivaId) }}%
                                        </span>
                                        <div class="input-wrapper input-with-prefix iva-input-wrap">
                                            <span class="input-prefix">$</span>
                                            <MoneyInput v-model="formTraspaso.ivas[ivaId].monto"
                                                class="form-input iva-input" placeholder="0.00" />
                                        </div>
                                        <span class="iva-detail-result">IVA: ${{
                                            formatNumberConComas(calcularIvaMontoTraspaso(ivaId)) }}</span>
                                        <button type="button" @click="quitarIvaTraspaso(ivaId)"
                                            class="iva-remove-btn"><i class="pi pi-times"></i></button>
                                    </div>
                                    <div class="iva-total">
                                        <span>Total: <strong>${{ formatNumberConComas(totalConIvaTraspasoCalculado)
                                        }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- VALIDACION DE IVA TRASPASO -->
                            <div v-if="formTraspaso.monto_directo > 0 && ivasSeleccionadosTraspaso.length > 0"
                                class="validacion-iva"
                                :class="totalConIvaTraspasoCalculado > formTraspaso.monto_directo ? 'iva-invalido' : 'iva-valido'">
                                <span class="validacion-icon">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path v-if="totalConIvaTraspasoCalculado >formTraspaso.monto_directo"
                                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                <span class="validacion-texto">
                                    {{ totalConIvaTraspasoCalculado > formTraspaso.monto_directo
                                        ? `La suma del desglose ($${formatNumberConComas(totalConIvaTraspasoCalculado)})
                                    excede el
                                    monto ($${formatNumberConComas(formTraspaso.monto_directo)})`
                                        : `Desglose correcto: $${formatNumberConComas(formTraspaso.monto_directo -
                                            totalConIvaTraspasoCalculado)} restante` }}
                                </span>
                            </div>

                            <!-- SECCION FISCAL TRASPASO -->
                            <div v-if="formTraspaso.es_fiscal" class="fiscal-section fade-slide">
                                <div class="fiscal-header">
                                    <span class="fiscal-title">
                                        <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                        </svg>
                                        Datos de Facturación - Traspaso
                                    </span>
                                    <span class="fiscal-badge">Fiscal</span>
                                </div>
                                <div class="fiscal-grid">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <svg class="inline-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            Fecha Factura
                                        </label>
                                        <div class="input-wrapper input-date-wrapper">
                                            <input type="date" v-model="formTraspaso.fecha_factura"
                                                class="form-input input-date"
                                                :class="{ error: formTraspaso.errors.fecha_factura }"
                                                :max="fechaActual">
                                            <svg class="input-icon input-date-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div v-if="formTraspaso.errors.fecha_factura" class="error-text">{{
                                            formTraspaso.errors.fecha_factura }}</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">
                                            <svg class="inline-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            Número Factura
                                        </label>
                                        <div class="input-wrapper">
                                            <input type="text" v-model="formTraspaso.numero_factura" class="form-input"
                                                :class="{ error: formTraspaso.errors.numero_factura }"
                                                placeholder="Ej: A-1258">
                                        </div>
                                        <div v-if="formTraspaso.errors.numero_factura" class="error-text">{{
                                            formTraspaso.errors.numero_factura }}</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">
                                            <svg class="inline-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            PDF
                                        </label>
                                        <div class="file-upload">
                                            <div class="file-upload-area" @click="$refs.pdfInputTraspaso.click()">
                                                <svg class="file-icon" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                <span>{{ archivosTraspaso.pdf ? archivosTraspaso.pdf.name : 'Seleccionar PDF' }}</span>
                                            </div>
                                            <input type="file" ref="pdfInputTraspaso"
                                                @change="handleFileUploadTraspaso('pdf', $event)" accept=".pdf"
                                                class="file-input-hidden">
                                            <button v-if="archivosTraspaso.pdf" type="button"
                                                @click="eliminarArchivoTraspaso('pdf')" class="file-remove"><i class="pi pi-times"></i></button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">
                                            <svg class="inline-icon" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            XML
                                        </label>
                                        <div class="file-upload">
                                            <div class="file-upload-area" @click="$refs.xmlInputTraspaso.click()">
                                                <svg class="file-icon" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span>{{ archivosTraspaso.xml ? archivosTraspaso.xml.name : 'Seleccionar XML' }}</span>
                                            </div>
                                            <input type="file" ref="xmlInputTraspaso"
                                                @change="handleFileUploadTraspaso('xml', $event)" accept=".xml"
                                                class="file-input-hidden">
                                            <button v-if="archivosTraspaso.xml" type="button"
                                                @click="eliminarArchivoTraspaso('xml')" class="file-remove"><i class="pi pi-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- VALIDACION DE SALDO TRASPASO -->
                            <div v-if="formTraspaso.id_cuenta_origen && totalMontoTraspaso > 0" class="validacion-saldo"
                                :class="totalMontoTraspaso > saldoCuentaOrigen ? 'saldo-insuficiente' : 'saldo-suficiente'">
                                <span class="validacion-icon">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path v-if="totalMontoTraspaso >
                                            saldoCuentaOrigen" stroke-linecap="round"
                                            stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                <span class="validacion-texto">
                                    {{ totalMontoTraspaso > saldoCuentaOrigen
                                        ? `El monto ($${formatNumberConComas(totalMontoTraspaso)}) excede el saldo de origen
                                    ($${formatNumberConComas(saldoCuentaOrigen)})`
                                        : `Saldo suficiente en origen` }}
                                </span>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- BOTONES -->
                        <!-- ============================================ -->
                        <div class="info-box">
                            <span>Los campos con <strong class="text-danger">*</strong> son obligatorios.</span>
                            <span v-if="polizaCargada" class="info-auto-cargado">
                                <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                Datos de última póliza cargados
                            </span>
                            <span v-if="ivasSeleccionados.length === 0 && tipoPolizaSeleccionado === 'INGRESO_EGRESO'"
                                class="info-sin-iva">Sin IVA</span>
                            <span v-if="ivasSeleccionadosTraspaso.length === 0 && tipoPolizaSeleccionado === 'TRASPASO'"
                                class="info-sin-iva">Sin IVA</span>
                            <span
                                v-else-if="tipoPolizaSeleccionado === 'INGRESO_EGRESO' && ivasSeleccionados.length > 0"
                                class="info-con-iva">Con IVA</span>
                            <span
                                v-else-if="tipoPolizaSeleccionado === 'TRASPASO' && ivasSeleccionadosTraspaso.length > 0"
                                class="info-con-iva">Con IVA</span>
                            <span v-if="formTraspaso.es_fiscal && tipoPolizaSeleccionado === 'TRASPASO'"
                                class="info-fiscal">
                                <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                </svg>
                                Fiscal
                            </span>
                        </div>

                        <div class="form-actions">
                            <div class="actions-left">
                                <div class="total-card">
                                    <span class="total-label">Total</span>
                                    <span class="total-value">${{ formatNumberConComas(totalMontoMostrar) }}</span>
                                </div>
                                <div class="total-card total-card-iva">
                                    <span class="total-label">IVA</span>
                                    <span class="total-value">${{ formatNumberConComas(totalIvaMostrar) }}</span>
                                </div>
                            </div>
                            <div class="actions-right">
                                <Link :href="route('movimientos.index')" class="btn btn-cancel">
                                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Cancelar
                                </Link>
                                <button type="submit" :disabled="processing || !isFormValid" class="btn btn-submit">
                                    <span v-if="processing" class="spinner-border"></span>
                                    <svg v-else class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                    </svg>
                                    {{ processing ? 'Guardando...' : 'Guardar Póliza' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Gestor de marcadores (crear / editar / eliminar) -->
        <MarcadoresModal v-model="marcadorGestorVisible" :marcadores="marcadores" @changed="(l) => marcadores = l" />

        <!-- Modal Marcador -->
        <div v-if="modalMarcadorVisible" class="modal-overlay" @click.self="cerrarModalMarcador">
            <div class="modal-container">
                <div class="modal-header">
                    <h3 class="modal-title">
                        <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        Nuevo Marcador
                    </h3>
                    <button type="button" @click="cerrarModalMarcador" class="modal-close">
                        <svg class="icon-svg-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="guardarMarcador">
                        <div class="form-group">
                            <label class="form-label">
                                <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                                Nombre <span class="required-star">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" v-model="nuevoMarcador.nombre" class="form-input"
                                    placeholder="Ej: Urgente, Importante">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 16px;">
                            <label class="form-label">
                                <svg class="inline-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Descripción
                            </label>
                            <div class="input-wrapper">
                                <textarea v-model="nuevoMarcador.descripcion" class="form-textarea" rows="2"
                                    placeholder="Breve descripción..."></textarea>
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
import MarcadoresModal from '@/Components/MarcadoresModal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useNotify } from '@/composables/useNotify';

const notify = useNotify();

// ============================================
// PROPS
// ============================================
const props = defineProps({
    empresa_id: { type: Number, default: null },
    cuentas_fondeadoras: { type: Array, default: () => [] },
    cuentas_egreso: { type: Array, default: () => [] },
    cuentas_ingreso: { type: Array, default: () => [] },
    tipos_iva: { type: Array, default: () => [] },
    marcadores: { type: Array, default: () => [] },
    personas: { type: Array, default: () => [] }
});

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

// Fecha de REGISTRO: la asigna el sistema al guardar (created_at). Aquí sólo
// se muestra quién registra y la fecha/hora actual (reloj en vivo).
const page = usePage();
const usuarioActual = computed(() => {
    const u = page.props.auth?.user;
    return u?.nombre_usuario || u?.nombre_completo || '—';
});
const ahora = ref(new Date());
let relojRegistro = null;
const fechaHoraSistema = computed(() => ahora.value.toLocaleString('es-MX', {
    timeZone: 'America/Mexico_City',
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hour12: true
}));
onMounted(() => { relojRegistro = setInterval(() => { ahora.value = new Date(); }, 1000); });
onBeforeUnmount(() => clearInterval(relojRegistro));
const modalMarcadorVisible = ref(false);
const guardandoMarcador = ref(false);
const nuevoMarcador = ref({ nombre: '', descripcion: '' });
const marcadorGestorVisible = ref(false);
const processing = ref(false);
const archivos = ref({ pdf: null, xml: null });
const archivosTraspaso = ref({ pdf: null, xml: null });
const cargandoPoliza = ref(false);
const polizaCargada = ref(false);

// SALDOS DE TRASPASO
const saldoCuentaOrigen = ref(0);
const saldoCuentaDestino = ref(0);

// DATOS REACTIVOS
const tiposIva = ref(props.tipos_iva || []);
const cuentasFondeadoras = ref(props.cuentas_fondeadoras || []);
const cuentasEgreso = ref(props.cuentas_egreso || []);
const cuentasIngreso = ref(props.cuentas_ingreso || []);
const marcadores = ref(props.marcadores || []);
const tipoPolizaSeleccionado = ref('INGRESO_EGRESO');

// IVAs seleccionados
const ivasSeleccionados = ref([]);
const ivasSeleccionadosTraspaso = ref([]);

// ============================================
// FUNCIÓN PARA FORMATEAR CON COMAS - NUEVA
// ============================================
const formatNumberConComas = (value) => {
    if (value === null || value === undefined || isNaN(value)) return '0.00';
    const num = Number(value);
    return num.toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

// Mantener la función original para compatibilidad
const formatNumber = formatNumberConComas;

// ============================================
// COMPUTED - TOTALES PARA MOSTRAR
// ============================================

const totalMontoTotal = computed(() => {
    if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
        if (ivasSeleccionados.value.length > 0) {
            return totalConIvaCalculado.value;
        }
        return form.monto_directo || 0;
    }
    return 0;
});

const totalMontoTraspaso = computed(() => {
    if (tipoPolizaSeleccionado.value === 'TRASPASO') {
        if (ivasSeleccionadosTraspaso.value.length > 0) {
            return totalConIvaTraspasoCalculado.value;
        }
        return formTraspaso.monto_directo || 0;
    }
    return 0;
});

const totalMontoMostrar = computed(() => {
    if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
        if (ivasSeleccionados.value.length > 0) {
            return totalConIvaCalculado.value;
        }
        return form.monto_directo || 0;
    } else {
        if (ivasSeleccionadosTraspaso.value.length > 0) {
            return totalConIvaTraspasoCalculado.value;
        }
        return formTraspaso.monto_directo || 0;
    }
});

const totalIvaMostrar = computed(() => {
    if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
        if (ivasSeleccionados.value.length > 0) {
            return totalIvaCalculado.value;
        }
        return 0;
    } else {
        if (ivasSeleccionadosTraspaso.value.length > 0) {
            return totalIvaTraspasoCalculado.value;
        }
        return 0;
    }
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
        if (!form.id_cuenta_fondeadora) return false;

        if (ivasSeleccionados.value.length > 0 && totalConIvaCalculado.value > form.monto_directo) {
            return false;
        }

        // FIX: se agregó "&& !form.es_por_pagar" — antes esto bloqueaba
        // el formulario (isFormValid = false) para CUALQUIER EGRESO con
        // saldo insuficiente, incluso cuando "Es por pagar" estaba marcado.
        // Una póliza diferida no necesita saldo disponible en la fondeadora
        // porque no se le resta nada hasta que se registre un abono.
        if (form.tipo_poliza === 'EGRESO' && !form.es_por_pagar && cuentaFondeadoraSeleccionada.value) {
            const montoTotal = ivasSeleccionados.value.length > 0 ? totalConIvaCalculado.value : form.monto_directo;
            if (montoTotal > (cuentaFondeadoraSeleccionada.value.saldo || 0)) return false;
        }
        return true;
    } else {
        if (!formTraspaso.fecha_poliza) return false;
        if (!formTraspaso.id_cuenta_origen) return false;
        if (!formTraspaso.id_cuenta_destino) return false;
        if (formTraspaso.id_cuenta_origen === formTraspaso.id_cuenta_destino) return false;
        if (!formTraspaso.monto_directo || formTraspaso.monto_directo <= 0) return false;

        if (ivasSeleccionadosTraspaso.value.length > 0 && totalConIvaTraspasoCalculado.value > formTraspaso.monto_directo) {
            return false;
        }

        const montoTotal = ivasSeleccionadosTraspaso.value.length > 0 ? totalConIvaTraspasoCalculado.value : formTraspaso.monto_directo;
        if (montoTotal > saldoCuentaOrigen.value) return false;
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
        return ['fecha_poliza', 'id_persona', 'tipo_poliza', 'id_cuenta', 'monto_directo', 'id_cuenta_fondeadora'];
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
        ? 'Nueva Póliza de Ingreso/Egreso'
        : 'Nueva Póliza de Traspaso';
});

const subtituloPagina = computed(() => {
    return tipoPolizaSeleccionado.value === 'INGRESO_EGRESO'
        ? 'Registra una póliza de ingreso o egreso'
        : 'Registra un traspaso entre cuentas fondeadoras';
});

// ============================================
// METODOS DE UTILIDAD
// ============================================

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
            notify.warn('Solo puedes seleccionar hasta 2 tipos de IVA', 'Límite alcanzado');
            return;
        }
        ivasSeleccionados.value.push(ivaId);
        if (!form.ivas[ivaId]) {
            form.ivas[ivaId] = { monto: null };
        }
    }
};

const quitarIva = (ivaId) => {
    const index = ivasSeleccionados.value.indexOf(ivaId);
    if (index > -1) {
        ivasSeleccionados.value.splice(index, 1);
        delete form.ivas[ivaId];
    }
};

const toggleIvaTraspaso = (ivaId) => {
    const index = ivasSeleccionadosTraspaso.value.indexOf(ivaId);
    if (index > -1) {
        ivasSeleccionadosTraspaso.value.splice(index, 1);
        delete formTraspaso.ivas[ivaId];
    } else {
        if (ivasSeleccionadosTraspaso.value.length >= 2) {
            notify.warn('Solo puedes seleccionar hasta 2 tipos de IVA', 'Límite alcanzado');
            return;
        }
        ivasSeleccionadosTraspaso.value.push(ivaId);
        if (!formTraspaso.ivas[ivaId]) {
            formTraspaso.ivas[ivaId] = { monto: null };
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

// ============================================
// METODOS GENERALES
// ============================================

const seleccionarTipo = (tipo) => {
    tipoPolizaSeleccionado.value = tipo;
    form.clearErrors();
    formTraspaso.clearErrors();
    saldoCuentaOrigen.value = 0;
    saldoCuentaDestino.value = 0;
    form.id_cuenta = null;

    // Limpiar campos de traspaso
    formTraspaso.id_cuenta_origen = null;
    formTraspaso.id_cuenta_destino = null;
    formTraspaso.monto_directo = null;
    ivasSeleccionadosTraspaso.value = [];
    formTraspaso.ivas = {};
};

const onTipoPolizaChange = () => {
    form.id_cuenta = null;
    form.clearErrors('id_cuenta');
};

const buscarPersonas = async (search) => {
    if (!search || search.length < 2) {
        if (personas.value.length === 0) {
            try {
                const res = await axios.get(route('movimientos.buscar.personas'), { params: { q: '' } });
                personas.value = res.data;
            } catch (error) { console.error('Error al cargar personas:', error); }
        }
        return;
    }
    try {
        const res = await axios.get(route('movimientos.buscar.personas'), { params: { q: search } });
        personas.value = res.data;
    } catch (error) { console.error('Error al buscar personas:', error); }
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

const obtenerUltimaPoliza = async (idPersona) => {
    if (!idPersona) {
        polizaCargada.value = false;
        return;
    }

    cargandoPoliza.value = true;
    polizaCargada.value = false;

    try {
        const response = await axios.get(route('movimientos.ultima.poliza'), {
            params: { id: idPersona }
        });
        const data = response.data;

        if (data && data.existe) {
            if (data.id_cuenta) {
                form.id_cuenta = data.id_cuenta;
            }

            form.es_por_pagar = data.es_por_pagar || false;
            form.fecha_vencimiento = data.fecha_vencimiento || null;
            form.es_fiscal = data.es_fiscal || false;

            if (data.id_marcador) {
                form.id_marcador = data.id_marcador;
            }

            if (data.es_fiscal) {
                form.fecha_factura = data.fecha_factura || null;
                form.numero_factura = data.numero_factura || '';
            }

            form.nota = data.nota || '';

            if (data.cuenta_fondeadora_valida && data.id_cuenta_fondeadora) {
                form.id_cuenta_fondeadora = data.id_cuenta_fondeadora;
                await cambiarCuentaFondeadora();
            } else {
                form.id_cuenta_fondeadora = null;
                cuentaFondeadoraSeleccionada.value = null;
            }

            polizaCargada.value = true;
        } else {
            polizaCargada.value = false;
        }
    } catch (error) {
        console.error('Error al obtener última póliza:', error);
        polizaCargada.value = false;
    } finally {
        cargandoPoliza.value = false;
    }
};

const onPersonaChange = async () => {
    polizaCargada.value = false;
    await obtenerUltimaPoliza(form.id_persona);
};

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
    if (!form.id_cuenta_fondeadora && cuentasFondeadoras.value.length > 0) {
        form.id_cuenta_fondeadora = cuentasFondeadoras.value[0].id_cuenta;
        cambiarCuentaFondeadora();
    }
};

// ============================================
// MANEJO DE ARCHIVOS
// ============================================

const handleFileUpload = (tipo, event) => {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            notify.error(`El archivo ${file.name} excede el límite de 5MB.`, 'Archivo demasiado grande');
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
};

const handleFileUploadTraspaso = (tipo, event) => {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            notify.error(`El archivo ${file.name} excede el límite de 5MB.`, 'Archivo demasiado grande');
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
    tipo_poliza: 'INGRESO',
    fecha_poliza: null,
    id_persona: null,
    id_cuenta: null,
    es_por_pagar: false,
    fecha_vencimiento: null,
    es_fiscal: false,
    id_cuenta_fondeadora: null,
    id_marcador: null,
    total_factura: 0,
    fecha_factura: null,
    numero_factura: null,
    nota: null,
    referencia: null,
    monto_directo: null,
    ivas: {}
});

const formTraspaso = useForm({
    tipo_poliza: 'TRASPASO',
    fecha_poliza: null,
    id_cuenta_origen: null,
    id_cuenta_destino: null,
    monto_directo: null,
    es_fiscal: false,
    id_marcador: null,
    fecha_factura: null,
    numero_factura: null,
    nota: null,
    ivas: {}
});

// ============================================
// SUBMIT - INGRESO/EGRESO
// ============================================

const submitIngresoEgreso = () => {
    if (!form.monto_directo || form.monto_directo <= 0) {
        notify.error('Debes ingresar un monto válido mayor a 0.', 'Monto requerido');
        return;
    }

    if (ivasSeleccionados.value.length > 0 && totalConIvaCalculado.value > form.monto_directo) {
        notify.error(`La suma del desglose ($${formatNumberConComas(totalConIvaCalculado.value)}) excede el monto ($${formatNumberConComas(form.monto_directo)}).`, 'Error en desglose de IVA');
        return;
    }

    if (form.fecha_poliza && form.fecha_poliza > fechaActual.value) {
        notify.error('La fecha de la póliza no puede ser futura.', 'Fecha inválida');
        return;
    }

    if (!form.id_persona) {
        notify.error('Debes seleccionar una persona para la póliza.', 'Persona requerida');
        return;
    }

    if (!form.id_cuenta_fondeadora) {
        notify.error('Debes seleccionar una cuenta fondeadora.', 'Cuenta fondeadora requerida');
        return;
    }

    // FIX: se agregó "&& !form.es_por_pagar" — antes este modal de
    // "Saldo insuficiente" aparecía y bloqueaba el envío incluso cuando la
    // póliza era diferida ("Es por pagar" marcado), caso en el que el
    // saldo de la fondeadora ni siquiera se toca al guardar. El backend
    // (MovimientoController@store) ya valida esto correctamente con la
    // misma condición — este cambio solo alinea al frontend con esa regla.
    if (form.tipo_poliza === 'EGRESO' && !form.es_por_pagar && cuentaFondeadoraSeleccionada.value) {
        const montoTotal = ivasSeleccionados.value.length > 0 ? totalConIvaCalculado.value : form.monto_directo;
        if (montoTotal > (cuentaFondeadoraSeleccionada.value.saldo || 0)) {
            notify.error(`El monto total ($${formatNumberConComas(montoTotal)}) excede el saldo disponible ($${formatNumberConComas(cuentaFondeadoraSeleccionada.value.saldo || 0)}).`, 'Saldo insuficiente');
            return;
        }
    }

    processing.value = true;

    const modoIva = ivasSeleccionados.value.length > 0 ? 'CON_IVA' : 'SIN_IVA';
    const totalFactura = ivasSeleccionados.value.length > 0 ? totalBaseCalculado.value : form.monto_directo;

    const ivasArray = ivasSeleccionados.value.map(ivaId => ({
        id: ivaId,
        monto: form.ivas[ivaId]?.monto || 0
    }));

    const formData = new FormData();
    const formDataObj = form.data();
    Object.keys(formDataObj).forEach(key => {
        if (key === 'ivas') return;
        let value = formDataObj[key];
        if (typeof value === 'boolean') value = value ? 'true' : 'false';
        if (value === null || value === undefined) value = '';
        if (value !== '' && value !== null && value !== undefined) {
            formData.append(key, value);
        }
    });

    formData.append('modo_iva', modoIva);
    formData.append('total_factura', totalFactura || 0);

    ivasArray.forEach((iva, index) => {
        formData.append(`ivas[${index}][id]`, iva.id);
        formData.append(`ivas[${index}][monto]`, iva.monto);
    });

    if (archivos.value.pdf) formData.append('pdf_file', archivos.value.pdf);
    if (archivos.value.xml) formData.append('xml_file', archivos.value.xml);

    formData.append('total_iva', totalIvaCalculado.value);
    formData.append('total_con_iva', totalConIvaCalculado.value);

    axios.post(route('movimientos.store'), formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
        .then(() => {
            processing.value = false;
            notify.success('La póliza se ha registrado correctamente.', 'Éxito');
            setTimeout(() => {
                // Ir a la lista filtrada por la fecha de la póliza recién creada
                // para que SIEMPRE se vea (aunque no sea de hoy).
                const fp = form.fecha_poliza ? String(form.fecha_poliza).slice(0, 10) : null;
                const params = fp ? { fecha_desde: fp, fecha_hasta: fp } : { sin_fecha: 1 };
                router.visit(route('movimientos.index', params), { method: 'get', replace: true });
            }, 1500);
        })
        .catch(error => {
            processing.value = false;
            console.error('Error completo:', error);

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
                notify.error('Error al registrar la póliza. Intenta nuevamente.', 'Error');
            }
        });
};

// ============================================
// SUBMIT - TRASPASO
// ============================================

const submitTraspaso = () => {
    if (!formTraspaso.monto_directo || formTraspaso.monto_directo <= 0) {
        notify.error('Debes ingresar un monto válido mayor a 0.', 'Monto requerido');
        return;
    }

    if (ivasSeleccionadosTraspaso.value.length > 0 && totalConIvaTraspasoCalculado.value > formTraspaso.monto_directo) {
        notify.error(`La suma del desglose ($${formatNumberConComas(totalConIvaTraspasoCalculado.value)}) excede el monto ($${formatNumberConComas(formTraspaso.monto_directo)}).`, 'Error en desglose de IVA');
        return;
    }

    if (formTraspaso.fecha_poliza && formTraspaso.fecha_poliza > fechaActual.value) {
        notify.error('La fecha de la póliza no puede ser futura.', 'Fecha inválida');
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
        notify.error('La cuenta de origen y destino no pueden ser la misma.', 'Cuentas inválidas');
        return;
    }

    const montoTotal = ivasSeleccionadosTraspaso.value.length > 0 ? totalConIvaTraspasoCalculado.value : formTraspaso.monto_directo;
    if (montoTotal > saldoCuentaOrigen.value) {
        notify.error(`El monto a transferir ($${formatNumberConComas(montoTotal)}) excede el saldo de la cuenta de origen ($${formatNumberConComas(saldoCuentaOrigen.value)}).`, 'Saldo insuficiente en origen');
        return;
    }

    processing.value = true;

    const modoIvaTraspaso = ivasSeleccionadosTraspaso.value.length > 0 ? 'CON_IVA' : 'SIN_IVA';
    const totalFacturaTraspaso = ivasSeleccionadosTraspaso.value.length > 0 ? totalBaseTraspasoCalculado.value : formTraspaso.monto_directo;

    const ivasArray = ivasSeleccionadosTraspaso.value.map(ivaId => ({
        id: ivaId,
        monto: formTraspaso.ivas[ivaId]?.monto || 0
    }));

    const traspasoFormData = new FormData();
    const traspasoDataObj = formTraspaso.data();
    Object.keys(traspasoDataObj).forEach(key => {
        if (key === 'ivas') return;
        let value = traspasoDataObj[key];
        if (typeof value === 'boolean') value = value ? 'true' : 'false';
        if (value === null || value === undefined) value = '';
        if (value !== '' && value !== null && value !== undefined) {
            traspasoFormData.append(key, value);
        }
    });

    traspasoFormData.append('modo_iva', modoIvaTraspaso);
    traspasoFormData.append('total_factura', totalFacturaTraspaso || 0);

    ivasArray.forEach((iva, index) => {
        traspasoFormData.append(`ivas[${index}][id]`, iva.id);
        traspasoFormData.append(`ivas[${index}][monto]`, iva.monto);
    });

    if (archivosTraspaso.value.pdf) traspasoFormData.append('pdf_file', archivosTraspaso.value.pdf);
    if (archivosTraspaso.value.xml) traspasoFormData.append('xml_file', archivosTraspaso.value.xml);

    traspasoFormData.append('total_iva', totalIvaTraspasoCalculado.value);
    traspasoFormData.append('total_con_iva', totalConIvaTraspasoCalculado.value);

    axios.post(route('movimientos.traspaso.store'), traspasoFormData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
        .then(() => {
            processing.value = false;
            notify.success('El traspaso se ha registrado correctamente.', 'Éxito');
            setTimeout(() => {
                const fp = formTraspaso.fecha_poliza ? String(formTraspaso.fecha_poliza).slice(0, 10) : null;
                const params = { vista: 'traspasos', ...(fp ? { fecha_desde: fp, fecha_hasta: fp } : { sin_fecha: 1 }) };
                router.visit(route('movimientos.index', params), { method: 'get', replace: true });
            }, 1500);
        })
        .catch(error => {
            processing.value = false;
            console.error('Error completo:', error);

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
                notify.error('Error al registrar el traspaso. Intenta nuevamente.', 'Error');
            }
        });
};

// ============================================
// SUBMIT - PRINCIPAL
// ============================================

const submit = () => {
    if (tipoPolizaSeleccionado.value === 'INGRESO_EGRESO') {
        submitIngresoEgreso();
    } else {
        submitTraspaso();
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
    form.fecha_poliza = hoy;
    formTraspaso.fecha_poliza = hoy;

    form.monto_directo = null;
    formTraspaso.monto_directo = null;


    if (props.tipos_iva && props.tipos_iva.length > 0) {
        tiposIva.value = props.tipos_iva;
    }

    if (props.cuentas_fondeadoras && props.cuentas_fondeadoras.length > 0) {
        cuentasFondeadoras.value = props.cuentas_fondeadoras;
        if (!form.id_cuenta_fondeadora) {
            form.id_cuenta_fondeadora = props.cuentas_fondeadoras[0].id_cuenta;
            const primeraCuenta = props.cuentas_fondeadoras[0];
            cuentaFondeadoraSeleccionada.value = {
                id_cuenta: primeraCuenta.id_cuenta,
                nombre_cuenta: primeraCuenta.nombre_cuenta,
                saldo: primeraCuenta.saldo || 0
            };
        }
    } else {
        console.warn(' No se recibieron cuentas fondeadoras');
    }

    if (props.cuentas_egreso && props.cuentas_egreso.length > 0) {
        cuentasEgreso.value = props.cuentas_egreso;
    }

    if (props.cuentas_ingreso && props.cuentas_ingreso.length > 0) {
        cuentasIngreso.value = props.cuentas_ingreso;
    }

    if (props.marcadores && props.marcadores.length > 0) {
        marcadores.value = props.marcadores;
    }

    buscarPersonas('');
});
</script>


<style scoped>
/* ========== ESTILOS MEJORADOS ========== */

/* --- HEADER --- */
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
    font-size: 0.8rem;
    color: #64748b;
    margin: 0;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-badge {
    padding: 4px 14px;
    border-radius: 18px;
    font-size: 0.7rem;
    font-weight: 700;
    transition: all 0.3s ease;
}

.status-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.status-error {
    background: #fecaca;
    color: #991b1b;
    border: 1px solid #f87171;
    animation: shake 0.4s ease;
}

.status-progress {
    background: #e0e7ff;
    color: #4338ca;
    border: 1px solid #818cf8;
}

/* --- FLASH --- */
.flash-container {
    margin: 0 0 8px 0;
    padding: 0 1.5rem;
}

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

.flash-success {
    background: #ecfdf5;
    border-left: 3px solid #10b981;
}

.flash-error {
    background: #fef2f2;
    border-left: 3px solid #dc2626;
}

.flash-info {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
}

.flash-text {
    font-weight: 500;
    color: #1f2937;
    flex: 1;
}

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

.flash-close:hover {
    background: rgba(0, 0, 0, 0.05);
    color: #dc2626;
}

/* --- PAGE --- */
.page-content {
    padding: 0.5rem 0;
}

.container-custom {
    max-width: 80rem;
    margin: 0 auto;
    padding: 0 1.5rem;
}

/* --- FORM CARD --- */
.form-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    padding: 1.25rem 1.5rem;
}

/* --- TIPO SELECTOR --- */
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

.tipo-btn-premium.active .tipo-btn-glow {
    opacity: 1;
}

.tipo-btn-icon {
    display: flex;
    align-items: center;
}

.tipo-btn-label {
    font-weight: 700;
    font-size: 0.85rem;
}

/* --- FORM ROWS --- */
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

/* --- FORM GROUP --- */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    font-size: 0.8rem;
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

.opcional-label {
    color: #6b7280;
    font-weight: 400;
    font-size: 0.7rem;
}

/* --- INPUTS --- */
.input-wrapper {
    position: relative;
}

.input-date-wrapper {
    position: relative;
}

.input-date {
    padding-right: 40px !important;
    -webkit-appearance: none;
    appearance: none;
}

/* Fecha de registro automática (solo lectura) */
.registro-auto {
    margin-top: 4px;
    font-size: 0.7rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.registro-auto strong {
    color: #334155;
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

.form-input.error:focus {
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
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
    z-index: 2;
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

.form-textarea.error {
    border-color: #ef4444;
}

.input-with-prefix .form-input {
    padding-left: 32px;
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

/* --- ERROR TEXT --- */
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
    display: flex;
    align-items: center;
    gap: 4px;
}

.hint-text.success {
    color: #059669;
}

/* --- IVA SELECTOR --- */
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

.iva-btn:hover:not(.disabled) {
    border-color: #1a3a5c;
    color: #1a3a5c;
}

.iva-btn.active {
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    border-color: #1a3a5c;
    color: white;
    box-shadow: 0 2px 10px rgba(26, 58, 92, 0.2);
}

.iva-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.iva-check {
    font-size: 0.6rem;
    font-weight: 700;
}

/* --- IVA DETAIL --- */
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

.iva-detail-badge.badge-cero {
    background: #64748b;
}

.iva-detail-badge.badge-dieciseis {
    background: #3b82f6;
}

.iva-input-wrap {
    width: 100px;
}

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

.iva-remove-btn:hover {
    background: #fecaca;
    transform: rotate(90deg) scale(1.1);
}

.iva-total {
    display: flex;
    gap: 12px;
    font-size: 0.8rem;
    color: #1f2937;
    padding-left: 6px;
}

.iva-total strong {
    color: #059669;
    font-size: 0.9rem;
}

/* --- VALIDACION DE IVA --- */
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

.validacion-iva .validacion-icon {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.validacion-iva .validacion-texto {
    font-weight: 500;
    flex: 1;
}

/* --- OPTIONS --- */
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

.checkbox-input:checked+.checkbox-custom {
    border-color: #1a3a5c;
}

.checkbox-input:checked+.checkbox-custom::after {
    transform: scale(1);
}

.checkbox-text {
    font-weight: 500;
}

/* --- VENCIMIENTO --- */
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
    display: flex;
    align-items: center;
    gap: 4px;
}

.vencimiento-input {
    height: 34px !important;
    font-size: 0.8rem !important;
    padding: 4px 10px !important;
}

/* --- MARCADOR --- */
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

.btn-add-marcador:hover {
    border-color: #1a3a5c;
    background: #f8f7ff;
    transform: scale(1.05);
}

/* --- SALDO --- */
.saldo-disponible {
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.saldo-disponible strong {
    color: #059669;
}

.nuevo-saldo {
    font-size: 0.7rem;
    color: #2563eb;
    font-weight: 600;
}

/* --- FISCAL --- */
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
    display: flex;
    align-items: center;
    gap: 6px;
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

/* --- FILE UPLOAD --- */
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

/* --- VALIDACION DE SALDO --- */
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

.validacion-icon {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.validacion-texto {
    font-weight: 500;
    flex: 1;
}

/* --- INFO BOX --- */
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

.text-danger {
    color: #ef4444;
}

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

.info-fiscal {
    background: #fef3c7;
    color: #92400e;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    gap: 4px;
}

.info-auto-cargado {
    background: #d1fae5;
    color: #065f46;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* --- FORM ACTIONS --- */
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

.total-card-iva .total-value {
    color: #d97706;
}

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

/* --- MODAL --- */
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
    display: flex;
    align-items: center;
    gap: 8px;
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

.modal-body {
    padding: 20px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

/* --- ANIMATIONS --- */
@keyframes shake {

    0%,
    100% {
        transform: translateX(0);
    }

    25% {
        transform: translateX(-4px);
    }

    75% {
        transform: translateX(4px);
    }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(15px) scale(0.96);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes spinner {
    to {
        transform: rotate(360deg);
    }
}

.fade-slide {
    animation: fadeSlide 0.3s ease;
}

@keyframes fadeSlide {
    from {
        opacity: 0;
        transform: translateY(8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ===== NUEVOS ESTILOS PARA ICONOS ===== */
.label-icon {
    width: 18px;
    height: 18px;
    stroke: #1a3a5c;
    flex-shrink: 0;
}

.inline-icon {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    flex-shrink: 0;
}

.hint-icon {
    width: 14px;
    height: 14px;
    stroke: #94a3b8;
    flex-shrink: 0;
    display: inline-block;
}

.hint-icon.spin {
    animation: spin 1s linear infinite;
}

.hint-text.success .hint-icon {
    stroke: #059669;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.icon-svg-sm {
    width: 16px;
    height: 16px;
}

.icon-svg-md {
    width: 20px;
    height: 20px;
}

.w-5 {
    width: 1.25rem;
}

.h-5 {
    height: 1.25rem;
}

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
    .form-row {
        grid-template-columns: 1fr 1fr;
    }

    .fiscal-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 10px;
        margin-bottom: 10px;
    }

    .form-card {
        padding: 1rem;
    }

    .container-custom {
        padding: 0 0.75rem;
    }

    .header-wrapper {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }

    .header-right {
        width: 100%;
        justify-content: flex-start;
    }

    .tipo-selector-premium {
        flex-direction: column;
    }

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

    .btn {
        flex: 1;
        justify-content: center;
        padding: 6px 16px;
        height: 36px;
        font-size: 0.8rem;
    }

    .fiscal-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .fiscal-section {
        padding: 10px;
    }

    .options-grid {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .iva-detail-item {
        flex-wrap: wrap;
    }

    .iva-input-wrap {
        width: 100%;
    }

    .modal-container {
        margin: 12px;
    }

    .total-card {
        padding: 4px 12px;
    }

    .total-value {
        font-size: 0.9rem;
    }
}
</style>