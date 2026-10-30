<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { message } from 'ant-design-vue';
import {
    BankOutlined, EditOutlined, ArrowLeftOutlined, SaveOutlined, IdcardOutlined, TagOutlined, FileTextOutlined, PartitionOutlined,
    PlusOutlined, StopOutlined, ReloadOutlined, CheckCircleOutlined, CloseCircleOutlined, LoadingOutlined, InfoCircleOutlined,
    ArrowUpOutlined, ArrowDownOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import { confirmar } from '@/composables/confirmar';

const props = defineProps({
    cuenta: { type: Object, default: null },
    empresas: { type: Array, default: () => [] },
    cuentasResultados: { type: Array, default: () => [] },
    empresaInicial: { type: Number, default: null },
});

const esEdicion = computed(() => !!props.cuenta);
const c = props.cuenta || {};
const idValido = (v) => (v && Number(v) > 0 ? Number(v) : null);

const TIPOS = [
    { value: 'normal', titulo: 'Normal', desc: 'Pertenece a una cuenta de resultados' },
    { value: 'resultados', titulo: 'De resultados', desc: 'Agrupa otras cuentas (nivel 2)' },
    { value: 'fondeadora', titulo: 'Fondeadora', desc: 'Fuente de fondeo' },
];
const tipoDe = (x) => (x.es_cuenta_resultados ? 'resultados' : Number(x.fondeo_c) ? 'fondeadora' : 'normal');

const form = useForm({
    id_empresa: c.id_empresa ?? props.empresaInicial ?? (props.empresas.length === 1 ? props.empresas[0].id : null),
    codigo_cuenta: c.codigo_cuenta ?? '',
    nombre_cuenta: c.nombre_cuenta ?? '',
    descripcion: c.descripcion ?? '',
    Naturaleza: c.Naturaleza ?? 'DEUDORA',
    nivel: c.nivel ?? 1,
    tipo: esEdicion.value ? tipoDe(c) : 'normal',
    cuenta_resultados: idValido(c.cuenta_resultados),
    en_uso: true,
});
watch(() => form.tipo, (t) => {
    if (t === 'resultados') form.nivel = 2;
    if (t !== 'normal') form.cuenta_resultados = null;
});

// ===== Cuentas de resultados del condominio =====
const resultados = ref([...props.cuentasResultados]);
const cargandoResultados = ref(false);
const cargarResultados = async (empresaId) => {
    if (!empresaId) { resultados.value = []; return; }
    cargandoResultados.value = true;
    try {
        const { data } = await axios.get(route('cuentas.get-cuentas-resultados'), { params: { empresa_id: empresaId } });
        resultados.value = data?.data || [];
    } catch {
        resultados.value = [];
    } finally {
        cargandoResultados.value = false;
    }
};
const opcionesResultados = (excluir = null) => resultados.value.filter((r) => r.id_cuenta !== excluir).map((r) => ({ value: r.id_cuenta, label: r.display }));
watch(() => form.id_empresa, (id, antes) => { if (antes !== undefined) form.cuenta_resultados = null; cargarResultados(id); });
onMounted(() => { if (form.id_empresa && !resultados.value.length) cargarResultados(form.id_empresa); });

// ===== Código disponible (consulta al servidor mientras escribe) =====
const usarVerificador = (obtener, original = '') => {
    const estado = reactive({ revisando: false, ocupado: false });
    let t = null;
    const revisar = () => {
        clearTimeout(t);
        const { codigo, empresa } = obtener();
        estado.ocupado = false;
        if (!codigo || !empresa || codigo === original) { estado.revisando = false; return; }
        estado.revisando = true;
        t = setTimeout(async () => {
            try {
                const { data } = await axios.get(route('cuentas.verificar-codigo'), { params: { empresa_id: empresa, codigo } });
                estado.ocupado = !!data.exists;
            } catch {
                estado.ocupado = false;
            } finally {
                estado.revisando = false;
            }
        }, 400);
    };
    return { estado, revisar };
};
const codigo = usarVerificador(() => ({ codigo: form.codigo_cuenta.trim(), empresa: form.id_empresa }), c.codigo_cuenta || '');

const err = (campo) => ({ 'validate-status': form.errors[campo] ? 'error' : '', help: form.errors[campo] });
const ayudaCodigo = computed(() => {
    if (form.errors.codigo_cuenta) return form.errors.codigo_cuenta;
    if (codigo.estado.ocupado) return 'Ese código ya existe en este condominio.';
    return '';
});

const guardar = () => {
    if (codigo.estado.ocupado) { message.warning('El código ya está en uso; elige otro.'); return; }
    if (form.tipo === 'normal' && !form.cuenta_resultados) { message.warning('Elige la cuenta de resultados a la que pertenece.'); return; }
    form.transform((d) => ({
        id_empresa: d.id_empresa,
        codigo_cuenta: d.codigo_cuenta.trim(),
        nombre_cuenta: d.nombre_cuenta.trim(),
        descripcion: d.descripcion || '',
        Naturaleza: d.Naturaleza,
        nivel: d.tipo === 'resultados' ? 2 : d.nivel,
        es_cuenta_resultados: d.tipo === 'resultados' ? 1 : 0,
        fondeo_c: d.tipo === 'fondeadora' ? 1 : 0,
        cuenta_resultados: d.tipo === 'normal' ? d.cuenta_resultados : null,
        ...(esEdicion.value ? { id_cuenta_madre: null } : { en_uso: d.en_uso ? 1 : 0 }),
    }));
    if (esEdicion.value) form.put(route('cuentas.update', props.cuenta.id_cuenta), { preserveScroll: true });
    else form.post(route('cuentas.store'), { preserveScroll: true });
};

// ===== Desactivar / reactivar (sólo edición) =====
const desactivar = async () => {
    if (!(await confirmar({ tipo: 'danger', titulo: '¿Desactivar la cuenta?', texto: `“${c.codigo_cuenta} · ${c.nombre_cuenta}” dejará de aparecer en el catálogo. Podrás restaurarla desde «Inactivas».`, confirmar: 'Sí, desactivar' }))) return;
    router.delete(route('cuentas.destroy', c.id_cuenta));
};
const reactivar = async () => {
    if (!(await confirmar({ tipo: 'success', titulo: '¿Reactivar la cuenta?', texto: `“${c.codigo_cuenta} · ${c.nombre_cuenta}” volverá a estar activa.`, confirmar: 'Sí, reactivar' }))) return;
    router.post(route('cuentas.restaurar', c.id_cuenta));
};

// ===== Crear cuenta hija (sólo edición) =====
const dialogoHija = ref(false);
const hija = useForm({ codigo_cuenta: '', nombre_cuenta: '', descripcion: '', Naturaleza: 'DEUDORA', tipo: 'normal', cuenta_resultados: null });
const codigoHija = usarVerificador(() => ({ codigo: hija.codigo_cuenta.trim(), empresa: c.id_empresa }));
const nivelHija = computed(() => (hija.tipo === 'resultados' ? 2 : Number(c.nivel || 0) + 1));
watch(() => hija.tipo, (t) => { if (t !== 'normal') hija.cuenta_resultados = null; });
const abrirHija = () => {
    hija.reset();
    hija.clearErrors();
    hija.Naturaleza = c.Naturaleza || 'DEUDORA';
    if (c.es_cuenta_resultados) hija.cuenta_resultados = c.id_cuenta; // la hija de una cuenta de resultados le pertenece
    dialogoHija.value = true;
};
const guardarHija = () => {
    if (!hija.codigo_cuenta.trim() || !hija.nombre_cuenta.trim()) { message.warning('Captura código y nombre de la cuenta hija.'); return; }
    if (codigoHija.estado.ocupado) { message.warning('El código ya está en uso; elige otro.'); return; }
    if (hija.tipo === 'normal' && !hija.cuenta_resultados) { message.warning('Elige la cuenta de resultados a la que pertenece.'); return; }
    hija.transform((d) => ({
        id_empresa: c.id_empresa,
        id_cuenta_madre: c.id_cuenta,
        codigo_cuenta: d.codigo_cuenta.trim(),
        nombre_cuenta: d.nombre_cuenta.trim(),
        descripcion: d.descripcion || '',
        Naturaleza: d.Naturaleza,
        nivel: nivelHija.value,
        es_cuenta_resultados: d.tipo === 'resultados' ? 1 : 0,
        fondeo_c: d.tipo === 'fondeadora' ? 1 : 0,
        cuenta_resultados: d.tipo === 'normal' ? d.cuenta_resultados : null,
        en_uso: 1,
    })).post(route('cuentas.store'), { preserveScroll: true, onSuccess: () => (dialogoHija.value = false) });
};
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader :title="esEdicion ? `Editar ${cuenta.codigo_cuenta} · ${cuenta.nombre_cuenta}` : 'Nueva cuenta'"
                subtitle="Cuenta contable del condominio: código, nivel, naturaleza y a qué cuenta de resultados pertenece" :icon="esEdicion ? EditOutlined : BankOutlined">
                <template #extra>
                    <a-button v-if="esEdicion && cuenta.en_uso" size="large" @click="abrirHija"><PlusOutlined /> Cuenta hija</a-button>
                    <Link :href="route('cuentas.index', { empresa_id: form.id_empresa })"><a-button size="large"><ArrowLeftOutlined /> Regresar</a-button></Link>
                </template>
            </PageHeader>

            <div v-if="esEdicion && !cuenta.en_uso" class="vt-alert mb-3"><StopOutlined /> <span>Esta cuenta está <b>inactiva</b>: no aparece en el catálogo ni se puede usar en movimientos.</span></div>

            <a-form layout="vertical" :model="form" @finish="guardar">
                <!-- Dos columnas parejas: cada fila junta dos tarjetas de la misma altura -->
                <div class="em-grid">
                    <div class="vt-card">
                        <div class="vt-seccion-t"><IdcardOutlined /> Identificación</div>
                        <div class="vt-form-grid">
                            <a-form-item class="full" label="Condominio" required v-bind="err('id_empresa')">
                                <a-select v-model:value="form.id_empresa" placeholder="Selecciona…" show-search option-filter-prop="label" :disabled="esEdicion"
                                    :options="empresas.map((e) => ({ value: e.id, label: e.nombre_empresa }))" />
                            </a-form-item>
                            <a-form-item label="Código" required :validate-status="ayudaCodigo ? 'error' : ''" :help="ayudaCodigo">
                                <a-input v-model:value="form.codigo_cuenta" :maxlength="50" placeholder="Ej. 1101" @input="codigo.revisar">
                                    <template #suffix>
                                        <LoadingOutlined v-if="codigo.estado.revisando" class="vt-muted" />
                                        <CloseCircleOutlined v-else-if="codigo.estado.ocupado" style="color: #d9363e" />
                                        <CheckCircleOutlined v-else-if="form.codigo_cuenta && form.codigo_cuenta !== (cuenta?.codigo_cuenta || '')" style="color: #1faf5c" />
                                    </template>
                                </a-input>
                            </a-form-item>
                            <a-form-item label="Nivel" required>
                                <a-select v-model:value="form.nivel" :disabled="form.tipo === 'resultados'" :options="[1, 2, 3, 4, 5].map((n) => ({ value: n, label: `Nivel ${n}` }))" />
                            </a-form-item>
                            <a-form-item class="full" label="Nombre de la cuenta" required v-bind="err('nombre_cuenta')" style="margin-bottom: 0">
                                <a-input v-model:value="form.nombre_cuenta" :maxlength="255" placeholder="Ej. Bancos" />
                            </a-form-item>
                        </div>
                    </div>

                    <div class="vt-card">
                        <div class="vt-seccion-t"><TagOutlined /> Clasificación</div>
                        <a-form-item label="Naturaleza" required>
                            <a-segmented v-model:value="form.Naturaleza" block :options="[{ value: 'DEUDORA', label: 'Deudora' }, { value: 'ACREEDORA', label: 'Acreedora' }]" />
                        </a-form-item>
                        <a-form-item label="Tipo de cuenta" required>
                            <div class="cu-tipos">
                                <button v-for="tp in TIPOS" :key="tp.value" type="button" class="cu-tipo" :class="{ on: form.tipo === tp.value }" @click="form.tipo = tp.value">
                                    <b>{{ tp.titulo }}</b><small>{{ tp.desc }}</small>
                                </button>
                            </div>
                        </a-form-item>
                        <a-form-item v-if="form.tipo === 'normal'" label="Pertenece a la cuenta de resultados" required v-bind="err('cuenta_resultados')" style="margin-bottom: 0">
                            <a-select v-model:value="form.cuenta_resultados" show-search option-filter-prop="label" :loading="cargandoResultados" :disabled="!form.id_empresa"
                                :placeholder="form.id_empresa ? 'Selecciona…' : 'Primero el condominio'" :options="opcionesResultados(cuenta?.id_cuenta)"
                                :not-found-content="cargandoResultados ? 'Cargando…' : 'Este condominio no tiene cuentas de resultados. Crea una primero.'" />
                        </a-form-item>
                    </div>

                    <div class="vt-card" :class="{ 'em-ancho': !esEdicion }">
                        <div class="vt-seccion-t"><FileTextOutlined /> Descripción</div>
                        <a-form-item label="Descripción" v-bind="err('descripcion')" :style="esEdicion ? 'margin-bottom: 0' : ''">
                            <a-textarea v-model:value="form.descripcion" :rows="esEdicion ? 5 : 2" placeholder="Para qué se usa esta cuenta (opcional)" />
                        </a-form-item>
                        <a-form-item v-if="!esEdicion" label="Estado" style="margin-bottom: 0">
                            <a-switch v-model:checked="form.en_uso" checked-children="Activa" un-checked-children="Inactiva" />
                        </a-form-item>
                    </div>

                    <div v-if="esEdicion" class="vt-card">
                        <div class="vt-seccion-t"><PartitionOutlined /> Estructura y estado</div>
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="vt-dato"><span>Cuentas hijas</span><span>{{ cuenta.cuentas_hijas_count ?? 0 }}</span></div>
                            <div class="vt-dato"><span>Cuenta madre</span><span>{{ cuenta.cuenta_madre ? `${cuenta.cuenta_madre.codigo_cuenta} · ${cuenta.cuenta_madre.nombre_cuenta}` : 'Principal' }}</span></div>
                            <div class="vt-dato"><span>Estado</span><span :style="{ color: cuenta.en_uso ? '#1faf5c' : '#94a3b8' }">{{ cuenta.en_uso ? 'Activa' : 'Inactiva' }}</span></div>
                            <div class="vt-dato"><span>Naturaleza</span><span>{{ cuenta.Naturaleza === 'ACREEDORA' ? 'Acreedora' : 'Deudora' }}</span></div>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <a-button v-if="cuenta.en_uso" @click="abrirHija"><PlusOutlined /> Crear cuenta hija</a-button>
                            <a-button v-if="cuenta.en_uso" danger @click="desactivar"><StopOutlined /> Desactivar</a-button>
                            <a-button v-else type="primary" @click="reactivar"><ReloadOutlined /> Reactivar</a-button>
                        </div>
                    </div>
                </div>

                <div class="vt-card mt-4 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex gap-6 flex-wrap">
                        <template v-if="esEdicion">
                            <Registro etiqueta="Registro" :fecha="cuenta.created_at" />
                            <Registro etiqueta="Última modificación" :fecha="cuenta.updated_at" />
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('cuentas.index', { empresa_id: form.id_empresa })"><a-button size="large">Cancelar</a-button></Link>
                        <a-button type="primary" size="large" html-type="submit" :loading="form.processing" :disabled="codigo.estado.ocupado">
                            <SaveOutlined /> {{ esEdicion ? 'Guardar cambios' : 'Crear cuenta' }}
                        </a-button>
                    </div>
                </div>
            </a-form>
        </div>

        <!-- Crear cuenta hija -->
        <a-modal v-if="esEdicion" v-model:open="dialogoHija" centered width="640px" :confirm-loading="hija.processing" ok-text="Crear cuenta hija" cancel-text="Cancelar" @ok="guardarHija">
            <template #title><span class="flex items-center gap-2"><PlusOutlined /> Nueva cuenta hija</span></template>
            <div class="vt-alert info mb-3"><InfoCircleOutlined /> <span>Quedará bajo <b>{{ cuenta.codigo_cuenta }} · {{ cuenta.nombre_cuenta }}</b>, en el nivel {{ nivelHija }}.</span></div>
            <a-form layout="vertical">
                <div class="vt-form-grid">
                    <a-form-item label="Código" required :validate-status="codigoHija.estado.ocupado || hija.errors.codigo_cuenta ? 'error' : ''"
                        :help="hija.errors.codigo_cuenta || (codigoHija.estado.ocupado ? 'Ese código ya existe.' : '')">
                        <a-input v-model:value="hija.codigo_cuenta" :maxlength="50" @input="codigoHija.revisar">
                            <template #suffix>
                                <LoadingOutlined v-if="codigoHija.estado.revisando" class="vt-muted" />
                                <CloseCircleOutlined v-else-if="codigoHija.estado.ocupado" style="color: #d9363e" />
                                <CheckCircleOutlined v-else-if="hija.codigo_cuenta" style="color: #1faf5c" />
                            </template>
                        </a-input>
                    </a-form-item>
                    <a-form-item label="Naturaleza" required>
                        <a-segmented v-model:value="hija.Naturaleza" block :options="[{ value: 'DEUDORA', label: 'Deudora' }, { value: 'ACREEDORA', label: 'Acreedora' }]" />
                    </a-form-item>
                    <a-form-item class="full" label="Nombre" required :validate-status="hija.errors.nombre_cuenta ? 'error' : ''" :help="hija.errors.nombre_cuenta">
                        <a-input v-model:value="hija.nombre_cuenta" :maxlength="255" />
                    </a-form-item>
                    <a-form-item class="full" label="Tipo de cuenta">
                        <div class="cu-tipos">
                            <button v-for="tp in TIPOS" :key="tp.value" type="button" class="cu-tipo" :class="{ on: hija.tipo === tp.value }" @click="hija.tipo = tp.value">
                                <b>{{ tp.titulo }}</b><small>{{ tp.desc }}</small>
                            </button>
                        </div>
                    </a-form-item>
                    <a-form-item v-if="hija.tipo === 'normal'" class="full" label="Pertenece a la cuenta de resultados" required>
                        <a-select v-model:value="hija.cuenta_resultados" show-search option-filter-prop="label" :options="opcionesResultados()" placeholder="Selecciona…" />
                    </a-form-item>
                    <a-form-item class="full" label="Descripción" style="margin-bottom: 0">
                        <a-textarea v-model:value="hija.descripcion" :rows="2" />
                    </a-form-item>
                </div>
            </a-form>
        </a-modal>
    </AppLayout>
</template>

<style scoped>
.em-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: stretch; }
.em-ancho { grid-column: 1 / -1; }
.cu-tipos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; width: 100%; }
.cu-tipo { display: flex; flex-direction: column; gap: 2px; text-align: left; padding: 10px 12px; border-radius: 12px; border: 1px solid var(--vt-line); background: #fff; transition: all 0.15s; }
.cu-tipo:hover { border-color: #bcd3f2; }
.cu-tipo.on { border-color: #1a5a96; background: #f2f7fd; box-shadow: 0 0 0 2px rgba(26, 90, 150, 0.12); }
.cu-tipo b { font-size: 13.5px; color: var(--vt-navy); }
.cu-tipo small { font-size: 11.5px; color: var(--vt-muted); line-height: 1.3; }
@media (max-width: 1023px) {
    .em-grid { grid-template-columns: minmax(0, 1fr); }
    .cu-tipos { grid-template-columns: minmax(0, 1fr); }
}
</style>
