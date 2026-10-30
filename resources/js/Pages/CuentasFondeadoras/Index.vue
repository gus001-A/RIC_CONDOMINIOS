<template>
    <AppLayout title="RIC - Cuentas Fondeadoras">
        <template #header>
            <div class="header-premium">
                <div class="header-content-premium">
                    <div class="header-left-premium">
                        <Link :href="route('cuentas.index')" class="cf-volver" title="Volver a Cuentas">
                            <i class="pi pi-arrow-left"></i>
                        </Link>
                        <div class="header-icon-wrapper">
                            <i class="pi pi-wallet" style="color: white; font-size: 22px;"></i>
                        </div>
                        <div>
                            <h2 class="header-title-premium">Asignar Cuentas Fondeadoras</h2>
                            <p class="header-subtitle-premium">
                                <span class="subtitle-line"></span>
                                Elige qué cuentas fondeadoras puede ver y usar cada usuario, por condominio
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-full px-4 sm:px-6 lg:px-8">
                <!-- Empresa + modo -->
                <div class="empresa-selector-premium">
                    <div class="empresa-selector-content">
                        <div class="empresa-selector-label">
                            <i class="pi pi-building" style="font-size: 16px; color: #1a3a5c;"></i>
                            <span>Condominio</span>
                        </div>
                        <div class="empresa-selector-field">
                            <select v-model="empresaId" @change="cambiarEmpresa" class="empresa-select-native">
                                <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.nombre_empresa }}</option>
                            </select>
                        </div>
                        <div class="view-toggle-group">
                            <button type="button" class="view-toggle-btn" :class="{ active: modo === 'usuario' }" @click="modo = 'usuario'">
                                <i class="pi pi-user"></i> Por usuario
                            </button>
                            <button type="button" class="view-toggle-btn" :class="{ active: modo === 'cuenta' }" @click="modo = 'cuenta'">
                                <i class="pi pi-wallet"></i> Por cuenta
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tarjetas -->
                <div class="stats-grid-cf">
                    <div class="stats-card-enhanced">
                        <div class="stats-card-enhanced-content">
                            <div class="stats-card-enhanced-left">
                                <span class="stats-card-enhanced-label">Fondeadoras</span>
                                <span class="stats-card-enhanced-value" style="color: #1a3a5c;">{{ fondeadoras.length }}</span>
                            </div>
                            <div class="stats-card-enhanced-icon" style="background: linear-gradient(135deg, rgba(26, 58, 92, 0.12), rgba(26, 58, 92, 0.05));">
                                <i class="pi pi-wallet" style="color: #1a3a5c; font-size: 22px;"></i>
                            </div>
                        </div>
                        <div class="stats-card-enhanced-progress"><div class="stats-card-enhanced-progress-bar" style="width: 100%; background: linear-gradient(90deg, #1a3a5c, #3d6ea5);"></div></div>
                    </div>

                    <div class="stats-card-enhanced">
                        <div class="stats-card-enhanced-content">
                            <div class="stats-card-enhanced-left">
                                <span class="stats-card-enhanced-label">Usuarios del condominio</span>
                                <span class="stats-card-enhanced-value" style="color: #1d4ed8;">{{ usuarios.length }}</span>
                            </div>
                            <div class="stats-card-enhanced-icon" style="background: linear-gradient(135deg, rgba(29, 78, 216, 0.12), rgba(29, 78, 216, 0.05));">
                                <i class="pi pi-users" style="color: #1d4ed8; font-size: 22px;"></i>
                            </div>
                        </div>
                        <div class="stats-card-enhanced-progress"><div class="stats-card-enhanced-progress-bar" style="width: 100%; background: linear-gradient(90deg, #1d4ed8, #60a5fa);"></div></div>
                    </div>

                    <div class="stats-card-enhanced">
                        <div class="stats-card-enhanced-content">
                            <div class="stats-card-enhanced-left">
                                <span class="stats-card-enhanced-label">Pueden generar pólizas</span>
                                <span class="stats-card-enhanced-value" style="color: #2e7d32;">{{ usuariosRestringidos }}</span>
                            </div>
                            <div class="stats-card-enhanced-icon" style="background: linear-gradient(135deg, rgba(46, 125, 50, 0.12), rgba(46, 125, 50, 0.05));">
                                <i class="pi pi-check-circle" style="color: #2e7d32; font-size: 22px;"></i>
                            </div>
                        </div>
                        <div class="stats-card-enhanced-progress">
                            <div class="stats-card-enhanced-progress-bar" :style="{ width: usuarios.length ? Math.round((usuariosRestringidos / usuarios.length) * 100) + '%' : '0%', background: 'linear-gradient(90deg, #2e7d32, #43a047)' }"></div>
                        </div>
                    </div>

                    <div class="stats-card-enhanced">
                        <div class="stats-card-enhanced-content">
                            <div class="stats-card-enhanced-left">
                                <span class="stats-card-enhanced-label">Sin cuentas (bloqueados)</span>
                                <span class="stats-card-enhanced-value" style="color: #dc2626;">{{ usuariosLibres.length }}</span>
                            </div>
                            <div class="stats-card-enhanced-icon" style="background: linear-gradient(135deg, rgba(220, 38, 38, 0.12), rgba(220, 38, 38, 0.05));">
                                <i class="pi pi-ban" style="color: #dc2626; font-size: 22px;"></i>
                            </div>
                        </div>
                        <div class="stats-card-enhanced-progress">
                            <div class="stats-card-enhanced-progress-bar" :style="{ width: usuarios.length ? Math.round((usuariosLibres.length / usuarios.length) * 100) + '%' : '0%', background: 'linear-gradient(90deg, #dc2626, #f87171)' }"></div>
                        </div>
                    </div>
                </div>

                <div class="cf-aviso">
                    <i class="pi pi-info-circle"></i>
                    <span>
                        Un usuario <strong>sin ninguna</strong> fondeadora marcada en un condominio <strong>no puede generar ningún tipo de póliza</strong> en ella.
                        Sólo podrá usar las cuentas fondeadoras que tenga marcadas.
                    </span>
                </div>

                <div v-if="!empresas.length" class="table-wrapper-premium cf-vacio">No hay condominios activos.</div>

                <!-- ============ POR USUARIO ============ -->
                <div v-else-if="modo === 'usuario'" class="cf-dos-paneles">
                    <div class="table-wrapper-premium cf-lista-usuarios">
                        <div class="cf-panel-titulo"><i class="pi pi-users"></i> Usuarios con acceso a el condominio</div>
                        <p v-if="!usuarios.length" class="cf-vacio">Ningún usuario activo tiene asignada este condominio.</p>
                        <button
                            v-for="u in usuarios"
                            :key="u.id"
                            type="button"
                            class="cf-usuario"
                            :class="{ activo: u.id === usuarioId }"
                            @click="elegirUsuario(u)"
                        >
                            <span class="cf-avatar" :style="{ background: colorTipo(u.tipo).grad }">{{ iniciales(u.nombre) }}</span>
                            <span class="cf-usuario-info">
                                <span class="cf-usuario-nombre">{{ u.nombre }}</span>
                                <span class="cf-usuario-meta">
                                    <span class="cf-rol" :style="{ background: colorTipo(u.tipo).bg, color: colorTipo(u.tipo).fg }">{{ etiquetaTipo(u.tipo) }}</span>
                                    {{ u.usuario }}
                                </span>
                            </span>
                            <span class="cf-badge" :class="u.fondeadoras.length ? 'cf-badge-restr' : 'cf-badge-todas'">
                                {{ u.fondeadoras.length ? `${u.fondeadoras.length} de ${fondeadoras.length}` : 'Ninguna' }}
                            </span>
                        </button>
                    </div>

                    <div class="table-wrapper-premium cf-panel-cuentas">
                        <template v-if="usuarioActual">
                            <div class="cf-panel-head">
                                <div class="cf-panel-head-user">
                                    <span class="cf-avatar cf-avatar-lg" :style="{ background: colorTipo(usuarioActual.tipo).grad }">{{ iniciales(usuarioActual.nombre) }}</span>
                                    <div>
                                        <div class="cf-panel-titulo" style="margin: 0;">{{ usuarioActual.nombre }}</div>
                                        <div class="cf-usuario-meta">
                                            <span class="cf-rol" :style="{ background: colorTipo(usuarioActual.tipo).bg, color: colorTipo(usuarioActual.tipo).fg }">{{ etiquetaTipo(usuarioActual.tipo) }}</span>
                                            <span class="cf-contador-pill" :class="draft.length ? 'verde' : 'naranja'">
                                                {{ draft.length ? `${draft.length} de ${fondeadoras.length} marcadas` : 'Sin marcar: no podrá generar pólizas' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="cf-acciones">
                                    <div class="cf-buscador">
                                        <i class="pi pi-search"></i>
                                        <input v-model="buscar" class="cf-input" placeholder="Buscar cuenta..." />
                                    </div>
                                    <button type="button" class="cf-chip-btn cf-chip-verde" @click="draft = fondeadoras.map(f => f.id)"><i class="pi pi-check-square"></i> Todas</button>
                                    <button type="button" class="cf-chip-btn cf-chip-rojo" @click="draft = []"><i class="pi pi-times-circle"></i> Ninguna</button>
                                </div>
                            </div>

                            <div class="cf-grid">
                                <label
                                    v-for="(f, i) in fondeadorasFiltradas"
                                    :key="f.id"
                                    class="cf-item"
                                    :class="{ activo: draft.includes(f.id) }"
                                    :style="{ '--acento': acento(i) }"
                                >
                                    <input type="checkbox" :checked="draft.includes(f.id)" @change="alternar(f.id)" />
                                    <span class="cf-item-icono"><i class="pi pi-wallet"></i></span>
                                    <span class="cf-item-textos">
                                        <span class="cf-item-nombre">{{ f.nombre }}</span>
                                        <span class="cf-item-codigo">{{ f.codigo }}</span>
                                    </span>
                                    <i v-if="draft.includes(f.id)" class="pi pi-check-circle cf-item-check"></i>
                                </label>
                            </div>

                            <div class="cf-pie">
                                <button type="button" class="cf-btn-sec" :disabled="!sucio" @click="draft = [...usuarioActual.fondeadoras]">
                                    <i class="pi pi-undo"></i> Descartar cambios
                                </button>
                                <button type="button" class="cf-btn" :disabled="!sucio || guardando" @click="guardarUsuario">
                                    <i class="pi pi-save"></i> {{ guardando ? 'Guardando...' : 'Guardar' }}
                                </button>
                            </div>
                        </template>
                        <div v-else class="cf-placeholder">
                            <i class="pi pi-hand-pointer"></i>
                            <p>Elige un usuario de la izquierda para asignarle sus cuentas fondeadoras.</p>
                        </div>
                    </div>
                </div>

                <!-- ============ POR CUENTA ============ -->
                <div v-else class="table-wrapper-premium">
                    <div class="cf-panel-titulo"><i class="pi pi-wallet"></i> Fondeadoras del condominio</div>
                    <p v-if="!fondeadoras.length" class="cf-vacio">Este condominio no tiene cuentas fondeadoras activas.</p>

                    <table v-else class="cf-tabla">
                        <thead>
                            <tr>
                                <th style="width: 110px;">Código</th>
                                <th style="width: 28%;">Cuenta</th>
                                <th>Usuarios asignados</th>
                                <th style="width: 140px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(f, i) in fondeadoras" :key="f.id">
                                <td><span class="cf-codigo-pill" :style="{ background: acento(i) + '22', color: acento(i) }">{{ f.codigo }}</span></td>
                                <td class="cf-fuerte">{{ f.nombre }}</td>
                                <td>
                                    <template v-if="usuariosDe(f.id).length">
                                        <span
                                            v-for="u in usuariosDe(f.id)"
                                            :key="u.id"
                                            class="cf-chip"
                                            :style="{ background: colorTipo(u.tipo).bg, color: colorTipo(u.tipo).fg }"
                                        >{{ u.nombre }}</span>
                                    </template>
                                    <span v-else class="cf-nadie">Ningún usuario asignado</span>
                                    <span v-if="usuariosLibres.length" class="cf-libres" title="Usuarios sin ninguna fondeadora asignada: no pueden generar pólizas">
                                        {{ usuariosLibres.length }} usuario(s) sin cuentas
                                    </span>
                                </td>
                                <td class="cf-td-acc">
                                    <button type="button" class="cf-btn-sec" @click="abrirCuenta(f)">
                                        <i class="pi pi-users"></i> Usuarios
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Modal: usuarios de una cuenta -->
                <div v-if="cuentaModal" class="cf-overlay" @click.self="cuentaModal = null">
                    <div class="cf-modal">
                        <div class="cf-modal-head">
                            <span class="cf-item-icono"><i class="pi pi-wallet"></i></span>
                            <div>
                                <div class="cf-panel-titulo" style="margin: 0;">{{ cuentaModal.nombre }}</div>
                                <div class="cf-usuario-meta">Marca los usuarios que podrán usar esta fondeadora</div>
                            </div>
                        </div>
                        <div class="cf-modal-lista">
                            <label v-for="u in usuarios" :key="u.id" class="cf-item" :class="{ activo: draftCuenta.includes(u.id) }" :style="{ '--acento': colorTipo(u.tipo).fg }">
                                <input type="checkbox" :checked="draftCuenta.includes(u.id)" @change="alternarCuenta(u.id)" />
                                <span class="cf-avatar" :style="{ background: colorTipo(u.tipo).grad }">{{ iniciales(u.nombre) }}</span>
                                <span class="cf-item-textos">
                                    <span class="cf-item-nombre">{{ u.nombre }}</span>
                                    <span class="cf-item-codigo">{{ etiquetaTipo(u.tipo) }}</span>
                                </span>
                                <i v-if="draftCuenta.includes(u.id)" class="pi pi-check-circle cf-item-check"></i>
                            </label>
                        </div>
                        <div class="cf-pie">
                            <button type="button" class="cf-btn-sec" @click="cuentaModal = null">Cancelar</button>
                            <button type="button" class="cf-btn" :disabled="guardando" @click="guardarCuenta">
                                <i class="pi pi-save"></i> {{ guardando ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    empresas: { type: Array, default: () => [] },
    empresa_seleccionada: { type: Number, default: 0 },
    fondeadoras: { type: Array, default: () => [] },
    usuarios: { type: Array, default: () => [] },
});

const empresaId = ref(props.empresa_seleccionada);
const modo = ref('usuario');
const usuarioId = ref(null);
const draft = ref([]);
const buscar = ref('');
const guardando = ref(false);
const cuentaModal = ref(null);
const draftCuenta = ref([]);

const TIPOS = { LECTOR: 'Lector', CAPTURISTA: 'Capturista', ADMINISTRADOR: 'Administrador', AUDITOR: 'Auditor', SUPERUSUARIO: 'Super Usuario' };
const COLORES = {
    SUPERUSUARIO: { bg: '#ede9fe', fg: '#6d28d9', grad: 'linear-gradient(135deg, #7c3aed, #a78bfa)' },
    ADMINISTRADOR: { bg: '#dbeafe', fg: '#1d4ed8', grad: 'linear-gradient(135deg, #1d4ed8, #60a5fa)' },
    AUDITOR: { bg: '#ffedd5', fg: '#c2410c', grad: 'linear-gradient(135deg, #ea580c, #fb923c)' },
    CAPTURISTA: { bg: '#dcfce7', fg: '#15803d', grad: 'linear-gradient(135deg, #16a34a, #4ade80)' },
    LECTOR: { bg: '#e2e8f0', fg: '#475569', grad: 'linear-gradient(135deg, #64748b, #94a3b8)' },
};
const ACENTOS = ['#2563eb', '#16a34a', '#ea580c', '#7c3aed', '#0891b2', '#db2777'];

const etiquetaTipo = (t) => TIPOS[t] || t;
const colorTipo = (t) => COLORES[t] || COLORES.LECTOR;
const acento = (i) => ACENTOS[i % ACENTOS.length];
const iniciales = (nombre) => (nombre || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase();

const usuarioActual = computed(() => props.usuarios.find(u => u.id === usuarioId.value) || null);
const usuariosRestringidos = computed(() => props.usuarios.filter(u => u.fondeadoras.length).length);
const usuariosLibres = computed(() => props.usuarios.filter(u => !u.fondeadoras.length));
const usuariosDe = (idCuenta) => props.usuarios.filter(u => u.fondeadoras.includes(idCuenta));

const fondeadorasFiltradas = computed(() => {
    const q = buscar.value.trim().toLowerCase();
    if (!q) return props.fondeadoras;
    return props.fondeadoras.filter(f => (f.nombre || '').toLowerCase().includes(q) || (f.codigo || '').toLowerCase().includes(q));
});

const sucio = computed(() => {
    if (!usuarioActual.value) return false;
    const a = [...usuarioActual.value.fondeadoras].sort((x, y) => x - y).join(',');
    const b = [...draft.value].sort((x, y) => x - y).join(',');
    return a !== b;
});

const elegirUsuario = (u) => {
    usuarioId.value = u.id;
    draft.value = [...u.fondeadoras];
    buscar.value = '';
};

const alternar = (id) => {
    draft.value = draft.value.includes(id) ? draft.value.filter(x => x !== id) : [...draft.value, id];
};

// Tras guardar, Inertia trae los usuarios actualizados: se refresca el borrador.
watch(() => props.usuarios, () => {
    if (usuarioActual.value) draft.value = [...usuarioActual.value.fondeadoras];
});

const cambiarEmpresa = () => {
    usuarioId.value = null;
    draft.value = [];
    router.get(route('cuentas-fondeadoras.index'), { empresa_id: empresaId.value }, { preserveState: true, replace: true });
};

const guardarUsuario = () => {
    if (!usuarioActual.value) return;
    guardando.value = true;
    router.put(route('cuentas-fondeadoras.usuario', usuarioActual.value.id), {
        empresa_id: empresaId.value,
        fondeadoras: draft.value,
    }, {
        preserveScroll: true,
        onFinish: () => { guardando.value = false; },
    });
};

const abrirCuenta = (f) => {
    cuentaModal.value = f;
    draftCuenta.value = usuariosDe(f.id).map(u => u.id);
};

const alternarCuenta = (id) => {
    draftCuenta.value = draftCuenta.value.includes(id) ? draftCuenta.value.filter(x => x !== id) : [...draftCuenta.value, id];
};

const guardarCuenta = () => {
    if (!cuentaModal.value) return;
    guardando.value = true;
    router.put(route('cuentas-fondeadoras.cuenta', cuentaModal.value.id), {
        usuarios: draftCuenta.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { cuentaModal.value = null; },
        onFinish: () => { guardando.value = false; },
    });
};
</script>

<style scoped>
/* ===== Encabezado (mismo estilo que Empresas) ===== */
.header-premium { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-radius: 20px; padding: 20px 24px; margin-bottom: 8px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04); border: 1px solid #f0f2f5; }
.header-content-premium { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.header-left-premium { display: flex; align-items: center; gap: 14px; }
.header-icon-wrapper { width: 48px; height: 48px; background: linear-gradient(135deg, #1a3a5c, #2c5282); border-radius: 14px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(26, 58, 92, 0.2); }
.header-title-premium { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.5px; }
.header-subtitle-premium { display: flex; align-items: center; gap: 10px; color: #64748b; font-size: 13px; margin: 2px 0 0 0; }
.subtitle-line { width: 20px; height: 2px; background: linear-gradient(90deg, #1a3a5c, transparent); border-radius: 2px; }
.cf-volver { width: 38px; height: 38px; border-radius: 10px; border: 2px solid #cbd5e1; color: #1a3a5c; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all .2s; }
.cf-volver:hover { background: #1a3a5c; color: #fff; border-color: #1a3a5c; }

/* ===== Empresa + modo (mismo estilo que Movimientos) ===== */
.empresa-selector-premium { background: #ffffff; border-radius: 12px; border: 1px solid #f0f2f5; padding: 10px 20px; margin-bottom: 14px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04); }
.empresa-selector-content { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.empresa-selector-label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #1a3a5c; }
.empresa-selector-field { flex: 1; min-width: 220px; }
.empresa-select-native { width: 100%; height: 38px; padding: 6px 12px; border: 2px solid #d1d5db; border-radius: 8px; font-size: 13px; background: #fff; color: #0f172a; outline: none; font-weight: 600; }
.empresa-select-native:focus { border-color: #1a3a5c; box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1); }
.view-toggle-group { display: inline-flex; background: #f1f5f9; border-radius: 10px; padding: 3px; margin-left: auto; }
.view-toggle-btn { border: none; background: transparent; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; display: inline-flex; gap: 6px; align-items: center; transition: all .2s; }
.view-toggle-btn:hover { color: #1a3a5c; }
.view-toggle-btn.active { background: linear-gradient(135deg, #1a3a5c, #2c5282); color: #fff; box-shadow: 0 4px 10px rgba(26, 58, 92, 0.25); }

/* ===== Tarjetas (mismo estilo que Empresas) ===== */
.stats-grid-cf { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 14px; }
@media (max-width: 992px) { .stats-grid-cf { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .stats-grid-cf { grid-template-columns: 1fr; } }
.stats-card-enhanced { background: #fff; border-radius: 14px; border: 1px solid #f0f2f5; overflow: hidden; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04); }
.stats-card-enhanced:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08); }
.stats-card-enhanced-content { padding: 16px 16px 12px; display: flex; align-items: flex-start; justify-content: space-between; }
.stats-card-enhanced-left { display: flex; flex-direction: column; gap: 2px; }
.stats-card-enhanced-label { font-size: 12px; font-weight: 500; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
.stats-card-enhanced-value { font-size: 26px; font-weight: 700; line-height: 1.2; }
.stats-card-enhanced-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.3s ease; }
.stats-card-enhanced:hover .stats-card-enhanced-icon { transform: scale(1.1) rotate(-5deg); }
.stats-card-enhanced-progress { height: 3px; background: #f1f5f9; }
.stats-card-enhanced-progress-bar { height: 100%; transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1); }

.cf-aviso { background: linear-gradient(135deg, #eff6ff, #f0f9ff); border: 1px solid #bfdbfe; box-shadow: inset 4px 0 0 #3b82f6; color: #1e40af; font-size: 12.5px; padding: 10px 16px; border-radius: 10px; margin-bottom: 14px; display: flex; gap: 10px; align-items: center; }

/* ===== Paneles ===== */
.table-wrapper-premium { background: #fff; border-radius: 16px; border: 1px solid #f0f2f5; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04); padding: 18px 20px; margin-bottom: 14px; }
.cf-panel-titulo { font-size: 14px; font-weight: 700; color: #1a3a5c; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.cf-vacio { color: #64748b; font-size: 13px; margin: 6px 0; }
.cf-dos-paneles { display: grid; grid-template-columns: 340px 1fr; gap: 14px; align-items: start; }
@media (max-width: 900px) { .cf-dos-paneles { grid-template-columns: 1fr; } }
.cf-lista-usuarios { max-height: 660px; overflow-y: auto; }

/* ===== Usuarios ===== */
.cf-usuario { width: 100%; text-align: left; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 2px solid transparent; border-radius: 12px; background: #f8fafc; margin-bottom: 8px; cursor: pointer; transition: all .2s; }
.cf-usuario:hover { border-color: #93c5fd; background: #f0f7ff; transform: translateX(2px); }
.cf-usuario.activo { border-color: #1a3a5c; background: linear-gradient(135deg, #eef3f9, #e6eef8); box-shadow: 0 4px 12px rgba(26, 58, 92, 0.12); }
.cf-avatar { width: 38px; height: 38px; border-radius: 50%; color: #fff; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15); }
.cf-avatar-lg { width: 46px; height: 46px; font-size: 15px; }
.cf-usuario-info { display: flex; flex-direction: column; gap: 3px; min-width: 0; flex: 1; }
.cf-usuario-nombre { font-size: 13px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cf-usuario-meta { font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.cf-rol { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: .3px; }
.cf-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; flex-shrink: 0; }
.cf-badge-todas { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.cf-badge-restr { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

/* ===== Panel de cuentas ===== */
.cf-panel-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
.cf-panel-head-user { display: flex; align-items: center; gap: 12px; }
.cf-contador-pill { font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 999px; }
.cf-contador-pill.verde { background: #dcfce7; color: #166534; }
.cf-contador-pill.naranja { background: #fee2e2; color: #b91c1c; }
.cf-acciones { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.cf-buscador { position: relative; }
.cf-buscador i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
.cf-input { height: 36px; padding: 6px 10px 6px 30px; border: 2px solid #d1d5db; border-radius: 8px; font-size: 13px; background: #fff; color: #0f172a; outline: none; width: 200px; }
.cf-input:focus { border-color: #1a3a5c; box-shadow: 0 0 0 3px rgba(26, 58, 92, 0.1); }
.cf-chip-btn { height: 34px; padding: 0 12px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: 2px solid transparent; transition: all .2s; }
.cf-chip-verde { background: #dcfce7; color: #166534; border-color: #86efac; }
.cf-chip-verde:hover { background: #bbf7d0; }
.cf-chip-rojo { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
.cf-chip-rojo:hover { background: #fecaca; }

.cf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; max-height: 470px; overflow-y: auto; padding: 4px; }
.cf-item { --acento: #2563eb; position: relative; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; background: #fff; transition: all .2s; }
.cf-item input { position: absolute; opacity: 0; pointer-events: none; }
.cf-item:hover { border-color: var(--acento); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08); }
.cf-item.activo { border-color: var(--acento); background: linear-gradient(135deg, color-mix(in srgb, var(--acento) 10%, white), #fff); box-shadow: 0 4px 12px color-mix(in srgb, var(--acento) 22%, transparent); }
.cf-item-icono { width: 34px; height: 34px; border-radius: 10px; background: color-mix(in srgb, var(--acento) 14%, white); color: var(--acento); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.cf-item-textos { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.cf-item-nombre { font-size: 13px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cf-item-codigo { font-size: 11px; color: #64748b; }
.cf-item-check { color: var(--acento); font-size: 18px; }

.cf-placeholder { text-align: center; padding: 60px 20px; color: #94a3b8; }
.cf-placeholder i { font-size: 38px; color: #cbd5e1; }
.cf-placeholder p { margin-top: 10px; font-size: 13px; }

.cf-pie { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9; }
.cf-btn, .cf-btn-sec { height: 38px; padding: 0 18px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all .2s; }
.cf-btn { background: linear-gradient(135deg, #1a3a5c, #2c5282); color: #fff; border: none; box-shadow: 0 4px 12px rgba(26, 58, 92, 0.25); }
.cf-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(26, 58, 92, 0.35); }
.cf-btn-sec { background: #fff; color: #1a3a5c; border: 2px solid #cbd5e1; }
.cf-btn-sec:hover:not(:disabled) { border-color: #1a3a5c; background: #f1f5f9; }
.cf-btn:disabled, .cf-btn-sec:disabled { opacity: .45; cursor: not-allowed; }

/* ===== Por cuenta ===== */
.cf-tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
.cf-tabla th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: #fff; padding: 10px; background: linear-gradient(135deg, #1a3a5c, #2c5282); }
.cf-tabla th:first-child { border-radius: 10px 0 0 10px; }
.cf-tabla th:last-child { border-radius: 0 10px 10px 0; }
.cf-tabla td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.cf-tabla tbody tr:hover { background: #f8fafc; }
.cf-codigo-pill { font-family: 'Courier New', monospace; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 8px; }
.cf-fuerte { font-weight: 700; color: #0f172a; }
.cf-chip { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; margin: 2px 4px 2px 0; }
.cf-nadie { color: #94a3b8; font-size: 12px; }
.cf-libres { color: #b91c1c; background: #fee2e2; border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 600; margin-left: 4px; }
.cf-td-acc { text-align: right; }

/* ===== Modal ===== */
.cf-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; z-index: 60; padding: 16px; }
.cf-modal { background: #fff; border-radius: 16px; padding: 22px; width: 100%; max-width: 540px; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
.cf-modal-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.cf-modal-lista { display: flex; flex-direction: column; gap: 8px; overflow-y: auto; padding: 4px; }
</style>