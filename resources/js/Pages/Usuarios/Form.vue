<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';
import {
    UserAddOutlined, EditOutlined, ArrowLeftOutlined, SaveOutlined, IdcardOutlined, LockOutlined, ApartmentOutlined,
    SafetyOutlined, ReloadOutlined, CopyOutlined, SearchOutlined, InfoCircleOutlined, WarningOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import { normalizar } from '@/composables/texto';

const props = defineProps({
    usuario: { type: Object, default: null },
    empresas: { type: Array, default: () => [] },
    tipos: { type: Object, default: () => ({}) },
    empresasUsuario: { type: Array, default: () => [] },
    esUsted: { type: Boolean, default: false },
});

const esEdicion = computed(() => !!props.usuario);
const u = props.usuario || {};

const form = useForm({
    nombre_completo: u.nombre_completo ?? '',
    nombre_usuario: u.nombre_usuario ?? '',
    email: u.email ?? '',
    telefono: u.telefono ?? '',
    tipo_usuario: u.tipo_usuario ?? (props.tipos.CONDOMINO ? 'CONDOMINO' : Object.keys(props.tipos)[0] ?? null),
    activo: u.activo ?? true,
    password: '',
    password_confirmation: '',
    empresas: [...props.empresasUsuario],
});

/** Qué puede hacer cada tipo (ayuda al elegirlo). */
const DESCRIPCION = {
    SUPERUSUARIO: 'Acceso total, incluida el alta de condominios. Ve todos los condominios.',
    ADMINISTRADOR: 'Administra sus condominios: casas, cuotas, usuarios, incidencias y contabilidad.',
    AUDITOR: 'Revisa y corrige la contabilidad de sus condominios.',
    CAPTURISTA: 'Captura movimientos contables.',
    LECTOR: 'Sólo consulta la información contable.',
    CONDOMINO: 'Residente: su casa, visitas, cuotas, alertas y mensajes. Se liga a su casa desde Casas.',
    VIGILANCIA: 'Caseta: registra visitas y atiende alertas.',
    MANTENIMIENTO: 'Atiende incidencias y lleva el inventario de insumos.',
};
const opcionesTipo = computed(() => Object.entries(props.tipos).map(([value, label]) => ({ value, label })));

// ===== Contraseña =====
const generar = () => {
    const letras = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    const azar = (s) => s[Math.floor(Math.random() * s.length)];
    const c = Array.from({ length: 7 }, () => azar(letras)).concat([azar('23456789'), azar('23456789'), azar('!@#$%*?')]).sort(() => Math.random() - 0.5).join('');
    form.password = c;
    form.password_confirmation = c;
};
const copiar = async () => {
    if (!form.password) return;
    try { await navigator.clipboard.writeText(form.password); message.success('Contraseña copiada.'); } catch { message.info('Selecciónala y cópiala manualmente.'); }
};
const fuerza = computed(() => {
    const p = form.password || '';
    let n = 0;
    if (p.length >= 8) n++;
    if (p.length >= 12) n++;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p)) n++;
    if (/\d/.test(p)) n++;
    if (/[^A-Za-z0-9]/.test(p)) n++;
    return [
        { pct: 15, color: '#d9363e', texto: 'Muy débil' }, { pct: 30, color: '#d9363e', texto: 'Débil' }, { pct: 50, color: '#f0a202', texto: 'Regular' },
        { pct: 70, color: '#1597a5', texto: 'Buena' }, { pct: 85, color: '#1faf5c', texto: 'Fuerte' }, { pct: 100, color: '#1faf5c', texto: 'Muy fuerte' },
    ][n];
});
const noCoinciden = computed(() => form.password_confirmation && form.password !== form.password_confirmation);

// ===== Condominios =====
const buscarCondominio = ref('');
const empresasFiltradas = computed(() => {
    const q = normalizar(buscarCondominio.value);
    return q ? props.empresas.filter((e) => normalizar(e.nombre_empresa).includes(q)) : props.empresas;
});
const todos = computed(() => props.empresas.length > 0 && form.empresas.length === props.empresas.length);
const alternarTodos = () => { form.empresas = todos.value ? [] : props.empresas.map((e) => e.id); };
const necesitaCondominio = computed(() => form.tipo_usuario !== 'SUPERUSUARIO');

const err = (campo) => ({ 'validate-status': form.errors[campo] ? 'error' : '', help: form.errors[campo] });

const guardar = () => {
    if (noCoinciden.value) { message.warning('Las contraseñas no coinciden.'); return; }
    if (!esEdicion.value && form.password.length < 8) { message.warning('La contraseña debe tener al menos 8 caracteres.'); return; }
    form.transform((d) => {
        const datos = { ...d, activo: d.activo ? 1 : 0 };
        if (esEdicion.value && !d.password) { delete datos.password; delete datos.password_confirmation; }
        return datos;
    });
    if (esEdicion.value) form.put(route('usuarios.update', props.usuario.id_usuario), { preserveScroll: true });
    else form.post(route('usuarios.store'), { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader :title="esEdicion ? `Editar ${usuario.nombre_completo}` : 'Nuevo usuario'"
                subtitle="Datos de la persona, su acceso al sistema y los condominios que puede operar" :icon="esEdicion ? EditOutlined : UserAddOutlined">
                <template #extra>
                    <Link :href="route('usuarios.index')"><a-button size="large"><ArrowLeftOutlined /> Regresar</a-button></Link>
                </template>
            </PageHeader>

            <div v-if="esUsted" class="vt-alert info mb-3"><InfoCircleOutlined /> <span>Estás editando tu propio usuario. Si cambias tu nombre de usuario o contraseña, úsalos la próxima vez que entres.</span></div>

            <a-form layout="vertical" :model="form" @finish="guardar">
                <!-- Dos columnas parejas: cada fila junta dos tarjetas de la misma altura -->
                <div class="em-grid">
                    <div class="vt-card">
                            <div class="vt-seccion-t"><IdcardOutlined /> Datos de la persona</div>
                            <div class="vt-form-grid">
                                <a-form-item class="full" label="Nombre completo" required v-bind="err('nombre_completo')">
                                    <a-input v-model:value="form.nombre_completo" :maxlength="255" placeholder="Nombre y apellidos" />
                                </a-form-item>
                                <a-form-item label="Correo electrónico" required v-bind="err('email')">
                                    <a-input v-model:value="form.email" type="email" :maxlength="255" placeholder="correo@ejemplo.com" />
                                </a-form-item>
                                <a-form-item label="Teléfono" v-bind="err('telefono')">
                                    <a-input v-model:value="form.telefono" :maxlength="10" placeholder="10 dígitos" @input="form.telefono = String(form.telefono || '').replace(/\D/g, '').slice(0, 10)" />
                                </a-form-item>
                            </div>
                        </div>
                    <div class="vt-card">
                            <div class="vt-seccion-t"><LockOutlined /> Acceso al sistema</div>
                            <div class="vt-form-grid">
                                <a-form-item class="full" label="Nombre de usuario" required v-bind="err('nombre_usuario')" :help="form.errors.nombre_usuario || 'Con este nombre entra al sistema.'">
                                    <a-input v-model:value="form.nombre_usuario" :maxlength="50" placeholder="Ej. JUAN.PEREZ" @blur="form.nombre_usuario = form.nombre_usuario.trim()" />
                                </a-form-item>
                                <a-form-item :label="esEdicion ? 'Nueva contraseña' : 'Contraseña'" :required="!esEdicion" v-bind="err('password')"
                                    :help="form.errors.password || (esEdicion ? 'Déjala vacía para conservar la actual.' : 'Mínimo 8 caracteres.')">
                                    <a-input-password v-model:value="form.password" :maxlength="64" autocomplete="new-password" />
                                </a-form-item>
                                <a-form-item label="Confirmar contraseña" :required="!esEdicion" :validate-status="noCoinciden ? 'error' : ''" :help="noCoinciden ? 'No coincide con la contraseña.' : ''">
                                    <a-input-password v-model:value="form.password_confirmation" :maxlength="64" autocomplete="new-password" />
                                </a-form-item>
                            </div>
                            <div class="flex items-center gap-3 flex-wrap">
                                <a-button @click="generar"><ReloadOutlined /> Generar segura</a-button>
                                <a-button :disabled="!form.password" @click="copiar"><CopyOutlined /> Copiar</a-button>
                                <span v-if="form.password" class="flex items-center gap-2 flex-1 min-w-[160px]">
                                    <span class="us-fuerza"><span :style="{ width: fuerza.pct + '%', background: fuerza.color }"></span></span>
                                    <small :style="{ color: fuerza.color, fontWeight: 600 }">{{ fuerza.texto }}</small>
                                </span>
                            </div>
                        </div>
                    <div class="vt-card">
                            <div class="vt-seccion-t"><SafetyOutlined /> Tipo de usuario</div>
                            <a-form-item label="Tipo" required v-bind="err('tipo_usuario')">
                                <a-select v-model:value="form.tipo_usuario" :options="opcionesTipo" :disabled="esUsted" />
                            </a-form-item>
                            <div v-if="DESCRIPCION[form.tipo_usuario]" class="vt-alert info mb-0"><InfoCircleOutlined /> <span>{{ DESCRIPCION[form.tipo_usuario] }}</span></div>
                            <a-form-item v-if="esEdicion && !esUsted" label="Estado" class="mt-3" style="margin-bottom: 0">
                                <a-switch v-model:checked="form.activo" checked-children="Activo" un-checked-children="Inactivo" />
                            </a-form-item>
                        </div>
                    <div class="vt-card">
                            <div class="vt-seccion-t">
                                <ApartmentOutlined /> Condominios
                                <span class="vt-tag ml-auto" style="background: #e6eef8; color: #1a5a96">{{ form.empresas.length }} de {{ empresas.length }}</span>
                            </div>
                            <div v-if="!necesitaCondominio" class="vt-alert info mb-0"><InfoCircleOutlined /> <span>El superusuario ve todos los condominios; no hace falta asignarle.</span></div>
                            <template v-else>
                                <div v-if="!form.empresas.length" class="vt-alert mb-3"><WarningOutlined /> <span>Sin condominio asignado no podrá ver ni operar nada.</span></div>
                                <div class="flex gap-2 mb-2">
                                    <a-input v-model:value="buscarCondominio" allow-clear placeholder="Buscar condominio"><template #prefix><SearchOutlined class="vt-muted" /></template></a-input>
                                    <a-button v-if="empresas.length > 1" @click="alternarTodos">{{ todos ? 'Quitar todos' : 'Todos' }}</a-button>
                                </div>
                                <a-checkbox-group v-model:value="form.empresas" class="us-condos">
                                    <label v-for="e in empresasFiltradas" :key="e.id" class="us-condo" :class="{ on: form.empresas.includes(e.id) }">
                                        <a-checkbox :value="e.id" />
                                        <ApartmentOutlined />
                                        <span class="truncate">{{ e.nombre_empresa }}</span>
                                    </label>
                                    <div v-if="!empresasFiltradas.length" class="vt-small vt-muted p-2">Sin coincidencias.</div>
                                </a-checkbox-group>
                                <div v-if="form.errors.empresas" class="vt-small mt-1" style="color: #d9363e">{{ form.errors.empresas }}</div>
                            </template>
                        </div>
                </div>

                <div class="vt-card mt-4 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex gap-6 flex-wrap">
                        <template v-if="esEdicion">
                            <Registro etiqueta="Registro" :fecha="usuario.created_at" />
                            <Registro etiqueta="Última modificación" :fecha="usuario.updated_at" />
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('usuarios.index')"><a-button size="large">Cancelar</a-button></Link>
                        <a-button type="primary" size="large" html-type="submit" :loading="form.processing">
                            <SaveOutlined /> {{ esEdicion ? 'Guardar cambios' : 'Crear usuario' }}
                        </a-button>
                    </div>
                </div>
            </a-form>
        </div>
    </AppLayout>
</template>

<style scoped>
.em-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: stretch; }
@media (max-width: 1023px) { .em-grid { grid-template-columns: minmax(0, 1fr); } }
.us-fuerza { flex: 1; height: 6px; border-radius: 99px; background: #eef2f6; overflow: hidden; }
.us-fuerza span { display: block; height: 100%; border-radius: 99px; transition: width 0.3s, background 0.3s; }
.us-condos { display: flex !important; flex-direction: column; gap: 6px; max-height: 260px; overflow-y: auto; }
.us-condo { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 12px; border: 1px solid var(--vt-line); cursor: pointer; transition: all 0.15s; color: var(--vt-navy); font-weight: 500; }
.us-condo:hover { border-color: #bcd3f2; }
.us-condo.on { border-color: #1a5a96; background: #f2f7fd; }
.us-condo .anticon { color: #1a5a96; }
</style>
