<template>
    <AppLayout :title="pageTitle">
        <template #header>
            <div class="header-premium">
                <div class="header-content-premium">
                    <div class="header-left-premium">
                        <div class="header-icon-wrapper">
                            <svg class="header-icon-svg" fill="none" stroke="white" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="header-title-premium">Dashboard
                            </h2>
                            <p class="header-subtitle-premium">
                                <span class="subtitle-line"></span>
                                <span>Bienvenido, {{ usuario?.nombre_completo || 'Usuario' }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="header-actions-premium">
                        <div class="header-date-badge">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="fecha-actual">{{ fechaActual }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <!-- Usuarios - Solo Admin, Auditor, Super -->
                    <div v-if="permisos?.puede_ver_usuarios" class="stats-card-modern">
                        <div class="stats-card-modern-content">
                            <div class="stats-card-modern-left">
                                <span class="stats-card-modern-label">Usuarios</span>
                                <div class="stats-card-modern-value-wrapper">
                                    <span class="stats-card-modern-value">{{ stats.total_usuarios || 0 }}</span>
                                    <span class="stats-card-modern-trend positive">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ stats.usuarios_activos || 0 }} activos
                                    </span>
                                </div>
                            </div>
                            <div class="stats-card-modern-icon blue">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-modern-footer">
                            <div class="stats-progress-bar">
                                <div class="stats-progress-fill blue" :style="{ width: stats.total_usuarios ? Math.round((stats.usuarios_activos / stats.total_usuarios) * 100) + '%' : '0%' }"></div>
                            </div>
                            <div class="stats-card-modern-meta">
                                <span>{{ stats.usuarios_inactivos || 0 }} inactivos</span>
                            </div>
                        </div>
                    </div>

                    <!-- Empresas - Solo Super Usuario -->
                    <div v-if="permisos?.puede_ver_empresas" class="stats-card-modern">
                        <div class="stats-card-modern-content">
                            <div class="stats-card-modern-left">
                                <span class="stats-card-modern-label">Condominios</span>
                                <div class="stats-card-modern-value-wrapper">
                                    <span class="stats-card-modern-value">{{ stats.total_empresas || 0 }}</span>
                                    <span class="stats-card-modern-trend positive">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ stats.empresas_activas || 0 }} activas
                                    </span>
                                </div>
                            </div>
                            <div class="stats-card-modern-icon purple">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-modern-footer">
                            <div class="stats-progress-bar">
                                <div class="stats-progress-fill purple" :style="{ width: stats.total_empresas ? Math.round((stats.empresas_activas / stats.total_empresas) * 100) + '%' : '0%' }"></div>
                            </div>
                            <div class="stats-card-modern-meta">
                                <span>{{ stats.empresas_inactivas || 0 }} inactivas</span>
                            </div>
                        </div>
                    </div>

                    <!-- Personas - Todos pueden ver -->
                    <div class="stats-card-modern">
                        <div class="stats-card-modern-content">
                            <div class="stats-card-modern-left">
                                <span class="stats-card-modern-label">Personas</span>
                                <div class="stats-card-modern-value-wrapper">
                                    <span class="stats-card-modern-value">{{ stats.total_personas || 0 }}</span>
                                    <span class="stats-card-modern-trend positive">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ stats.personas_activas || 0 }} activas
                                    </span>
                                </div>
                            </div>
                            <div class="stats-card-modern-icon green">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-modern-footer">
                            <div class="stats-progress-bar">
                                <div class="stats-progress-fill green" :style="{ width: stats.total_personas ? Math.round((stats.personas_activas / stats.total_personas) * 100) + '%' : '0%' }"></div>
                            </div>
                            <div class="stats-card-modern-meta">
                                <span>{{ stats.personas_fisicas || 0 }} fisicas · {{ stats.personas_morales || 0 }} morales</span>
                            </div>
                        </div>
                    </div>

                    <!-- Polizas - Todos pueden ver -->
                    <div class="stats-card-modern">
                        <div class="stats-card-modern-content">
                            <div class="stats-card-modern-left">
                                <span class="stats-card-modern-label">Polizas</span>
                                <div class="stats-card-modern-value-wrapper">
                                    <span class="stats-card-modern-value">{{ stats.total_polizas || 0 }}</span>
                                    <span class="stats-card-modern-trend neutral">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ stats.polizas_ingresos || 0 }} ingresos
                                    </span>
                                </div>
                            </div>
                            <div class="stats-card-modern-icon gold">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-modern-footer">
                            <div class="stats-progress-bar multi">
                                <div class="stats-progress-fill pending" :style="{ width: stats.total_polizas ? Math.round((stats.polizas_pendientes / stats.total_polizas) * 100) + '%' : '0%' }"></div>
                                <div class="stats-progress-fill abonado" :style="{ width: stats.total_polizas ? Math.round((stats.polizas_abonadas / stats.total_polizas) * 100) + '%' : '0%' }"></div>
                                <div class="stats-progress-fill liquidado" :style="{ width: stats.total_polizas ? Math.round((stats.polizas_liquidadas / stats.total_polizas) * 100) + '%' : '0%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Segunda fila de estadisticas -->
                <div class="stats-secondary-grid">
                    <!-- Movimientos del dia -->
                    <div class="stats-card-secondary">
                        <div class="stats-card-secondary-content">
                            <div class="stats-card-secondary-left">
                                <span class="stats-card-secondary-label">Movimientos Hoy</span>
                                <span class="stats-card-secondary-value">{{ stats.polizas_hoy || 0 }}</span>
                            </div>
                            <div class="stats-card-secondary-icon indigo">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-secondary-footer">
                            <span class="text-green-600">Ingresos: {{ stats.ingresos_hoy || 0 }}</span>
                            <span class="text-red-600">Egresos: {{ stats.egresos_hoy || 0 }}</span>
                        </div>
                    </div>

                    <!-- Cuentas y Cajas -->
                    <div class="stats-card-secondary">
                        <div class="stats-card-secondary-content">
                            <div class="stats-card-secondary-left">
                                <span class="stats-card-secondary-label">Cuentas</span>
                                <span class="stats-card-secondary-value">{{ stats.total_cuentas || 0 }}</span>
                            </div>
                            <div class="stats-card-secondary-icon pink">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-secondary-footer">
                            <span class="text-blue-600">Cuentas: {{ stats.cuentas_en_uso || 0 }} en uso</span>
                            <span class="text-purple-600">Marcadores: {{ stats.marcadores_activos || 0 }} activos</span>
                        </div>
                    </div>

                    <!-- Polizas pendientes -->
                    <div class="stats-card-secondary">
                        <div class="stats-card-secondary-content">
                            <div class="stats-card-secondary-left">
                                <span class="stats-card-secondary-label">Polizas Pendientes</span>
                                <span class="stats-card-secondary-value">{{ stats.polizas_pendientes || 0 }}</span>
                            </div>
                            <div class="stats-card-secondary-icon orange">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stats-card-secondary-footer">
                            <span class="text-blue-600">Abonadas: {{ stats.polizas_abonadas || 0 }}</span>
                            <span class="text-green-600">Liquidadas: {{ stats.polizas_liquidadas || 0 }}</span>
                        </div>
                    </div>
                </div>
                <!-- Mis Empresas -->
                <div v-if="empresasUsuario.length > 0" class="table-wrapper-modern mt-6">
                    <div class="table-header-modern">
                        <div class="table-header-left-modern">
                            <div class="table-header-icon">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <h3 class="table-header-title">Mis Condominios</h3>
                        </div>
                        <div class="table-header-right-modern">
                            <span class="table-badge">{{ empresasUsuario.length }} empresas</span>
                        </div>
                    </div>

                    <div class="empresas-grid">
                        <div v-for="empresa in empresasUsuario" :key="empresa.id" class="empresa-card">
                            <div class="empresa-avatar">
                                {{ empresa.nombre?.charAt(0) || 'E' }}
                            </div>
                            <div class="empresa-info">
                                <span class="empresa-nombre">{{ empresa.nombre }}</span>
                                <span v-if="empresa.rfc" class="empresa-rfc">{{ empresa.rfc }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

// ============================================
// PERMISOS DESDE EL BACKEND
// ============================================
const page = usePage();
const permisos = computed(() => page.props.permisos || {});

const pageTitle = 'RIC - Dashboard';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({})
    },
    ultimasPolizas: {
        type: Array,
        default: () => []
    },
    empresasUsuario: {
        type: Array,
        default: () => []
    },
    usuario: {
        type: Object,
        default: () => ({})
    },
    flash: {
        type: Object,
        default: () => ({})
    }
});

const fechaActual = ref('');

// ============================================
// FILTRAR PÓLIZAS DE HOY
// ============================================
const polizasHoy = computed(() => {
    const hoy = new Date().toLocaleDateString('es-MX');
    return props.ultimasPolizas.filter(poliza => {
        // Si la fecha viene en formato 'dd/mm/yyyy HH:MM'
        const fechaPoliza = poliza.fecha?.split(' ')[0]; // Obtener solo la fecha
        return fechaPoliza === hoy;
    });
});

const formatNumber = (value) => {
    if (value === null || value === undefined) return '0.00';
    return Number(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
};

const obtenerFechaActual = () => {
    const now = new Date();
    const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
    return now.toLocaleDateString('es-MX', options);
};

// Los flash messages ahora se muestran de forma global (AppLayout useFlashMessages)

onMounted(() => {
    fechaActual.value = obtenerFechaActual();
});
</script>

<style scoped>
/* ===== HEADER PREMIUM ===== */
.header-premium {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 24px;
    padding: 28px 32px;
    margin-bottom: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(241, 245, 249, 0.8);
    position: relative;
    overflow: hidden;
}

.header-premium::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #1a3a5c, #3b82f6, #1a3a5c);
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
    gap: 20px;
}

.header-icon-wrapper {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(26, 58, 92, 0.25);
    transition: transform 0.3s ease;
}

.header-icon-wrapper:hover {
    transform: scale(1.05) rotate(-3deg);
}

.header-icon-svg {
    width: 30px;
    height: 30px;
    stroke: white;
}

.header-title-premium {
    font-size: 26px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, #0f172a, #1a3a5c);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.header-subtitle-premium {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #64748b;
    font-size: 14px;
    margin: 4px 0 0 0;
}

.subtitle-line {
    width: 28px;
    height: 2px;
    background: linear-gradient(90deg, #1a3a5c, transparent);
    border-radius: 2px;
}

.header-actions-premium {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-date-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-size: 13px;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.header-date-badge svg {
    color: #94a3b8;
}

.fecha-actual {
    font-size: 13px;
    color: #475569;
    font-weight: 500;
}

/* ===== STATS CARDS MODERN ===== */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

@media (min-width: 640px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.stats-card-modern {
    position: relative;
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #eef2f7;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
}

/* Barra de acento superior según el color de la tarjeta */
.stats-card-modern::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 3px;
    background: linear-gradient(90deg, #1a3a5c, #3d6ea5);
}

.stats-card-modern:has(.stats-card-modern-icon.blue)::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.stats-card-modern:has(.stats-card-modern-icon.green)::before { background: linear-gradient(90deg, #16a34a, #4ade80); }
.stats-card-modern:has(.stats-card-modern-icon.gold)::before { background: linear-gradient(90deg, #d97706, #fbbf24); }
.stats-card-modern:has(.stats-card-modern-icon.purple)::before { background: linear-gradient(90deg, #1a3a5c, #3d6ea5); }

.stats-card-modern:hover {
    transform: translateY(-6px);
    box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
    border-color: #e2e8f0;
}

.stats-card-modern-content {
    padding: 20px 24px 16px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.stats-card-modern-left {
    flex: 1;
    min-width: 0;
}

.stats-card-modern-label {
    font-size: 12px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    display: block;
    margin-bottom: 6px;
}

.stats-card-modern-value-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.stats-card-modern-value {
    font-size: 34px;
    font-weight: 850;
    color: #0b1426;
    line-height: 1.05;
    letter-spacing: -0.02em;
}

.stats-card-modern-trend {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    background: #f1f5f9;
}

.stats-card-modern-trend.positive {
    color: #22c55e;
    background: #dcfce7;
}

.stats-card-modern-trend.neutral {
    color: #64748b;
    background: #f1f5f9;
}

.stats-card-modern-icon {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #fff;
    transition: all 0.3s ease;
}

.stats-card-modern-icon :deep(svg),
.stats-card-modern-icon svg { stroke: #fff; }

.stats-card-modern:hover .stats-card-modern-icon {
    transform: scale(1.08) rotate(-4deg);
}

.stats-card-modern-icon.blue {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    box-shadow: 0 8px 18px -6px rgba(37, 99, 235, 0.5);
}

.stats-card-modern-icon.purple {
    background: linear-gradient(135deg, #1a3a5c, #3d6ea5);
    box-shadow: 0 8px 18px -6px rgba(26, 58, 92, 0.5);
}

.stats-card-modern-icon.green {
    background: linear-gradient(135deg, #16a34a, #22c55e);
    box-shadow: 0 8px 18px -6px rgba(22, 163, 74, 0.5);
}

.stats-card-modern-icon.gold {
    background: linear-gradient(135deg, #d97706, #f59e0b);
    box-shadow: 0 8px 18px -6px rgba(217, 119, 6, 0.5);
}

.stats-card-modern-footer {
    padding: 0 24px 16px;
}

.stats-progress-bar {
    height: 4px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.stats-progress-bar.multi {
    display: flex;
}

.stats-progress-fill {
    height: 100%;
    transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.stats-progress-fill.blue {
    background: linear-gradient(90deg, #3b82f6, #2563eb);
}

.stats-progress-fill.purple {
    background: linear-gradient(90deg, #1a3a5c, #132a44);
}

.stats-progress-fill.green {
    background: linear-gradient(90deg, #22c55e, #16a34a);
}

.stats-progress-fill.pending {
    background: #f59e0b;
}

.stats-progress-fill.abonado {
    background: #3b82f6;
}

.stats-progress-fill.liquidado {
    background: #22c55e;
}

.stats-card-modern-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.stats-card-modern-meta span {
    font-size: 12px;
    color: #94a3b8;
}

/* ===== STATS SECONDARY ===== */
.stats-secondary-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

@media (min-width: 640px) {
    .stats-secondary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .stats-secondary-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.stats-card-secondary {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #f1f5f9;
    padding: 20px 24px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
}

.stats-card-secondary:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
}

.stats-card-secondary-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.stats-card-secondary-left {
    flex: 1;
}

.stats-card-secondary-label {
    font-size: 12px;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    display: block;
    margin-bottom: 4px;
}

.stats-card-secondary-value {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
}

.stats-card-secondary-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stats-card-secondary-icon.indigo {
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    color: #4f46e5;
}

.stats-card-secondary-icon.pink {
    background: linear-gradient(135deg, #fce7f3, #fbcfe8);
    color: #db2777;
}

.stats-card-secondary-icon.orange {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #d97706;
}

.stats-card-secondary-footer {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
}

.stats-card-secondary-footer span {
    font-size: 13px;
    font-weight: 500;
}

/* ===== TABLE MODERN ===== */
.table-wrapper-modern {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #f1f5f9;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
    padding: 24px;
}

.table-header-modern {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

@media (min-width: 640px) {
    .table-header-modern {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}

.table-header-left-modern {
    display: flex;
    align-items: center;
    gap: 12px;
}

.table-header-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1a3a5c;
}

.table-header-title {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.table-header-right-modern {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.table-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    background: #f1f5f9;
    border-radius: 20px;
    color: #64748b;
}

.today-badge {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
}

.total-badge {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    color: #475569;
}

.table-scroll-modern {
    overflow: hidden;
    border-radius: 12px;
    border: 1px solid #f1f5f9;
}

.folio-cell {
    font-weight: 600;
    color: #0f172a;
    font-family: 'Courier New', monospace;
}

.tipo-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.tipo-badge.ingreso {
    background: #dcfce7;
    color: #16a34a;
}

.tipo-badge.egreso {
    background: #fee2e2;
    color: #dc2626;
}

.persona-cell {
    color: #475569;
}

.fecha-cell {
    color: #94a3b8;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
}

.fecha-cell svg {
    color: #94a3b8;
}

.total-cell {
    font-weight: 700;
    font-size: 14px;
}

.status-badge-modern {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.status-badge-modern.pending {
    background: #fef3c7;
    color: #d97706;
}

.status-badge-modern.abonado {
    background: #dbeafe;
    color: #2563eb;
}

.status-badge-modern.liquidado {
    background: #dcfce7;
    color: #16a34a;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 48px 20px;
}

.empty-state-icon {
    color: #e2e8f0;
    margin-bottom: 16px;
}

.empty-state-title {
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
}

.empty-state-subtitle {
    font-size: 14px;
    color: #94a3b8;
}

/* ===== EMPRESAS ===== */
.empresas-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.empresa-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 18px 10px 12px;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    cursor: default;
}

.empresa-card:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    border-color: #cbd5e1;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
}

.empresa-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #1a3a5c, #2c5282);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}

.empresa-info {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.empresa-nombre {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
}

.empresa-rfc {
    font-size: 11px;
    color: #94a3b8;
    font-family: 'Courier New', monospace;
}

/* ===== UTILITIES ===== */
.text-green-600 { color: #16a34a; }
.text-red-600 { color: #dc2626; }
.text-blue-600 { color: #2563eb; }
.text-purple-600 { color: #132a44; }
.w-3 { width: 0.75rem; }
.h-3 { height: 0.75rem; }
.w-4 { width: 1rem; }
.h-4 { height: 1rem; }
.w-5 { width: 1.25rem; }
.h-5 { height: 1.25rem; }
.w-6 { width: 1.5rem; }
.h-6 { height: 1.5rem; }
.w-16 { width: 4rem; }
.h-16 { height: 4rem; }
.inline { display: inline; }
.mr-1 { margin-right: 0.25rem; }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .header-premium {
        padding: 20px;
        border-radius: 16px;
    }
    
    .header-title-premium {
        font-size: 22px;
    }
    
    .header-icon-wrapper {
        width: 48px;
        height: 48px;
    }
    
    .header-icon-svg {
        width: 24px;
        height: 24px;
    }
    
    .table-wrapper-modern {
        padding: 16px;
    }
    
    .stats-card-modern-content {
        padding: 16px 18px 12px;
    }
    
    .stats-card-modern-value {
        font-size: 26px;
    }
    
    .stats-card-secondary-value {
        font-size: 24px;
    }

    .stats-card-modern-icon {
        width: 40px;
        height: 40px;
    }

    .stats-card-secondary-icon {
        width: 38px;
        height: 38px;
    }
}

@media (max-width: 480px) {
    .header-left-premium {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-date-badge {
        width: 100%;
        justify-content: center;
    }
    
    .stats-card-modern-value-wrapper {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .empresa-card {
        width: 100%;
    }
    
    .table-header-right-modern {
        width: 100%;
        justify-content: flex-start;
    }
}

</style>