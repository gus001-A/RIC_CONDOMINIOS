<template>
    <AppLayout title="RIC - Reportes">
        <div class="py-6">
            <div class="max-w-full px-4 sm:px-6 lg:px-8">
                <!-- Secciones: reportes contables (RIC) y reportes del condominio -->
                <div class="vt-page rp-cab">
                    <PageHeader title="Reportes" subtitle="Contabilidad del condominio y reportes de cobranza, operación, seguridad, almacén y padrón" :icon="BarChartOutlined">
                        <template #extra>
                            <a-select v-if="seccion === 'condominio' && empresas.length > 1" v-model:value="empresaCondominio" size="large" style="min-width: 240px"
                                show-search option-filter-prop="label" :options="empresas.map((e) => ({ value: e.id, label: e.nombre_empresa }))" />
                            <a-segmented v-model:value="seccion" size="large" :options="[{ value: 'contables', label: 'Contables' }, { value: 'condominio', label: 'Del condominio' }]" />
                        </template>
                    </PageHeader>
                </div>
                <ReportesCondominio v-if="seccion === 'condominio'" :empresa-id="empresaCondominio" />
                <div v-show="seccion === 'contables'">
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
                                    :class="{ active: vistaActual === 'por_cuenta' }"
                                    @click="cambiarVista('por_cuenta')"
                                >
                                    <svg class="view-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                    </svg>
                                    Por Cuenta
                                </button>
                                <button 
                                    class="view-toggle-btn" 
                                    :class="{ active: vistaActual === 'por_persona' }"
                                    @click="cambiarVista('por_persona')"
                                >
                                    <svg class="view-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Por Persona
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FILTROS SUPERIOR - SIEMPRE INICIA CON HOY -->
                <div v-if="empresas.length > 0" class="filtros-superior-premium">
                    <div class="filtros-superior-content">
                        <div class="fecha-item">
                            <InputLabel>Desde</InputLabel>
                            <input 
                                type="date" 
                                v-model="filtros.fecha_desde"
                                @change="onFechaDesdeChange"
                                :max="fechaActual"
                                class="fecha-input-premium"
                            />
                        </div>
                        <div class="fecha-item">
                            <InputLabel>Hasta</InputLabel>
                            <input 
                                type="date" 
                                v-model="filtros.fecha_hasta"
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
                                v-if="filtros.fecha_desde || filtros.fecha_hasta"
                                type="button"
                                class="btn-limpiar-fechas"
                                @click="limpiarFechas"
                            >
                                <i class="pi pi-times"></i>
                                Limpiar
                            </button>
                        </div>
                        <div class="filtros-separator"></div>
                        <div class="fecha-item fecha-actions-export">
                            <button type="button" class="btn-export-excel-mini" @click="exportarExcel">
                                <i class="pi pi-file-excel"></i>
                                Excel
                            </button>
                            <button type="button" class="btn-resultados-mini" @click="abrirModalCuentasResultados">
                                <i class="pi pi-chart-bar"></i>
                                Resultados
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CONTENIDO DEL REPORTE -->
                <div v-if="empresas.length > 0 && cargado" class="reporte-wrapper-premium">
                    <div class="tabla-principal-premium">
                        <div class="table-header-ultra">
                            <div class="table-header-left-ultra">
                                <span class="table-title-premium">Movimientos por {{ vistaActual === 'por_cuenta' ? 'Cuenta' : 'Persona' }}
                                </span>
                                <span v-if="filtrosActivos" class="filter-tag-ultra">
                                    <span class="filter-dot-active"></span>
                                    Filtros activos
                                </span>
                            </div>
                            <div class="table-header-right-ultra">
                                <span class="total-registros-premium">Total: <strong>{{ reporteData.length }}</strong> registros
                                </span>
                            </div>
                        </div>
                        
                        <!-- TABLA PRINCIPAL -->
                        <div class="table-scroll-container">
                            <DataTable
                                :value="datosFiltrados"
                                :loading="loading"
                                data-key="id"
                                scrollable
                                scroll-height="400px"
                                row-hover
                                table-style="min-width: 55rem"
                                class="reporte-table-ultra"
                                @row-click="(e) => onRowClick(e.data)"
                            >
                                <template #empty><div class="tabla-vacia">Sin registros.</div></template>
                                <Column
                                    v-for="col in columnasPrincipales"
                                    :key="col.key"
                                    :header="col.title"
                                    :style="{ width: col.width, textAlign: col.align || 'left' }"
                                    :frozen="col.fixed === 'left'"
                                >
                                    <template #body="{ data: record }">
                                        <span v-if="col.key === 'codigo'" class="codigo-text-ultra">{{ record.codigo || '---' }}</span>
                                        <span v-else-if="col.key === 'nombre'" class="nombre-text-ultra clickable" @click="onRowClick(record)">{{ record.nombre || '---' }}</span>
                                        <span v-else-if="col.key === 'persona'" class="persona-text-ultra">{{ record.persona || '---' }}</span>
                                        <span v-else-if="col.key === 'fondeo'" class="fondeo-text-ultra">{{ record.fondeo || '---' }}</span>
                                        <span v-else-if="col.key === 'ingreso'" class="monto-text-ultra ingreso">${{ formatNumber(record.ingreso || 0) }}</span>
                                        <span v-else-if="col.key === 'egreso'" class="monto-text-ultra egreso">${{ formatNumber(record.egreso || 0) }}</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        
                        <!-- FILTROS INFERIOR - TABLA SEPARADA CON MISMOS ANCHOS -->
                        <div class="filtros-ultra-full">
                            <table class="filtros-table">
                                <tbody>
                                    <tr>
                                        <td style="width: 170px; padding: 0 4px;">
                                            <div class="filtro-item-ultra">
                                                <InputLabel>Código</InputLabel>
                                                <input 
                                                    v-model="filtrosTabla.codigo" 
                                                    @input="aplicarFiltrosTabla"
                                                    placeholder="Buscar..." 
                                                    class="filtro-input-ultra"
                                                />
                                            </div>
                                        </td>
                                        <td style="width: 280px; padding: 0 4px;">
                                            <div class="filtro-item-ultra">
                                                <InputLabel>Cuenta</InputLabel>
                                                <input 
                                                    v-model="filtrosTabla.nombre" 
                                                    @input="aplicarFiltrosTabla"
                                                    placeholder="Buscar..." 
                                                    class="filtro-input-ultra"
                                                />
                                            </div>
                                        </td>
                                        <td style="width: 250px; padding: 0 4px;">
                                            <div class="filtro-item-ultra">
                                                <InputLabel>Persona</InputLabel>
                                                <input 
                                                    v-model="filtrosTabla.persona" 
                                                    @input="aplicarFiltrosTabla"
                                                    placeholder="Buscar..." 
                                                    class="filtro-input-ultra"
                                                />
                                            </div>
                                        </td>
                                        <td style="width: 250px; padding: 0 4px;">
                                            <div class="filtro-item-ultra">
                                                <InputLabel>Fondeadora</InputLabel>
                                                <input 
                                                    v-model="filtrosTabla.fondeo" 
                                                    @input="aplicarFiltrosTabla"
                                                    placeholder="Buscar..." 
                                                    class="filtro-input-ultra"
                                                />
                                            </div>
                                        </td>
                                        <td style="width: 150px; padding: 0 4px;">
                                            <div class="filtro-item-ultra">
                                                <InputLabel>&nbsp;</InputLabel>
                                                <div style="height: 36px;"></div>
                                            </div>
                                        </td>
                                        <td style="width: 150px; padding: 0 4px;">
                                            <div class="filtro-item-ultra">
                                                <InputLabel>&nbsp;</InputLabel>
                                                <div style="height: 36px;"></div>
                                            </div>
                                        </td>
                                        <td style="width: 120px; padding: 0 4px; text-align: right;">
                                            <div class="filtro-item-ultra">
                                                <InputLabel>Acciones</InputLabel>
                                                <button
                                                    v-if="filtrosTablaActivos"
                                                    class="btn-clear-ultra"
                                                    @click="limpiarFiltrosTabla"
                                                >
                                                    <i class="pi pi-times"></i> Limpiar
                                                </button>
                                                <button 
                                                    v-else
                                                    class="btn-no-filtros-ultra"
                                                    disabled
                                                >Sin filtros
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="totales-principal-wrapper">
                            <div class="totales-principal-container">
                                <div class="total-item-principal">
                                    <span class="total-label-principal">Total Ingresos</span>
                                    <span class="total-value-principal ingreso">${{ formatNumber(totalIngresos) }}</span>
                                </div>
                                <div class="total-item-principal">
                                    <span class="total-label-principal">Total Egresos</span>
                                    <span class="total-value-principal egreso">${{ formatNumber(totalEgresos) }}</span>
                                </div>
                                <div class="total-item-principal total-balance">
                                    <span class="total-label-principal">Balance</span>
                                    <span class="total-value-principal" :class="balanceClass">${{ formatNumber(balance) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tabla-fondeadoras-premium">
                        <div class="table-header-ultra">
                            <div class="table-header-left-ultra">
                                <span class="table-title-premium">Cuentas Fondeadoras - Saldos</span>
                            </div>
                            <div class="table-header-right-ultra">
                                <span class="total-registros-premium">Total disponible: <strong class="total-disponible">${{ formatNumber(totalDisponible) }}</strong>
                                </span>
                            </div>
                        </div>
                        <div class="table-scroll-container">
                            <DataTable
                                :value="reporteFondeadoras"
                                :loading="loading"
                                data-key="id_cuenta"
                                scrollable
                                scroll-height="200px"
                                row-hover
                                table-style="min-width: 40rem"
                                class="reporte-table-ultra"
                            >
                                <template #empty><div class="tabla-vacia">Sin cuentas fondeadoras.</div></template>
                                <Column header="Código" style="width: 120px">
                                    <template #body="{ data: record }">
                                        <span class="codigo-text-ultra">{{ record.codigo_cuenta || '---' }}</span>
                                    </template>
                                </Column>
                                <Column header="Nombre de la Cuenta" style="width: 300px">
                                    <template #body="{ data: record }">
                                        <span class="nombre-text-ultra">{{ record.nombre_cuenta || '---' }}</span>
                                    </template>
                                </Column>
                                <Column header="Saldo Disponible" style="width: 180px; text-align: right">
                                    <template #body="{ data: record }">
                                        <span class="monto-text-ultra" :class="record.saldo >= 0 ? 'ingreso' : 'egreso'">
                                            ${{ formatNumber(record.saldo || 0) }}
                                        </span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        <div class="totales-fondeadoras-wrapper">
                            <div class="totales-fondeadoras-container">
                                <div class="total-item-fondeadora">
                                    <span class="total-label-fondeadora">Total disponible</span>
                                    <span class="total-value-fondeadora ingreso">${{ formatNumber(totalDisponible) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="reporte-wrapper-premium">
                    <div class="text-center py-12">
                        <i class="pi pi-building empty-state-icon"></i>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No tienes condominios asignados</h3>
                        <p class="text-gray-500">Contacta al administrador para que te asigne un condominio.</p>
                    </div>
                </div>

                <div v-if="loading && empresas.length > 0" class="reporte-wrapper-premium">
                    <div class="text-center py-12">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="text-gray-500 mt-4">Cargando reporte...</p>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- MODAL: CUENTAS DE RESULTADOS -->
        <!-- ============================================ -->
        <Dialog
            v-model:visible="modalResultadosVisible"
            modal
            header="Cuentas de Resultados"
            :style="{ width: '1100px', maxWidth: '96vw' }"
            class="modal-resultados-premium"
        >
            <div class="modal-filtros-container-top">
                <div class="modal-filtros-row-top">
                    <div class="modal-filtro-item-top">
                        <label class="modal-filtro-label-top">Fecha Inicio</label>
                        <input 
                            type="date" 
                            v-model="filtrosResultados.fecha_desde"
                            @change="onResultadosFechaDesdeChange"
                            :max="fechaActual"
                            class="modal-filtro-input-top"
                        />
                    </div>
                    <div class="modal-filtro-item-top">
                        <label class="modal-filtro-label-top">Fecha Fin</label>
                        <input 
                            type="date" 
                            v-model="filtrosResultados.fecha_hasta"
                            @change="onResultadosFechaHastaChange"
                            class="modal-filtro-input-top"
                        />
                    </div>
                    <div class="modal-filtro-item-top modal-filtro-actions-top">
                        <button class="btn-modal-hoy-top" @click="setResultadosFechaHoy">
                            <i class="pi pi-calendar"></i> Hoy
                        </button>
                        <button
                            v-if="filtrosResultados.fecha_desde || filtrosResultados.fecha_hasta"
                            class="btn-modal-limpiar-top"
                            @click="limpiarResultadosFechas"
                        >
                            <i class="pi pi-times"></i> Limpiar
                        </button>
                    </div>
                    <div class="modal-filtros-separator-top"></div>
                    <div class="modal-filtro-item-top">
                        <label class="modal-filtro-label-top">Tipo</label>
                        <select v-model="tipoResultado" @change="aplicarFiltrosResultados" class="modal-filtro-input-top" style="min-width: 150px;">
                            <option value="todas">Todas las cuentas</option>
                            <option value="fiscales">Solo Fiscales</option>
                            <option value="no_fiscales">No Fiscales</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- TOTALES DEL MODAL -->
            <div class="resultados-totales-container">
                <div class="resultados-totales-grid">
                    <div class="resultado-total-item ingresos">
                        <span class="resultado-total-label">Ingresos</span>
                        <span class="resultado-total-value ingreso">${{ formatNumber(totalesResultados.ingresos) }}</span>
                    </div>
                    <div class="resultado-total-item egresos">
                        <span class="resultado-total-label">Egresos</span>
                        <span class="resultado-total-value egreso">${{ formatNumber(totalesResultados.egresos) }}</span>
                    </div>
                    <template v-if="tipoResultado === 'fiscales'">
                        <div class="resultado-total-item iva-ingresos">
                            <span class="resultado-total-label">IVA Ingresos</span>
                            <span class="resultado-total-value iva">${{ formatNumber(totalesResultados.iva_ingresos) }}</span>
                        </div>
                        <div class="resultado-total-item iva-egresos">
                            <span class="resultado-total-label">IVA Egresos</span>
                            <span class="resultado-total-value iva-egreso">${{ formatNumber(totalesResultados.iva_egresos) }}</span>
                        </div>
                        <div class="resultado-total-item balance-iva">
                            <span class="resultado-total-label">Balance IVA</span>
                            <span class="resultado-total-value" :class="totalesResultados.balance_iva >= 0 ? 'iva' : 'iva-egreso'">
                                ${{ formatNumber(totalesResultados.balance_iva) }}
                            </span>
                        </div>
                    </template>
                    <div v-if="tipoResultado !== 'fiscales'" class="resultado-total-item iva">
                        <span class="resultado-total-label">Total IVA</span>
                        <span class="resultado-total-value iva">${{ formatNumber(totalesResultados.iva) }}</span>
                    </div>
                    <div class="resultado-total-item balance">
                        <span class="resultado-total-label">Balance</span>
                        <span class="resultado-total-value" :class="totalesResultados.balance >= 0 ? 'ingreso' : 'egreso'">
                            ${{ formatNumber(totalesResultados.balance) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- INFO DE RESULTADOS -->
            <div class="resultado-info-top">
                <span class="resultado-count-top">
                    <span class="resultado-count-icon">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </span>
                    <strong>{{ cuentasAplanadas.length }}</strong> cuentas encontradas
                </span>
                <span v-if="tipoResultado === 'fiscales'" class="resultado-filter-badge-top fiscal">
                    Filtro: Fiscales
                </span>
                <span v-if="tipoResultado === 'no_fiscales'" class="resultado-filter-badge-top no-fiscal">
                    Filtro: No Fiscales
                </span>
                <span v-if="loadingResultados" class="resultado-loading-top">
                    <span class="spinner-border-sm" role="status"></span> Cargando...
                </span>
                
                <button class="btn-imprimir-modal" @click="imprimirCuentasResultados" :disabled="loadingResultados || cuentasAplanadas.length === 0">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir
                </button>
            </div>

            <!-- TABLA DE RESULTADOS -->
            <div v-if="loadingResultados" class="text-center py-8">
                <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="text-gray-500 mt-3">Cargando cuentas de resultados...</p>
            </div>
            <div v-else-if="cuentasAplanadas.length === 0" class="text-center py-8">
                <div class="empty-state-icon">
                    <svg width="48" height="48" fill="none" stroke="#94a3b8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <p class="text-gray-500 mt-2">No hay cuentas de resultados registradas.</p>
            </div>
            <div v-else>
                <div class="table-scroll-container" style="max-height: 420px;">
                    <DataTable
                        :value="cuentasAplanadas"
                        :loading="loadingResultados"
                        data-key="id_cuenta"
                        scrollable
                        scroll-height="400px"
                        row-hover
                        class="reporte-table-ultra"
                    >
                        <Column header="Nombre de la Cuenta" style="width: 70%">
                            <template #body="{ data: record }">
                                <div class="nombre-container" :style="{ paddingLeft: ((record.nivel || 1) - 1) * 25 + 'px' }">
                                    <span v-if="record.es_madre" class="icon-folder">
                                        <svg width="16" height="16" fill="none" stroke="#1a3a5c" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                        </svg>
                                    </span>
                                    <span v-else class="icon-file">
                                        <svg width="14" height="14" fill="none" stroke="#64748b" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </span>
                                    <span class="nombre-text-ultra clickable" :class="{ 'nombre-madre': record.es_madre }" @click="onRowClickResultados(record)">
                                        {{ record.nombre_cuenta || '---' }}
                                    </span>
                                    <span v-if="record.es_madre" class="subtotal-badge">Subtotal: ${{ formatNumber(record.subtotal || 0) }}
                                    </span>
                                    <span v-if="record.es_fiscal" class="fiscal-badge-mini">FISCAL</span>
                                </div>
                            </template>
                        </Column>
                        <Column header="Saldo" style="width: 30%; text-align: right">
                            <template #body="{ data: record }">
                                <span class="monto-text-ultra" :class="(record.saldo || 0) >= 0 ? 'ingreso' : 'egreso'">
                                    ${{ formatNumber(record.saldo || 0) }}
                                </span>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="totales-resultados-wrapper">
                    <div class="totales-resultados-container">
                        <div class="total-item-resultados">
                            <span class="total-label-resultados">Total de cuentas</span>
                            <span class="total-value-resultados">{{ cuentasAplanadas.length }}</span>
                        </div>
                        <div class="total-item-resultados">
                            <span class="total-label-resultados">Saldo total</span>
                            <span class="total-value-resultados" :class="saldoResultadosTotal >= 0 ? 'ingreso' : 'egreso'">
                                ${{ formatNumber(saldoResultadosTotal) }}
                            </span>
                        </div>
                        <div class="total-item-resultados" v-if="tipoResultado === 'fiscales'">
                            <span class="total-label-resultados">Filtro activo</span>
                            <span class="total-value-resultados fiscal-badge-text">Solo Fiscales</span>
                        </div>
                        <div class="total-item-resultados" v-if="tipoResultado === 'no_fiscales'">
                            <span class="total-label-resultados">Filtro activo</span>
                            <span class="total-value-resultados no-fiscal-badge-text">No Fiscales</span>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- ============================================ -->
        <!-- MODAL: DETALLE DE CUENTA -->
        <!-- ============================================ -->
        <Dialog
            v-model:visible="modalDetalleVisible"
            modal
            :header="'Detalle de: ' + (cuentaSeleccionada?.nombre || cuentaSeleccionada?.nombre_cuenta || '')"
            :style="{ width: '1000px', maxWidth: '96vw' }"
            class="modal-detalle-premium"
        >
            <div v-if="loadingDetalle" class="text-center py-8">
                <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="text-gray-500 mt-3">Cargando movimientos...</p>
            </div>
            <div v-else-if="movimientosCuenta.length === 0" class="text-center py-8">
                <div class="empty-state-icon">
                    <svg width="48" height="48" fill="none" stroke="#94a3b8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <p class="text-gray-500 mt-2">No hay movimientos para esta cuenta.</p>
            </div>
            <div v-else>
                <div class="filtro-activo-detalle" v-if="tipoFiltroDetalle !== 'todas'">
                    <span class="filtro-detalle-badge" :class="tipoFiltroDetalle === 'fiscales' ? 'fiscal' : 'no-fiscal'">
                        {{ tipoFiltroDetalle === 'fiscales' ? ' Solo Fiscales' : 'No Fiscales' }}
                    </span>
                    <span class="filtro-detalle-text">Filtro aplicado en este detalle</span>
                </div>

                <div class="totales-modal-detalle">
                    <div class="total-modal-item">
                        <span class="total-modal-label">Total Ingresos</span>
                        <span class="total-modal-value ingreso">${{ formatNumber(totalIngresosCuenta) }}</span>
                    </div>
                    <div class="total-modal-item">
                        <span class="total-modal-label">Total Egresos</span>
                        <span class="total-modal-value egreso">${{ formatNumber(totalEgresosCuenta) }}</span>
                    </div>
                    <div class="total-modal-item total-modal-balance">
                        <span class="total-modal-label">Balance</span>
                        <span class="total-modal-value" :class="balanceCuentaClass">${{ formatNumber(balanceCuenta) }}</span>
                    </div>
                    <div class="total-modal-item">
                        <span class="total-modal-label">Movimientos</span>
                        <span class="total-modal-value">{{ movimientosCuenta.length }}</span>
                    </div>
                </div>
                <div class="table-scroll-container" style="max-height: 400px;">
                    <DataTable
                        :value="movimientosCuenta"
                        data-key="id_movimiento"
                        scrollable
                        scroll-height="350px"
                        row-hover
                        table-style="min-width: 60rem"
                        class="reporte-table-ultra"
                    >
                        <Column
                            v-for="col in columnasDetalle"
                            :key="col.key"
                            :header="col.title"
                            :style="{ width: col.width, textAlign: col.align || 'left' }"
                        >
                            <template #body="{ data: record }">
                                <span v-if="col.key === 'fecha'" class="fecha-text-ultra">{{ record.fecha_poliza || '---' }}</span>
                                <span v-else-if="col.key === 'folio'" class="folio-text-ultra clickable" @click="verPoliza(record.id_poliza)">{{ record.folio || '---' }}</span>
                                <span v-else-if="col.key === 'persona'" class="persona-text-ultra">{{ record.persona || '---' }}</span>
                                <span v-else-if="col.key === 'cuenta'" class="cuenta-text-ultra">{{ record.cuenta || '---' }}</span>
                                <span v-else-if="col.key === 'cuenta_fondeadora'" class="fondeo-text-ultra">{{ record.cuenta_fondeadora || '---' }}</span>
                                <span v-else-if="col.key === 'monto'" class="monto-text-ultra" :class="record.tipo === 'INGRESO' ? 'ingreso' : 'egreso'">${{ formatNumber(record.monto) }}</span>
                                <span v-else-if="col.key === 'categoria'" class="categoria-badge" :class="record.categoria === 'FISCAL' ? 'fiscal' : 'no-fiscal'">{{ record.categoria || 'NO FISCAL' }}</span>
                                <button v-else-if="col.key === 'acciones'" type="button" class="btn-ver-poliza-mini" @click="verPoliza(record.id_poliza)">Ver Póliza</button>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </Dialog>

    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { BarChartOutlined } from '@ant-design/icons-vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReportesCondominio from '@/Components/ReportesCondominio.vue';
import InputLabel from '@/Components/InputLabel.vue';
import axios from 'axios';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import { useNotify } from '@/composables/useNotify';
import { useEmpresa } from '@/composables/useEmpresa';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    reporte: { type: Object, default: () => ({ data: [], fondeadoras: [] }) },
    filtros: { type: Object, default: () => ({}) },
    empresa_seleccionada: { type: [Number, String], default: null },
    vista: { type: String, default: 'por_cuenta' }
});

const { empresaSeleccionada, cargarEmpresaGuardada, guardarEmpresa } = useEmpresa();

// Sección visible (se recuerda en la dirección: ?seccion=condominio).
const seccion = ref(new URLSearchParams(window.location.search).get('seccion') === 'condominio' ? 'condominio' : 'contables');
watch(seccion, (v) => {
    const url = new URL(window.location.href);
    if (v === 'condominio') url.searchParams.set('seccion', v); else url.searchParams.delete('seccion');
    history.replaceState(history.state, '', url);
});
const empresaCondominio = ref(Number(props.empresa_seleccionada) || props.empresas[0]?.id || null);

// ============================================
// REFS Y VARIABLES
// ============================================
const loading = ref(false);
const cargado = ref(false);
const notify = useNotify();

const obtenerFechaLocal = () => {
    const hoy = new Date();
    const year = hoy.getFullYear();
    const month = String(hoy.getMonth() + 1).padStart(2, '0');
    const day = String(hoy.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const fechaActual = ref(obtenerFechaLocal());
const vistaActual = ref(props.vista || 'por_cuenta');
let timeoutTabla = null;

// ============================================
// MODAL DE RESULTADOS
// ============================================
const modalResultadosVisible = ref(false);
const loadingResultados = ref(false);
const cuentasResultados = ref([]);
const cuentasAplanadas = ref([]);
const tipoResultado = ref('todas');

const filtrosResultados = ref({
    fecha_desde: '',
    fecha_hasta: ''
});

// ============================================
// TOTALES DEL MODAL DE RESULTADOS
// ============================================
const totalesResultados = ref({
    ingresos: 0,
    egresos: 0,
    iva: 0,
    iva_ingresos: 0,
    iva_egresos: 0,
    balance_iva: 0,
    balance: 0
});

// ============================================
// MODAL DE DETALLE
// ============================================
const modalDetalleVisible = ref(false);
const loadingDetalle = ref(false);
const cuentaSeleccionada = ref(null);
const movimientosCuenta = ref([]);
const totalIngresosCuenta = ref(0);
const totalEgresosCuenta = ref(0);
const tipoFiltroDetalle = ref('todas');

// ============================================
// FILTROS PRINCIPALES
// ============================================
const filtros = ref({
    fecha_desde: '',
    fecha_hasta: '',
});

// ============================================
// FILTROS DE TABLA
// ============================================
const filtrosTabla = ref({ 
    codigo: '',
    nombre: '', 
    persona: '', 
    fondeo: '' 
});

const filtrosTablaActivos = computed(() => {
    return Object.values(filtrosTabla.value).some(v => v !== '' && v !== null && v !== undefined);
});

// ============================================
// DATOS DEL REPORTE
// ============================================
const reporteData = ref([]);
const reporteFondeadoras = ref([]);

// ============================================
// COLUMNAS
// ============================================
const columnasPrincipales = computed(() => {
    if (vistaActual.value === 'por_cuenta') {
        return [
            { title: 'Código', key: 'codigo', width: '120px', fixed: 'left' },
            { title: 'Cuenta de Orden', key: 'nombre', width: '200px' },
            { title: 'Persona', key: 'persona', width: '180px' },
            { title: 'Cuenta Fondeadora', key: 'fondeo', width: '180px' },
            { title: 'Ingreso', key: 'ingreso', width: '150px', align: 'right' },
            { title: 'Egreso', key: 'egreso', width: '150px', align: 'right' }
        ];
    } else {
        return [
            { title: 'Persona', key: 'nombre', width: '250px', fixed: 'left' },
            { title: 'Fondero', key: 'fondeo', width: '200px' },
            { title: 'Ingreso', key: 'ingreso', width: '150px', align: 'right' },
            { title: 'Egreso', key: 'egreso', width: '150px', align: 'right' }
        ];
    }
});

const columnasFondeadoras = [
    { title: 'Código', key: 'codigo', width: '120px' },
    { title: 'Nombre de la Cuenta', key: 'nombre', width: '300px' },
    { title: 'Saldo Disponible', key: 'saldo', width: '180px', align: 'right' }
];

const columnasResultados = [
    { title: 'Nombre de la Cuenta', key: 'nombre', width: '70%' },
    { title: 'Saldo', key: 'saldo', width: '30%', align: 'right' }
];

const columnasDetalle = [
    { title: 'Fecha', key: 'fecha', width: '120px' },
    { title: 'Folio', key: 'folio', width: '140px' },
    { title: 'Persona', key: 'persona', width: '180px' },
    { title: 'Cuenta', key: 'cuenta', width: '180px' },
    { title: 'Cuenta Fondero', key: 'cuenta_fondeadora', width: '180px' },
    { title: 'Monto', key: 'monto', width: '140px', align: 'right' },
    { title: 'Categoría', key: 'categoria', width: '120px', align: 'center' },
    { title: 'Acciones', key: 'acciones', width: '120px', align: 'center' }
];

// ============================================
// COMPUTED
// ============================================
const filtrosActivos = computed(() => {
    const { fecha_desde, fecha_hasta } = filtros.value;
    return !!(fecha_desde || fecha_hasta);
});

const datosFiltrados = computed(() => {
    let data = reporteData.value;
    
    if (filtrosTabla.value.codigo) {
        const search = filtrosTabla.value.codigo.toLowerCase().trim();
        data = data.filter(item => 
            item.codigo && item.codigo.toLowerCase().includes(search)
        );
    }
    
    if (filtrosTabla.value.nombre) {
        const search = filtrosTabla.value.nombre.toLowerCase().trim();
        data = data.filter(item => 
            item.nombre && item.nombre.toLowerCase().includes(search)
        );
    }
    
    if (filtrosTabla.value.persona) {
        const search = filtrosTabla.value.persona.toLowerCase().trim();
        data = data.filter(item => 
            item.persona && item.persona.toLowerCase().includes(search)
        );
    }
    
    if (filtrosTabla.value.fondeo) {
        const search = filtrosTabla.value.fondeo.toLowerCase().trim();
        data = data.filter(item => 
            item.fondeo && item.fondeo.toLowerCase().includes(search)
        );
    }
    
    return data;
});

const totalIngresos = computed(() => {
    return datosFiltrados.value.reduce((sum, item) => sum + (item.ingreso || 0), 0);
});

const totalEgresos = computed(() => {
    return datosFiltrados.value.reduce((sum, item) => sum + (item.egreso || 0), 0);
});

const balance = computed(() => {
    return totalIngresos.value - totalEgresos.value;
});

const balanceClass = computed(() => {
    if (balance.value > 0) return 'ingreso';
    if (balance.value < 0) return 'egreso';
    return 'neutro';
});

const totalDisponible = computed(() => {
    return reporteFondeadoras.value.reduce((sum, item) => sum + (item.saldo || 0), 0);
});

const saldoResultadosTotal = computed(() => {
    if (!cuentasAplanadas.value || cuentasAplanadas.value.length === 0) {
        return 0;
    }
    const cuentasMadre = cuentasAplanadas.value.filter(item => item.es_madre === true);
    return cuentasMadre.reduce((sum, item) => {
        const saldo = parseFloat(item.saldo) || 0;
        return sum + saldo;
    }, 0);
});

const balanceCuenta = computed(() => {
    return totalIngresosCuenta.value - totalEgresosCuenta.value;
});

const balanceCuentaClass = computed(() => {
    if (balanceCuenta.value > 0) return 'ingreso';
    if (balanceCuenta.value < 0) return 'egreso';
    return 'neutro';
});

// ============================================
// FUNCIONES DE FORMATO
// ============================================
const formatNumber = (value) => {
    if (value === null || value === undefined || isNaN(value)) {
        return '0.00';
    }
    return Number(value).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

// ============================================
// FUNCIÓN PARA APLANAR CUENTAS CON ORDEN INGRESOS/EGRESOS
// ============================================
const aplanarCuentasJerarquicas = (cuentas, nivel = 1, resultado = []) => {
    const cuentasFiltradas = Array.isArray(cuentas) ? cuentas.filter(c => c !== '_totales') : cuentas;
    
    // ORDENAR: INGRESOS (saldo >= 0) ARRIBA, EGRESOS (saldo < 0) ABAJO
    const cuentasOrdenadas = [...cuentasFiltradas].sort((a, b) => {
        const aEsIngreso = (a.subtotal || 0) >= 0;
        const bEsIngreso = (b.subtotal || 0) >= 0;
        
        if (aEsIngreso && !bEsIngreso) return -1;
        if (!aEsIngreso && bEsIngreso) return 1;
        return (a.codigo_cuenta || '').localeCompare(b.codigo_cuenta || '');
    });
    
    cuentasOrdenadas.forEach(cuenta => {
        const cuentaAplanada = {
            ...cuenta,
            nivel: nivel
        };
        resultado.push(cuentaAplanada);
        
        if (cuenta.hijas && cuenta.hijas.length > 0) {
            // TAMBIÉN ORDENAR LAS HIJAS
            const hijasOrdenadas = [...cuenta.hijas].sort((a, b) => {
                const aEsIngreso = (a.subtotal || 0) >= 0;
                const bEsIngreso = (b.subtotal || 0) >= 0;
                
                if (aEsIngreso && !bEsIngreso) return -1;
                if (!aEsIngreso && bEsIngreso) return 1;
                return (a.codigo_cuenta || '').localeCompare(b.codigo_cuenta || '');
            });
            
            aplanarCuentasJerarquicas(hijasOrdenadas, nivel + 1, resultado);
        }
    });
    return resultado;
};

// ============================================
// FUNCIONES DE FECHAS - VISTA PRINCIPAL
// ============================================
const onFechaHastaChange = () => {
    if (filtros.value.fecha_hasta) {
        filtros.value.fecha_desde = filtros.value.fecha_hasta;
    }
    cargarReporte();
};

const onFechaDesdeChange = () => {
    cargarReporte();
};

const limpiarFechas = () => {
    filtros.value.fecha_desde = '';
    filtros.value.fecha_hasta = '';
    cargarReporte();
};

const setFechaHoy = () => {
    const hoy = obtenerFechaLocal();
    filtros.value.fecha_desde = hoy;
    filtros.value.fecha_hasta = hoy;
    cargarReporte();
};

// ============================================
// FUNCIONES DE FECHAS - MODAL RESULTADOS
// ============================================
const onResultadosFechaHastaChange = () => {
    if (filtrosResultados.value.fecha_hasta) {
        filtrosResultados.value.fecha_desde = filtrosResultados.value.fecha_hasta;
    }
    aplicarFiltrosResultados();
};

const onResultadosFechaDesdeChange = () => {
    aplicarFiltrosResultados();
};

const limpiarResultadosFechas = () => {
    filtrosResultados.value.fecha_desde = '';
    filtrosResultados.value.fecha_hasta = '';
    aplicarFiltrosResultados();
};

const setResultadosFechaHoy = () => {
    const hoy = obtenerFechaLocal();
    filtrosResultados.value.fecha_desde = hoy;
    filtrosResultados.value.fecha_hasta = hoy;
    aplicarFiltrosResultados();
};

// ============================================
// FUNCIONES DEL MODAL DE RESULTADOS
// ============================================
const aplicarFiltrosResultados = async () => {
    if (!empresaSeleccionada.value) return;
    
    loadingResultados.value = true;
    
    try {
        const params = {
            empresa_id: empresaSeleccionada.value,
            vista: vistaActual.value,
            fecha_desde: filtrosResultados.value.fecha_desde,
            fecha_hasta: filtrosResultados.value.fecha_hasta,
            tipo_filtro: tipoResultado.value
        };

        const response = await axios.get(route('reportes.movimientos'), { params });

        if (response.data.success) {
            const cuentas = response.data.cuentas_resultados || [];
            // APLANAR CON ORDEN INGRESOS/EGRESOS
            cuentasAplanadas.value = aplanarCuentasJerarquicas(cuentas);
            cuentasResultados.value = cuentas;
            
            totalesResultados.value = {
                ingresos: response.data.totales_ingresos || 0,
                egresos: response.data.totales_egresos || 0,
                iva: response.data.total_iva || 0,
                iva_ingresos: response.data.total_iva_ingresos || 0,
                iva_egresos: response.data.total_iva_egresos || 0,
                balance_iva: response.data.balance_iva || 0,
                balance: (response.data.totales_ingresos || 0) - (response.data.totales_egresos || 0)
            };
        } else {
            throw new Error(response.data.message || 'Error al cargar las cuentas de resultados');
        }
    } catch (error) {
        console.error('Error al filtrar cuentas de resultados:', error);
        notify.error(error.response?.data?.message || error.message || 'Error al filtrar las cuentas', 'Error');
    } finally {
        loadingResultados.value = false;
    }
};

// ============================================
// IMPRIMIR CUENTAS DE RESULTADOS
// ============================================
const imprimirCuentasResultados = async () => {
    if (!empresaSeleccionada.value) {
        notify.warn('Selecciona un condominio primero', 'Sin condominio');
        return;
    }

    if (cuentasAplanadas.value.length === 0) {
        notify.info('No hay cuentas de resultados para imprimir', 'Sin datos');
        return;
    }

    try {
        const params = new URLSearchParams();
        params.append('empresa_id', empresaSeleccionada.value);
        params.append('vista', vistaActual.value);
        params.append('fecha_desde', filtrosResultados.value.fecha_desde || '');
        params.append('fecha_hasta', filtrosResultados.value.fecha_hasta || '');
        params.append('tipo_filtro', tipoResultado.value);

        window.open(route('reportes.export.pdf.resultados') + '?' + params.toString(), '_blank');

    } catch (error) {
        console.error('Error al imprimir:', error);
        notify.error(error.message || 'Error al generar el PDF', 'Error');
    }
};

// ============================================
// FILTROS DE TABLA
// ============================================
const aplicarFiltrosTabla = () => {
    clearTimeout(timeoutTabla);
    timeoutTabla = setTimeout(() => {}, 300);
};

const limpiarFiltrosTabla = () => {
    filtrosTabla.value = { codigo: '', nombre: '', persona: '', fondeo: '' };
};

// ============================================
// CAMBIAR VISTA
// ============================================
const cambiarVista = (vista) => {
    if (vistaActual.value === vista) return;
    vistaActual.value = vista;
    limpiarFiltrosTabla();
    cargarReporte();
};

// ============================================
// CAMBIAR EMPRESA
// ============================================
const cambiarEmpresa = () => {
    if (empresaSeleccionada.value) {
        guardarEmpresa(empresaSeleccionada.value);
        cargarReporte();
    }
};

// ============================================
// ABRIR MODAL DE RESULTADOS
// ============================================
const abrirModalCuentasResultados = async () => {
    if (!empresaSeleccionada.value) {
        notify.warn('Selecciona un condominio primero', 'Sin condominio');
        return;
    }

    filtrosResultados.value.fecha_desde = '';
    filtrosResultados.value.fecha_hasta = '';

    modalResultadosVisible.value = true;
    loadingResultados.value = true;
    tipoResultado.value = 'todas';

    try {
        const params = {
            empresa_id: empresaSeleccionada.value,
            vista: vistaActual.value,
            fecha_desde: filtrosResultados.value.fecha_desde,
            fecha_hasta: filtrosResultados.value.fecha_hasta,
            tipo_filtro: 'todas'
        };

        const response = await axios.get(route('reportes.movimientos'), { params });

        if (response.data.success) {
            const cuentas = response.data.cuentas_resultados || [];
            // APLANAR CON ORDEN INGRESOS/EGRESOS
            cuentasAplanadas.value = aplanarCuentasJerarquicas(cuentas);
            cuentasResultados.value = cuentas;
            
            totalesResultados.value = {
                ingresos: response.data.totales_ingresos || 0,
                egresos: response.data.totales_egresos || 0,
                iva: response.data.total_iva || 0,
                iva_ingresos: response.data.total_iva_ingresos || 0,
                iva_egresos: response.data.total_iva_egresos || 0,
                balance_iva: response.data.balance_iva || 0,
                balance: (response.data.totales_ingresos || 0) - (response.data.totales_egresos || 0)
            };
        } else {
            throw new Error(response.data.message || 'Error al cargar las cuentas de resultados');
        }
    } catch (error) {
        console.error('Error al cargar cuentas de resultados:', error);
        notify.error(error.response?.data?.message || error.message || 'Error al cargar las cuentas de resultados', 'Error');
        cuentasResultados.value = [];
        cuentasAplanadas.value = [];
        totalesResultados.value = {
            ingresos: 0,
            egresos: 0,
            iva: 0,
            iva_ingresos: 0,
            iva_egresos: 0,
            balance_iva: 0,
            balance: 0
        };
    } finally {
        loadingResultados.value = false;
    }
};

// ============================================
// CARGAR REPORTE
// ============================================
const cargarReporte = async () => {
    if (!empresaSeleccionada.value) {
        notify.warn('Selecciona un condominio primero', 'Sin condominio');
        return;
    }

    loading.value = true;
    cargado.value = false;

    try {
        const params = {
            empresa_id: empresaSeleccionada.value,
            vista: vistaActual.value,
            fecha_desde: filtros.value.fecha_desde,
            fecha_hasta: filtros.value.fecha_hasta,
            tipo_filtro: 'todas'
        };

        const response = await axios.get(route('reportes.movimientos'), { params });

        if (response.data.success) {
            reporteData.value = response.data.data || [];
            reporteFondeadoras.value = response.data.fondeadoras || [];
            cargado.value = true;
        } else {
            throw new Error(response.data.message || 'Error al cargar el reporte');
        }

    } catch (error) {
        console.error('Error al cargar reporte:', error);
        notify.error(error.response?.data?.message || error.message || 'Ocurrió un error inesperado', 'Error al cargar');
        reporteData.value = [];
        reporteFondeadoras.value = [];
        cargado.value = true;
    } finally {
        loading.value = false;
    }
};

// ============================================
// CLICK EN FILA - ABRIR DETALLE
// ============================================
const onRowClick = (record) => {
    if (vistaActual.value === 'por_cuenta' && record.id_cuenta) {
        abrirDetalleCuenta(record, 'todas');
    }
};

// ============================================
// ABRIR DETALLE DE CUENTA
// ============================================
const abrirDetalleCuenta = async (record, tipoFiltro = 'todas', fechaDesde = '', fechaHasta = '') => {
    if (!record.id_cuenta) return;
    
    cuentaSeleccionada.value = record;
    tipoFiltroDetalle.value = tipoFiltro;
    modalDetalleVisible.value = true;
    loadingDetalle.value = true;
    movimientosCuenta.value = [];
    totalIngresosCuenta.value = 0;
    totalEgresosCuenta.value = 0;

    try {
        const fechaDesdeFinal = fechaDesde || filtrosResultados.value.fecha_desde || '';
        const fechaHastaFinal = fechaHasta || filtrosResultados.value.fecha_hasta || '';
        const tipoFiltroFinal = tipoFiltro || tipoResultado.value || 'todas';

        const params = {
            empresa_id: empresaSeleccionada.value,
            id_cuenta: record.id_cuenta,
            tipo_filtro: tipoFiltroFinal,
            fecha_desde: fechaDesdeFinal,
            fecha_hasta: fechaHastaFinal
        };

        const response = await axios.get(route('reportes.movimientos.cuenta'), { params });

        if (response.data.success) {
            movimientosCuenta.value = response.data.data || [];
            totalIngresosCuenta.value = response.data.total_ingresos || 0;
            totalEgresosCuenta.value = response.data.total_egresos || 0;
        } else {
            throw new Error(response.data.message || 'Error al cargar los movimientos');
        }
    } catch (error) {
        console.error('Error al cargar movimientos de cuenta:', error);
        notify.error(error.response?.data?.message || error.message || 'Error al cargar los movimientos', 'Error');
    } finally {
        loadingDetalle.value = false;
    }
};

// ============================================
// CLICK EN FILA DE RESULTADOS
// ============================================
const onRowClickResultados = (record) => {
    const recordData = {
        id_cuenta: record.id_cuenta,
        nombre: record.nombre_cuenta,
        codigo: record.codigo_cuenta || '---',
        persona: 'N/A',
        fondeo: 'N/A'
    };
    
    abrirDetalleCuenta(
        recordData, 
        tipoResultado.value,
        filtrosResultados.value.fecha_desde,
        filtrosResultados.value.fecha_hasta
    );
};

// ============================================
// VER POLIZA
// ============================================
const verPoliza = (idPoliza) => {
    if (idPoliza) {
        router.visit(route('movimientos.index', { poliza_id: idPoliza }));
    }
};

// ============================================
// EXPORTAR EXCEL
// ============================================
const exportarExcel = () => {
    if (!empresaSeleccionada.value) {
        notify.warn('Selecciona un condominio primero', 'Sin condominio');
        return;
    }
    if (!reporteData.value.length) {
        notify.info('No hay datos para exportar', 'Sin datos');
        return;
    }
    
    const params = new URLSearchParams();
    params.append('empresa_id', empresaSeleccionada.value);
    params.append('vista', vistaActual.value);
    params.append('fecha_desde', filtros.value.fecha_desde || '');
    params.append('fecha_hasta', filtros.value.fecha_hasta || '');
    params.append('tipo_filtro', 'todas');
    
    window.open(route('reportes.export.excel') + '?' + params.toString(), '_blank');
};

// ============================================
// LIFECYCLE
// ============================================
onMounted(() => {
    const hoy = obtenerFechaLocal();
    filtros.value.fecha_desde = hoy;
    filtros.value.fecha_hasta = hoy;
    
    if (props.filtros?.fecha_desde) {
        filtros.value.fecha_desde = props.filtros.fecha_desde;
    }
    if (props.filtros?.fecha_hasta) {
        filtros.value.fecha_hasta = props.filtros.fecha_hasta;
    }
    
    const empresaGuardada = cargarEmpresaGuardada();
    
    if (empresaGuardada && props.empresas.some(e => e.id === empresaGuardada)) {
        empresaSeleccionada.value = empresaGuardada;
    } else if (props.empresa_seleccionada) {
        empresaSeleccionada.value = parseInt(props.empresa_seleccionada);
        guardarEmpresa(props.empresa_seleccionada);
    } else if (props.empresas && props.empresas.length > 0) {
        empresaSeleccionada.value = props.empresas[0].id;
        guardarEmpresa(props.empresas[0].id);
    }
    
    if (empresaSeleccionada.value) {
        cargarReporte();
    }
});
</script>

<style scoped>
/* ===== EMPRESA SELECTOR ===== */
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

.empresa-selector-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
}

@media (max-width: 640px) {
    .empresa-selector-actions {
        margin-left: 0;
        width: 100%;
        flex-wrap: wrap;
    }
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
    appearance: auto;
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

.view-toggle-btn:first-child {
    border-right: 1px solid #e2e8f0;
}

/* ===== BARRA SUPERIOR DE FILTROS ===== */
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
.btn-resultados-mini {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 13px;
}

.tabla-vacia {
    padding: 28px 16px;
    text-align: center;
    color: #94a3b8;
    font-size: 14px;
}

.btn-ver-poliza-mini {
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    border: none;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-ver-poliza-mini:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(26, 58, 92, 0.25);
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

.btn-resultados-mini {
    border-radius: 6px !important;
    background: linear-gradient(135deg, #132a44, #6d28d9) !important;
    border: none !important;
    color: white !important;
    height: 36px !important;
    font-weight: 600 !important;
    padding: 0 16px !important;
    transition: all 0.3s ease !important;
}

.btn-resultados-mini:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 58, 92, 0.3) !important;
}

/* ===== REPORTE WRAPPER ===== */
.reporte-wrapper-premium {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #f0f2f5;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    padding: 20px;
}

/* ===== HEADER DE TABLA ===== */
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

.table-title-premium {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.table-header-right-ultra {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.total-registros-premium {
    font-size: 13px;
    color: #64748b;
}

.total-registros-premium strong {
    color: #0f172a;
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

/* ===== CONTENEDOR DE TABLA ===== */
.table-scroll-container {
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
}

.table-scroll-container :deep(.p-datatable-table-container) {
    max-height: 400px !important;
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

/* ===== TABLA ANT DESIGN ===== */
.reporte-table-ultra {
    width: 100%;
}

.reporte-table-ultra :deep(.p-datatable-thead > tr > th) {
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

.reporte-table-ultra :deep(.p-datatable-tbody > tr:hover) {
    background: linear-gradient(90deg, #f8faff, #f0f7ff) !important;
    box-shadow: inset 0 0 0 1px #1a3a5c;
}

.reporte-table-ultra :deep(.p-datatable-tbody > tr:nth-child(even)) {
    background: #fafbfc;
}

.reporte-table-ultra :deep(.p-datatable-tbody > tr:nth-child(even):hover) {
    background: linear-gradient(90deg, #f8faff, #f0f7ff) !important;
}

.reporte-table-ultra :deep(.p-datatable-tbody > tr > td) {
    padding: 10px 12px !important;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}

.reporte-table-ultra :deep(.p-frozen-column) {
    background: #ffffff;
    z-index: 5;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.04);
}

/* ===== CELDAS ===== */
.clickable {
    cursor: pointer;
}

.clickable:hover {
    color: #1a3a5c;
    text-decoration: underline;
}

.codigo-text-ultra {
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    font-family: 'Courier New', monospace;
}

.nombre-text-ultra {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.persona-text-ultra {
    font-size: 13px;
    color: #475569;
}

.fondeo-text-ultra {
    font-size: 13px;
    color: #475569;
}

.monto-text-ultra {
    font-size: 14px;
    font-weight: 700;
    font-family: 'Courier New', monospace;
}

.monto-text-ultra.ingreso {
    color: #2563eb;
}

.monto-text-ultra.egreso {
    color: #dc2626;
}

.monto-text-ultra.neutro {
    color: #94a3b8;
}

/* ===== FILTROS INFERIOR ===== */
.filtros-ultra-full {
    margin-top: 12px;
    padding: 8px 0;
    background: transparent;
    overflow-x: auto;
}

.filtros-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    min-width: 980px;
}

.filtros-table td {
    padding: 0 4px;
    vertical-align: bottom;
}

.filtros-table td:first-child {
    padding-left: 0;
}

.filtros-table td:last-child {
    padding-right: 0;
}

.filtro-item-ultra {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filtro-item-ultra :deep(.input-label) {
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    margin-bottom: 0 !important;
}

.filtro-input-ultra {
    height: 36px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    padding: 0 10px;
    font-size: 12px;
    width: 100%;
    outline: none;
    transition: all 0.3s ease;
    background: #ffffff;
    color: #0f172a;
    box-sizing: border-box;
}

.filtro-input-ultra:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1);
}

.filtro-input-ultra::placeholder {
    color: #94a3b8;
    font-size: 11px;
}

.btn-clear-ultra {
    height: 36px;
    border: none;
    border-radius: 6px;
    padding: 0 16px;
    font-size: 12px;
    font-weight: 700;
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(26, 58, 92, 0.2);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    width: 100%;
    justify-content: center;
}

.btn-clear-ultra:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.3);
}

.btn-no-filtros-ultra {
    height: 36px;
    border: 2px dashed #d1d5db;
    border-radius: 6px;
    padding: 0 16px;
    font-size: 12px;
    font-weight: 600;
    background: #f8fafc;
    color: #94a3b8;
    cursor: not-allowed;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    width: 100%;
}

/* ===== TOTALES ===== */
.totales-principal-wrapper {
    margin-top: 16px;
}

.totales-principal-container {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-radius: 12px;
    padding: 16px 24px;
    border: 1px solid #e2e8f0;
}

.total-item-principal {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 16px;
    background: white;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
    min-width: 140px;
    flex: 1;
}

.total-item-principal.total-balance {
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    border-color: #1a3a5c;
}

.total-item-principal.total-balance .total-label-principal {
    color: rgba(255, 255, 255, 0.7);
}

.total-item-principal.total-balance .total-value-principal {
    color: white;
}

.total-label-principal {
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.total-value-principal {
    font-size: 20px;
    font-weight: 700;
    font-family: 'Courier New', monospace;
}

.total-value-principal.ingreso {
    color: #2563eb;
}

.total-value-principal.egreso {
    color: #dc2626;
}

.total-value-principal.neutro {
    color: #94a3b8;
}

/* ===== TOTALES FONDEADORAS ===== */
.tabla-fondeadoras-premium {
    margin-top: 32px;
    padding-top: 32px;
    border-top: 2px solid #f1f5f9;
}

.totales-fondeadoras-wrapper {
    margin-top: 16px;
}

.totales-fondeadoras-container {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border-radius: 12px;
    padding: 16px 24px;
    border: 1px solid #a7f3d0;
}

.total-item-fondeadora {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 16px;
    background: white;
    border-radius: 8px;
    border: 1px solid #d1fae5;
    min-width: 140px;
    flex: 1;
}

.total-label-fondeadora {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.total-value-fondeadora {
    font-size: 20px;
    font-weight: 700;
    font-family: 'Courier New', monospace;
}

.total-value-fondeadora.ingreso {
    color: #10b981;
}

.total-disponible {
    color: #10b981 !important;
    font-size: 16px;
}

/* ===== MODALES ===== */
.modal-resultados-premium :deep(.p-dialog-header) {
    background: linear-gradient(135deg, #132a44, #6d28d9);
    border-radius: 8px 8px 0 0;
    padding: 16px 24px;
}

.modal-resultados-premium :deep(.p-dialog-title) {
    color: white;
    font-weight: 700;
}

.modal-resultados-premium :deep(.p-dialog-close-button) {
    color: white;
}

.modal-resultados-premium :deep(.p-dialog-close-button:hover) {
    color: #fca5a5;
}

.modal-detalle-premium :deep(.p-dialog-header) {
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    border-radius: 8px 8px 0 0;
    padding: 16px 24px;
}

.modal-detalle-premium :deep(.p-dialog-title) {
    color: white;
    font-weight: 700;
}

.modal-detalle-premium :deep(.p-dialog-close-button) {
    color: white;
}

.modal-detalle-premium :deep(.p-dialog-close-button:hover) {
    color: #fca5a5;
}

/* ===== MODAL FILTROS ===== */
.modal-filtros-container-top {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 14px;
    border: 1px solid #e2e8f0;
}

.modal-filtros-row-top {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}

.modal-filtro-item-top {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.modal-filtro-label-top {
    font-size: 10px;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.modal-filtro-input-top {
    padding: 5px 10px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    font-size: 12px;
    height: 32px;
    background: #ffffff;
    color: #0f172a;
    transition: all 0.3s ease;
    outline: none;
    min-width: 130px;
}

.modal-filtro-input-top:focus {
    border-color: #1a3a5c;
    box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1);
}

.modal-filtro-actions-top {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    gap: 6px;
    min-width: auto;
}

.modal-filtros-separator-top {
    width: 1px;
    height: 32px;
    background: #d1d5db;
    flex-shrink: 0;
}

.btn-modal-hoy-top {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 32px;
}

.btn-modal-hoy-top:hover {
    border-color: #1a3a5c;
    color: #1a3a5c;
    transform: translateY(-1px);
}

.btn-modal-limpiar-top {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 32px;
}

.btn-modal-limpiar-top:hover {
    border-color: #dc2626;
    color: #dc2626;
    transform: translateY(-1px);
}

/* ===== TOTALES MODAL RESULTADOS ===== */
.resultados-totales-container {
    margin-bottom: 14px;
    padding: 10px 14px;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.resultados-totales-grid {
    display: flex;
    flex-direction: row;
    gap: 6px;
    align-items: stretch;
    flex-wrap: nowrap;
    justify-content: center;
}

.resultado-total-item {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 6px 12px;
    background: white;
    border-radius: 6px;
    border: 1px solid #f1f5f9;
    transition: all 0.3s ease;
    flex: 1 1 auto;
    min-width: 80px;
    max-width: 160px;
}

.resultado-total-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.resultado-total-item.ingresos {
    border-left: 3px solid #2563eb;
}

.resultado-total-item.egresos {
    border-left: 3px solid #dc2626;
}

.resultado-total-item.iva {
    border-left: 3px solid #132a44;
}

.resultado-total-item.iva-ingresos {
    border-left: 3px solid #1a3a5c;
}

.resultado-total-item.iva-egresos {
    border-left: 3px solid #ef4444;
}

.resultado-total-item.balance-iva {
    border-left: 3px solid #1a3a5c;
}

.resultado-total-item.balance {
    border-left: 3px solid #1a3a5c;
}

.resultado-total-label {
    font-size: 9px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.resultado-total-value {
    font-size: 15px;
    font-weight: 700;
    font-family: 'Courier New', monospace;
}

.resultado-total-value.ingreso {
    color: #2563eb;
}

.resultado-total-value.egreso {
    color: #dc2626;
}

.resultado-total-value.iva {
    color: #132a44;
}

.resultado-total-value.iva-egreso {
    color: #ef4444;
}

@media (max-width: 992px) {
    .resultados-totales-grid {
        flex-wrap: wrap;
        justify-content: center;
    }
    .resultado-total-item {
        min-width: 100px;
        max-width: 160px;
        flex: 0 1 auto;
    }
}

@media (max-width: 768px) {
    .resultados-totales-grid {
        flex-wrap: wrap;
        justify-content: center;
        gap: 4px;
    }
    .resultado-total-item {
        min-width: 80px;
        max-width: 130px;
        padding: 4px 10px;
    }
    .resultado-total-value {
        font-size: 13px;
    }
}

@media (max-width: 480px) {
    .resultado-total-item {
        min-width: 70px;
        max-width: 100px;
        padding: 3px 8px;
    }
    .resultado-total-value {
        font-size: 11px;
    }
    .resultado-total-label {
        font-size: 7px;
    }
}

/* ===== RESULTADO INFO TOP ===== */
.resultado-info-top {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 4px 12px 4px;
    flex-wrap: wrap;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 12px;
}

.resultado-count-top {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #64748b;
}

.resultado-count-icon {
    display: flex;
    align-items: center;
    color: #94a3b8;
}

.resultado-count-top strong {
    color: #0f172a;
}

.resultado-filter-badge-top {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.resultado-filter-badge-top.fiscal {
    background: #dcfce7;
    color: #166534;
}

.resultado-filter-badge-top.no-fiscal {
    background: #f3f4f6;
    color: #374151;
}

.resultado-loading-top {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
}

.btn-imprimir-modal {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 32px;
    margin-left: auto;
}

.btn-imprimir-modal:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 58, 92, 0.3);
}

.btn-imprimir-modal:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

/* ===== TOTALES MODAL DETALLE ===== */
.totales-modal-detalle {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.total-modal-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 4px 12px;
    background: white;
    border-radius: 6px;
    border: 1px solid #f1f5f9;
    min-width: 100px;
    flex: 1;
}

.total-modal-item.total-modal-balance {
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    border-color: #1a3a5c;
}

.total-modal-item.total-modal-balance .total-modal-label {
    color: rgba(255, 255, 255, 0.7);
}

.total-modal-item.total-modal-balance .total-modal-value {
    color: white;
}

.total-modal-label {
    font-size: 10px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.total-modal-value {
    font-size: 18px;
    font-weight: 700;
    font-family: 'Courier New', monospace;
}

.total-modal-value.ingreso {
    color: #2563eb;
}

.total-modal-value.egreso {
    color: #dc2626;
}

.total-modal-value.neutro {
    color: #94a3b8;
}

/* ===== FILTRO ACTIVO EN DETALLE ===== */
.filtro-activo-detalle {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding: 8px 16px;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.filtro-detalle-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}

.filtro-detalle-badge.fiscal {
    background: #dcfce7;
    color: #166534;
}

.filtro-detalle-badge.no-fiscal {
    background: #f3f4f6;
    color: #374151;
}

.filtro-detalle-text {
    font-size: 12px;
    color: #64748b;
}

/* ===== OTROS ===== */
.nombre-container {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.nombre-madre {
    font-weight: 700 !important;
    color: #1a3a5c !important;
    font-size: 14px !important;
}

.icon-folder, .icon-file {
    display: inline-flex;
    align-items: center;
}

.fiscal-badge-mini {
    display: inline-block;
    padding: 1px 8px;
    background: #10b981;
    color: white;
    border-radius: 3px;
    font-size: 9px;
    font-weight: 700;
    margin-left: 8px;
    text-transform: uppercase;
}

.subtotal-badge {
    display: inline-block;
    padding: 2px 10px;
    background: #eff6ff;
    color: #1e40af;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 8px;
}

.categoria-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.categoria-badge.fiscal {
    background: #d1fae5;
    color: #065f46;
}

.categoria-badge.no-fiscal {
    background: #f3f4f6;
    color: #374151;
}

.fecha-text-ultra {
    font-size: 13px;
    color: #475569;
}

.folio-text-ultra {
    font-size: 13px;
    font-weight: 600;
    color: #1a3a5c;
    cursor: pointer;
}

.folio-text-ultra:hover {
    color: #1a3a5c;
    text-decoration: underline;
}

.cuenta-text-ultra {
    font-size: 13px;
    color: #475569;
}

.totales-resultados-wrapper {
    margin-top: 16px;
}

.totales-resultados-container {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    background: linear-gradient(135deg, #e8eef5, #d3e0ee);
    border-radius: 12px;
    padding: 16px 24px;
    border: 1px solid #a9c3dd;
}

.total-item-resultados {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 16px;
    background: white;
    border-radius: 8px;
    border: 1px solid #d3e0ee;
    min-width: 140px;
    flex: 1;
}

.total-label-resultados {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.total-value-resultados {
    font-size: 20px;
    font-weight: 700;
    font-family: 'Courier New', monospace;
}

.total-value-resultados.ingreso {
    color: #132a44;
}

.total-value-resultados.egreso {
    color: #dc2626;
}

.fiscal-badge-text {
    font-size: 14px;
    color: #10b981;
}

.no-fiscal-badge-text {
    font-size: 14px;
    color: #6b7280;
}

.empty-state-icon {
    display: flex;
    justify-content: center;
    color: #94a3b8;
}

/* ===== ANIMACIONES ===== */
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .filtros-table {
        min-width: 850px;
    }
    .filtros-ultra-full {
        overflow-x: auto;
    }
}

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
    .btn-resultados-mini {
        flex: 1;
        justify-content: center;
    }

    .reporte-wrapper-premium {
        padding: 12px;
    }

    .view-toggle-group {
        width: 100%;
    }

    .view-toggle-btn {
        flex: 1;
        justify-content: center;
    }

    .totales-principal-container,
    .totales-fondeadoras-container,
    .totales-resultados-container {
        flex-direction: column;
    }

    .total-item-principal,
    .total-item-fondeadora,
    .total-item-resultados {
        min-width: 100%;
    }

    .totales-modal-detalle {
        flex-direction: column;
    }

    .total-modal-item {
        min-width: 100%;
    }

    .modal-filtros-row-top {
        flex-direction: column;
        align-items: stretch;
    }

    .modal-filtro-actions-top {
        margin-left: 0;
        width: 100%;
        flex-wrap: wrap;
    }

    .btn-modal-hoy-top,
    .btn-modal-limpiar-top {
        flex: 1;
        justify-content: center;
    }

    .modal-filtros-separator-top {
        display: none;
    }
    
    .btn-imprimir-modal {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }

    .filtros-ultra-full {
        overflow-x: auto;
    }
    
    .filtros-table {
        min-width: 780px;
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
    .btn-resultados-mini {
        width: 100%;
        justify-content: center;
    }
}
</style>