<template>
    <AppLayout title="Editar Persona">
        <template #header>
            <div class="header-premium">
                <div class="header-content-premium">
                    <div class="header-left-premium">
                        <Link :href="route('personas.index')" class="btn-back-premium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                        </Link>
                        <div class="header-icon-wrapper">
                            <svg class="header-icon-svg" fill="none" stroke="white" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="header-title-premium">
                                <span class="title-highlight">Editar</span> Persona
                            </h2>
                            <p class="header-subtitle-premium">
                                <span class="subtitle-line"></span>
                                Actualice la información de la persona
                                <span class="header-hint">Los campos con <strong>*</strong> son obligatorios</span>
                            </p>
                        </div>
                    </div>
                    <div class="header-right-premium">
                        <div class="status-badge-premium" :class="statusClass">
                            <svg v-if="hasErrors" class="badge-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <svg v-else-if="isComplete" class="badge-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <svg v-else class="badge-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span v-if="hasErrors">{{ errorCount }} errores</span>
                            <span v-else-if="isComplete">Completado</span>
                            <span v-else>{{ Math.round(progressPercentage) }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="page-content-premium">
            <div class="container-premium">
                <div class="form-card-premium">
                    <form @submit.prevent="submit" id="personaForm" novalidate>
                        <!-- ===== TABS ===== -->
                        <div class="tabs-premium">
                            <button type="button" 
                                    class="tab-premium" 
                                    :class="{ active: activeTab === 'generales' }"
                                    @click="activeTab = 'generales'">
                                <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span>Datos Generales</span>
                                <span v-if="hasGeneralesErrors" class="tab-dot error-dot"></span>
                                <span v-else-if="isGeneralesComplete" class="tab-dot success-dot"></span>
                            </button>
                            <button type="button" 
                                    class="tab-premium" 
                                    :class="{ active: activeTab === 'contacto' }"
                                    @click="activeTab = 'contacto'">
                                <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                                <span>Contacto</span>
                                <span v-if="hasContactoErrors" class="tab-dot error-dot"></span>
                            </button>
                            <button type="button" 
                                    class="tab-premium" 
                                    :class="{ active: activeTab === 'direccion' }"
                                    @click="activeTab = 'direccion'">
                                <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span>Dirección</span>
                                <span v-if="hasDireccionErrors" class="tab-dot error-dot"></span>
                            </button>
                            <button type="button" 
                                    class="tab-premium" 
                                    :class="{ active: activeTab === 'notas' }"
                                    @click="activeTab = 'notas'">
                                <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                                <span>Notas</span>
                            </button>
                            <button type="button" 
                                    v-if="form.tipo_persona === 'MORAL'"
                                    class="tab-premium" 
                                    :class="{ active: activeTab === 'representante' }"
                                    @click="activeTab = 'representante'">
                                <svg class="tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <span>Representante Legal</span>
                                <span v-if="hasRepresentanteErrors" class="tab-dot error-dot"></span>
                                <span v-else-if="isRepresentanteComplete && form.tipo_persona === 'MORAL'" class="tab-dot success-dot"></span>
                            </button>
                        </div>

                        <!-- ===== CONTENIDO DE TABS ===== -->
                        <div class="tab-content-premium">
                            <!-- TAB 1: DATOS GENERALES -->
                            <div v-show="activeTab === 'generales'" class="tab-pane-premium">
                                <!-- Tipo de Persona -->
                                <div class="section-premium section-tipo">
                                    <div class="section-header-premium">
                                        <div class="section-icon-premium">
                                            <svg class="icon-sm" fill="none" stroke="white" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                        </div>
                                        <div class="section-title-wrapper">
                                            <span class="section-title-premium">Tipo de Persona</span>
                                            <span class="badge-required">Requerido</span>
                                        </div>
                                    </div>
                                    <div class="radio-group-premium">
                                        <div class="radio-premium" 
                                             :class="{ 'selected': form.tipo_persona === 'FISICA' }"
                                             @click="form.tipo_persona = 'FISICA'; clearError('tipo_persona'); onTipoChange()">
                                            <svg class="radio-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <span class="radio-label">Persona Física</span>
                                            <div class="radio-check" v-if="form.tipo_persona === 'FISICA'">
                                                <svg class="check-icon" fill="none" stroke="#10b981" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                            <input type="radio" v-model="form.tipo_persona" value="FISICA" class="radio-input">
                                        </div>
                                        <div class="radio-premium" 
                                             :class="{ 'selected': form.tipo_persona === 'MORAL' }"
                                             @click="form.tipo_persona = 'MORAL'; clearError('tipo_persona'); onTipoChange()">
                                            <svg class="radio-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                            <span class="radio-label">Persona Moral</span>
                                            <div class="radio-check" v-if="form.tipo_persona === 'MORAL'">
                                                <svg class="check-icon" fill="none" stroke="#10b981" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                            <input type="radio" v-model="form.tipo_persona" value="MORAL" class="radio-input">
                                        </div>
                                    </div>
                                    <div v-if="form.errors.tipo_persona" class="error-premium">{{ form.errors.tipo_persona }}</div>
                                </div>

                                <!-- Datos Personales -->
                                <div class="section-premium section-datos">
                                    <div class="section-header-premium">
                                        <div class="section-icon-premium">
                                            <svg class="icon-sm" fill="none" stroke="white" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                        </div>
                                        <div class="section-title-wrapper">
                                            <span class="section-title-premium">Datos Personales</span>
                                            <span class="badge-required">Requerido</span>
                                        </div>
                                    </div>

                                    <!-- Nombre completo (Nombre + Paterno + Materno) -->
                                    <div class="grid-nombres">
                                        <div class="field-premium">
                                            <label class="label-premium">Nombre(s) 
                                                <span class="star">*</span>
                                            </label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.Nombre"
                                                       @input="clearError('Nombre'); autoGenerarRFC(); validateText('Nombre')"
                                                       class="input-premium"
                                                       :class="{ 'error': form.errors.Nombre || textErrors.Nombre }"
                                                       placeholder="Juan Carlos">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <div v-if="form.errors.Nombre" class="error-premium">{{ form.errors.Nombre }}</div>
                                            <div v-if="textErrors.Nombre" class="error-premium">{{ textErrors.Nombre }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Apellido Paterno</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.Paterno"
                                                       @input="clearError('Paterno'); autoGenerarRFC(); validateText('Paterno')"
                                                       class="input-premium"
                                                       :class="{ 'error': form.errors.Paterno || textErrors.Paterno }"
                                                       placeholder="Pérez">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <div v-if="form.errors.Paterno" class="error-premium">{{ form.errors.Paterno }}</div>
                                            <div v-if="textErrors.Paterno" class="error-premium">{{ textErrors.Paterno }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Apellido Materno</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.Materno"
                                                       @input="autoGenerarRFC(); validateText('Materno')"
                                                       class="input-premium"
                                                       :class="{ 'error': textErrors.Materno }"
                                                       placeholder="García">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <div v-if="textErrors.Materno" class="error-premium">{{ textErrors.Materno }}</div>
                                        </div>
                                    </div>

                                    <!-- RFC + CURP (primera fila) -->
                                    <div class="grid-2">
                                        <div class="field-premium">
                                            <label class="label-premium">RFC</label>
                                            <div class="input-with-btn">
                                                <input type="text" v-model="form.rfc"
                                                       @input="clearError('rfc'); form.rfc = form.rfc.toUpperCase(); validateRFC()"
                                                       class="input-premium text-uppercase"
                                                       :class="{ 'error': form.errors.rfc || rfcError }"
                                                       placeholder="PERE890101XXX"
                                                       maxlength="13">
                                                <button type="button" 
                                                        @click="generarRFC" 
                                                        class="btn-rfc-premium"
                                                        :disabled="generandoRFC || !puedeGenerarRFC">
                                                    <span v-if="generandoRFC" class="spinner-sm"></span>
                                                    <span v-else>Generar RFC</span>
                                                </button>
                                            </div>
                                            <div v-if="form.errors.rfc" class="error-premium">{{ form.errors.rfc }}</div>
                                            <div v-if="rfcError" class="error-premium">{{ rfcError }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">CURP <span class="optional">(Opcional)</span></label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.curp"
                                                       @input="clearError('curp'); form.curp = form.curp.toUpperCase(); validateCURP()"
                                                       class="input-premium text-uppercase"
                                                       :class="{ 'error': form.errors.curp || curpError }"
                                                       placeholder="PERE890101HDFRRN09"
                                                       maxlength="18">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                                </svg>
                                            </div>
                                            <div v-if="form.errors.curp" class="error-premium">{{ form.errors.curp }}</div>
                                            <div v-if="curpError" class="error-premium">{{ curpError }}</div>
                                        </div>
                                    </div>

                                    <!-- Fecha Nacimiento + Sexo + Empleado (segunda fila) -->
                                    <div class="grid-3">
                                        <div class="field-premium">
                                            <label class="label-premium">Fecha Nacimiento</label>
                                            <div class="input-wrapper-premium">
                                                <input type="date" v-model="form.Fecha_nacimiento"
                                                       @change="clearError('Fecha_nacimiento'); validateEdad(); autoGenerarRFC()"
                                                       class="input-premium date"
                                                       :class="{ 'error': form.errors.Fecha_nacimiento || edadError }"
                                                       :max="fechaMaxima">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div v-if="form.errors.Fecha_nacimiento" class="error-premium">{{ form.errors.Fecha_nacimiento }}</div>
                                            <div v-if="edadError" class="error-premium">{{ edadError }}</div>
                                        </div>

                                        <div class="field-premium">
                                            <label class="label-premium">Sexo</label>
                                            <div class="input-wrapper-premium" style="padding-right: 0;">
                                                <div class="radio-group-sm">
                                                    <div class="radio-sm" 
                                                         :class="{ 'selected': form.sexo === 'MASCULINO' }"
                                                         @click="form.sexo = 'MASCULINO'; clearError('sexo')">
                                                        <svg class="radio-sexo-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v4m0 0l-2-2m2 2l2-2"/>
                                                        </svg>
                                                        <span>Masculino</span>
                                                        <input type="radio" v-model="form.sexo" value="MASCULINO" class="radio-input">
                                                    </div>
                                                    <div class="radio-sm" 
                                                         :class="{ 'selected': form.sexo === 'FEMENINO' }"
                                                         @click="form.sexo = 'FEMENINO'; clearError('sexo')">
                                                        <svg class="radio-sexo-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v4m0 0l-2-2m2 2l2-2"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 14v3m0 0v3m0-3h-3m3 0h3"/>
                                                        </svg>
                                                        <span>Femenino</span>
                                                        <input type="radio" v-model="form.sexo" value="FEMENINO" class="radio-input">
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-if="form.errors.sexo" class="error-premium">{{ form.errors.sexo }}</div>
                                        </div>

                                        <div class="field-premium">
                                            <label class="label-premium">¿Es empleado?</label>
                                            <div class="checkbox-wrapper-premium">
                                                <label class="checkbox-premium" :class="{ 'checked': form.empleado }">
                                                    <input type="checkbox" v-model="form.empleado" @change="clearError('empleado')">
                                                    <span class="checkbox-custom">
                                                        <svg v-if="form.empleado" class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    </span>
                                                    <span class="checkbox-label">{{ form.empleado ? 'Sí' : 'No' }}</span>
                                                </label>
                                            </div>
                                            <div v-if="form.errors.empleado" class="error-premium">{{ form.errors.empleado }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 2: CONTACTO -->
                            <div v-show="activeTab === 'contacto'" class="tab-pane-premium">
                                <div class="section-premium section-contacto">
                                    <div class="section-header-premium">
                                        <div class="section-icon-premium">
                                            <svg class="icon-sm" fill="none" stroke="white" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                            </svg>
                                        </div>
                                        <div class="section-title-wrapper">
                                            <span class="section-title-premium">Contacto</span>
                                            <span class="badge-optional">Opcional</span>
                                        </div>
                                    </div>
                                    <div class="grid-contacto">
                                        <div class="field-premium full">
                                            <label class="label-premium">Correo Electrónico</label>
                                            <div class="input-wrapper-premium">
                                                <input type="email" v-model="form.email"
                                                       @input="clearError('email'); validateEmail()"
                                                       class="input-premium"
                                                       :class="{ 'error': form.errors.email || emailError }"
                                                       placeholder="contacto@correo.com">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div v-if="form.errors.email" class="error-premium">{{ form.errors.email }}</div>
                                            <div v-if="emailError" class="error-premium">{{ emailError }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Teléfono Particular</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.telefono_particular"
                                                       @input="validatePhone('telefono_particular')"
                                                       class="input-premium"
                                                       :class="{ 'error': phoneErrors.telefono_particular }"
                                                       placeholder="7771234567"
                                                       maxlength="10">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                </svg>
                                            </div>
                                            <div v-if="phoneErrors.telefono_particular" class="error-premium">{{ phoneErrors.telefono_particular }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Teléfono Trabajo</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.telefono_trabajo"
                                                       @input="validatePhone('telefono_trabajo')"
                                                       class="input-premium"
                                                       :class="{ 'error': phoneErrors.telefono_trabajo }"
                                                       placeholder="7779876543"
                                                       maxlength="10">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                </svg>
                                            </div>
                                            <div v-if="phoneErrors.telefono_trabajo" class="error-premium">{{ phoneErrors.telefono_trabajo }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Extensión</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.extension_trabajo"
                                                       @input="validateNumeric('extension_trabajo')"
                                                       class="input-premium"
                                                       :class="{ 'error': numericErrors.extension_trabajo }"
                                                       placeholder="123"
                                                       maxlength="10">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                                </svg>
                                            </div>
                                            <div v-if="numericErrors.extension_trabajo" class="error-premium">{{ numericErrors.extension_trabajo }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 3: DIRECCIÓN -->
                            <div v-show="activeTab === 'direccion'" class="tab-pane-premium">
                                <div class="section-premium section-direccion">
                                    <div class="section-header-premium">
                                        <div class="section-icon-premium">
                                            <svg class="icon-sm" fill="none" stroke="white" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </div>
                                        <div class="section-title-wrapper">
                                            <span class="section-title-premium">Dirección</span>
                                            <span class="badge-optional">Opcional</span>
                                        </div>
                                    </div>
                                    <div class="grid-direccion">
                                        <div class="field-premium dir-cp">
                                            <label class="label-premium">Código Postal</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.codigo_postal"
                                                       @input="validateCodigoPostal()"
                                                       @blur="onBuscarCP()"
                                                       @keydown.enter.prevent="onBuscarCP()"
                                                       class="input-premium"
                                                       :class="{ 'error': codigoPostalError }"
                                                       placeholder="06600"
                                                       inputmode="numeric"
                                                       maxlength="5">
                                                <span v-if="dir.cargandoCP.value" class="cp-spinner"></span>
                                            </div>
                                            <div v-if="codigoPostalError" class="error-premium">{{ codigoPostalError }}</div>
                                            <div v-else-if="dir.cpMensaje.value" class="hint-premium">{{ dir.cpMensaje.value }}</div>
                                            <div v-else class="hint-premium">Se autocompletan estado, municipio y colonias.</div>
                                        </div>
                                        <div class="field-premium dir-estado">
                                            <label class="label-premium">Estado</label>
                                            <div class="input-wrapper-premium">
                                                <select v-model="form.estado" class="select-premium"
                                                        :class="{ 'error': textErrors.estado }"
                                                        @change="onEstado($event.target.value)">
                                                    <option value="">Selecciona un estado</option>
                                                    <option v-for="estado in dir.estados.value" :key="estado" :value="estado">
                                                        {{ estado }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div v-if="textErrors.estado" class="error-premium">{{ textErrors.estado }}</div>
                                        </div>
                                        <div class="field-premium dir-municipio">
                                            <label class="label-premium">Municipio</label>
                                            <ComboBox
                                                v-model="form.municipio"
                                                :options="dir.municipios.value"
                                                :input-class="{ error: textErrors.municipio }"
                                                placeholder="Cuauhtémoc"
                                                :empty-text="form.estado ? 'Sin coincidencias' : 'Elige primero un estado'"
                                            />
                                            <div v-if="textErrors.municipio" class="error-premium">{{ textErrors.municipio }}</div>
                                        </div>
                                        <div class="field-premium dir-colonia">
                                            <label class="label-premium">Colonia</label>
                                            <ComboBox
                                                v-model="form.colonia"
                                                :options="coloniaOptions"
                                                :input-class="{ error: textErrors.colonia }"
                                                placeholder="Colonia Juárez"
                                                :empty-text="form.codigo_postal ? 'Escribe el CP para ver colonias' : 'Escribe el código postal'"
                                            />
                                            <div v-if="textErrors.colonia" class="error-premium">{{ textErrors.colonia }}</div>
                                            <div v-else class="hint-premium">Elige una de la lista, o escríbela y haz clic fuera del recuadro.</div>
                                        </div>
                                        <div class="field-premium dir-calle">
                                            <label class="label-premium">Calle</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.calle"
                                                       @input="validateText('calle')"
                                                       class="input-premium"
                                                       :class="{ 'error': textErrors.calle }"
                                                       placeholder="Avenida Reforma">
                                            </div>
                                            <div v-if="textErrors.calle" class="error-premium">{{ textErrors.calle }}</div>
                                        </div>
                                        <div class="field-premium dir-ext">
                                            <label class="label-premium">Núm. Exterior</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.numero_exterior"
                                                       @input="validateAlphanumeric('numero_exterior')"
                                                       class="input-premium"
                                                       :class="{ 'error': alphanumericErrors.numero_exterior }"
                                                       placeholder="123">
                                            </div>
                                            <div v-if="alphanumericErrors.numero_exterior" class="error-premium">{{ alphanumericErrors.numero_exterior }}</div>
                                        </div>
                                        <div class="field-premium dir-int">
                                            <label class="label-premium">Núm. Interior</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.numero_interior"
                                                       @input="validateAlphanumeric('numero_interior')"
                                                       class="input-premium"
                                                       :class="{ 'error': alphanumericErrors.numero_interior }"
                                                       placeholder="2B">
                                            </div>
                                            <div v-if="alphanumericErrors.numero_interior" class="error-premium">{{ alphanumericErrors.numero_interior }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 4: NOTAS -->
                            <div v-show="activeTab === 'notas'" class="tab-pane-premium">
                                <div class="section-premium section-notas">
                                    <div class="section-header-premium">
                                        <div class="section-icon-premium">
                                            <svg class="icon-sm" fill="none" stroke="white" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                            </svg>
                                        </div>
                                        <div class="section-title-wrapper">
                                            <span class="section-title-premium">Notas Adicionales</span>
                                            <span class="badge-optional">Opcional</span>
                                        </div>
                                    </div>
                                    <div class="field-premium full">
                                        <textarea v-model="form.notas"
                                                  class="textarea-premium"
                                                  rows="6"
                                                  placeholder="Información adicional sobre la persona..."
                                                  maxlength="500"></textarea>
                                        <div class="char-counter">
                                            <span v-if="form.notas && form.notas.length > 0">
                                                {{ form.notas.length }} / 500 caracteres
                                            </span>
                                            <span v-else>
                                                0 / 500 caracteres
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB 5: REPRESENTANTE LEGAL -->
                            <div v-show="activeTab === 'representante'" class="tab-pane-premium">
                                <div class="section-premium section-representante">
                                    <div class="section-header-premium">
                                        <div class="section-icon-premium">
                                            <svg class="icon-sm" fill="none" stroke="white" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                        </div>
                                        <div class="section-title-wrapper">
                                            <span class="section-title-premium">Representante Legal</span>
                                            <span class="badge-optional">Opcional</span>
                                        </div>
                                    </div>
                                    <div class="grid-representante">
                                        <!-- Nombre completo del representante -->
                                        <div class="field-premium">
                                            <label class="label-premium">Nombre del Representante</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.representante_nombre"
                                                       @input="clearError('representante_nombre'); validateText('representante_nombre')"
                                                       class="input-premium"
                                                       :class="{ 'error': form.errors.representante_nombre || textErrors.representante_nombre }"
                                                       placeholder="María">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <div v-if="form.errors.representante_nombre" class="error-premium">{{ form.errors.representante_nombre }}</div>
                                            <div v-if="textErrors.representante_nombre" class="error-premium">{{ textErrors.representante_nombre }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Apellido Paterno</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.representante_paterno"
                                                       @input="clearError('representante_paterno'); validateText('representante_paterno')"
                                                       class="input-premium"
                                                       :class="{ 'error': form.errors.representante_paterno || textErrors.representante_paterno }"
                                                       placeholder="López">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <div v-if="form.errors.representante_paterno" class="error-premium">{{ form.errors.representante_paterno }}</div>
                                            <div v-if="textErrors.representante_paterno" class="error-premium">{{ textErrors.representante_paterno }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Apellido Materno</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.representante_materno"
                                                       @input="validateText('representante_materno')"
                                                       class="input-premium"
                                                       :class="{ 'error': textErrors.representante_materno }"
                                                       placeholder="Martínez">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <div v-if="textErrors.representante_materno" class="error-premium">{{ textErrors.representante_materno }}</div>
                                        </div>

                                        <!-- Fecha Nacimiento + Sexo + Correo -->
                                        <div class="field-premium">
                                            <label class="label-premium">Fecha Nacimiento</label>
                                            <div class="input-wrapper-premium">
                                                <input type="date" v-model="form.representante_fecha_nacimiento"
                                                       @change="clearError('representante_fecha_nacimiento')"
                                                       class="input-premium date"
                                                       :class="{ 'error': form.errors.representante_fecha_nacimiento }"
                                                       :max="fechaMaxima">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div v-if="form.errors.representante_fecha_nacimiento" class="error-premium">{{ form.errors.representante_fecha_nacimiento }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Sexo</label>
                                            <div class="input-wrapper-premium" style="padding-right: 0;">
                                                <div class="radio-group-sm">
                                                    <div class="radio-sm" 
                                                         :class="{ 'selected': form.representante_sexo === 'MASCULINO' }"
                                                         @click="form.representante_sexo = 'MASCULINO'">
                                                        <svg class="radio-sexo-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v4m0 0l-2-2m2 2l2-2"/>
                                                        </svg>
                                                        <span>Masculino</span>
                                                        <input type="radio" v-model="form.representante_sexo" value="MASCULINO" class="radio-input">
                                                    </div>
                                                    <div class="radio-sm" 
                                                         :class="{ 'selected': form.representante_sexo === 'FEMENINO' }"
                                                         @click="form.representante_sexo = 'FEMENINO'">
                                                        <svg class="radio-sexo-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v4m0 0l-2-2m2 2l2-2"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 14v3m0 0v3m0-3h-3m3 0h3"/>
                                                        </svg>
                                                        <span>Femenino</span>
                                                        <input type="radio" v-model="form.representante_sexo" value="FEMENINO" class="radio-input">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Correo del Representante</label>
                                            <div class="input-wrapper-premium">
                                                <input type="email" v-model="form.representante_email"
                                                       @input="validateRepresentanteEmail()"
                                                       class="input-premium"
                                                       :class="{ 'error': representanteEmailError }"
                                                       placeholder="representante@correo.com">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div v-if="representanteEmailError" class="error-premium">{{ representanteEmailError }}</div>
                                        </div>

                                        <!-- Teléfonos -->
                                        <div class="field-premium">
                                            <label class="label-premium">Teléfono Particular</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.representante_telefono_particular"
                                                       @input="validatePhone('representante_telefono_particular')"
                                                       class="input-premium"
                                                       :class="{ 'error': phoneErrors.representante_telefono_particular }"
                                                       placeholder="7771234567"
                                                       maxlength="10">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                </svg>
                                            </div>
                                            <div v-if="phoneErrors.representante_telefono_particular" class="error-premium">{{ phoneErrors.representante_telefono_particular }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Teléfono Trabajo</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.representante_telefono_trabajo"
                                                       @input="validatePhone('representante_telefono_trabajo')"
                                                       class="input-premium"
                                                       :class="{ 'error': phoneErrors.representante_telefono_trabajo }"
                                                       placeholder="7779876543"
                                                       maxlength="10">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                </svg>
                                            </div>
                                            <div v-if="phoneErrors.representante_telefono_trabajo" class="error-premium">{{ phoneErrors.representante_telefono_trabajo }}</div>
                                        </div>
                                        <div class="field-premium">
                                            <label class="label-premium">Extensión</label>
                                            <div class="input-wrapper-premium">
                                                <input type="text" v-model="form.representante_extension_trabajo"
                                                       @input="validateNumeric('representante_extension_trabajo')"
                                                       class="input-premium"
                                                       :class="{ 'error': numericErrors.representante_extension_trabajo }"
                                                       placeholder="123"
                                                       maxlength="10">
                                                <svg class="input-icon-premium" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                                </svg>
                                            </div>
                                            <div v-if="numericErrors.representante_extension_trabajo" class="error-premium">{{ numericErrors.representante_extension_trabajo }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== FOOTER ===== -->
                        <div class="footer-premium">
                            <div class="actions-premium">
                                <Link :href="route('personas.index')" class="btn-cancel-premium">
                                    <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Cancelar
                                </Link>
                                <button type="submit"
                                        :disabled="form.processing"
                                        class="btn-submit-premium">
                                    <span v-if="form.processing" class="spinner-border-sm" role="status"></span>
                                    <svg v-else class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                    </svg>
                                    {{ form.processing ? 'Guardando...' : 'Actualizar Persona' }}
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
// ============================================
// SCRIPT - SIN CAMBIOS, ES EL MISMO QUE ANTES
// ============================================
import { ref, computed, watch, onMounted } from 'vue';
import { useNotify } from '@/composables/useNotify';
import { useDireccionMx } from '@/composables/useDireccionMx';
import ComboBox from '@/Components/ComboBox.vue';

const notify = useNotify();
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    persona: {
        type: Object,
        required: true
    }
});

// ============================================
// ESTADOS DE VALIDACIÓN
// ============================================
const emailError = ref('');
const rfcError = ref('');
const curpError = ref('');
const codigoPostalError = ref('');
const representanteEmailError = ref('');
const edadError = ref('');
const generandoRFC = ref(false);

const textErrors = ref({
    Nombre: '',
    Paterno: '',
    Materno: '',
    calle: '',
    colonia: '',
    ciudad: '',
    municipio: '',
    estado: '',
    representante_nombre: '',
    representante_paterno: '',
    representante_materno: ''
});

const numericErrors = ref({
    extension_trabajo: '',
    representante_extension_trabajo: ''
});

const alphanumericErrors = ref({
    numero_exterior: '',
    numero_interior: ''
});

const phoneErrors = ref({
    telefono_particular: '',
    telefono_trabajo: '',
    representante_telefono_particular: '',
    representante_telefono_trabajo: ''
});

// ============================================
// TAB ACTIVO
// ============================================
const activeTab = ref('generales');

// ============================================
// FECHA MAXIMA (18 años)
// ============================================
const fechaMaxima = computed(() => {
    const fecha = new Date();
    fecha.setFullYear(fecha.getFullYear() - 18);
    return fecha.toISOString().split('T')[0];
});

// ============================================
// FUNCIÓN PARA FORMATEAR FECHA
// ============================================
const formatDate = (date) => {
    if (!date) return '';
    if (typeof date === 'string' && date.match(/^\d{4}-\d{2}-\d{2}$/)) {
        return date;
    }
    const d = new Date(date);
    if (isNaN(d.getTime())) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// ============================================
// FORMULARIO
// ============================================
const form = useForm({
    tipo_persona: props.persona?.tipo_persona || 'FISICA',
    empleado: props.persona?.empleado ? 1 : 0,
    Nombre: props.persona?.Nombre || '',
    Paterno: props.persona?.Paterno || '',
    Materno: props.persona?.Materno || '',
    Fecha_nacimiento: formatDate(props.persona?.Fecha_nacimiento) || '',
    sexo: props.persona?.sexo || 'MASCULINO',
    rfc: props.persona?.rfc || '',
    curp: props.persona?.curp || '',
    email: props.persona?.email || '',
    telefono_particular: props.persona?.telefono_particular || '',
    telefono_trabajo: props.persona?.telefono_trabajo || '',
    extension_trabajo: props.persona?.extension_trabajo || '',
    calle: props.persona?.calle || '',
    numero_exterior: props.persona?.numero_exterior || '',
    numero_interior: props.persona?.numero_interior || '',
    colonia: props.persona?.colonia || '',
    ciudad: props.persona?.ciudad || '',
    municipio: props.persona?.municipio || '',
    estado: props.persona?.estado || '',
    codigo_postal: props.persona?.codigo_postal || '',
    representante_nombre: props.persona?.representante_nombre || '',
    representante_paterno: props.persona?.representante_paterno || '',
    representante_materno: props.persona?.representante_materno || '',
    representante_fecha_nacimiento: formatDate(props.persona?.representante_fecha_nacimiento) || '',
    representante_sexo: props.persona?.representante_sexo || 'MASCULINO',
    representante_email: props.persona?.representante_email || '',
    representante_telefono_particular: props.persona?.representante_telefono_particular || '',
    representante_telefono_trabajo: props.persona?.representante_telefono_trabajo || '',
    representante_extension_trabajo: props.persona?.representante_extension_trabajo || '',
    notas: props.persona?.notas || '',
});

// ============================================
// DIRECCIÓN (catálogo + autocompletado por CP)
// ============================================
const dir = useDireccionMx();
onMounted(async () => {
    await dir.cargarEstados();
    if (form.estado) await dir.cargarMunicipios(form.estado);
});

const onEstado = (estado) => {
    form.estado = estado;
    form.municipio = '';
    form.ciudad = '';
    dir.cargarMunicipios(estado);
};

const onBuscarCP = async () => {
    const datos = await dir.buscarCP(form.codigo_postal);
    if (!datos) return;
    form.estado = datos.estado;
    await dir.cargarMunicipios(datos.estado);
    form.municipio = datos.municipio;
    // La ciudad ya no se captura: siempre es igual al municipio.
    form.ciudad = datos.municipio || datos.ciudad || '';
    if (dir.colonias.value.length === 1) {
        form.colonia = dir.colonias.value[0].nombre;
    }
};

const coloniaOptions = computed(() =>
    dir.colonias.value.map((c) => ({
        label: c.tipo ? `${c.nombre} · ${c.tipo}` : c.nombre,
        value: c.nombre,
    })),
);

// ============================================
// VALIDACIONES
// ============================================
const validateText = (field) => {
    const value = form[field];
    if (!value || value.trim() === '') {
        textErrors.value[field] = '';
        return;
    }
    const textRegex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/;
    textErrors.value[field] = textRegex.test(value) ? '' : 'Solo se permiten letras y espacios';
};

const validateNumeric = (field) => {
    const value = form[field];
    if (!value || value.trim() === '') {
        numericErrors.value[field] = '';
        return;
    }
    numericErrors.value[field] = /^\d+$/.test(value) ? '' : 'Solo se permiten números';
};

const validateAlphanumeric = (field) => {
    const value = form[field];
    if (!value || value.trim() === '') {
        alphanumericErrors.value[field] = '';
        return;
    }
    alphanumericErrors.value[field] = /^[a-zA-Z0-9]+$/.test(value) ? '' : 'Solo letras y números (sin espacios)';
};

const validateCodigoPostal = () => {
    const value = form.codigo_postal;
    if (!value || value.trim() === '') {
        codigoPostalError.value = '';
        return;
    }
    if (!/^\d+$/.test(value)) {
        codigoPostalError.value = 'Solo se permiten números';
    } else if (value.length !== 5) {
        codigoPostalError.value = 'El código postal debe tener 5 dígitos';
    } else {
        codigoPostalError.value = '';
    }
};

const validateCURP = () => {
    const curp = form.curp;
    if (!curp || curp.trim() === '') {
        curpError.value = '';
        return;
    }
    const curpLimpio = curp.trim().replace(/\s/g, '').toUpperCase();
    if (form.curp !== curpLimpio) {
        form.curp = curpLimpio;
        return;
    }
    const curpRegex = /^[A-Z]{4}[0-9]{6}[A-Z]{6}[0-9A-Z]{2}$/;
    if (curpLimpio.length !== 18) {
        curpError.value = `La CURP debe tener 18 caracteres (tiene ${curpLimpio.length})`;
        return;
    }
    curpError.value = curpRegex.test(curpLimpio) ? '' : 'Formato de CURP inválido';
};

const validateRFC = () => {
    const rfc = form.rfc;
    if (!rfc) {
        rfcError.value = '';
        return;
    }
    let rfcRegex;
    if (form.tipo_persona === 'FISICA') {
        rfcRegex = /^[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}$/;
        rfcError.value = rfcRegex.test(rfc) ? '' : 'RFC de persona física inválido';
    } else {
        rfcRegex = /^[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}$/;
        rfcError.value = rfcRegex.test(rfc) ? '' : 'RFC de persona moral inválido';
    }
};

const validateEdad = () => {
    const fecha = form.Fecha_nacimiento;
    if (!fecha) {
        edadError.value = '';
        return;
    }
    const fechaNac = new Date(fecha);
    const hoy = new Date();
    let edad = hoy.getFullYear() - fechaNac.getFullYear();
    const mes = hoy.getMonth() - fechaNac.getMonth();
    if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) edad--;
    edadError.value = edad < 18 ? `Debe ser mayor de edad (${edad} años)` : '';
};

const validateEmail = () => {
    const email = form.email;
    if (!email) { emailError.value = ''; return; }
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    emailError.value = emailRegex.test(email) ? '' : 'Correo electrónico inválido';
};

const validateRepresentanteEmail = () => {
    const email = form.representante_email;
    if (!email) { representanteEmailError.value = ''; return; }
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    representanteEmailError.value = emailRegex.test(email) ? '' : 'Correo electrónico inválido';
};

const validatePhone = (field) => {
    const phone = form[field];
    if (!phone || phone.trim() === '') {
        phoneErrors.value[field] = '';
        return;
    }
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned !== phone) {
        form[field] = cleaned;
        return;
    }
    if (phone.length < 10) {
        phoneErrors.value[field] = 'El teléfono debe tener 10 dígitos';
    } else if (phone.length > 10) {
        phoneErrors.value[field] = 'El teléfono no puede tener más de 10 dígitos';
    } else {
        phoneErrors.value[field] = '';
    }
};

const clearError = (field) => {
    if (form.errors[field]) delete form.errors[field];
};

// ============================================
// GENERACIÓN DE RFC
// ============================================
const puedeGenerarRFC = computed(() => {
    return form.Nombre && form.Paterno && form.Fecha_nacimiento && !edadError.value;
});

const autoGenerarRFC = () => {
    if (puedeGenerarRFC.value && !form.rfc) {
        generarRFC();
    }
};

const generarRFC = async () => {
    if (!puedeGenerarRFC.value) {
        notify.warn('Complete el nombre, apellido paterno y fecha de nacimiento para generar el RFC.', 'Datos incompletos');
        return;
    }

    generandoRFC.value = true;

    try {
        const response = await axios.post(route('personas.generar-rfc'), {
            tipo_persona: form.tipo_persona,
            Nombre: form.Nombre,
            Paterno: form.Paterno,
            Materno: form.Materno,
            Fecha_nacimiento: form.Fecha_nacimiento
        });

        if (response.data.success) {
            form.rfc = response.data.rfc;
            clearError('rfc');
            rfcError.value = '';
        }
    } catch (error) {
        const message = error.response?.data?.message || 'Error al generar el RFC';
        notify.error(message, 'Error');
    } finally {
        generandoRFC.value = false;
    }
};

const onTipoChange = () => {
    form.representante_nombre = '';
    form.representante_paterno = '';
    form.representante_materno = '';
    form.representante_fecha_nacimiento = '';
    form.representante_sexo = 'MASCULINO';
    form.representante_email = '';
    form.representante_telefono_particular = '';
    form.representante_telefono_trabajo = '';
    form.representante_extension_trabajo = '';
    
    curpError.value = '';
    rfcError.value = '';
    
    if (form.tipo_persona === 'FISICA' && activeTab.value === 'representante') {
        activeTab.value = 'generales';
    }
    
    if (puedeGenerarRFC.value) {
        generarRFC();
    }
};

// ============================================
// COMPUTED PARA ERRORES POR TAB
// ============================================
const hasGeneralesErrors = computed(() => {
    return form.errors.tipo_persona || form.errors.Nombre ||
           form.errors.Fecha_nacimiento || form.errors.sexo ||
           edadError.value;
});

const isGeneralesComplete = computed(() => {
    // Sólo el nombre es obligatorio (a pedido del negocio).
    return form.tipo_persona && form.Nombre && !edadError.value;
});

const hasContactoErrors = computed(() => {
    return form.errors.email || emailError.value || 
           phoneErrors.value.telefono_particular || phoneErrors.value.telefono_trabajo || 
           numericErrors.value.extension_trabajo;
});

const hasDireccionErrors = computed(() => {
    return alphanumericErrors.value.numero_exterior || alphanumericErrors.value.numero_interior || 
           codigoPostalError.value || textErrors.value.calle || textErrors.value.colonia ||
           textErrors.value.ciudad || textErrors.value.municipio || textErrors.value.estado;
});

const hasRepresentanteErrors = computed(() => {
    return form.errors.representante_nombre || form.errors.representante_paterno || 
           representanteEmailError.value || phoneErrors.value.representante_telefono_particular ||
           phoneErrors.value.representante_telefono_trabajo || numericErrors.value.representante_extension_trabajo ||
           textErrors.value.representante_nombre || textErrors.value.representante_paterno ||
           textErrors.value.representante_materno;
});

const isRepresentanteComplete = computed(() => {
    if (form.tipo_persona !== 'MORAL') return false;
    return form.representante_nombre && form.representante_paterno;
});

// ============================================
// COMPUTED
// ============================================
const isFormValid = computed(() => {
    // Sólo el nombre es obligatorio (a pedido del negocio).
    const requiredFields = ['tipo_persona', 'Nombre'];
    const hasRequiredErrors = requiredFields.some(field => {
        const val = form[field];
        return !val || val.toString().trim().length === 0;
    });
    if (hasRequiredErrors) return false;

    const textFields = ['Nombre', 'Materno'];
    const hasTextErrors = textFields.some(field => textErrors.value[field]);
    if (hasTextErrors) return false;

    if (emailError.value) return false;
    if (curpError.value) return false;
    if (edadError.value) return false;
    if (codigoPostalError.value) return false;
    if (representanteEmailError.value) return false;
    
    if (phoneErrors.value.telefono_particular) return false;
    if (phoneErrors.value.telefono_trabajo) return false;
    if (phoneErrors.value.representante_telefono_particular) return false;
    if (phoneErrors.value.representante_telefono_trabajo) return false;
    
    if (numericErrors.value.extension_trabajo) return false;
    if (numericErrors.value.representante_extension_trabajo) return false;
    
    if (alphanumericErrors.value.numero_exterior) return false;
    if (alphanumericErrors.value.numero_interior) return false;
    
    return true;
});

const hasErrors = computed(() => {
    let errorCount = Object.keys(form.errors).length;
    if (emailError.value) errorCount++;
    if (rfcError.value) errorCount++;
    if (curpError.value) errorCount++;
    if (edadError.value) errorCount++;
    if (codigoPostalError.value) errorCount++;
    if (representanteEmailError.value) errorCount++;
    
    Object.values(textErrors.value).forEach(err => { if (err) errorCount++; });
    Object.values(numericErrors.value).forEach(err => { if (err) errorCount++; });
    Object.values(alphanumericErrors.value).forEach(err => { if (err) errorCount++; });
    Object.values(phoneErrors.value).forEach(err => { if (err) errorCount++; });
    
    return errorCount > 0;
});

const errorCount = computed(() => {
    let count = Object.keys(form.errors).length;
    if (emailError.value) count++;
    if (rfcError.value) count++;
    if (curpError.value) count++;
    if (edadError.value) count++;
    if (codigoPostalError.value) count++;
    if (representanteEmailError.value) count++;
    
    Object.values(textErrors.value).forEach(err => { if (err) count++; });
    Object.values(numericErrors.value).forEach(err => { if (err) count++; });
    Object.values(alphanumericErrors.value).forEach(err => { if (err) count++; });
    Object.values(phoneErrors.value).forEach(err => { if (err) count++; });
    
    return count;
});

const requiredFields = computed(() => {
    return ['tipo_persona', 'Nombre'];
});

const progressPercentage = computed(() => {
    const total = requiredFields.value.length;
    const filled = requiredFields.value.filter(f => {
        const val = form[f];
        return val && val.toString().trim().length > 0;
    }).length;
    return total > 0 ? (filled / total) * 100 : 0;
});

const isComplete = computed(() => {
    return progressPercentage.value === 100 && !hasErrors.value;
});

const statusClass = computed(() => {
    if (hasErrors.value) return 'status-error';
    if (isComplete.value) return 'status-success';
    return 'status-progress';
});

// ============================================
// WATCHERS
// ============================================
watch(() => form.email, () => {
    if (form.email) validateEmail();
}, { immediate: true });

watch(() => form.rfc, () => {
    if (form.rfc) validateRFC();
}, { immediate: true });

watch(() => form.curp, () => {
    if (form.curp) validateCURP();
}, { immediate: true });

watch(() => form.telefono_particular, () => {
    if (form.telefono_particular) validatePhone('telefono_particular');
}, { immediate: true });

watch(() => form.telefono_trabajo, () => {
    if (form.telefono_trabajo) validatePhone('telefono_trabajo');
}, { immediate: true });

watch(() => form.codigo_postal, () => {
    if (form.codigo_postal) validateCodigoPostal();
}, { immediate: true });

// El campo "Ciudad" se quitó del formulario: se mantiene igual al municipio.
watch(() => form.municipio, (nuevo) => {
    form.ciudad = nuevo || '';
});

watch(() => form.representante_email, () => {
    if (form.representante_email) validateRepresentanteEmail();
}, { immediate: true });

// ============================================
// SUBMIT
// ============================================
const submit = () => {
    if (form.email) validateEmail();
    if (form.rfc) validateRFC();
    if (form.curp) validateCURP();
    if (form.telefono_particular) validatePhone('telefono_particular');
    if (form.telefono_trabajo) validatePhone('telefono_trabajo');
    if (form.Fecha_nacimiento) validateEdad();
    if (form.codigo_postal) validateCodigoPostal();
    if (form.representante_email) validateRepresentanteEmail();
    
    ['Nombre', 'Materno'].forEach(field => validateText(field));

    // Mínimo indispensable (a pedido del negocio): sólo el NOMBRE.
    const camposMinimos = ['tipo_persona', 'Nombre'];
    const faltanMinimos = camposMinimos.some(f => !form[f] || !form[f].toString().trim());
    if (faltanMinimos) {
        activeTab.value = 'generales';
        notify.error('El nombre es obligatorio.', 'Datos mínimos');
        return;
    }

    // El resto de avisos (apellido paterno vacío, RFC con formato raro, etc.)
    // NO bloquean: se avisa y se continúa con el guardado.
    if (!isFormValid.value) {
        notify.warn('Hay campos con observaciones; se guardará de todos modos.', 'Aviso');
    }

    // Inertia sigue el redirect a personas.index; el toast de exito lo muestra el
    // manejador global de flash (AppLayout -> useFlashMessages).
    form.put(route('personas.update', props.persona.id_persona), {
        onError: (errors) => {
            notify.error(
                errors.rfc
                    ? 'El RFC que ingresaste ya esta registrado en otra persona.'
                    : 'Revisa los errores marcados en el formulario.',
                'No se pudo actualizar',
            );
        },
    });
};
</script>

<style scoped>
/* ============================================
   TODOS LOS ESTILOS IGUALES QUE EN CREATE
   ============================================ */

/* ===== HEADER PREMIUM ===== */
.header-premium {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 20px;
    padding: 20px 24px;
    margin-bottom: 8px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
    border: 1px solid #f0f2f5;
}

.header-content-premium {
    display: flex;
    flex-direction: column;
    gap: 12px;
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
    gap: 14px;
}

.btn-back-premium {
    padding: 8px;
    color: #94a3b8;
    border-radius: 12px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-back-premium:hover {
    color: #1a3a5c;
    background: #f1f5f9;
    transform: translateX(-2px);
}

.header-icon-wrapper {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(26, 58, 92, 0.2);
}

.header-icon-svg {
    width: 24px;
    height: 24px;
    stroke: white;
}

.header-title-premium {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.5px;
}

.title-highlight {
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.header-subtitle-premium {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #64748b;
    font-size: 13px;
    margin: 2px 0 0 0;
}

.subtitle-line {
    width: 20px;
    height: 2px;
    background: linear-gradient(90deg, #1a3a5c, transparent);
    border-radius: 2px;
}

.header-hint {
    padding-left: 10px;
    border-left: 1px solid #d1d5db;
    font-size: 12px;
    color: #9ca3af;
}

.header-hint strong {
    color: #dc2626;
}

.header-right-premium {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.status-badge-premium {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 18px;
    border-radius: 24px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.badge-icon {
    width: 16px;
    height: 16px;
}

.status-success {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
}

.status-error {
    background: linear-gradient(135deg, #fecaca, #fca5a5);
    color: #991b1b;
    animation: shake 0.5s ease;
}

.status-progress {
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    color: #4338ca;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
}

/* ===== PAGE CONTENT ===== */
.page-content-premium {
    padding: 0.75rem 0;
}

.container-premium {
    max-width: 100%;
    margin: 0 auto;
    padding: 0 1.25rem;
}

.form-card-premium {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    border: 1px solid #f1f3f5;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 225px);
    overflow: hidden;
    transition: all 0.3s ease;
}

#personaForm {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}

.form-card-premium:hover {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
}

/* ===== TABS ===== */
.tabs-premium {
    display: flex;
    gap: 4px;
    margin-bottom: 14px;
    border-bottom: 2px solid #f1f3f5;
    padding-bottom: 2px;
    flex-shrink: 0;
    flex-wrap: wrap;
    background: #fafbfc;
    border-radius: 12px;
    padding: 4px;
}

.tab-premium {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    background: transparent;
    border: none;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    position: relative;
}

.tab-premium:hover {
    color: #1f2937;
    background: rgba(26, 58, 92, 0.06);
}

.tab-premium.active {
    color: #1a3a5c;
    background: white;
    box-shadow: 0 2px 8px rgba(26, 58, 92, 0.12);
}

.tab-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.tab-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.error-dot {
    background: #ef4444;
    animation: pulse-dot 1.5s ease-in-out infinite;
}

.success-dot {
    background: #10b981;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

/* ===== TAB CONTENT ===== */
.tab-content-premium {
    flex: 1;
    overflow-y: auto;
    padding-right: 4px;
    min-height: 0;
}

.tab-content-premium::-webkit-scrollbar {
    width: 5px;
}

.tab-content-premium::-webkit-scrollbar-track {
    background: #f1f3f5;
    border-radius: 4px;
}

.tab-content-premium::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.tab-content-premium::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

.tab-pane-premium {
    display: flex;
    flex-direction: column;
    gap: 14px;
    animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ===== SECCIONES ===== */
.section-premium {
    background: linear-gradient(135deg, #fafbfc, #ffffff);
    border-radius: 12px;
    padding: 14px 18px 16px;
    border: 1px solid #f1f3f5;
    transition: all 0.25s ease;
    flex-shrink: 0;
}

.section-premium:hover {
    border-color: #e5e7eb;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.section-tipo { border-left: 4px solid #1a3a5c; }
.section-datos { border-left: 4px solid #1a3a5c; }
.section-contacto { border-left: 4px solid #1a3a5c; }
.section-direccion { border-left: 4px solid #1a3a5c; }
.section-notas { border-left: 4px solid #1a3a5c; }
.section-representante { border-left: 4px solid #1a3a5c; }

.section-header-premium {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.section-icon-premium {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    color: white;
    flex-shrink: 0;
    background: #1a3a5c;
}

.icon-sm {
    width: 16px;
    height: 16px;
}

.section-title-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    flex-wrap: wrap;
}

.section-title-premium {
    font-size: 0.9rem;
    font-weight: 700;
    color: #1f2937;
    letter-spacing: 0.3px;
}

.badge-required {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 700;
    border: 1px solid #93c5fd;
}

.badge-optional {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    color: #6b7280;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    border: 1px solid #d1d5db;
}

.optional {
    font-weight: 400;
    color: #9ca3af;
    font-size: 0.65rem;
}

.star {
    color: #ef4444;
    font-weight: 700;
}

/* ===== GRID SISTEM ===== */
.grid-nombres {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px 14px;
}

.grid-3 {
    display: grid;
    grid-template-columns: 1fr 1.2fr 1fr;
    gap: 10px 14px;
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 14px;
}

.grid-contacto {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 10px 14px;
}

.grid-direccion {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px 14px;
}

.grid-direccion .field-premium {
    grid-column: span 3;
}

.grid-direccion .field-premium.full {
    grid-column: 1 / -1;
}

.grid-direccion .dir-cp,
.grid-direccion .dir-estado,
.grid-direccion .dir-municipio {
    grid-column: span 2;
}

/* Fila 2: Colonia + Calle + Núm. Exterior + Núm. Interior (en la misma línea) */
.grid-direccion .dir-colonia,
.grid-direccion .dir-calle {
    grid-column: span 2;
}

.grid-direccion .dir-ext,
.grid-direccion .dir-int {
    grid-column: span 1;
}

.hint-premium {
    font-size: 0.7rem;
    color: #9ca3af;
    line-height: 1.3;
}

.input-readonly {
    background: #f1f5f9;
    color: #475569;
    cursor: default;
    border-color: #e2e8f0 !important;
}

.input-readonly:focus {
    box-shadow: none !important;
    border-color: #e2e8f0 !important;
}

.cp-spinner {
    position: absolute;
    right: 12px;
    top: 50%;
    width: 14px;
    height: 14px;
    margin-top: -7px;
    border: 2px solid #d1d5db;
    border-top-color: #1a3a5c;
    border-radius: 50%;
    animation: cp-spin 0.7s linear infinite;
}

@keyframes cp-spin {
    to { transform: rotate(360deg); }
}

.grid-representante {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px 14px;
}

.field-premium {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.field-premium.full {
    grid-column: 1 / -1;
}

/* ===== LABELS ===== */
.label-premium {
    font-size: 0.78rem;
    font-weight: 600;
    color: #4b5563;
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

/* ===== INPUT WRAPPER ===== */
.input-wrapper-premium {
    position: relative;
}

.input-wrapper-premium .input-premium,
.input-wrapper-premium .select-premium {
    padding-right: 38px;
}

.input-icon-premium {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: #9ca3af;
    pointer-events: none;
    transition: all 0.2s ease;
}

.input-wrapper-premium:focus-within .input-icon-premium {
    color: #1a3a5c;
}

/* ===== INPUTS ===== */
.input-premium {
    width: 100%;
    padding: 9px 38px 9px 14px;
    font-size: 0.85rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    color: #1f2937;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    outline: none;
    height: 42px;
}

.input-premium:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92, 0.1);
    transform: translateY(-1px);
}

.input-premium:hover:not(:focus) {
    border-color: #9ca3af;
}

.input-premium.error {
    border-color: #ef4444;
    background: #fef2f2;
}

.input-premium.error:focus {
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
}

.input-premium.text-uppercase {
    text-transform: uppercase;
}

.input-premium.date {
    padding: 8px 38px 8px 14px;
}

/* ===== SELECT ===== */
.select-premium {
    width: 100%;
    padding: 9px 38px 9px 14px;
    font-size: 0.85rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    color: #1f2937;
    outline: none;
    height: 42px;
    appearance: none;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 18px;
}

.select-premium:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92, 0.1);
    transform: translateY(-1px);
}

.select-premium:hover:not(:focus) {
    border-color: #9ca3af;
}

.select-premium.error {
    border-color: #ef4444;
    background-color: #fef2f2;
}

/* ===== CHECKBOX ===== */
.checkbox-wrapper-premium {
    display: flex;
    align-items: center;
    height: 42px;
}

.checkbox-premium {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 8px;
    transition: all 0.2s ease;
    user-select: none;
    background: #f8fafc;
    border: 2px solid #e5e7eb;
    min-width: 100px;
    height: 42px;
}

.checkbox-premium:hover {
    border-color: #1a3a5c;
    background: #f0f4ff;
}

.checkbox-premium.checked {
    border-color: #1a3a5c;
    background: #f0f4ff;
}

.checkbox-premium input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}

.checkbox-custom {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    border: 2px solid #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
    background: white;
}

.checkbox-premium.checked .checkbox-custom {
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    border-color: #1a3a5c;
    box-shadow: 0 2px 8px rgba(26, 58, 92, 0.3);
}

.checkbox-icon {
    width: 14px;
    height: 14px;
    color: white;
    animation: popIn 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.checkbox-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #4b5563;
    transition: color 0.2s ease;
}

.checkbox-premium.checked .checkbox-label {
    color: #1a3a5c;
}

@keyframes popIn {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.3); }
    100% { transform: scale(1); opacity: 1; }
}

/* ===== TEXTAREA ===== */
.textarea-premium {
    width: 100%;
    padding: 10px 14px;
    font-size: 0.85rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    color: #1f2937;
    outline: none;
    font-family: inherit;
    resize: vertical;
    min-height: 120px;
    max-height: 180px;
    transition: all 0.2s ease;
}

.textarea-premium:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 4px rgba(26, 58, 92, 0.1);
}

.textarea-premium:hover:not(:focus) {
    border-color: #9ca3af;
}

/* ===== CHAR COUNTER ===== */
.char-counter {
    font-size: 0.7rem;
    color: #9ca3af;
    margin-top: 4px;
    text-align: right;
}

/* ===== RADIO ===== */
.radio-group-premium {
    display: flex;
    gap: 12px;
}

.radio-premium {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 18px;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.85rem;
    font-weight: 500;
    color: #4b5563;
    height: 42px;
    position: relative;
    flex: 1;
}

.radio-premium:hover {
    border-color: #1a3a5c;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 58, 92, 0.08);
}

.radio-premium.selected {
    border-color: #1a3a5c;
    background: linear-gradient(135deg, #f0f4ff, #e8edff);
    color: #1f2937;
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.12);
}

.radio-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    color: #6b7280;
}

.radio-premium.selected .radio-icon {
    color: #1a3a5c;
}

.radio-check {
    margin-left: auto;
    animation: popIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.check-icon {
    width: 20px;
    height: 20px;
}

.radio-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.radio-group-sm {
    display: flex;
    gap: 6px;
    width: 100%;
}

.radio-sm {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 16px;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.8rem;
    font-weight: 600;
    color: #4b5563;
    height: 38px;
    min-width: 60px;
    position: relative;
    flex: 1;
}

.radio-sm:hover {
    border-color: #1a3a5c;
}

.radio-sm.selected {
    border-color: #1a3a5c;
    background: #f0f4ff;
    color: #1f2937;
}

.radio-sexo-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    color: #6b7280;
}

.radio-sm.selected .radio-sexo-icon {
    color: #1a3a5c;
}

/* ===== INPUT CON BOTON ===== */
.input-with-btn {
    position: relative;
}

.input-with-btn .input-premium {
    padding-right: 110px;
}

.btn-rfc-premium {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    padding: 5px 16px;
    background: linear-gradient(135deg, #1a3a5c, #132a44);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}

.btn-rfc-premium:hover:not(:disabled) {
    transform: translateY(-50%) scale(1.03);
    box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
}

.btn-rfc-premium:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.spinner-sm {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ===== ERROR ===== */
.error-premium {
    font-size: 0.72rem;
    color: #ef4444;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: #fef2f2;
    border-radius: 6px;
    border-left: 3px solid #ef4444;
}

/* ===== FOOTER ===== */
.footer-premium {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-top: 14px;
    margin-top: 12px;
    border-top: 2px solid #f1f3f5;
    flex-shrink: 0;
    background: white;
    padding-bottom: 2px;
}

.info-premium {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
    color: #6b7280;
}

.info-icon-sm {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.text-danger {
    color: #ef4444;
}

.actions-premium {
    display: flex;
    gap: 12px;
}

.btn-cancel-premium {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 28px;
    font-weight: 600;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 0.85rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    text-decoration: none;
    color: #6b7280;
    background: white;
    height: 42px;
}

.btn-cancel-premium:hover {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08);
}

.btn-submit-premium {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 36px;
    font-weight: 600;
    border: none;
    border-radius: 12px;
    font-size: 0.85rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    color: white;
    height: 42px;
    box-shadow: 0 2px 12px rgba(26, 58, 92, 0.2);
}

.btn-submit-premium:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(26, 58, 92, 0.35);
}

.btn-submit-premium:active:not(:disabled) {
    transform: translateY(-1px);
}

.btn-submit-premium:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-icon-sm {
    width: 18px;
    height: 18px;
}

.spinner-border-sm {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2.5px solid rgba(255, 255, 255, 0.3);
    border-right-color: transparent;
    border-radius: 50%;
    animation: spin 0.75s linear infinite;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .tabs-premium {
        gap: 4px;
        padding: 4px;
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
    }
    .tab-premium {
        font-size: 0.75rem;
        padding: 6px 14px;
        flex: 0 0 auto;
        min-width: 0;
    }
    .tab-icon { width: 16px; height: 16px; }
    .tab-premium span:not(.tab-dot) { font-size: 0.7rem; }
    
    .grid-nombres,
    .grid-3,
    .grid-2,
    .grid-contacto,
    .grid-representante {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .grid-direccion {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }

    .grid-direccion .field-premium,
    .grid-direccion .field-premium.full,
    .grid-direccion [class*="dir-"] {
        grid-column: span 1;
    }
    
    .form-card-premium {
        padding: 0.8rem 1rem;
        max-height: calc(100vh - 200px);
        border-radius: 16px;
    }
    
    .footer-premium {
        flex-direction: column;
        gap: 10px;
        align-items: stretch;
    }
    .info-premium { justify-content: center; font-size: 0.7rem; }
    .actions-premium { 
        justify-content: center;
        flex-wrap: wrap;
    }
    .btn-cancel-premium,
    .btn-submit-premium {
        flex: 1;
        justify-content: center;
        padding: 8px 18px;
        font-size: 0.8rem;
        height: 38px;
    }
    .header-wrapper-premium {
        flex-wrap: wrap;
        gap: 8px;
    }
    .header-right-premium {
        width: 100%;
        justify-content: flex-start;
    }
}

@media (max-width: 480px) {
    .section-premium { 
        padding: 10px 12px 12px; 
        border-radius: 10px;
    }
    .input-premium {
        font-size: 0.8rem;
        height: 38px;
        padding: 7px 34px 7px 12px;
    }
    .select-premium {
        font-size: 0.8rem;
        height: 38px;
        padding: 7px 34px 7px 12px;
    }
    .label-premium { 
        font-size: 0.72rem; 
    }
    .section-title-premium { 
        font-size: 0.82rem; 
    }
    .status-badge-premium {
        font-size: 0.7rem;
        padding: 4px 12px;
    }
    .header-title-premium { 
        font-size: 1.05rem; 
    }
    .radio-premium {
        font-size: 0.8rem;
        height: 36px;
        padding: 6px 14px;
    }
    .radio-sm {
        font-size: 0.75rem;
        height: 34px;
        padding: 4px 12px;
        min-width: 50px;
    }
    .radio-sexo-icon {
        width: 16px;
        height: 16px;
    }
    .form-card-premium { 
        padding: 0.6rem 0.8rem; 
        border-radius: 14px;
    }
    .grid-nombres,
    .grid-3,
    .grid-2,
    .grid-contacto,
    .grid-direccion,
    .grid-representante {
        gap: 6px;
    }
    .input-icon-premium {
        width: 16px;
        height: 16px;
        right: 10px;
    }
    .badge-required,
    .badge-optional {
        font-size: 0.55rem;
        padding: 1px 8px;
    }
    .btn-cancel-premium,
    .btn-submit-premium {
        font-size: 0.75rem;
        height: 36px;
        padding: 6px 14px;
    }
    .btn-icon-sm {
        width: 16px;
        height: 16px;
    }
    .btn-rfc-premium {
        font-size: 0.65rem;
        height: 26px;
        padding: 3px 10px;
        right: 4px;
    }
    .input-with-btn .input-premium {
        padding-right: 95px;
    }
    .textarea-premium {
        font-size: 0.8rem;
        min-height: 100px;
        padding: 8px 12px;
    }
    .char-counter {
        font-size: 0.65rem;
    }
    .checkbox-premium {
        min-width: 80px;
        height: 38px;
        padding: 4px 10px;
    }
    .checkbox-custom {
        width: 18px;
        height: 18px;
    }
    .checkbox-icon {
        width: 12px;
        height: 12px;
    }
    .checkbox-wrapper-premium {
        height: 38px;
    }
}

/* === Estilo sobrio RIC: ocultar adornos === */
.status-badge,
.status-badge-premium,
.badge-required,
.badge-optional,
.label-icon,
.input-icon-premium {
    display: none !important;
}

.input-wrapper-premium .input-premium,
.input-wrapper-premium .select-premium {
    padding-right: 14px;
}

.input-with-btn .input-premium {
    padding-right: 110px;
}
</style>
