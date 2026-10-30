<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    HomeOutlined, ArrowLeftOutlined, EditOutlined, StopOutlined, CheckCircleOutlined, InfoCircleOutlined, WalletOutlined, UnorderedListOutlined,
    FilePdfOutlined, EnvironmentOutlined, SaveOutlined, PictureOutlined, UploadOutlined, TeamOutlined, UserAddOutlined, UserDeleteOutlined,
    StarFilled, MobileOutlined, PhoneOutlined, MailOutlined, HeartOutlined, MessageOutlined, UserOutlined, KeyOutlined, CameraOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import MapaGps from '@/Components/MapaGps.vue';
import EstadoCuentaCasa from '@/Components/EstadoCuentaCasa.vue';
import Registro from '@/Components/Registro.vue';
import Galeria from '@/Components/Galeria.vue';
import SelectorArchivos from '@/Components/SelectorArchivos.vue';
import VisorArchivo from '@/Components/VisorArchivo.vue';
import { confirmar, confirmarEstado } from '@/composables/confirmar';
import { fechaHora, moneda } from '@/composables/useFechaHora';

const props = defineProps({
    casa: Object,
    documentos: Array,
    puedeEditar: Boolean,
    puedeActualizarCasa: Boolean,
    esMiCasa: Boolean,
    tiposPropiedad: Object,
    estatusOcupacion: Object,
    tiposCondomino: Object,
    tiposFoto: Object,
});

const page = usePage();
const activos = computed(() => props.casa.condominos.filter((c) => c.activo));
const opciones = (obj) => Object.entries(obj).map(([value, label]) => ({ value, label }));
const COLOR_TIPO = { PROPIETARIO: '#1a5a96', INQUILINO: '#6e4bc2', FAMILIAR: '#5d7085' };

// ===== Estado de cuenta (el condómino de la casa y la administración) =====
const verEstadoCuenta = computed(() =>
    props.esMiCasa || (page.props.permisos?.puede_ver_transparencia && page.props.auth?.user?.tipo_usuario !== 'CONDOMINO'),
);
const dialogoEstadoCuenta = ref(false);
const resumenCuenta = ref(null);
onMounted(async () => {
    if (!verEstadoCuenta.value) return;
    try {
        const { data } = await axios.get(route('cuotas.estado-cuenta', props.casa.id));
        resumenCuenta.value = data.resumen;
    } catch (e) {
        resumenCuenta.value = { saldo: 0, cargos_vencidos: 0, ultimo_pago: null };
    }
});

const direccionCondominio = computed(() => {
    const e = props.casa.condominio || {};
    return [e.nombre_empresa, e.colonia, e.municipio, e.estado, e.codigo_postal ? `C.P. ${e.codigo_postal}` : null].filter(Boolean).join(', ');
});
const iniciales = (c) => `${c.nombre?.[0] || ''}${c.apellido_paterno?.[0] || ''}`.toUpperCase();

// ===== Casa =====
const toggleCasa = async () => {
    if (await confirmarEstado(props.casa.activo, 'la casa', props.casa.identificador)) {
        router.post(route('casas.toggle-activo', props.casa.id), {}, { preserveScroll: true });
    }
};

// ===== GPS =====
const editandoGps = ref(false);
const num = (v) => (v === null || v === undefined || v === '' ? null : Number(v));
const gpsForm = useForm({ latitud: num(props.casa.latitud), longitud: num(props.casa.longitud) });
const cancelarGps = () => {
    gpsForm.latitud = num(props.casa.latitud);
    gpsForm.longitud = num(props.casa.longitud);
    editandoGps.value = false;
};
const guardarGps = () => gpsForm.post(route('casas.ubicacion', props.casa.id), { preserveScroll: true, onSuccess: () => (editandoGps.value = false) });

// ===== Fotos =====
const modalFoto = ref(false);
const fotoArchivos = ref([]);
const fotoForm = useForm({ tipo: 'FACHADA', descripcion: '', foto: null });
const abrirFoto = () => { fotoForm.reset(); fotoForm.clearErrors(); fotoArchivos.value = []; modalFoto.value = true; };
const subirFoto = () => {
    fotoForm.transform((d) => ({ ...d, foto: fotoArchivos.value[0] || null }))
        .post(route('casas.fotos.store', props.casa.id), { preserveScroll: true, forceFormData: true, onSuccess: () => (modalFoto.value = false) });
};
const puedeEliminarFoto = (foto) => props.puedeEditar || (props.puedeActualizarCasa && foto.id_usuario_subio === page.props.auth?.user?.id_usuario);
const itemsFotos = computed(() => props.casa.fotos.map((f) => ({
    id: f.id, url: f.url, nombre: f.descripcion || props.tiposFoto[f.tipo] || 'Foto', mime: 'image/jpeg',
    etiqueta: props.tiposFoto[f.tipo] || f.tipo,
    detalle: `${f.subio?.nombre_usuario || '—'} · ${fechaHora(f.created_at)}${f.descripcion ? ' · ' + f.descripcion : ''}`,
    eliminable: puedeEliminarFoto(f),
})));
const eliminarFoto = async (i) => {
    if (await confirmar({ tipo: 'danger', titulo: '¿Eliminar esta foto?', texto: 'Se quitará de la ficha de la casa.', confirmar: 'Sí, eliminar' })) {
        router.delete(route('casas.fotos.destroy', i.id), { preserveScroll: true });
    }
};
const visorCondomino = ref({ open: false, url: null, nombre: '' });

// ===== Condóminos =====
const dialogo = ref(false);
const editando = ref(null);
const fotoCondomino = ref([]);

const vacioCondomino = () => ({
    tipo: 'PROPIETARIO',
    es_responsable: !props.casa.condominos.some((c) => c.es_responsable && c.activo),
    nombre: '', apellido_paterno: '', apellido_materno: '',
    telefono: '', telefono_alterno: '', correo: '',
    emergencia_nombre: '', emergencia_telefono: '', emergencia_parentesco: '',
    notas: '', foto_archivo: null, crear_acceso: false, nombre_usuario: '', password: '',
});
const cForm = useForm(vacioCondomino());

const abrirCondomino = (c = null) => {
    editando.value = c;
    cForm.clearErrors();
    const base = vacioCondomino();
    if (c) {
        for (const k of Object.keys(base)) if (k in c && c[k] !== null) base[k] = c[k];
        base.nombre_usuario = c.usuario?.nombre_usuario || '';
        base.password = '';
        base.foto_archivo = null;
    }
    cForm.defaults(base);
    cForm.reset();
    fotoCondomino.value = [];
    dialogo.value = true;
};

const guardarCondomino = () => {
    const url = editando.value ? route('condominos.update', editando.value.id) : route('condominos.store', props.casa.id);
    cForm.transform((d) => ({ ...d, foto_archivo: fotoCondomino.value[0] || null }))
        .post(url, { preserveScroll: true, forceFormData: true, onSuccess: () => (dialogo.value = false) });
};

const toggleCondomino = async (c) => {
    const baja = c.activo;
    const extra = c.usuario ? ` Su acceso al sistema también se ${baja ? 'desactivará' : 'reactivará'}.` : '';
    const ok = await confirmar({
        tipo: baja ? 'danger' : 'success',
        titulo: baja ? '¿Dar de baja al condómino?' : '¿Reactivar al condómino?',
        texto: `${c.nombre_completo} ${baja ? 'se marcará como baja de la casa; su historial se conserva.' : 'volverá a estar activo en la casa.'}${extra}`,
        confirmar: baja ? 'Sí, dar de baja' : 'Sí, reactivar',
    });
    if (ok) router.post(route('condominos.toggle-activo', c.id), {}, { preserveScroll: true });
};
const err = (k) => ({ 'validate-status': cForm.errors[k] ? 'error' : '', help: cForm.errors[k] });
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader :title="`${esMiCasa ? 'Mi casa' : 'Casa'} ${casa.identificador}`" :subtitle="casa.condominio?.nombre_empresa" :icon="HomeOutlined">
                <template #titulo>
                    <span class="vt-tag solido" :style="{ background: casa.activo ? '#1faf5c' : '#94a3b8', fontSize: '12px' }">{{ casa.activo ? 'Activa' : 'Inactiva' }}</span>
                </template>
                <template #extra>
                    <Link v-if="!esMiCasa" :href="route('casas.index')"><a-button size="large"><ArrowLeftOutlined /> Casas</a-button></Link>
                    <template v-if="puedeEditar">
                        <a-button size="large" :danger="casa.activo" @click="toggleCasa">
                            <component :is="casa.activo ? StopOutlined : CheckCircleOutlined" /> {{ casa.activo ? 'Desactivar' : 'Activar' }}
                        </a-button>
                        <Link :href="route('casas.edit', casa.id)"><a-button type="primary" size="large"><EditOutlined /> Editar casa</a-button></Link>
                    </template>
                </template>
            </PageHeader>

            <div class="grid gap-4 xl:grid-cols-5">
                <!-- ===== Columna izquierda: datos, cuotas, ubicación y fotos ===== -->
                <div class="xl:col-span-2 flex flex-col gap-4">
                    <div class="vt-card">
                        <div class="vt-card-title"><h3><InfoCircleOutlined /> Datos de la casa</h3></div>
                        <div class="vt-grid-datos">
                            <div class="vt-dato"><span>Tipo</span><span>{{ tiposPropiedad[casa.tipo_propiedad] || casa.tipo_propiedad }}</span></div>
                            <div class="vt-dato"><span>Ocupación</span><span>{{ estatusOcupacion[casa.estatus_ocupacion] || casa.estatus_ocupacion }}</span></div>
                            <div class="vt-dato full"><span>Dirección</span><span>{{ casa.direccion_corta || 'Sin capturar' }}</span></div>
                            <div class="vt-dato full"><span>Condominio</span><span>{{ direccionCondominio }}</span></div>
                            <div class="vt-dato"><span>Cuota mensual</span><span>{{ casa.cuota_mantenimiento !== null ? moneda(casa.cuota_mantenimiento) : '—' }}</span></div>
                            <div class="vt-dato"><span>Residentes activos</span><span>{{ activos.length }}</span></div>
                            <div v-if="casa.referencias" class="vt-dato full"><span>Referencias</span><span>{{ casa.referencias }}</span></div>
                            <div v-if="casa.notas && !esMiCasa" class="vt-dato full"><span>Notas internas</span><span>{{ casa.notas }}</span></div>
                        </div>
                        <a-divider style="margin: 14px 0 10px" />
                        <div class="flex gap-6 flex-wrap">
                            <Registro etiqueta="Registró" :usuario="casa.registro?.nombre_usuario" :fecha="casa.created_at" />
                            <Registro etiqueta="Modificó" :usuario="casa.modifico?.nombre_usuario" :fecha="casa.updated_at" />
                        </div>
                    </div>

                    <div v-if="verEstadoCuenta" class="vt-card">
                        <div class="vt-card-title">
                            <h3><WalletOutlined /> Cuotas de mantenimiento</h3>
                            <a-button size="small" @click="dialogoEstadoCuenta = true"><UnorderedListOutlined /> Estado de cuenta</a-button>
                        </div>
                        <a-skeleton v-if="!resumenCuenta" active :paragraph="{ rows: 1 }" />
                        <div v-else class="grid grid-cols-3 gap-3">
                            <div class="vt-dato"><span>Saldo</span><span :style="{ color: resumenCuenta.saldo > 0.009 ? '#d9363e' : '#15803d', fontWeight: 700 }">{{ moneda(resumenCuenta.saldo) }}</span></div>
                            <div class="vt-dato"><span>Cargos vencidos</span><span>{{ resumenCuenta.cargos_vencidos }}</span></div>
                            <div class="vt-dato"><span>Último pago</span><span>{{ resumenCuenta.ultimo_pago ? resumenCuenta.ultimo_pago.split('-').reverse().join('/') : 'Sin pagos' }}</span></div>
                            <div v-if="resumenCuenta.saldo <= 0.009" class="col-span-3"><span class="vt-tag" style="background: #e6f7ee; color: #15803d"><CheckCircleOutlined /> Al corriente</span></div>
                        </div>
                    </div>

                    <div v-if="documentos && documentos.length" class="vt-card">
                        <div class="vt-card-title"><h3><FilePdfOutlined /> Contratos y documentos</h3></div>
                        <Link v-for="d in documentos" :key="d.id" :href="route('documentos.show', d.id)" class="vt-row">
                            <FilePdfOutlined style="color: #d9363e; font-size: 18px" />
                            <span class="flex-1 truncate font-semibold">{{ d.titulo }}</span>
                            <span class="vt-small vt-muted">{{ fechaHora(d.created_at) }}</span>
                        </Link>
                    </div>

                    <div class="vt-card">
                        <div class="vt-card-title">
                            <h3><EnvironmentOutlined /> Ubicación GPS</h3>
                            <template v-if="puedeActualizarCasa">
                                <a-button v-if="!editandoGps" size="small" @click="editandoGps = true"><EditOutlined /> {{ casa.latitud !== null ? 'Cambiar' : 'Fijar ubicación' }}</a-button>
                                <div v-else class="flex gap-2">
                                    <a-button size="small" @click="cancelarGps">Cancelar</a-button>
                                    <a-button size="small" type="primary" :loading="gpsForm.processing" @click="guardarGps"><SaveOutlined /> Guardar</a-button>
                                </div>
                            </template>
                        </div>
                        <MapaGps :key="editandoGps ? 'edit' : 'ver'" v-model:lat="gpsForm.latitud" v-model:lng="gpsForm.longitud" :editable="editandoGps" height="280px" />
                    </div>

                    <div class="vt-card">
                        <div class="vt-card-title">
                            <h3><PictureOutlined /> Fotos <span class="vt-tag" style="background: #eef2f7; color: #44566c">{{ casa.fotos.length }}</span></h3>
                            <a-button v-if="puedeActualizarCasa" size="small" type="primary" @click="abrirFoto"><UploadOutlined /> Subir foto</a-button>
                        </div>
                        <Galeria :items="itemsFotos" vacio="Sin fotos. Sube la fachada para que vigilancia y mantenimiento identifiquen la casa." @eliminar="eliminarFoto" />
                    </div>
                </div>

                <!-- ===== Columna derecha: condóminos ===== -->
                <div class="xl:col-span-3">
                    <div class="vt-card">
                        <div class="vt-card-title">
                            <h3><TeamOutlined /> Condóminos <span class="vt-tag" style="background: #eef2f7; color: #44566c">{{ activos.length }} activos</span></h3>
                            <a-button v-if="puedeEditar" type="primary" @click="abrirCondomino()"><UserAddOutlined /> Agregar condómino</a-button>
                        </div>

                        <div v-if="!casa.condominos.length" class="vt-empty">Esta casa aún no tiene condóminos registrados.</div>

                        <div class="flex flex-col gap-3">
                            <div v-for="c in casa.condominos" :key="c.id" class="cd-item" :class="{ inactivo: !c.activo }">
                                <div class="flex gap-3">
                                    <a-avatar :size="58" :src="c.foto_url || undefined" :style="c.foto_url ? 'cursor: zoom-in' : 'background:#1a5a96;font-weight:700'"
                                        @click="c.foto_url && (visorCondomino = { open: true, url: c.foto_url, nombre: c.nombre_completo })">{{ iniciales(c) }}</a-avatar>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-2 flex-wrap">
                                            <div>
                                                <div class="font-bold" style="color: var(--vt-navy); font-size: 15px">{{ c.nombre_completo }}</div>
                                                <div class="flex gap-1 flex-wrap mt-1">
                                                    <span class="vt-tag solido" :style="{ background: COLOR_TIPO[c.tipo] || '#5d7085' }">{{ tiposCondomino[c.tipo] || c.tipo }}</span>
                                                    <span v-if="c.es_responsable" class="vt-tag" style="background: #e6f7ee; color: #15803d"><StarFilled /> Responsable</span>
                                                    <a-tooltip v-if="c.usuario" title="Entra al sistema con este usuario">
                                                        <span class="vt-tag" style="background: #fff4d9; color: #a86f00"><MobileOutlined /> {{ c.usuario.nombre_usuario }}</span>
                                                    </a-tooltip>
                                                    <span v-if="!c.activo" class="vt-tag solido" style="background: #d9363e">Baja</span>
                                                </div>
                                            </div>
                                            <div v-if="puedeEditar" class="vt-acciones-fila">
                                                <a-tooltip title="Editar"><a-button class="vt-ab naranja" @click="abrirCondomino(c)"><EditOutlined /></a-button></a-tooltip>
                                                <a-tooltip :title="c.activo ? 'Dar de baja' : 'Reactivar'">
                                                    <a-button class="vt-ab" :class="c.activo ? 'rojo' : 'verde'" @click="toggleCondomino(c)"><component :is="c.activo ? UserDeleteOutlined : UserAddOutlined" /></a-button>
                                                </a-tooltip>
                                            </div>
                                        </div>

                                        <div class="grid sm:grid-cols-3 gap-x-4 gap-y-2 mt-3">
                                            <div class="vt-dato"><span><PhoneOutlined /> Teléfonos</span><span>{{ [c.telefono, c.telefono_alterno].filter(Boolean).join(' / ') || '—' }}</span></div>
                                            <div class="vt-dato"><span><MailOutlined /> Correo</span><span>{{ c.correo || '—' }}</span></div>
                                            <div class="vt-dato">
                                                <span><HeartOutlined /> Emergencia</span>
                                                <span>
                                                    <template v-if="c.emergencia_nombre">{{ c.emergencia_nombre }}<template v-if="c.emergencia_parentesco"> ({{ c.emergencia_parentesco }})</template><br />{{ c.emergencia_telefono }}</template>
                                                    <template v-else>—</template>
                                                </span>
                                            </div>
                                        </div>
                                        <div v-if="c.notas && !esMiCasa" class="vt-small vt-muted mt-2"><MessageOutlined /> {{ c.notas }}</div>
                                        <div class="flex gap-6 flex-wrap mt-3">
                                            <Registro etiqueta="Registró" :usuario="c.registro?.nombre_usuario" :fecha="c.created_at" />
                                            <Registro etiqueta="Modificó" :usuario="c.modifico?.nombre_usuario" :fecha="c.updated_at" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <EstadoCuentaCasa v-if="verEstadoCuenta" v-model:visible="dialogoEstadoCuenta" :casa-id="casa.id" />
        <VisorArchivo v-model:open="visorCondomino.open" :url="visorCondomino.url" :nombre="visorCondomino.nombre" mime="image/jpeg" />

        <!-- ===== Subir foto de la casa ===== -->
        <a-modal v-model:open="modalFoto" width="560px" centered :confirm-loading="fotoForm.processing" ok-text="Subir foto" cancel-text="Cancelar"
            :ok-button-props="{ disabled: !fotoArchivos.length }" @ok="subirFoto">
            <template #title>
                <div class="vt-modal-title"><span class="vt-head-icon"><CameraOutlined /></span><div>Subir foto<small>Casa {{ casa.identificador }}</small></div></div>
            </template>
            <a-form layout="vertical">
                <div class="vt-form-grid">
                    <a-form-item label="Tipo"><a-select v-model:value="fotoForm.tipo" :options="opciones(tiposFoto)" /></a-form-item>
                    <a-form-item label="Descripción"><a-input v-model:value="fotoForm.descripcion" :maxlength="255" placeholder="Opcional" /></a-form-item>
                </div>
                <a-form-item label="Foto" required :validate-status="fotoForm.errors.foto ? 'error' : ''" :help="fotoForm.errors.foto" style="margin-bottom: 0">
                    <SelectorArchivos v-model:archivos="fotoArchivos" solo-imagenes :max-mb="8" texto="Arrastra la foto o haz clic para elegirla" />
                </a-form-item>
            </a-form>
        </a-modal>

        <!-- ===== Alta / edición de condómino ===== -->
        <a-modal v-model:open="dialogo" width="820px" centered :confirm-loading="cForm.processing" :ok-text="editando ? 'Guardar cambios' : 'Agregar condómino'"
            cancel-text="Cancelar" @ok="guardarCondomino">
            <template #title>
                <div class="vt-modal-title">
                    <span class="vt-head-icon"><component :is="editando ? EditOutlined : UserAddOutlined" /></span>
                    <div>{{ editando ? 'Editar condómino' : 'Agregar condómino' }}<small>Casa {{ casa.identificador }}</small></div>
                </div>
            </template>

            <a-form layout="vertical">
                <a-tabs class="vt-tabs-form">
                    <a-tab-pane key="datos">
                        <template #tab><span class="vt-tab"><UserOutlined /> Datos personales</span></template>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="md:col-span-2">
                                <div class="vt-form-grid">
                                    <a-form-item label="Tipo" required><a-select v-model:value="cForm.tipo" :options="opciones(tiposCondomino)" /></a-form-item>
                                    <a-form-item label="Responsable de la casa" help="Sólo puede haber uno; al marcarlo se quita al anterior.">
                                        <a-switch v-model:checked="cForm.es_responsable" checked-children="Sí" un-checked-children="No" />
                                    </a-form-item>
                                    <a-form-item class="full" label="Nombre(s)" required v-bind="err('nombre')"><a-input v-model:value="cForm.nombre" :maxlength="100" /></a-form-item>
                                    <a-form-item label="Apellido paterno"><a-input v-model:value="cForm.apellido_paterno" :maxlength="60" /></a-form-item>
                                    <a-form-item label="Apellido materno"><a-input v-model:value="cForm.apellido_materno" :maxlength="60" /></a-form-item>
                                    <a-form-item label="Teléfono"><a-input v-model:value="cForm.telefono" :maxlength="20" inputmode="tel"><template #prefix><PhoneOutlined class="vt-muted" /></template></a-input></a-form-item>
                                    <a-form-item label="Teléfono alterno"><a-input v-model:value="cForm.telefono_alterno" :maxlength="20" inputmode="tel"><template #prefix><PhoneOutlined class="vt-muted" /></template></a-input></a-form-item>
                                    <a-form-item class="full" label="Correo" :required="cForm.crear_acceso" v-bind="err('correo')">
                                        <a-input v-model:value="cForm.correo" type="email" :maxlength="100"><template #prefix><MailOutlined class="vt-muted" /></template></a-input>
                                    </a-form-item>
                                </div>
                            </div>
                            <a-form-item label="Foto del condómino" v-bind="cForm.errors.foto_archivo ? err('foto_archivo') : { help: 'Para que vigilancia identifique a los residentes.' }">
                                <img v-if="editando?.foto_url && !fotoCondomino.length" :src="editando.foto_url" class="cd-foto-actual" alt="Foto actual" />
                                <SelectorArchivos v-model:archivos="fotoCondomino" solo-imagenes :max-mb="5" compacto :texto="editando?.foto_url ? 'Cambiar foto' : 'Elegir foto'" />
                            </a-form-item>
                        </div>
                    </a-tab-pane>

                    <a-tab-pane key="emergencia">
                        <template #tab><span class="vt-tab"><HeartOutlined /> Emergencia y notas</span></template>
                        <div class="vt-form-grid vt-g3">
                            <a-form-item label="Contacto de emergencia"><a-input v-model:value="cForm.emergencia_nombre" :maxlength="150" /></a-form-item>
                            <a-form-item label="Teléfono"><a-input v-model:value="cForm.emergencia_telefono" :maxlength="20" inputmode="tel" /></a-form-item>
                            <a-form-item label="Parentesco"><a-input v-model:value="cForm.emergencia_parentesco" :maxlength="50" /></a-form-item>
                        </div>
                        <a-form-item label="Notas"><a-textarea v-model:value="cForm.notas" :rows="3" /></a-form-item>
                    </a-tab-pane>

                    <a-tab-pane key="acceso">
                        <template #tab><span class="vt-tab" :class="{ 'vt-tab-err': cForm.errors.nombre_usuario || cForm.errors.password }"><KeyOutlined /> Acceso al sistema</span></template>
                        <template v-if="editando?.usuario">
                            <div class="vt-alert info mb-3"><InfoCircleOutlined /> Entra con el usuario <b>{{ editando.usuario.nombre_usuario }}</b>. Deja la contraseña vacía para no cambiarla.</div>
                            <div class="vt-form-grid">
                                <a-form-item label="Usuario" v-bind="err('nombre_usuario')"><a-input v-model:value="cForm.nombre_usuario" :maxlength="50" /></a-form-item>
                                <a-form-item label="Nueva contraseña" v-bind="err('password')"><a-input-password v-model:value="cForm.password" autocomplete="new-password" /></a-form-item>
                            </div>
                        </template>
                        <template v-else>
                            <a-checkbox v-model:checked="cForm.crear_acceso" class="mb-3">Darle acceso para consultar su casa, sus cuotas y la transparencia del condominio</a-checkbox>
                            <div v-if="cForm.crear_acceso" class="vt-form-grid">
                                <a-form-item label="Usuario" required v-bind="err('nombre_usuario')"><a-input v-model:value="cForm.nombre_usuario" :maxlength="50" /></a-form-item>
                                <a-form-item label="Contraseña inicial" required v-bind="err('password')"><a-input-password v-model:value="cForm.password" autocomplete="new-password" /></a-form-item>
                            </div>
                        </template>
                    </a-tab-pane>
                </a-tabs>
            </a-form>
        </a-modal>
    </AppLayout>
</template>

<style>
.cd-item { border: 1px solid var(--vt-line); border-radius: 14px; padding: 14px 16px; background: #fff; transition: box-shadow 0.2s; }
.cd-item:hover { box-shadow: 0 6px 18px rgba(15, 42, 71, 0.08); }
.cd-item.inactivo { opacity: 0.6; background: #f8fafc; }
.cd-item .vt-dato > span:first-child .anticon { color: var(--vt-teal); }
.cd-foto-actual { width: 100%; height: 150px; object-fit: cover; border-radius: 12px; margin-bottom: 8px; border: 1px solid var(--vt-line); }
</style>
