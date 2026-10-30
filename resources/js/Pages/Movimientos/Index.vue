<template>
    <AppLayout title="RIC - Movimientos">
        <div class="py-6">
            <div class="max-w-full px-4 sm:px-6 lg:px-8">
                <!-- Selector de Empresa -->
                <div v-if="empresas.length > 0" class="empresa-selector-premium">
                    <div class="empresa-selector-content">
                        <div class="empresa-selector-label">
                            <i class="pi pi-building" style="font-size: 16px; color: #1a3a5c;"></i>
                            <span>Condominio</span>
                        </div>
                        <div class="empresa-selector-field">
                            <select 
                                v-model="empresaSeleccionada"
                                @change="cambiarEmpresa"
                                class="empresa-select-native"
                            >
                                <option 
                                    v-for="empresa in empresas" 
                                    :key="empresa.id" 
                                    :value="empresa.id"
                                >
                                    {{ empresa.nombre_empresa }}
                                </option>
                            </select>
                        </div>
                        <div class="empresa-selector-actions">
                            <div class="view-toggle-group">
                                <button 
                                    class="view-toggle-btn" 
                                    :class="{ active: vistaActual === 'normal' }"
                                    @click="cambiarVista('normal')"
                                >
                                    <svg class="view-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                    </svg>
                                    Polizas
                                </button>
                                <button 
                                    class="view-toggle-btn" 
                                    :class="{ active: vistaActual === 'diferidas' }"
                                    @click="cambiarVista('diferidas')"
                                >
                                    <svg class="view-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Diferidas
                                </button>
                                <button 
                                    class="view-toggle-btn" 
                                    :class="{ active: vistaActual === 'traspasos' }"
                                    @click="cambiarVista('traspasos')"
                                >
                                    <svg class="view-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                    </svg>
                                    Traspasos
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BARRA SUPERIOR: FECHAS + EXCEL + PDF + CHECKBOX TODOS + FILTRO FISCAL -->
                <div v-if="empresas.length > 0 && vistaActual !== 'diferidas'" class="filtros-superior-premium">
                    <div class="filtros-superior-content">
                        <!-- Tipo de fecha: un clic alterna entre Póliza / Registro / Factura -->
                        <div class="fecha-item">
                            <InputLabel>Buscar por</InputLabel>
                            <div class="tipo-fecha-toggle" role="group" aria-label="Tipo de fecha">
                                <button
                                    type="button"
                                    class="tipo-fecha-btn"
                                    :class="{ activo: tipoFecha === 'poliza' }"
                                    @click="cambiarTipoFecha('poliza')"
                                    title="Fecha de la póliza: cuándo se hizo el gasto"
                                >
                                    <i class="pi pi-calendar"></i>
                                    Póliza
                                </button>
                                <button
                                    type="button"
                                    class="tipo-fecha-btn"
                                    :class="{ activo: tipoFecha === 'registro' }"
                                    @click="cambiarTipoFecha('registro')"
                                    title="Fecha de registro: cuándo se capturó en el sistema (usuario, fecha y hora)"
                                >
                                    <i class="pi pi-clock"></i>
                                    Registro
                                </button>
                                <button
                                    type="button"
                                    class="tipo-fecha-btn"
                                    :class="{ activo: tipoFecha === 'factura' }"
                                    @click="cambiarTipoFecha('factura')"
                                    title="Fecha en que se emitió el comprobante (solo pólizas fiscales)"
                                >
                                    <i class="pi pi-file"></i>
                                    Factura
                                </button>
                            </div>
                        </div>

                        <!-- Fechas (aplican al tipo de fecha elegido) -->
                        <div class="fecha-item">
                            <InputLabel>Desde</InputLabel>
                            <input
                                type="date"
                                v-model="fechaDesde"
                                @change="aplicarFiltros"
                                :max="fechaActual"
                                class="fecha-input-premium"
                            />
                        </div>
                        <div class="fecha-item">
                            <InputLabel>Hasta</InputLabel>
                            <input
                                type="date"
                                v-model="fechaHasta"
                                @change="onFechaHastaChange"
                                class="fecha-input-premium"
                            />
                        </div>
                        <div class="fecha-item fecha-actions">
                            <button type="button" class="btn-hoy-premium" @click="setFechaHoy">
                                <i class="pi pi-calendar"></i>
                                Hoy
                            </button>
                            <button
                                v-if="filtros.fecha_desde || filtros.fecha_hasta || filtros.factura_desde || filtros.factura_hasta || filtros.registro_desde || filtros.registro_hasta"
                                type="button"
                                class="btn-limpiar-fechas"
                                @click="limpiarFechas"
                            >
                                <i class="pi pi-times"></i>
                                Limpiar
                            </button>
                        </div>

                        <!-- Separador -->
                        <div class="filtros-separator"></div>

                        <!-- FILTRO: SOLO FISCALES -->
                        <div class="fecha-item checkbox-fiscal-item">
                            <label class="checkbox-fiscal-label">
                                <input
                                    type="checkbox"
                                    v-model="soloFiscales"
                                    @change="aplicarFiltros"
                                    class="checkbox-fiscal-input"
                                />
                                <span class="checkbox-fiscal-text">
                                    <i class="pi pi-file-pdf" style="font-size: 14px;"></i>
                                    Solo Fiscales
                                </span>
                            </label>
                        </div>

                        <!-- Separador -->
                        <div class="filtros-separator"></div>

                        <!-- Botones Exportacion -->
                        <div class="fecha-item fecha-actions-export">
                            <button type="button" class="btn-export-excel-mini" @click="exportarExcel">
                                <i class="pi pi-file-excel"></i>
                                Excel
                            </button>
                            <button type="button" class="btn-export-pdf-mini" @click="exportarPdf">
                                <i class="pi pi-file-pdf"></i>
                                PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sin cuentas fondeadoras asignadas: no puede generar pólizas -->
                <div v-if="empresas.length > 0 && sin_fondeadoras" class="aviso-sin-fondeadoras">
                    <i class="pi pi-exclamation-triangle"></i>
                    <span>
                        No tienes <strong>cuentas fondeadoras asignadas</strong> en este condominio, por lo que no puedes generar ningún tipo de póliza.
                        Pídele a un superusuario que te las asigne.
                    </span>
                </div>

                <!-- Tabla Premium -->
                <div v-if="empresas.length > 0" class="table-wrapper-premium">
                    <!-- Header de la tabla -->
                    <div class="table-header-ultra">
                        <div class="table-header-left-ultra">
                            <span v-if="filtrosActivos" class="filter-tag-ultra">
                                <span class="filter-dot-active"></span>
                                Filtros activos
                            </span>
                            <span v-if="vistaActual === 'diferidas' && movimientos.data && movimientos.data.length > 0" class="total-pendiente-badge">
                                <span class="total-pendiente-label">Total pendiente:</span>
                                <span class="total-pendiente-value">${{ formatNumber(totalPendiente) }}</span>
                            </span>
                        </div>
                        <div class="table-header-right-ultra">
                            <div class="btn-group-actions">
                                <!-- SELECTOR DE COLUMNAS -->
                                <ColumnSelector 
                                    :columnas="columnasDisponibles"
                                    v-model="columnasActivas"
                                    :vista="vistaActual"
                                    storage-key="columnas_visibles"
                                />

                                <!-- NUEVA POLIZA -->
                                <Link
                                    v-if="permisos?.puede_crear"
                                    :href="route('movimientos.create')"
                                    class="btn-nueva-poliza"
                                    :class="{ 'btn-bloqueado': sin_fondeadoras }"
                                    :title="sin_fondeadoras ? 'Sin cuentas fondeadoras asignadas' : ''"
                                    @click="avisarSinFondeadoras"
                                >
                                    <i class="pi pi-plus"></i>
                                    Nueva Poliza
                                </Link>
                                
                                <!-- NOMINA (oculta en RIC Condominios: config/condominios.php) -->
                                <Link
                                    v-if="permisos?.puede_crear && !$page.props.modulos_ocultos?.includes('nomina')"
                                    :href="route('movimientos.nomina.create')" 
                                    class="btn-nomina-poliza"
                                    :class="{ 'btn-bloqueado': sin_fondeadoras }"
                                    :title="sin_fondeadoras ? 'Sin cuentas fondeadoras asignadas' : ''"
                                    @click="avisarSinFondeadoras"
                                >
                                    <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Nomina
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA UNIFICADA (siempre visible, aunque no haya resultados) -->
                    <div class="table-scroll-container">
                        <DataTable
                            :value="movimientos.data || []"
                            :loading="loading"
                            data-key="id_movimiento"
                            lazy
                            :sort-field="filtros.sort_by === 'fecha_vencimiento' ? 'vencimiento' : filtros.sort_by"
                            :sort-order="filtros.sort_order === 'asc' ? 1 : -1"
                            scrollable
                            scroll-height="500px"
                            row-hover
                            :row-class="getRowClassName"
                            resizable-columns
                            column-resize-mode="fit"
                            table-style="width: 100%; min-width: 1100px; table-layout: fixed"
                            class="movimiento-table-ultra"
                            @sort="handleTableChange"
                        >
                            <Column
                                v-for="col in columnasActuales"
                                :key="col.key"
                                :header="col.key === 'fecha_poliza' ? TITULO_COLUMNA_FECHA[tipoFecha] : col.title"
                                :sort-field="col.sorter ? col.key : undefined"
                                :sortable="!!col.sorter"
                                :style="{ width: col.width, textAlign: col.align || 'left' }"
                            >
                                <template #body="{ data: record }">
                                <!-- REFERENCIA -->
                                <template v-if="col.key === 'referencia'">
                                    <div class="referencia-cell">
                                        <Link
                                            :href="route('movimientos.show', record.id_movimiento)"
                                            class="referencia-link"
                                        >
                                            <span class="referencia-text-ultra">{{ record.referencia || '—' }}</span>
                                            <span v-if="record.es_fiscal && !pdfJuntoAReferencia" class="fiscal-icon" title="Poliza Fiscal">
                                                <i class="pi pi-file-pdf" style="font-size: 12px; color: #10b981;"></i>
                                            </span>
                                            <span v-if="record.es_traspaso" class="traspaso-icon" title="Traspaso">
                                                <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #1a3a5c;">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                                </svg>
                                            </span>
                                        </Link>
                                        <!-- Anotaciones de condóminos / administración (transparencia) -->
                                        <button
                                            v-if="record.anotaciones_total"
                                            type="button"
                                            class="btn-anotaciones"
                                            :class="{ abiertas: record.anotaciones_abiertas }"
                                            :title="record.anotaciones_abiertas ? `${record.anotaciones_abiertas} anotación(es) sin resolver` : 'Anotaciones resueltas'"
                                            @click="abrirAnotaciones(record)"
                                        >
                                            <i :class="record.anotaciones_abiertas ? 'pi pi-exclamation-circle' : 'pi pi-comments'"></i>
                                            {{ record.anotaciones_abiertas || record.anotaciones_total }}
                                        </button>
                                        <!-- En pólizas normales el PDF va junto a la referencia (ya no hay columna PDF) -->
                                        <button
                                            v-if="pdfJuntoAReferencia"
                                            type="button"
                                            @click="verPdf(record)"
                                            :disabled="!record.tiene_pdf_fiscal"
                                            class="btn-pdf btn-pdf-inline"
                                            :title="record.tiene_pdf_fiscal ? 'Abrir Comprobante Fiscal PDF' : (record.es_fiscal ? 'Sin PDF Fiscal' : 'Póliza no fiscal')"
                                        >
                                            <i class="pi pi-file-pdf" :style="{ color: getPdfColor(record), fontSize: '16px' }"></i>
                                        </button>
                                    </div>
                                </template>

                                <!-- TIPO POLIZA -->
                                <template v-else-if="col.key === 'tipo_poliza'">
                                    <span class="tipo-badge" :class="getTipoClass(record.tipo_poliza)">
                                        {{ record.tipo_poliza || '—' }}
                                    </span>
                                </template>

                                <!-- CUENTA DESTINO (para traspasos) -->
                                <template v-else-if="col.key === 'cuenta_destino'">
                                    <span class="cuenta-text-ultra">
                                        {{ record.cuenta_destino || '—' }}
                                    </span>
                                </template>

                                <!-- FECHA POLIZA -->
                                <template v-else-if="col.key === 'fecha_poliza'">
                                    <div class="fecha-usuario-cell">
                                        <span v-if="tipoFecha === 'factura'" class="fecha-text-ultra" title="Fecha de emisión del comprobante">
                                            {{ record.fecha_factura ? formatFechaSolo(record.fecha_factura) : 'Sin factura' }}
                                        </span>
                                        <span v-else-if="tipoFecha === 'registro'" class="fecha-text-ultra" title="Fecha en que se capturó en el sistema">
                                            {{ formatFecha(record.created_at) }}
                                        </span>
                                        <span v-else class="fecha-text-ultra" title="Fecha del gasto">{{ formatFecha(record.fecha_poliza) }}</span>
                                        <!-- Registro: quién la capturó y cuándo (fecha y hora del sistema) -->
                                        <span v-if="record.usuario" class="usuario-fecha-creacion" title="Registro (usuario | fecha | hora)">
                                            {{ record.usuario }} | {{ formatFechaHora(record.created_at) }}
                                        </span>
                                    </div>
                                </template>

                                <!-- ESTATUS -->
                                <template v-else-if="col.key === 'estatus'">
                                    <span class="estatus-badge" :class="getEstatusClass(record.estatus)">
                                        {{ record.estatus || '—' }}
                                    </span>
                                </template>

                                <!-- PERSONA -->
                                <template v-else-if="col.key === 'persona'">
                                    <span class="persona-text-ultra">{{ record.persona || '—' }}</span>
                                </template>

                                <!-- CUENTA -->
                                <template v-else-if="col.key === 'cuenta'">
                                    <span class="cuenta-text-ultra">{{ record.cuenta || '—' }}</span>
                                </template>

                                <!-- CUENTA FONDEADORA -->
                                <template v-else-if="col.key === 'cuenta_fondeadora'">
                                    <span class="cuenta-text-ultra">{{ record.cuenta_fondeadora || '—' }}</span>
                                </template>

                                <!-- NOTA -->
                                <template v-else-if="col.key === 'nota'">
                                    <span class="nota-text-ultra" :title="record.nota || ''">{{ record.nota || '—' }}</span>
                                </template>

                                <!-- MONTO -->
                                <template v-else-if="col.key === 'monto'">
                                    <span class="monto-text-ultra" :class="getMontoClass(record.monto)">
                                        ${{ formatNumber(Math.abs(record.monto)) }}
                                    </span>
                                    <span v-if="record.es_traspaso" class="traspaso-amount-badge" title="Traspaso">T
                                    </span>
                                </template>

                                <!-- ABONADO -->
                                <template v-else-if="col.key === 'abonado'">
                                    <span class="monto-text-ultra abonado-text">
                                        ${{ formatNumber(record.abonado || 0) }}
                                    </span>
                                </template>

                                <!-- SALDO PENDIENTE -->
                                <template v-else-if="col.key === 'saldo_pendiente'">
                                    <span class="monto-text-ultra" :class="getSaldoPendienteClass(record.saldo_pendiente)">
                                        ${{ formatNumber(record.saldo_pendiente || 0) }}
                                    </span>
                                </template>

                                <!-- VENCIMIENTO -->
                                <template v-else-if="col.key === 'vencimiento'">
                                    <span class="fecha-text-ultra" :class="getVencimientoClass(record.fecha_vencimiento)">
                                        {{ formatFecha(record.fecha_vencimiento) }}
                                    </span>
                                    <span v-if="isVencido(record.fecha_vencimiento)" class="vencido-icon">!</span>
                                </template>

                                <!-- USUARIO -->
                                <template v-else-if="col.key === 'usuario'">
                                    <div class="usuario-cell">
                                        <span class="usuario-text-ultra">{{ record.usuario || '—' }}</span>
                                        <span v-if="record.created_at" class="usuario-fecha-ultra">
                                            {{ formatFechaHora(record.created_at) }}
                                        </span>
                                    </div>
                                </template>

                                <!-- RECURSO -->
                                <template v-else-if="col.key === 'recurso'">
                                    <div class="recurso-cell">
                                        <button
                                            v-if="record.tiene_recurso"
                                            class="btn-recurso btn-recurso-verde"
                                            @click="verRecurso(record)"
                                            title="Ver recurso adjunto"
                                        >
                                            <svg class="btn-recurso-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span class="btn-recurso-texto">Ver</span>
                                        </button>
                                        <!-- Cuándo se subió el recurso: negro si fue dentro de los 20 min de registrada la póliza, rojo si no -->
                                        <span
                                            v-if="record.tiene_recurso && record.recurso_subido_en"
                                            class="recurso-fecha-celda"
                                            :class="record.recurso_tarde ? 'recurso-fecha-tarde' : 'recurso-fecha-ok'"
                                            :title="'Subido por ' + (record.recurso_subido_por || '—') + ' el ' + record.recurso_subido_en + (record.recurso_retraso_texto ? ' (' + record.recurso_retraso_texto + ' después de registrar la póliza)' : '')"
                                        >
                                            <span v-if="record.recurso_subido_por" class="recurso-fecha-usuario">{{ record.recurso_subido_por }}</span>
                                            <span>{{ record.recurso_subido_en.slice(0, 10) }}</span>
                                            <span>{{ record.recurso_subido_en.slice(11) }}</span>
                                        </span>
                                        <button
                                            v-else-if="permisos?.puede_subir_recursos"
                                            class="btn-recurso btn-recurso-azul"
                                            @click="abrirModalSubir(record)"
                                            title="Subir recurso (PDF o imagen)"
                                        >
                                            <svg class="btn-recurso-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            <span class="btn-recurso-texto">Subir</span>
                                        </button>
                                    </div>
                                </template>

                                <!-- PDF FISCAL -->
                                <template v-else-if="col.key === 'pdf'">
                                    <div class="pdf-cell">
                                        <button
                                            type="button"
                                            @click="verPdf(record)"
                                            :disabled="!record.tiene_pdf_fiscal"
                                            class="btn-pdf"
                                            :title="record.tiene_pdf_fiscal ? 'Abrir Comprobante Fiscal PDF' : 'Sin PDF Fiscal'"
                                        >
                                            <i class="pi pi-file-pdf" :style="{ color: getPdfColor(record), fontSize: '18px' }"></i>
                                        </button>
                                    </div>
                                </template>

                                <!-- ============================================ -->
                                <!-- ACCIONES -->
                                <!-- ============================================ -->
                                <template v-else-if="col.key === 'acciones'">
                                    <div class="acciones-cell">
                                        <!-- Botón Abonar -->
                                        <button 
                                            v-if="permisos?.puede_autorizar && record.es_por_pagar && record.saldo_pendiente > 0"
                                            class="btn-accion abonar" 
                                            @click="accionAbonar(record)"
                                            :disabled="record.estatus === 'LIQUIDADO' || record.saldo_pendiente === 0"
                                            title="Abonar"
                                        >
                                            <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Abonar
                                        </button>
                                        
                                        <!-- Botón Editar (disponible en TODAS las vistas) -->
                                        <button 
                                            v-if="record.puede_editar_esta"
                                            class="btn-accion editar" 
                                            @click="accionEditar(record)"
                                            title="Editar"
                                        >
                                            <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Editar
                                        </button>
                                        
                                        <!-- Botón Ver (si no tiene permiso de editar) -->
                                        <button 
                                            v-if="!record.puede_editar_esta"
                                            class="btn-accion ver" 
                                            @click="accionVer(record)"
                                            title="Ver"
                                        >
                                            <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Ver
                                        </button>
                                    </div>
                                </template>
                                </template>
                            </Column>

                            <template #empty>
                                <div class="tabla-vacia-inline">
                                    <i class="pi pi-inbox"></i>
                                    <span v-if="filtrosActivos">No se encontraron movimientos con los filtros aplicados.</span>
                                    <span v-else>No hay movimientos para mostrar.</span>
                                </div>
                            </template>
                        </DataTable>
                    </div>

                    <!-- FILTROS INFERIOR (Pólizas y Traspasos) -->
                    <div v-if="vistaActual !== 'diferidas'" class="filtros-inferior-tabla">
                        <div class="filtros-inferior-grid">
                            <div class="filtro-inferior-item" v-if="vistaActual === 'normal' || vistaActual === 'traspasos'">
                                <label class="filtro-inferior-label">Referencia</label>
                                <input 
                                    v-model="filtros.referencia"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    class="filtro-inferior-input"
                                />
                            </div>

                            <div class="filtro-inferior-item" v-if="vistaActual === 'normal' || vistaActual === 'traspasos'">
                                <label class="filtro-inferior-label">Estatus</label>
                                <select 
                                    v-model="filtros.estatus"
                                    @change="aplicarFiltros"
                                    class="filtro-inferior-select"
                                >
                                    <option value="">Todos</option>
                                    <option value="CAPTURADO">Capturado</option>
                                    <option value="REVISADO">Revisado</option>
                                    <option value="AUTORIZADO">Autorizado</option>
                                    <option value="PENDIENTE">Pendiente</option>
                                    <option value="ABONADO">Abonado</option>
                                    <option value="LIQUIDADO">Liquidado</option>
                                    <option value="CERRADO">Cerrado</option>
                                </select>
                            </div>

                            <div class="filtro-inferior-item">
                                <label class="filtro-inferior-label">Persona</label>
                                <input 
                                    v-model="filtros.persona"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    class="filtro-inferior-input"
                                />
                            </div>

                            <div class="filtro-inferior-item" v-if="vistaActual === 'normal'">
                                <label class="filtro-inferior-label">Cuenta</label>
                                <input 
                                    v-model="filtros.cuenta"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    class="filtro-inferior-input"
                                />
                            </div>

                            <div class="filtro-inferior-item" v-if="vistaActual === 'normal'">
                                <label class="filtro-inferior-label">Cta. Fondeo</label>
                                <input 
                                    v-model="filtros.cuenta_fondeadora"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    class="filtro-inferior-input"
                                />
                            </div>

                            <div class="filtro-inferior-item">
                                <label class="filtro-inferior-label">Nota</label>
                                <input 
                                    v-model="filtros.nota"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    class="filtro-inferior-input"
                                />
                            </div>

                            <div class="filtro-inferior-item" v-if="vistaActual === 'traspasos'">
                                <label class="filtro-inferior-label">Usuario</label>
                                <input 
                                    v-model="filtros.usuario"
                                    @input="aplicarFiltros"
                                    placeholder="Buscar..."
                                    class="filtro-inferior-input"
                                />
                            </div>

                            <div class="filtro-inferior-item filtro-inferior-actions">
                                <button
                                    v-if="filtrosActivos"
                                    class="btn-limpiar-filtros-inferior"
                                    @click="limpiarFiltros"
                                >
                                    <i class="pi pi-times"></i>
                                    Limpiar filtros
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- FILTROS DE DIFERIDAS: cada filtro va debajo de SU columna -->
                    <div v-else class="filtros-diferidas-fila">
                        <div
                            v-for="col in columnasActuales"
                            :key="col.key"
                            class="filtros-diferidas-celda"
                            :style="{ width: col.width }"
                        >
                            <input
                                v-if="col.key === 'persona'"
                                v-model="filtros.persona"
                                @input="aplicarFiltros"
                                placeholder="Buscar persona..."
                                class="filtro-inferior-input"
                            />
                            <select
                                v-else-if="col.key === 'saldo_pendiente'"
                                v-model="filtros.estado_deuda"
                                @change="aplicarFiltros"
                                class="filtro-inferior-select"
                                title="Estado de pago"
                            >
                                <option value="">Pendientes</option>
                                <option value="mayor">Se debe más</option>
                                <option value="menor">Se debe menos</option>
                                <option value="pagadas">Pagadas</option>
                            </select>
                            <button
                                v-else-if="col.key === 'acciones' && filtrosActivos"
                                type="button"
                                class="btn-limpiar-filtros-inferior"
                                @click="limpiarFiltros"
                            >
                                <i class="pi pi-times"></i> Limpiar
                            </button>
                        </div>
                    </div>

                    <!-- Paginacion (debajo de los filtros de busqueda) -->
                    <div v-if="movimientos.data && movimientos.data.length > 0" class="pagination-ultra">
                        <span class="pagination-info-ultra">Mostrando <span class="pagination-highlight-ultra">{{ movimientos.from || 0 }}</span> a 
                            <span class="pagination-highlight-ultra">{{ movimientos.to || 0 }}</span> de 
                            <span class="pagination-highlight-ultra">{{ movimientos.total || 0 }}</span> resultados
                        </span>
                        <Pagination :links="movimientos.links" />
                    </div>
                </div>

                <!-- Mensaje si no hay empresas -->
                <div v-else class="table-wrapper-premium">
                    <div class="text-center py-12">
                        <i class="pi pi-building empty-state-icon"></i>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No tienes condominios asignados</h3>
                        <p class="text-gray-500">Contacta al administrador para que te asigne un condominio.</p>
                    </div>
                </div>

                <!-- RESUMEN DE TOTALES (fuera de la tabla) -->
                <div
                    v-if="empresas.length > 0 && movimientos.data && movimientos.data.length > 0"
                    class="resumen-totales-wrapper resumen-totales-externo"
                >
                    <div class="resumen-totales-container-grande">
                        <div class="resumen-totales-header-grande">
                            <span class="resumen-totales-title-grande">Resumen de Totales</span>
                        </div>
                        <div class="resumen-totales-items-grande">
                            <div class="total-item-grande total-ingresos">
                                <div class="total-icon-grande">
                                    <svg class="total-icon-svg-grande" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                </div>
                                <div class="total-info-grande">
                                    <span class="total-label-grande">Ingresos</span>
                                    <span class="total-value-grande ingresos-value">${{ formatNumber(totalIngresos) }}</span>
                                </div>
                            </div>

                            <div class="total-item-grande total-egresos">
                                <div class="total-icon-grande">
                                    <svg class="total-icon-svg-grande" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                                    </svg>
                                </div>
                                <div class="total-info-grande">
                                    <span class="total-label-grande">Egresos</span>
                                    <span class="total-value-grande egresos-value">-${{ formatNumber(totalEgresos) }}</span>
                                </div>
                            </div>

                            <div class="total-item-grande total-traspasos" v-if="vistaActual === 'traspasos'">
                                <div class="total-icon-grande traspaso-icon">
                                    <svg class="total-icon-svg-grande" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                    </svg>
                                </div>
                                <div class="total-info-grande">
                                    <span class="total-label-grande">Traspasos</span>
                                    <span class="total-value-grande traspaso-value">${{ formatNumber(totalTraspasos) }}</span>
                                </div>
                            </div>

                            <div class="total-item-grande total-saldo-neto">
                                <div class="total-icon-grande saldo-icon">
                                    <svg class="total-icon-svg-grande" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                    </svg>
                                </div>
                                <div class="total-info-grande">
                                    <span class="total-label-grande">Saldo Neto</span>
                                    <span class="total-value-grande saldo-net-value">${{ formatNumber(saldoNeto) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE LIQUIDACIÓN -->
        <ModalLiquidacion
            ref="modalLiquidacion"
            :movimiento="movimientoSeleccionado"
            :cuentas-fondeadoras="cuentasFondeadorasDisponibles"
            @liquidado="onLiquidado"
        />

        <!-- MODAL PARA SUBIR/VER RECURSO -->
        <Dialog
            v-model:visible="modalRecursoVisible"
            modal
            class="modal-recurso-premium"
            :class="{ 'modal-recurso-premium-ver': modalRecursoModo === 'ver' }"
            :style="{ width: '95vw', maxWidth: '1200px' }"
        >
            <template #header>
                <div class="recurso-header">
                    <span class="recurso-header-titulo">{{ modalRecursoTitulo }}</span>
                    <!-- Datos de la póliza: chips en UNA SOLA LÍNEA, siempre en negro -->
                    <div
                        v-if="modalRecursoModo === 'ver' && modalRecursoMovimiento"
                        class="recurso-datos-poliza"
                    >
                        <span class="recurso-chip">
                            <span class="recurso-chip-label">Tipo</span>
                            <span class="recurso-chip-valor">{{ modalRecursoMovimiento.tipo_poliza || '—' }}</span>
                        </span>
                        <span class="recurso-chip">
                            <span class="recurso-chip-label">Usuario</span>
                            <span class="recurso-chip-valor">{{ modalRecursoMovimiento.usuario || '—' }}</span>
                        </span>
                        <span class="recurso-chip">
                            <span class="recurso-chip-label">Registro</span>
                            <span class="recurso-chip-valor">{{ formatFechaHora(modalRecursoMovimiento.created_at) }}</span>
                        </span>
                        <span class="recurso-chip">
                            <span class="recurso-chip-label">Persona</span>
                            <span class="recurso-chip-valor">{{ modalRecursoMovimiento.persona || '—' }}</span>
                        </span>
                        <span class="recurso-chip">
                            <span class="recurso-chip-label">Monto</span>
                            <span class="recurso-chip-valor">${{ formatNumber(Math.abs(modalRecursoMovimiento.monto || 0)) }}</span>
                        </span>
                        <span class="recurso-chip">
                            <span class="recurso-chip-label">Fiscal</span>
                            <span class="recurso-chip-valor">{{ modalRecursoMovimiento.es_fiscal ? 'Sí' : 'No' }}</span>
                        </span>
                    </div>
                </div>
            </template>

            <div class="modal-recurso-content" :class="{ 'modal-recurso-content-ver': modalRecursoModo === 'ver' }">
                <!-- Si es para ver -->
                <div v-if="modalRecursoModo === 'ver' && modalRecursoUrl">
                    <div v-if="modalRecursoTipo === 'pdf'" class="recurso-pdf-wrapper">
                        <iframe :src="modalRecursoUrl" class="recurso-pdf" frameborder="0"></iframe>
                    </div>
                    <div v-else-if="modalRecursoTipo === 'image'" class="recurso-image-wrapper">
                        <img :src="modalRecursoUrl" alt="Recurso adjunto" class="recurso-image" />
                    </div>
                    <div v-else class="recurso-other">
                        <div class="recurso-other-icon">
                            <svg class="recurso-other-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="recurso-other-text">Vista previa no disponible para este tipo de archivo</p>
                        <a :href="modalRecursoUrl" target="_blank" class="recurso-other-link">Descargar archivo</a>
                    </div>
                </div>

                <!-- Si es para subir o reemplazar -->
                <div v-if="modalRecursoModo === 'subir' || modalRecursoModo === 'reemplazar'" class="recurso-upload-wrapper">
                    <div class="recurso-upload-info">
                        <p v-if="modalRecursoModo === 'reemplazar'">Selecciona el nuevo <strong>PDF</strong> o <strong>imagen</strong>. El archivo actual se reemplazará.</p>
                        <p v-else>Sube un <strong>PDF</strong> o una <strong>imagen</strong> (JPG, PNG, GIF) para esta póliza.</p>
                        <p class="recurso-upload-hint">Solo se permite un archivo por póliza.</p>
                    </div>

                    <form @submit.prevent="guardarRecurso">
                        <div class="recurso-drop-zone" 
                             :class="{ 'recurso-drop-zone-dragover': dragging }"
                             @dragover.prevent="dragging = true"
                             @dragleave.prevent="dragging = false"
                             @drop.prevent="onDrop"
                             @click="$refs.recursoFileInput.click()"
                        >
                            <div class="recurso-drop-zone-content">
                                <svg class="recurso-drop-zone-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span v-if="!archivoSeleccionado" class="recurso-drop-zone-text">
                                    Arrastra y suelta tu archivo aquí, o haz clic para seleccionarlo
                                </span>
                                <span v-else class="recurso-drop-zone-text archivo-seleccionado">
                                    <svg class="archivo-seleccionado-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ archivoSeleccionado.name }} ({{ formatFileSize(archivoSeleccionado.size) }})
                                </span>
                                <span class="recurso-drop-zone-hint">Formatos permitidos: PDF, JPG, PNG, GIF, WEBP</span>
                            </div>
                            <input 
                                type="file" 
                                ref="recursoFileInput" 
                                @change="onFileSelect" 
                                accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,image/*"
                                class="recurso-file-input-hidden"
                            >
                        </div>

                        <div v-if="errorRecurso" class="recurso-error">
                            <svg class="error-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ errorRecurso }}
                        </div>

                        <div class="recurso-modal-actions">
                            <button type="button" class="btn-modal-cancel" @click="cerrarModalRecurso">Cancelar</button>
                            <button type="submit" class="btn-modal-submit" :disabled="!archivoSeleccionado || subiendoRecurso">
                                <span v-if="subiendoRecurso" class="spinner-border-sm"></span>
                                <span v-else>{{ modalRecursoModo === 'reemplazar' ? 'Reemplazar archivo' : 'Subir archivo' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PIE DEL MODAL (solo en modo ver): todo en UNA SOLA LÍNEA -->
            <template #footer>
                <div v-if="modalRecursoModo === 'ver'" class="recurso-footer">
                    <!-- Izquierda: quién subió el recurso y cuándo (rojo si fue más de 20 min después del registro) -->
                    <div class="recurso-footer-left">
                        <div
                            v-if="!modalRecursoEsFiscal && modalRecursoMovimiento?.recurso_subido_en"
                            class="recurso-subida-linea"
                            :class="modalRecursoMovimiento.recurso_tarde ? 'recurso-subida-linea-tarde' : 'recurso-subida-linea-ok'"
                        >
                            <i class="pi pi-upload"></i>
                            <span>
                                Subido por <strong>{{ modalRecursoMovimiento.recurso_subido_por || '—' }}</strong>
                                el {{ modalRecursoMovimiento.recurso_subido_en }}
                                <template v-if="modalRecursoMovimiento.recurso_retraso_texto">
                                    ({{ modalRecursoMovimiento.recurso_retraso_texto }} después del registro)
                                </template>
                            </span>
                        </div>
                    </div>
                    <!-- Derecha: botones de acción -->
                    <div class="recurso-footer-right">
                        <button type="button" class="btn-modal-submit" @click="abrirRecursoEnPestana">
                            <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Ver
                        </button>
                        <a :href="modalRecursoUrl" target="_blank" class="btn-modal-submit btn-modal-submit-outline" download>
                            <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Descargar
                        </a>
                        <button
                            v-if="permisos.puede_editar && !modalRecursoEsFiscal"
                            type="button"
                            class="btn-modal-submit btn-modal-submit-outline"
                            @click="modoReemplazarRecurso"
                        >
                            <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Reemplazar
                        </button>
                        <button
                            v-if="permisos.puede_editar && !modalRecursoEsFiscal"
                            type="button"
                            class="btn-modal-cancel btn-modal-cancel-danger"
                            @click="eliminarRecurso"
                        >
                            <svg class="btn-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Eliminar
                        </button>
                    </div>
                </div>
            </template>
        </Dialog>

        <PolizaTransparencia v-model:visible="dialogoAnotaciones" :poliza-id="polizaAnotaciones" @cambio="alCambiarAnotaciones" />
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PolizaTransparencia from '@/Components/PolizaTransparencia.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import Pagination from '@/Components/Pagination.vue';
import InputLabel from '@/Components/InputLabel.vue';
import ColumnSelector from '@/Components/ColumnSelector.vue';
import ModalLiquidacion from '@/Components/ModalLiquidacion.vue';
import axios from 'axios';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import { useNotify } from '@/composables/useNotify';
import { useEmpresa } from '@/composables/useEmpresa';

// ============================================
// PERMISOS
// ============================================
const page = usePage();
const permisos = computed(() => page.props.permisos || {});

const props = defineProps({
    movimientos: {
        type: Object,
        default: () => ({ data: [], from: 0, to: 0, total: 0, links: [] })
    },
    empresas: {
        type: Array,
        default: () => []
    },
    filtros: {
        type: Object,
        default: () => ({})
    },
    flash: {
        type: Object,
        default: () => ({})
    },
    empresa_seleccionada: {
        type: [Number, String],
        default: null
    },
    saldo_total: {
        type: [Number, null],
        default: null
    },
    resumen_totales: {
        type: Object,
        default: null
    },
    vista: {
        type: String,
        default: 'normal'
    },
    // true cuando el usuario no tiene ninguna cuenta fondeadora asignada en la empresa
    sin_fondeadoras: {
        type: Boolean,
        default: false
    },
    cuentas_fondeadoras: {
        type: Array,
        default: () => []
    }
});

const { empresaSeleccionada, cargarEmpresaGuardada, guardarEmpresa } = useEmpresa();

// ============================================
// REFS
// ============================================
const notify = useNotify();
const loading = ref(false);

// Anotaciones de transparencia (condominios): alerta junto al folio.
const dialogoAnotaciones = ref(false);
const polizaAnotaciones = ref(null);
const abrirAnotaciones = (record) => {
    polizaAnotaciones.value = record.id_poliza;
    dialogoAnotaciones.value = true;
};
const alCambiarAnotaciones = ({ polizaId, total, abiertas }) => {
    for (const fila of props.movimientos.data.filter((m) => m.id_poliza === polizaId)) {
        fila.anotaciones_total = total;
        fila.anotaciones_abiertas = abiertas;
    }
};
const modalLiquidacion = ref(null);
const movimientoSeleccionado = ref(null);

// MODAL RECURSO
const modalRecursoVisible = ref(false);
const modalRecursoModo = ref('ver');
const modalRecursoTitulo = ref('');
const modalRecursoUrl = ref('');
// true cuando el modal muestra el PDF de una póliza fiscal (no un recurso subido)
const modalRecursoEsFiscal = ref(false);
const modalRecursoTipo = ref('');
const modalRecursoId = ref(null);
const modalRecursoMovimiento = ref(null);

// Archivos
const archivoSeleccionado = ref(null);
const subiendoRecurso = ref(false);
const errorRecurso = ref('');
const dragging = ref(false);
const recursoFileInput = ref(null);

const obtenerFechaLocal = () => {
    const hoy = new Date();
    const year = hoy.getFullYear();
    const month = String(hoy.getMonth() + 1).padStart(2, '0');
    const day = String(hoy.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const fechaActual = ref(obtenerFechaLocal());
const vistaActual = ref(props.vista || 'normal');
const mostrarTodos = ref(false);
const soloFiscales = ref(false);
const columnasActivas = ref([]);
let timeoutId = null;

const cuentasFondeadorasDisponibles = computed(() => {
    if (props.cuentas_fondeadoras && props.cuentas_fondeadoras.length > 0) {
        return props.cuentas_fondeadoras;
    }
    return [];
});

// ============================================
// COLUMNAS
// ============================================
const getColumnasDisponibles = () => {
    if (vistaActual.value === 'diferidas') {
        return [
            { key: 'vencimiento', title: 'Vencimiento', required: true, visibleByDefault: true },
            { key: 'persona', title: 'Persona', required: true, visibleByDefault: true },
            { key: 'nota', title: 'Nota', required: false, visibleByDefault: true },
            { key: 'monto', title: 'Monto', required: true, visibleByDefault: true },
            { key: 'abonado', title: 'Abonado', required: false, visibleByDefault: true },
            { key: 'saldo_pendiente', title: 'Saldo Pendiente', required: true, visibleByDefault: true },
            { key: 'usuario', title: 'Usuario', required: false, visibleByDefault: true },
            { key: 'recurso', title: 'Recurso', required: false, visibleByDefault: true },
            { key: 'pdf', title: 'PDF', required: false, visibleByDefault: true },
            { key: 'acciones', title: 'Acciones', required: true, visibleByDefault: true },
        ];
    } else if (vistaActual.value === 'traspasos') {
        return [
            { key: 'referencia', title: 'Referencia', required: true, visibleByDefault: true },
            { key: 'tipo_poliza', title: 'Tipo', required: true, visibleByDefault: true },
            { key: 'fecha_poliza', title: 'Fecha Poliza', required: false, visibleByDefault: true },
            { key: 'estatus', title: 'Estatus', required: false, visibleByDefault: true },
            { key: 'persona', title: 'Persona', required: true, visibleByDefault: true },
            { key: 'cuenta', title: 'Cuenta Origen', required: true, visibleByDefault: true },
            { key: 'cuenta_destino', title: 'Cuenta Destino', required: true, visibleByDefault: true },
            { key: 'nota', title: 'Nota', required: false, visibleByDefault: true },
            { key: 'monto', title: 'Monto', required: true, visibleByDefault: true },
            { key: 'recurso', title: 'Recurso', required: false, visibleByDefault: true },
            { key: 'pdf', title: 'PDF', required: false, visibleByDefault: true },
        ];
    } else {
        return [
            { key: 'referencia', title: 'Referencia', required: true, visibleByDefault: true },
            { key: 'fecha_poliza', title: 'Fecha Poliza', required: false, visibleByDefault: true },
            { key: 'estatus', title: 'Estatus', required: false, visibleByDefault: true },
            { key: 'persona', title: 'Persona', required: true, visibleByDefault: true },
            { key: 'cuenta', title: 'Cuenta', required: false, visibleByDefault: true },
            { key: 'cuenta_fondeadora', title: 'Cta. Fondeo', required: false, visibleByDefault: true },
            { key: 'nota', title: 'Nota', required: false, visibleByDefault: true },
            { key: 'monto', title: 'Monto', required: true, visibleByDefault: true },
            { key: 'recurso', title: 'Recurso', required: false, visibleByDefault: true },
        ];
    }
};

const columnasDisponibles = computed(() => getColumnasDisponibles());

const columnasNormal = [
    { title: 'Referencia', key: 'referencia', width: '10%', sorter: true },
    { title: 'Fecha Poliza', key: 'fecha_poliza', width: '11%', align: 'center', sorter: true },
    { title: 'Estatus', key: 'estatus', width: '8%', align: 'center' },
    { title: 'Persona', key: 'persona', width: '12%' },
    { title: 'Cuenta', key: 'cuenta', width: '13%' },
    { title: 'Cta. Fondeo', key: 'cuenta_fondeadora', width: '8%' },
    { title: 'Nota', key: 'nota', width: '22%' },
    { title: 'Monto', key: 'monto', width: '7%', align: 'right' },
    { title: 'Recurso', key: 'recurso', width: '11%', align: 'center' }
];

const columnasTraspasos = [
    { title: 'Referencia', key: 'referencia', width: '8%', sorter: true },
    { title: 'Tipo', key: 'tipo_poliza', width: '6%', align: 'center' },
    { title: 'Fecha Poliza', key: 'fecha_poliza', width: '10%', align: 'center', sorter: true },
    { title: 'Estatus', key: 'estatus', width: '8%', align: 'center' },
    { title: 'Persona', key: 'persona', width: '11%' },
    { title: 'Cuenta Origen', key: 'cuenta', width: '12%' },
    { title: 'Cuenta Destino', key: 'cuenta_destino', width: '12%' },
    { title: 'Nota', key: 'nota', width: '13%' },
    { title: 'Monto', key: 'monto', width: '8%', align: 'right' },
    { title: 'Recurso', key: 'recurso', width: '8%', align: 'center' },
    { title: 'PDF', key: 'pdf', width: '4%', align: 'center' }
];

const columnasDiferidas = [
    { title: 'Vencimiento', key: 'vencimiento', width: '9%', align: 'center', sorter: true },
    { title: 'Persona', key: 'persona', width: '12%' },
    { title: 'Nota', key: 'nota', width: '15%' },
    { title: 'Monto', key: 'monto', width: '8%', align: 'right' },
    { title: 'Abonado', key: 'abonado', width: '8%', align: 'right' },
    { title: 'Saldo Pendiente', key: 'saldo_pendiente', width: '11%', align: 'right' },
    { title: 'Usuario', key: 'usuario', width: '9%' },
    { title: 'Recurso', key: 'recurso', width: '9%', align: 'center' },
    { title: 'PDF', key: 'pdf', width: '5%', align: 'center' },
    { title: 'Acciones', key: 'acciones', width: '14%', align: 'center' }
];

const pdfJuntoAReferencia = computed(() => vistaActual.value !== 'diferidas' && vistaActual.value !== 'traspasos');

const columnasActuales = computed(() => {
    let todasLasColumnas = [];
    if (vistaActual.value === 'diferidas') {
        todasLasColumnas = columnasDiferidas;
    } else if (vistaActual.value === 'traspasos') {
        todasLasColumnas = columnasTraspasos;
    } else {
        todasLasColumnas = columnasNormal;
    }
    if (!columnasActivas.value || columnasActivas.value.length === 0) {
        return todasLasColumnas;
    }
    return todasLasColumnas.filter(col => columnasActivas.value.includes(col.key));
});

// ============================================
// TOTALES
// ============================================
const totalPendiente = computed(() => {
    if (vistaActual.value !== 'diferidas') return 0;
    if (props.resumen_totales && props.resumen_totales.pendiente !== undefined) {
        return Number(props.resumen_totales.pendiente) || 0;
    }
    if (!props.movimientos.data) return 0;
    return props.movimientos.data.reduce((sum, item) => sum + (item.saldo_pendiente || 0), 0);
});

const totalIngresos = computed(() => {
    if (props.resumen_totales) return Number(props.resumen_totales.ingresos) || 0;
    if (!props.movimientos.data || props.movimientos.data.length === 0) return 0;
    const ingresos = props.movimientos.data.filter(item => item.monto > 0);
    if (ingresos.length === 0) return 0;
    return ingresos.reduce((sum, item) => sum + Math.abs(item.monto || 0), 0);
});

const totalEgresos = computed(() => {
    if (props.resumen_totales) return Number(props.resumen_totales.egresos) || 0;
    if (!props.movimientos.data || props.movimientos.data.length === 0) return 0;
    const egresos = props.movimientos.data.filter(item => item.monto < 0);
    if (egresos.length === 0) return 0;
    return egresos.reduce((sum, item) => sum + Math.abs(item.monto || 0), 0);
});

const totalTraspasos = computed(() => {
    if (vistaActual.value !== 'traspasos') return 0;
    if (props.resumen_totales) return Number(props.resumen_totales.traspasos) || 0;
    if (!props.movimientos.data) return 0;
    return props.movimientos.data.reduce((sum, item) => sum + Math.abs(item.monto || 0), 0);
});

const saldoNeto = computed(() => {
    return totalIngresos.value - totalEgresos.value;
});

// ============================================
// FILTROS
// ============================================
const hoy = obtenerFechaLocal();

// Filtros que SÍ aplican en la vista Diferidas (las fechas y "solo fiscales" no).
const FILTROS_EN_DIFERIDAS = ['persona', 'estado_deuda'];

const filtros = ref({
    fecha_desde: props.filtros?.fecha_desde || '',
    fecha_hasta: props.filtros?.fecha_hasta || '',
    factura_desde: props.filtros?.factura_desde || '',
    factura_hasta: props.filtros?.factura_hasta || '',
    registro_desde: props.filtros?.registro_desde || '',
    registro_hasta: props.filtros?.registro_hasta || '',
    estado_deuda: props.filtros?.estado_deuda || '',
    referencia: props.filtros?.referencia || '',
    estatus: props.filtros?.estatus || '',
    persona: props.filtros?.persona || '',
    cuenta: props.filtros?.cuenta || '',
    cuenta_fondeadora: props.filtros?.cuenta_fondeadora || '',
    nota: props.filtros?.nota || '',
    usuario: props.filtros?.usuario || '',
    sort_by: props.filtros?.sort_by || (vistaActual.value === 'diferidas' ? 'fecha_vencimiento' : 'referencia'),
    sort_order: props.filtros?.sort_order || (vistaActual.value === 'diferidas' ? 'asc' : 'desc'),
    vista: vistaActual.value,
    mostrar_todos: false,
    solo_fiscales: false
});

watch(mostrarTodos, (val) => { filtros.value.mostrar_todos = val; });
watch(soloFiscales, (val) => { filtros.value.solo_fiscales = val; });

const filtrosActivos = computed(() => {
    if (vistaActual.value === 'diferidas') {
        return FILTROS_EN_DIFERIDAS.some(k => filtros.value[k] !== '' && filtros.value[k] !== null && filtros.value[k] !== undefined);
    }
    const { fecha_desde, fecha_hasta, factura_desde, factura_hasta, registro_desde, registro_hasta, estado_deuda, vista, sort_by, sort_order, mostrar_todos, solo_fiscales, ...otros } = filtros.value;
    return Object.values(otros).some(v => v !== '' && v !== null && v !== undefined) ||
           fecha_desde || fecha_hasta || factura_desde || factura_hasta || registro_desde || registro_hasta || mostrar_todos || solo_fiscales;
});

// ============================================
// FUNCIONES DE FECHAS
// ============================================
// Un solo Desde/Hasta que filtra, según el botón elegido, por:
//  - FECHA PÓLIZA  (fecha_desde/fecha_hasta): cuándo se hizo el gasto.
//  - FECHA REGISTRO (registro_desde/registro_hasta): cuándo se capturó en el sistema.
//  - FECHA FACTURA (factura_desde/factura_hasta): cuándo se emitió el comprobante fiscal.
const tipoFecha = ref(
    (props.filtros?.registro_desde || props.filtros?.registro_hasta) ? 'registro'
        : (props.filtros?.factura_desde || props.filtros?.factura_hasta) ? 'factura'
        : 'poliza'
);
const CAMPOS_FECHA = {
    poliza: ['fecha_desde', 'fecha_hasta'],
    registro: ['registro_desde', 'registro_hasta'],
    factura: ['factura_desde', 'factura_hasta'],
};
const TITULO_COLUMNA_FECHA = { poliza: 'Fecha Poliza', registro: 'Fecha Registro', factura: 'Fecha Factura' };
const fechaDesde = computed({
    get: () => filtros.value[CAMPOS_FECHA[tipoFecha.value][0]],
    set: (v) => { filtros.value[CAMPOS_FECHA[tipoFecha.value][0]] = v; },
});
const fechaHasta = computed({
    get: () => filtros.value[CAMPOS_FECHA[tipoFecha.value][1]],
    set: (v) => { filtros.value[CAMPOS_FECHA[tipoFecha.value][1]] = v; },
});

// Al cambiar de tipo, el rango elegido se pasa al otro tipo de fecha.
const cambiarTipoFecha = (tipo) => {
    if (tipoFecha.value === tipo) return;
    const [desdeAnt, hastaAnt] = CAMPOS_FECHA[tipoFecha.value];
    const [desdeNuevo, hastaNuevo] = CAMPOS_FECHA[tipo];
    filtros.value[desdeNuevo] = filtros.value[desdeAnt];
    filtros.value[hastaNuevo] = filtros.value[hastaAnt];
    filtros.value[desdeAnt] = '';
    filtros.value[hastaAnt] = '';
    tipoFecha.value = tipo;
    aplicarFiltros();
};

// Al elegir "Hasta", "Desde" se mueve a la misma fecha.
const onFechaHastaChange = () => {
    if (fechaHasta.value) {
        fechaDesde.value = fechaHasta.value;
    }
    aplicarFiltros();
};

const aplicarFiltros = () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
        if (!empresaSeleccionada.value) return;
        loading.value = true;
        const params = { 
            empresa_id: empresaSeleccionada.value, 
            vista: vistaActual.value,
            mostrar_todos: mostrarTodos.value,
            solo_fiscales: soloFiscales.value
        };
        
        if (vistaActual.value === 'diferidas') {
            if (filtros.value.sort_by) params.sort_by = filtros.value.sort_by;
            for (const k of FILTROS_EN_DIFERIDAS) {
                if (filtros.value[k]) params[k] = filtros.value[k];
            }
            if (filtros.value.sort_order) params.sort_order = filtros.value.sort_order;
        } else {
            for (const [key, value] of Object.entries(filtros.value)) {
                if (value !== '' && value !== null && value !== undefined &&
                    key !== 'vista' && key !== 'mostrar_todos' && key !== 'solo_fiscales') {
                    params[key] = value;
                }
            }
            if (!filtros.value.fecha_desde && !filtros.value.fecha_hasta) {
                params.sin_fecha = 1;
            }
        }

        router.get(route('movimientos.index'), params, {
            preserveState: true,
            replace: true,
            onFinish: () => { loading.value = false; }
        });
    }, 300);
};

const limpiarFiltros = () => {
    filtros.value = {
        fecha_desde: '',
        fecha_hasta: '',
        factura_desde: '',
        factura_hasta: '',
        registro_desde: '',
        registro_hasta: '',
        estado_deuda: '',
        referencia: '',
        estatus: '',
        persona: '',
        cuenta: '',
        cuenta_fondeadora: '',
        nota: '',
        usuario: '',
        sort_by: vistaActual.value === 'diferidas' ? 'fecha_vencimiento' : 'referencia',
        sort_order: vistaActual.value === 'diferidas' ? 'asc' : 'desc',
        vista: vistaActual.value,
        mostrar_todos: false,
        solo_fiscales: false
    };
    mostrarTodos.value = false;
    soloFiscales.value = false;
    aplicarFiltros();
};

const limpiarFechas = () => {
    filtros.value.fecha_desde = '';
    filtros.value.fecha_hasta = '';
    filtros.value.factura_desde = '';
    filtros.value.factura_hasta = '';
    filtros.value.registro_desde = '';
    filtros.value.registro_hasta = '';
    aplicarFiltros();
};

const setFechaHoy = () => {
    const hoy = obtenerFechaLocal();
    fechaDesde.value = hoy;
    fechaHasta.value = hoy;
    aplicarFiltros();
};

const cambiarVista = (vista) => {
    if (vistaActual.value === vista) return;
    vistaActual.value = vista;
    filtros.value.vista = vista;
    filtros.value.estado_deuda = '';
    filtros.value.sort_by = vista === 'diferidas' ? 'fecha_vencimiento' : 'referencia';
    filtros.value.sort_order = vista === 'diferidas' ? 'asc' : 'desc';
    soloFiscales.value = false;
    filtros.value.solo_fiscales = false;
    aplicarFiltros();
};

const handleTableChange = (event) => {
    if (event && event.sortField) {
        const map = { vencimiento: 'fecha_vencimiento', fecha_poliza: 'fecha_poliza', referencia: 'referencia' };
        filtros.value.sort_by = map[event.sortField] || event.sortField;
        filtros.value.sort_order = event.sortOrder === 1 ? 'asc' : 'desc';
        aplicarFiltros();
    }
};

// ============================================
// CAMBIAR EMPRESA
// ============================================
const cambiarEmpresa = () => {
    if (empresaSeleccionada.value) {
        guardarEmpresa(empresaSeleccionada.value);
        loading.value = true;
        const params = { 
            empresa_id: empresaSeleccionada.value, 
            vista: vistaActual.value,
            mostrar_todos: mostrarTodos.value,
            solo_fiscales: soloFiscales.value
        };
        
        if (vistaActual.value === 'diferidas') {
            if (filtros.value.sort_by) params.sort_by = filtros.value.sort_by;
            for (const k of FILTROS_EN_DIFERIDAS) {
                if (filtros.value[k]) params[k] = filtros.value[k];
            }
            if (filtros.value.sort_order) params.sort_order = filtros.value.sort_order;
        } else {
            for (const [key, value] of Object.entries(filtros.value)) {
                if (value !== '' && value !== null && value !== undefined &&
                    key !== 'vista' && key !== 'mostrar_todos' && key !== 'solo_fiscales') {
                    params[key] = value;
                }
            }
            if (!filtros.value.fecha_desde && !filtros.value.fecha_hasta) {
                params.sin_fecha = 1;
            }
        }

        router.get(route('movimientos.index'), params, {
            preserveState: true,
            replace: true,
            onFinish: () => { loading.value = false; }
        });
    } else {
        router.get(route('movimientos.index'), { vista: vistaActual.value }, {
            preserveState: true,
            replace: true
        });
    }
};

// ============================================
// CLASES Y ESTILOS
// ============================================
const getEstatusClass = (estatus) => {
    const classes = {
        'CAPTURADO': 'capturado',
        'REVISADO': 'revisado',
        'AUTORIZADO': 'autorizado',
        'PENDIENTE': 'pendiente',
        'ABONADO': 'abonado',
        'LIQUIDADO': 'liquidado',
        'CERRADO': 'cerrado',
    };
    return classes[estatus] || 'pendiente';
};

const getTipoClass = (tipo) => {
    const classes = { 'INGRESO': 'ingreso-tipo', 'EGRESO': 'egreso-tipo', 'TRASPASO': 'traspaso-tipo' };
    return classes[tipo] || '';
};

const getMontoClass = (monto) => {
    if (monto > 0) return 'ingreso';
    if (monto < 0) return 'egreso';
    return 'neutro';
};

const getSaldoPendienteClass = (saldo) => {
    if (saldo > 0) return 'saldo-positivo';
    if (saldo < 0) return 'saldo-negativo';
    return 'saldo-cero';
};

const getVencimientoClass = (fecha) => {
    if (!fecha) return '';
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const fechaVenc = new Date(fecha);
    fechaVenc.setHours(0, 0, 0, 0);
    if (fechaVenc < hoy) return 'vencido';
    return 'vigente';
};

const isVencido = (fecha) => {
    if (!fecha) return false;
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const fechaVenc = new Date(fecha);
    fechaVenc.setHours(0, 0, 0, 0);
    return fechaVenc < hoy;
};

const getRowClassName = (record) => {
    if (vistaActual.value !== 'diferidas') return '';
    if (record.fecha_vencimiento) {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const fechaVenc = new Date(record.fecha_vencimiento);
        fechaVenc.setHours(0, 0, 0, 0);
        if (fechaVenc < hoy) return 'row-vencida';
    }
    return '';
};

const getPdfColor = (record) => {
    if (record.es_fiscal && record.tiene_pdf_fiscal) return '#10b981';
    if (record.es_fiscal && !record.tiene_pdf_fiscal) return '#f59e0b';
    return '#d1d5db';
};

// ============================================
// FORMATOS
// ============================================
const formatNumber = (value) => {
    if (value === null || value === undefined || isNaN(value)) return '0.00';
    return Number(value).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

// Fechas sin hora ('YYYY-MM-DD'): se formatean sin pasar por Date para que
// la zona horaria no las recorra un día.
const formatFechaSolo = (fecha) => {
    if (!fecha) return '—';
    const [y, m, d] = String(fecha).slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
};

const formatFechaHora = (fecha) => {
    if (!fecha) return '';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) + 
           ' | ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
};

const formatFileSize = (bytes) => {
    if (!bytes) return '—';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const value = bytes / Math.pow(1024, i);
    return `${value.toFixed(i > 0 ? 1 : 0)} ${sizes[i]}`;
};

// ============================================
// RECURSO - FUNCIONES DEL MODAL
// ============================================
const abrirModalSubir = (record) => {
    modalRecursoMovimiento.value = record;
    modalRecursoModo.value = 'subir';
    modalRecursoTitulo.value = `Subir recurso - ${record.referencia || 'Póliza'}`;
    modalRecursoUrl.value = '';
    modalRecursoTipo.value = '';
    archivoSeleccionado.value = null;
    errorRecurso.value = '';
    modalRecursoVisible.value = true;
};

const verRecurso = (record) => {
    modalRecursoEsFiscal.value = false;
    modalRecursoMovimiento.value = record;
    modalRecursoModo.value = 'ver';
    modalRecursoTitulo.value = `Poliza - ${record.referencia || 'Póliza'}`;
    modalRecursoUrl.value = record.recurso_url || '';
    modalRecursoId.value = record.recurso_id || null;

    if (record.recurso_tipo) {
        modalRecursoTipo.value = record.recurso_tipo;
    } else {
        const url = modalRecursoUrl.value.toLowerCase();
        if (url.endsWith('.pdf')) {
            modalRecursoTipo.value = 'pdf';
        } else if (url.endsWith('.jpg') || url.endsWith('.jpeg') || url.endsWith('.png') || url.endsWith('.gif') || url.endsWith('.webp')) {
            modalRecursoTipo.value = 'image';
        } else {
            modalRecursoTipo.value = 'other';
        }
    }
    
    modalRecursoVisible.value = true;
};

const cerrarModalRecurso = () => {
    modalRecursoVisible.value = false;
    modalRecursoModo.value = 'ver';
    modalRecursoEsFiscal.value = false;
    modalRecursoMovimiento.value = null;
    modalRecursoId.value = null;
    archivoSeleccionado.value = null;
    errorRecurso.value = '';
    dragging.value = false;
};

const abrirRecursoEnPestana = () => {
    if (modalRecursoUrl.value) {
        window.open(modalRecursoUrl.value, '_blank', 'noopener');
    }
};

const modoReemplazarRecurso = () => {
    modalRecursoModo.value = 'reemplazar';
    modalRecursoTitulo.value = `Reemplazar recurso - ${modalRecursoMovimiento.value?.referencia || 'Póliza'}`;
    archivoSeleccionado.value = null;
    errorRecurso.value = '';
};

const eliminarRecurso = () => {
    const idArchivo = modalRecursoId.value;
    if (!idArchivo) return;

    notify.confirmDelete({
        message: '¿Eliminar el recurso adjunto de esta póliza? Esta acción no se puede deshacer.',
        accept: async () => {
            try {
                const response = await axios.delete(route('movimientos.archivos.eliminar', idArchivo));
                if (response.data.success) {
                    mostrarModal('success', 'Éxito', 'Recurso eliminado correctamente');
                    cerrarModalRecurso();
                    router.reload({ only: ['movimientos'] });
                } else {
                    throw new Error(response.data.message || 'Error al eliminar el recurso');
                }
            } catch (error) {
                mostrarModal('error', 'Error', error.response?.data?.message || error.message || 'Error al eliminar el recurso');
            }
        },
    });
};

// === DROP ZONE ===
const onDrop = (e) => {
    dragging.value = false;
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        procesarArchivo(files[0]);
    }
};

const onFileSelect = (e) => {
    const files = e.target.files;
    if (files.length > 0) {
        procesarArchivo(files[0]);
    }
    e.target.value = '';
};

const procesarArchivo = (file) => {
    const tiposPermitidos = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const extensionesPermitidas = ['.pdf', '.jpg', '.jpeg', '.png', '.gif', '.webp'];
    
    const extension = '.' + file.name.split('.').pop().toLowerCase();
    
    if (!tiposPermitidos.includes(file.type) && !extensionesPermitidas.includes(extension)) {
        errorRecurso.value = 'Tipo de archivo no permitido. Solo PDF e imágenes (JPG, PNG, GIF, WEBP).';
        archivoSeleccionado.value = null;
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) {
        errorRecurso.value = 'El archivo no debe exceder 10 MB.';
        archivoSeleccionado.value = null;
        return;
    }
    
    errorRecurso.value = '';
    archivoSeleccionado.value = file;
};

// === SUBIR / REEMPLAZAR RECURSO ===
const guardarRecurso = async () => {
    if (!archivoSeleccionado.value || !modalRecursoMovimiento.value) return;

    const esReemplazo = modalRecursoModo.value === 'reemplazar';
    if (esReemplazo && !modalRecursoId.value) return;

    subiendoRecurso.value = true;
    errorRecurso.value = '';

    const idPoliza = modalRecursoMovimiento.value.id_poliza || modalRecursoMovimiento.value.id_movimiento;
    const formData = new FormData();
    formData.append('archivo', archivoSeleccionado.value);
    formData.append('id_poliza', idPoliza);

    const url = esReemplazo
        ? route('movimientos.archivos.reemplazar', modalRecursoId.value)
        : route('movimientos.archivos.subir', idPoliza);

    try {
        const response = await axios.post(url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        if (response.data.success) {
            mostrarModal('success', 'Éxito', esReemplazo ? 'Recurso reemplazado correctamente' : 'Recurso subido correctamente');
            cerrarModalRecurso();
            router.reload({ only: ['movimientos'] });
        } else {
            throw new Error(response.data.message || 'Error al guardar el recurso');
        }
    } catch (error) {
        errorRecurso.value = error.response?.data?.message || error.message || 'Error al guardar el recurso';
        mostrarModal('error', 'Error', errorRecurso.value);
    } finally {
        subiendoRecurso.value = false;
    }
};

// ============================================
// ACCIONES
// ============================================
const mostrarModal = (type, title, message) => {
    const map = { success: 'success', error: 'error', info: 'info', warning: 'warn' };
    const fn = notify[map[type] || 'info'];
    fn(message, title);
};

const mostrarModalExito = (message, redirectUrl = null) => {
    mostrarModal('success', '¡Abono registrado!', message);
    if (redirectUrl) {
        setTimeout(() => router.visit(redirectUrl, { method: 'get', replace: true }), 1500);
    }
};

const accionAbonar = (record) => {
    if (record.saldo_pendiente <= 0) {
        mostrarModal('warning', 'Ya liquidado', 'Esta poliza ya está completamente liquidada.');
        return;
    }
    router.get(route('movimientos.abono', record.id_movimiento));
};

const accionLiquidar = (record) => {
    if (record.saldo_pendiente <= 0) {
        mostrarModal('warning', 'Ya liquidado', 'Esta poliza ya está completamente liquidada.');
        return;
    }
    movimientoSeleccionado.value = record;
    if (modalLiquidacion.value) {
        modalLiquidacion.value.open();
    } else {
        mostrarModal('error', 'Error', 'No se pudo abrir el modal de liquidación.');
    }
};

const onLiquidado = () => {
    setTimeout(() => {
        router.reload({ only: ['movimientos'] });
    }, 1200);
};

// Si no tiene fondeadoras asignadas, "Nueva póliza" y "Nómina" no navegan.
const avisarSinFondeadoras = (event) => {
    if (!props.sin_fondeadoras) return;
    event.preventDefault();
    mostrarModal('warning', 'Sin cuentas fondeadoras', 'No tienes cuentas fondeadoras asignadas en este condominio, por lo que no puedes generar pólizas. Pídele a un superusuario que te las asigne.');
};

const accionEditar = (record) => {
    if (record.id_movimiento) {
        router.get(route('movimientos.edit', record.id_movimiento));
    } else {
        console.error(' ID de movimiento no válido:', record);
        mostrarModal('error', 'Error', 'No se puede editar esta póliza: ID no válido');
    }
};

const accionVer = (record) => {
    router.get(route('movimientos.show', record.id_movimiento));
};

const verPdf = (record) => {
    if (record.es_fiscal && record.tiene_pdf_fiscal && record.pdf_url) {
        // Se muestra en el mismo modal del recurso (iframe), sin abrir otra pestaña.
        modalRecursoEsFiscal.value = true;
        modalRecursoMovimiento.value = record;
        modalRecursoModo.value = 'ver';
        modalRecursoTitulo.value = `PDF Fiscal - ${record.referencia || 'Póliza'}`;
        modalRecursoUrl.value = record.pdf_url;
        modalRecursoId.value = null;
        modalRecursoTipo.value = 'pdf';
        modalRecursoVisible.value = true;
    } else if (record.es_fiscal && !record.tiene_pdf_fiscal) {
        mostrarModal('info', 'PDF no disponible', 'Esta poliza fiscal no tiene un PDF asociado.');
    } else {
        mostrarModal('info', 'Sin PDF Fiscal', 'Esta poliza no es fiscal.');
    }
};

const exportarExcel = () => {
    if (!empresaSeleccionada.value) {
        mostrarModal('warning', 'Sin condominio', 'Selecciona un condominio primero');
        return;
    }
    const params = new URLSearchParams();
    params.append('empresa_id', empresaSeleccionada.value);
    params.append('vista', vistaActual.value);
    params.append('mostrar_todos', mostrarTodos.value);
    params.append('solo_fiscales', soloFiscales.value);
    
    if (vistaActual.value === 'diferidas') {
        if (filtros.value.sort_by) params.append('sort_by', filtros.value.sort_by);
        for (const k of FILTROS_EN_DIFERIDAS) {
            if (filtros.value[k]) params.append(k, filtros.value[k]);
        }
        if (filtros.value.sort_order) params.append('sort_order', filtros.value.sort_order);
    } else {
        for (const [key, value] of Object.entries(filtros.value)) {
            if (value !== '' && value !== null && value !== undefined && 
                key !== 'vista' && key !== 'mostrar_todos' && key !== 'solo_fiscales') {
                params.append(key, value);
            }
        }
    }
    window.open(route('movimientos.export.excel') + '?' + params.toString(), '_blank');
};

const exportarPdf = () => {
    if (!empresaSeleccionada.value) {
        mostrarModal('warning', 'Sin condominio', 'Selecciona un condominio primero');
        return;
    }
    const params = new URLSearchParams();
    params.append('empresa_id', empresaSeleccionada.value);
    params.append('vista', vistaActual.value);
    params.append('mostrar_todos', mostrarTodos.value);
    params.append('solo_fiscales', soloFiscales.value);
    
    if (vistaActual.value === 'diferidas') {
        if (filtros.value.sort_by) params.append('sort_by', filtros.value.sort_by);
        for (const k of FILTROS_EN_DIFERIDAS) {
            if (filtros.value[k]) params.append(k, filtros.value[k]);
        }
        if (filtros.value.sort_order) params.append('sort_order', filtros.value.sort_order);
    } else {
        for (const [key, value] of Object.entries(filtros.value)) {
            if (value !== '' && value !== null && value !== undefined && 
                key !== 'vista' && key !== 'mostrar_todos' && key !== 'solo_fiscales') {
                params.append(key, value);
            }
        }
    }
    window.open(route('movimientos.export.pdf') + '?' + params.toString(), '_blank');
};

// ============================================
// LIFECYCLE
// ============================================
onMounted(() => {
    if (columnasActivas.value.length === 0) {
        const defaultKeys = getColumnasDisponibles().filter(col => col.visibleByDefault !== false).map(col => col.key);
        columnasActivas.value = defaultKeys;
    }
    
    const empresaGuardada = cargarEmpresaGuardada();
    const empresaDelServidor = props.empresa_seleccionada ? parseInt(props.empresa_seleccionada) : null;
    let empresaObjetivo = null;

    if (empresaGuardada && props.empresas.some(e => e.id === empresaGuardada)) {
        empresaObjetivo = empresaGuardada;
    } else if (empresaDelServidor) {
        empresaObjetivo = empresaDelServidor;
    } else if (props.empresas && props.empresas.length > 0) {
        empresaObjetivo = props.empresas[0].id;
    }

    empresaSeleccionada.value = empresaObjetivo;
    if (empresaObjetivo) {
        guardarEmpresa(empresaObjetivo);
    }

    if (!empresaObjetivo) return;

    const urlParams = new URLSearchParams(window.location.search);
    const hayPagina = urlParams.has('page');
    const servidorTraeFecha = !!(props.filtros?.fecha_desde || props.filtros?.fecha_hasta || props.filtros?.factura_desde || props.filtros?.factura_hasta || props.filtros?.registro_desde || props.filtros?.registro_hasta);
    const fechaQuitadaAdrede = urlParams.get('sin_fecha') === '1';
    const cambioDeEmpresa = empresaObjetivo !== empresaDelServidor;

    // Al RECARGAR la página (F5) la URL trae las fechas del último filtro; se
    // regresa a "hoy" por fecha de póliza. Sólo se revisa en la primera carga
    // del documento, no en cada visita de Inertia.
    const esRecarga = !window.__ricIndexRecargaRevisada &&
        performance.getEntriesByType?.('navigation')?.[0]?.type === 'reload';
    window.__ricIndexRecargaRevisada = true;

    const hoy = obtenerFechaLocal();
    const yaEsHoy = tipoFecha.value === 'poliza' &&
        filtros.value.fecha_desde === hoy && filtros.value.fecha_hasta === hoy;

    if (!hayPagina && vistaActual.value !== 'diferidas' && !yaEsHoy &&
        (esRecarga || (!servidorTraeFecha && !fechaQuitadaAdrede))) {
        Object.values(CAMPOS_FECHA).flat().forEach(k => { filtros.value[k] = ''; });
        tipoFecha.value = 'poliza';
        filtros.value.fecha_desde = hoy;
        filtros.value.fecha_hasta = hoy;
        aplicarFiltros();
        return;
    }

    if (cambioDeEmpresa) {
        aplicarFiltros();
    }
});
</script>

<style scoped>
/* ===== ANOTACIONES (transparencia) ===== */
.btn-anotaciones {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 1px 6px; margin-left: 4px; border-radius: 10px;
    font-size: 11px; font-weight: 700; cursor: pointer;
    background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;
}
.btn-anotaciones.abiertas {
    background: #fee2e2; color: #b91c1c; border-color: #fecaca;
    animation: anotacion-parpadeo 1.6s ease-in-out infinite;
}
@keyframes anotacion-parpadeo { 50% { opacity: 0.55; } }

/* ========== ESTILOS COMPLETOS ========== */
.empresa-selector-premium {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #f0f2f5;
    padding: 10px 20px;
    margin-bottom: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.empresa-selector-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

@media (min-width: 640px) {
    .empresa-selector-content {
        flex-direction: row;
        align-items: center;
        gap: 12px;
    }
}

.empresa-selector-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
}

.empresa-selector-field {
    flex: 1;
    min-width: 150px;
}

.empresa-select-native {
    width: 100%;
    padding: 6px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    background: #fafbfc;
    color: #0f172a;
    transition: all 0.3s ease;
    outline: none;
    height: 36px;
    cursor: pointer;
}

.empresa-select-native:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1);
    background: #ffffff;
}

.view-toggle-group {
    display: flex;
    align-items: center;
    gap: 0;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    background: #f8fafc;
}

.view-toggle-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border: none;
    background: transparent;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-toggle-btn:hover {
    color: #1a3a5c;
    background: #f1f5f9;
}

.view-toggle-btn.active {
    background: #1a3a5c;
    color: white;
}

.view-toggle-icon {
    width: 16px;
    height: 16px;
}

.view-toggle-btn:not(:last-child) {
    border-right: 1px solid #e2e8f0;
}

/* --- FILTROS SUPERIOR --- */
.filtros-superior-premium {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #f0f2f5;
    padding: 12px 20px;
    margin-bottom: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.filtros-superior-content {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}

.fecha-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 120px;
}

.fecha-item :deep(.input-label) {
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    margin-bottom: 0 !important;
}

.fecha-input-premium {
    padding: 6px 10px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    font-size: 12px;
    height: 36px;
    background: #ffffff;
    color: #0f172a;
    transition: all 0.3s ease;
    outline: none;
    width: 100%;
    min-width: 120px;
}

.fecha-input-premium:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1);
}

.filtros-separator {
    width: 1px;
    height: 36px;
    background: #e2e8f0;
    flex-shrink: 0;
}

.fecha-actions {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    gap: 8px;
    min-width: auto;
}

.fecha-actions-export {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    gap: 8px;
    min-width: auto;
    margin-left: auto;
}

@media (max-width: 768px) {
    .fecha-actions-export {
        margin-left: 0;
        width: 100%;
        flex-wrap: wrap;
    }
}

.btn-hoy-premium,
.btn-limpiar-fechas,
.btn-export-excel-mini,
.btn-export-pdf-mini {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 13px;
}

.btn-hoy-premium {
    border-radius: 6px !important;
    background: linear-gradient(135deg, #1a3a5c, #2c5282) !important;
    border: none !important;
    color: white !important;
    height: 36px !important;
    font-weight: 600 !important;
    padding: 0 16px !important;
    transition: all 0.3s ease !important;
}

/* Alternar Fecha póliza / Fecha factura */
.tipo-fecha-toggle {
    display: inline-flex;
    height: 36px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
    background: #f8fafc;
}

.tipo-fecha-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    background: transparent;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.tipo-fecha-btn + .tipo-fecha-btn {
    border-left: 1px solid #cbd5e1;
}

.tipo-fecha-btn:hover:not(.activo) {
    background: #e2e8f0;
    color: #1a3a5c;
}

.tipo-fecha-btn.activo {
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    color: white;
}

.btn-hoy-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 58, 92, 0.3) !important;
}

.btn-limpiar-fechas {
    border-radius: 6px !important;
    color: #64748b !important;
    border: 2px solid #d1d5db !important;
    height: 36px !important;
    font-weight: 600 !important;
    background: #ffffff !important;
    transition: all 0.3s ease !important;
}

.btn-limpiar-fechas:hover {
    color: #1a3a5c !important;
    border-color: #1a3a5c !important;
    transform: translateY(-2px);
}

.btn-export-excel-mini {
    border-radius: 6px !important;
    background: linear-gradient(135deg, #217346, #2e7d32) !important;
    border: none !important;
    color: white !important;
    height: 36px !important;
    font-weight: 600 !important;
    padding: 0 16px !important;
    transition: all 0.3s ease !important;
}

.btn-export-excel-mini:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(33, 115, 70, 0.3) !important;
}

.btn-export-pdf-mini {
    border-radius: 6px !important;
    background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
    border: none !important;
    color: white !important;
    height: 36px !important;
    font-weight: 600 !important;
    padding: 0 16px !important;
    transition: all 0.3s ease !important;
}

.btn-export-pdf-mini:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3) !important;
}

.checkbox-fiscal-item {
    min-width: auto;
    justify-content: center;
}

.checkbox-fiscal-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 4px 12px;
    border-radius: 6px;
    transition: all 0.3s ease;
    height: 36px;
    background: #f0fdf4;
    border: 2px solid #86efac;
}

.checkbox-fiscal-label:hover {
    background: #dcfce7;
}

.checkbox-fiscal-input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #10b981;
}

.checkbox-fiscal-text {
    font-size: 13px;
    font-weight: 600;
    color: #166534;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* --- BOTONES DE ACCION --- */
.btn-nueva-poliza {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 20px;
    height: 36px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.btn-nueva-poliza:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    color: white;
}

.btn-nomina-poliza {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 20px;
    height: 36px;
    background: linear-gradient(135deg, #1a3a5c, #132a44);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(26, 58, 92, 0.2);
}

.btn-nomina-poliza:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.4);
    color: white;
}

.btn-icon-sm {
    width: 16px;
    height: 16px;
}

/* --- TABLA --- */
.table-wrapper-premium {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #f0f2f5;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    padding: 20px;
}

.table-scroll-container {
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
}

.tabla-vacia-inline {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 10px;
    padding: 40px 16px;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 500;
    position: sticky;
    left: 0;
    width: 100%;
}

.tabla-vacia-inline .pi {
    font-size: 20px;
    color: #94a3b8;
}

.table-scroll-container :deep(.p-datatable-table-container) {
    max-height: 500px !important;
    overflow-y: auto !important;
}

.table-scroll-container :deep(.p-datatable-table-container)::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.table-scroll-container :deep(.p-datatable-table-container)::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.table-scroll-container :deep(.p-datatable-table-container)::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.table-scroll-container :deep(.p-datatable-table-container)::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
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
    gap: 12px;
    flex-wrap: wrap;
}

.table-header-right-ultra {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.btn-group-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-tag-ultra {
    border-radius: 30px !important;
    background: linear-gradient(135deg, #eff6ff, #dbeafe) !important;
    border: none !important;
    color: #1a3a5c !important;
    font-weight: 600 !important;
    padding: 4px 16px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
}

.filter-dot-active {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #1a3a5c;
    display: inline-block;
    animation: pulse 2s infinite;
}

.total-pendiente-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 4px 16px;
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #166534;
}

.total-pendiente-value {
    font-weight: 700;
    font-size: 14px;
    color: #166534;
}

/* --- ESTILOS DE LA TABLA --- */
.movimiento-table-ultra {
    width: 100%;
}

.movimiento-table-ultra :deep(.p-datatable-thead > tr > th) {
    background: linear-gradient(135deg, #1a3a5c 0%, #2c5282 100%) !important;
    font-weight: 700;
    color: #ffffff !important;
    border-bottom: none !important;
    padding: 12px 14px !important;
    font-size: 11px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(26, 58, 92, 0.15);
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr) {
    transition: all 0.2s ease;
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr.row-vencida) {
    background: linear-gradient(90deg, #fef2f2, #fecaca) !important;
    border-left: 4px solid #dc2626 !important;
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr.row-vencida:hover) {
    background: linear-gradient(90deg, #fecaca, #fca5a5) !important;
    box-shadow: inset 0 0 0 1px #dc2626 !important;
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr.row-vencida td) {
    color: #991b1b !important;
    font-weight: 600 !important;
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr:not(.row-vencida):hover) {
    background: linear-gradient(90deg, #f8faff, #f0f7ff) !important;
    box-shadow: inset 0 0 0 1px #1a3a5c;
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr:nth-child(even)) {
    background: #fafbfc;
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr:nth-child(even):not(.row-vencida):hover) {
    background: linear-gradient(90deg, #f8faff, #f0f7ff) !important;
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr > td) {
    padding: 10px 12px !important;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}

.movimiento-table-ultra :deep(.p-frozen-column),
.movimiento-table-ultra :deep(.p-frozen-column) {
    background: #ffffff;
    z-index: 5;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.04);
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr.row-vencida .p-frozen-column),
.movimiento-table-ultra :deep(.p-datatable-tbody > tr.row-vencida .p-frozen-column) {
    background: linear-gradient(90deg, #fef2f2, #fecaca) !important;
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr.row-vencida:hover .p-frozen-column),
.movimiento-table-ultra :deep(.p-datatable-tbody > tr.row-vencida:hover .p-frozen-column) {
    background: linear-gradient(90deg, #fecaca, #fca5a5) !important;
}

/* --- CELDAS --- */
.referencia-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #0f172a;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    font-weight: 600;
}

.referencia-link:hover {
    color: #1a3a5c;
    text-decoration: underline;
}

.referencia-text-ultra {
    font-size: 13px;
    color: inherit;
}

.fiscal-icon {
    display: inline-flex;
    margin-left: 4px;
}

.traspaso-icon {
    display: inline-flex;
    margin-left: 4px;
}

.fecha-usuario-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.fecha-text-ultra {
    font-size: 13px;
    font-weight: 500;
    color: #475569;
}

.fecha-text-ultra.vencido {
    color: #dc2626;
    font-weight: 700;
}

.fecha-text-ultra.vigente {
    color: #10b981;
    font-weight: 600;
}

.usuario-fecha-creacion {
    font-size: 9px;
    color: #94a3b8;
    font-weight: 400;
    background: #f1f5f9;
    padding: 1px 8px;
    border-radius: 4px;
    white-space: nowrap;
}

.vencido-icon {
    font-size: 14px;
    margin-left: 4px;
}

.estatus-badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.estatus-badge.pendiente {
    background: #fef3c7;
    color: #92400e;
}

.estatus-badge.abonado {
    background: #dbeafe;
    color: #1e40af;
}

.estatus-badge.liquidado {
    background: #dcfce7;
    color: #166534;
}

.estatus-badge.capturado {
    background: #e5e7eb;
    color: #374151;
}

.estatus-badge.revisado {
    background: #e0e7ff;
    color: #3730a3;
}

.estatus-badge.autorizado {
    background: #cffafe;
    color: #155e75;
}

.estatus-badge.cerrado {
    background: #fee2e2;
    color: #991b1b;
}

.persona-text-ultra {
    font-size: 13px;
    color: #0f172a;
    font-weight: 500;
    display: block;
    white-space: normal;
    word-break: break-word;
    line-height: 1.3;
}

.cuenta-text-ultra {
    font-size: 13px;
    color: #475569;
    display: block;
    white-space: normal;
    word-break: break-word;
    line-height: 1.3;
}

.nota-text-ultra {
    font-size: 13px;
    color: #64748b;
    font-style: italic;
    display: block;
    white-space: normal;
    overflow-wrap: anywhere;
    line-height: 1.3;
}

.monto-text-ultra {
    font-size: 12px;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    white-space: nowrap;
    margin: 0 -10px;
}

.monto-text-ultra.ingreso {
    color: #2563eb;
}

.monto-text-ultra.egreso {
    color: #dc2626;
}

.monto-text-ultra.abonado-text {
    color: #2563eb;
}

.monto-text-ultra.saldo-positivo {
    color: #dc2626;
    font-weight: 700;
}

.monto-text-ultra.saldo-negativo {
    color: #2563eb;
}

.monto-text-ultra.saldo-cero {
    color: #10b981;
}

.traspaso-amount-badge {
    display: inline-block;
    margin-left: 4px;
    padding: 0 6px;
    background: #e8eef5;
    color: #132a44;
    font-size: 8px;
    font-weight: 700;
    border-radius: 3px;
    line-height: 16px;
}

.usuario-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.usuario-text-ultra {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.usuario-fecha-ultra {
    font-size: 10px;
    color: #94a3b8;
}

/* --- RECURSO --- */
.recurso-cell {
    flex-direction: column;
    gap: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.recurso-fecha-celda {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 10px;
    line-height: 1.25;
    font-weight: 600;
    white-space: nowrap;
    cursor: default;
}

.recurso-fecha-usuario {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    text-transform: uppercase;
}

.recurso-fecha-ok {
    color: #111827;
}

.recurso-fecha-tarde {
    color: #dc2626;
}

.btn-recurso {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border: none;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: inherit;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.btn-recurso-verde {
    background: #dcfce7;
    color: #166534;
    border: 2px solid #86efac;
}

.btn-recurso-verde:hover {
    background: #bbf7d0;
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(22, 101, 52, 0.15);
}

.btn-recurso-azul {
    background: #dbeafe;
    color: #1e40af;
    border: 2px solid #93c5fd;
}

.btn-recurso-azul:hover {
    background: #bfdbfe;
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(30, 64, 175, 0.15);
}

.btn-recurso-icon {
    width: 14px;
    height: 14px;
}

.btn-recurso-texto {
    font-size: 10px;
}

/* --- PDF --- */
.pdf-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.btn-pdf {
    font-size: 16px !important;
    padding: 0 4px !important;
    height: 28px !important;
    width: 28px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: all 0.3s ease !important;
}

.btn-pdf:hover:not(:disabled) {
    transform: scale(1.15);
    background: rgba(16, 185, 129, 0.05) !important;
}

.referencia-cell {
    display: flex;
    align-items: center;
    gap: 4px;
}

.btn-pdf-inline {
    flex-shrink: 0;
    background: transparent;
    border: none;
    cursor: pointer;
    width: 24px !important;
    height: 24px !important;
}

.movimiento-table-ultra :deep(.p-datatable-tbody > tr > td) {
    overflow-wrap: anywhere;
}

.movimiento-table-ultra :deep(.p-datatable-column-resizer) {
    width: 6px;
    right: -3px;
    cursor: col-resize;
    z-index: 11;
}

.movimiento-table-ultra :deep(.p-datatable-column-resizer:hover),
.movimiento-table-ultra :deep(.p-datatable-resizable-column:hover .p-datatable-column-resizer) {
    background: rgba(255, 255, 255, 0.45);
}

.btn-pdf:disabled {
    cursor: not-allowed;
    transform: none !important;
}

/* --- ACCIONES --- */
.acciones-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    flex-wrap: nowrap;
}

.btn-accion {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 10px;
    border: none;
    border-radius: 5px;
    font-size: 9px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
    min-width: 38px;
    text-align: center;
    justify-content: center;
    height: 24px;
}

.btn-accion.abonar {
    background: #dbeafe;
    color: #1e40af;
}

.btn-accion.abonar:hover:not(:disabled) {
    background: #bfdbfe;
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(30, 64, 175, 0.15);
}

.btn-accion.editar {
    background: #fef3c7;
    color: #92400e;
}

.btn-accion.editar:hover {
    background: #fde68a;
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(146, 64, 14, 0.15);
}

.btn-accion.ver {
    background: #e0e7ff;
    color: #3730a3;
}

.btn-accion.ver:hover {
    background: #c7d2fe;
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(55, 48, 163, 0.15);
}

.btn-accion:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none !important;
}

/* --- FILTROS INFERIOR --- */
/* Filtros de Diferidas: una celda por columna, mismo ancho en % que la tabla */
.aviso-sin-fondeadoras {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #fff7ed, #ffedd5);
    border: 1px solid #fdba74;
    box-shadow: inset 4px 0 0 #ea580c;
    color: #9a3412;
    font-size: 13px;
    padding: 10px 16px;
    border-radius: 10px;
    margin-bottom: 12px;
}

.btn-bloqueado {
    opacity: 0.5;
    filter: grayscale(0.4);
    cursor: not-allowed;
}

.filtros-diferidas-fila {
    display: flex;
    width: 100%;
    margin-top: 12px;
    padding: 10px 0;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.filtros-diferidas-celda {
    padding: 0 6px;
    box-sizing: border-box;
    min-width: 0;
}

.filtros-diferidas-celda .filtro-inferior-input,
.filtros-diferidas-celda .filtro-inferior-select {
    width: 100%;
}

.filtros-inferior-tabla {
    margin-top: 16px;
    padding: 16px 20px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.filtros-inferior-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
    align-items: end;
}

.filtro-inferior-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filtro-inferior-label {
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.filtro-inferior-input {
    padding: 6px 10px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    font-size: 12px;
    height: 32px;
    background: #ffffff;
    color: #0f172a;
    transition: all 0.3s ease;
    outline: none;
    width: 100%;
}

.filtro-inferior-input:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1);
}

.filtro-inferior-input::placeholder {
    color: #94a3b8;
}

.filtro-inferior-select {
    padding: 6px 10px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    font-size: 12px;
    height: 32px;
    background: #ffffff;
    color: #0f172a;
    transition: all 0.3s ease;
    outline: none;
    width: 100%;
    cursor: pointer;
}

.filtro-inferior-select:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1);
}

.filtro-inferior-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    min-width: 150px;
}

.btn-limpiar-filtros-inferior {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 32px;
    white-space: nowrap;
}

.btn-limpiar-filtros-inferior:hover {
    color: #dc2626;
    border-color: #dc2626;
    background: #fef2f2;
    transform: translateY(-1px);
}

/* --- RESUMEN DE TOTALES --- */
.resumen-totales-wrapper {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

.resumen-totales-externo {
    margin-top: 16px;
    justify-content: stretch;
}

.resumen-totales-container-grande {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    border: 2px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    padding: 20px 28px;
    display: flex;
    align-items: center;
    gap: 28px;
    flex-wrap: wrap;
    min-width: 400px;
    width: 100%;
    justify-content: center;
    transition: all 0.3s ease;
}

.resumen-totales-container-grande:hover {
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.08);
    border-color: #cbd5e1;
}

.resumen-totales-header-grande {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-right: 20px;
    border-right: 2px solid #e2e8f0;
}

.resumen-totales-title-grande {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.resumen-totales-items-grande {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    flex-wrap: wrap;
}

.total-item-grande {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    transition: all 0.3s ease;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #f1f5f9;
    min-width: 140px;
    flex: 1;
}

.total-item-grande:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.total-icon-grande {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.total-ingresos .total-icon-grande {
    background: #eff6ff;
}

.total-egresos .total-icon-grande {
    background: #fef2f2;
}

.total-traspasos .total-icon-grande {
    background: #e8eef5;
}

.total-saldo-neto .total-icon-grande {
    background: #f0fdf4;
}

.total-icon-svg-grande {
    width: 20px;
    height: 20px;
}

.total-ingresos .total-icon-svg-grande {
    color: #2563eb;
}

.total-egresos .total-icon-svg-grande {
    color: #dc2626;
}

.total-traspasos .total-icon-svg-grande {
    color: #132a44;
}

.total-saldo-neto .total-icon-svg-grande {
    color: #10b981;
}

.total-info-grande {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.total-label-grande {
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.total-value-grande {
    font-size: 18px;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    line-height: 1.2;
}

.ingresos-value {
    color: #2563eb;
}

.egresos-value {
    color: #dc2626;
}

.traspaso-value {
    color: #132a44;
}

.saldo-net-value {
    color: #10b981;
}

/* --- PAGINACION --- */
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

/* --- MODAL RECURSO --- */
.modal-recurso-content {
    min-height: 300px;
    padding: 24px;
}

.modal-recurso-content-ver {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    padding: 14px 16px 16px;
    overflow: hidden;
}

.modal-recurso-content-ver > div:first-child {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}

.recurso-header {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.recurso-header-titulo {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    flex-shrink: 0;
}

.recurso-datos-poliza {
    display: flex;
    flex-wrap: nowrap;
    gap: 6px;
    align-items: center;
    overflow-x: auto;
    overflow-y: hidden;
    min-width: 0;
    padding-bottom: 2px;
    scrollbar-width: thin;
}

.recurso-datos-poliza::-webkit-scrollbar {
    height: 4px;
}

.recurso-datos-poliza::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.35);
    border-radius: 2px;
}

.recurso-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    font-size: 0.74rem;
    line-height: 1.2;
    color: #0f172a;
    white-space: nowrap;
    flex-shrink: 0;
}

.recurso-chip-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
}

.recurso-chip-valor {
    font-weight: 600;
    color: #0f172a;
}

.recurso-toolbar-top {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
    margin-bottom: 12px;
    flex-shrink: 0;
}

.btn-modal-submit-outline {
    background: #fff !important;
    color: #1a3a5c !important;
    border: 1px solid #1a3a5c !important;
}

.btn-modal-submit-outline:hover:not(:disabled) {
    background: #eef2f7 !important;
}

.btn-modal-cancel-danger {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #b91c1c !important;
    border: 1px solid #b91c1c !important;
    background: #fff !important;
}

.btn-modal-cancel-danger:hover {
    background: #fef2f2 !important;
}

.recurso-pdf-wrapper {
    width: 100%;
    height: 70vh;
    min-height: 400px;
}

.modal-recurso-content-ver .recurso-pdf-wrapper {
    flex: 1 1 auto;
    height: auto;
    min-height: 0;
}

.modal-recurso-content-ver .recurso-image-wrapper {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
}

.modal-recurso-content-ver .recurso-other {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
}

.recurso-pdf {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
}

.recurso-image-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
    background: #f8fafc;
    border-radius: 8px;
}

.modal-recurso-content-ver .recurso-image-wrapper {
    min-height: 0;
}

.recurso-image {
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
    border-radius: 4px;
}

.modal-recurso-content-ver .recurso-image {
    max-height: 100%;
}

.recurso-other {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    gap: 12px;
}

.recurso-other-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    background: #f3f4f6;
    border-radius: 50%;
}

.recurso-other-svg {
    width: 40px;
    height: 40px;
    color: #94a3b8;
}

.recurso-other-text {
    color: #64748b;
    font-size: 0.95rem;
}

.recurso-other-link {
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
    padding: 6px 16px;
    border: 1px solid #2563eb;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.recurso-other-link:hover {
    background: #eff6ff;
}

.recurso-footer {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    padding: 12px 20px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    border-radius: 0 0 8px 8px;
    width: 100%;
    overflow-x: auto;
}

.recurso-footer-left {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 8px;
    min-width: 0;
    flex: 1 1 auto;
    overflow: hidden;
}

.recurso-footer-right {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.recurso-subida-linea {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 500;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.recurso-subida-linea span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.recurso-subida-linea-ok,
.recurso-subida-linea-ok strong {
    color: #0f172a;
}

.recurso-subida-linea-tarde {
    background: #fef2f2;
    border-color: #fca5a5;
}

.recurso-subida-linea-tarde,
.recurso-subida-linea-tarde strong {
    color: #dc2626;
}

.recurso-upload-wrapper {
    padding: 8px 0;
}

.recurso-upload-info {
    margin-bottom: 16px;
    font-size: 0.9rem;
    color: #475569;
}

.recurso-upload-info strong {
    color: #0f172a;
}

.recurso-upload-hint {
    font-size: 0.8rem;
    color: #94a3b8;
    margin-top: 4px;
}

.recurso-drop-zone {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 32px 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #fafbfc;
}

.recurso-drop-zone:hover {
    border-color: #1a3a5c;
    background: #f8f7ff;
}

.recurso-drop-zone-dragover {
    border-color: #1a3a5c;
    background: #e8eef5;
    transform: scale(1.02);
}

.recurso-drop-zone-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.recurso-drop-zone-icon {
    width: 48px;
    height: 48px;
    color: #94a3b8;
}

.recurso-drop-zone-text {
    font-size: 0.9rem;
    color: #64748b;
}

.recurso-drop-zone-text.archivo-seleccionado {
    color: #10b981;
    font-weight: 600;
}

.archivo-seleccionado-icon {
    width: 20px;
    height: 20px;
    display: inline-block;
    vertical-align: middle;
    margin-right: 6px;
}

.recurso-drop-zone-hint {
    font-size: 0.7rem;
    color: #94a3b8;
}

.recurso-file-input-hidden {
    display: none;
}

.recurso-error {
    margin-top: 12px;
    padding: 8px 14px;
    background: #fee2e2;
    border-radius: 6px;
    color: #991b1b;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.recurso-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #f3f4f6;
}

.btn-modal-cancel {
    padding: 8px 24px;
    background: #f1f5f9;
    color: #64748b;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-modal-cancel:hover {
    background: #e2e8f0;
}

.btn-modal-submit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    white-space: nowrap;
}

.btn-modal-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 58, 92, 0.3);
}

.btn-modal-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.spinner-border-sm {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 0.15em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner 0.75s linear infinite;
}

.error-icon-sm {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}

@keyframes spinner {
    to { transform: rotate(360deg); }
}

/* --- RESPONSIVE --- */
@media (max-width: 768px) {
    .filtros-superior-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filtros-separator {
        display: none;
    }
    
    .fecha-actions-export {
        margin-left: 0;
        width: 100%;
        flex-wrap: wrap;
    }

    .btn-export-excel-mini,
    .btn-export-pdf-mini {
        flex: 1;
        justify-content: center;
    }

    .checkbox-fiscal-item {
        align-items: flex-start;
    }

    .filtros-inferior-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }

    .table-wrapper-premium {
        padding: 12px;
    }
    
    .btn-nueva-poliza,
    .btn-nomina-poliza {
        width: 100%;
        justify-content: center;
    }

    .btn-group-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .btn-group-actions .column-selector-wrapper {
        width: 100%;
    }
    
    .btn-group-actions .column-selector-wrapper .btn-column-selector {
        width: 100%;
        justify-content: center;
    }

    .table-header-right-ultra {
        width: 100%;
        flex-direction: column;
    }

    .view-toggle-group {
        width: 100%;
    }

    .view-toggle-btn {
        flex: 1;
        justify-content: center;
    }

    .acciones-cell {
        flex-wrap: wrap;
        gap: 3px;
    }

    .btn-accion {
        font-size: 8px;
        padding: 2px 6px;
        min-width: 30px;
        height: 20px;
    }

    .btn-icon-sm {
        width: 10px;
        height: 10px;
    }
    
    .recurso-pdf-wrapper {
        height: 50vh;
        min-height: 300px;
    }

    .recurso-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .recurso-footer-right {
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .recurso-datos-poliza {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .empresa-selector-actions {
        flex-direction: column;
        width: 100%;
    }

    .view-toggle-group {
        width: 100%;
    }

    .fecha-actions-export {
        flex-direction: column;
    }

    .btn-export-excel-mini,
    .btn-export-pdf-mini {
        width: 100%;
        justify-content: center;
    }

    .filtros-inferior-grid {
        grid-template-columns: 1fr;
    }

    .filtro-inferior-actions {
        justify-content: center;
    }

    .resumen-totales-container-grande {
        min-width: 100%;
        width: 100%;
        padding: 12px 16px;
        flex-direction: column;
        gap: 12px;
    }
    
    .resumen-totales-header-grande {
        padding-right: 0;
        border-right: none;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 8px;
        width: 100%;
        justify-content: center;
    }

    .total-item-grande {
        min-width: 100%;
        flex: 1 1 100%;
    }

    .acciones-cell {
        gap: 2px;
    }

    .btn-accion {
        font-size: 7px;
        padding: 2px 4px;
        min-width: 24px;
        height: 18px;
    }

    .btn-icon-sm {
        width: 8px;
        height: 8px;
    }
    
    .checkbox-fiscal-text {
        font-size: 12px;
    }
    
    .recurso-pdf-wrapper {
        height: 40vh;
        min-height: 250px;
    }
    
    .recurso-image {
        max-height: 50vh;
    }
}

.empty-state-icon {
    font-size: 3.25rem;
    color: #cbd5e1;
    display: block;
    margin: 0 auto 1rem;
}
</style>

<!-- Estilos del diálogo de recurso: NO scoped porque PrimeVue teletransporta
     el .p-dialog fuera de este componente. -->
<style>
.modal-recurso-premium.p-dialog {
    max-height: 92vh;
    display: flex;
    flex-direction: column;
}

.modal-recurso-premium-ver.p-dialog {
    height: 90vh;
}

.modal-recurso-premium .p-dialog-header {
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    border-radius: 8px 8px 0 0;
    padding: 12px 20px;
    flex-shrink: 0;
}

.modal-recurso-premium .p-dialog-title {
    color: #fff;
    font-weight: 700;
    font-size: 1.1rem;
}

.modal-recurso-premium .p-dialog-content {
    padding: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}

.modal-recurso-premium .p-dialog-footer {
    padding: 0;
    border: none;
}

/* Botón X de cerrar: SIEMPRE en color ROJO, sin animaciones */
.modal-recurso-premium .p-dialog-close-button {
    color: #ef4444 !important;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.modal-recurso-premium .p-dialog-close-button:hover {
    color: #dc2626 !important;
    background: rgba(239, 68, 68, 0.15) !important;
}

.modal-recurso-premium .p-dialog-close-button svg,
.modal-recurso-premium .p-dialog-close-button .p-icon {
    color: #ef4444 !important;
}
</style>