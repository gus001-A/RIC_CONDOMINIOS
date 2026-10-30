<script setup>
import { computed, onMounted } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import {
    ApartmentOutlined, EditOutlined, ArrowLeftOutlined, SaveOutlined, IdcardOutlined, EnvironmentOutlined, PhoneOutlined,
    UserOutlined, SearchOutlined, InfoCircleOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import { useDireccionMx } from '@/composables/useDireccionMx';

const props = defineProps({
    empresa: { type: Object, default: null },
});

const esEdicion = computed(() => !!props.empresa);
const e = props.empresa || {};

const form = useForm({
    nombre_empresa: e.nombre_empresa ?? '',
    clave: e.clave ?? '',
    rfc: e.rfc ?? '',
    tipo_persona: e.tipo_persona ?? 'MORAL',
    regimen: e.regimen ?? '',
    representante_nombre: e.representante_nombre ?? '',
    representante_apellido_paterno: e.representante_apellido_paterno ?? '',
    representante_apellido_materno: e.representante_apellido_materno ?? '',
    representante_rfc: e.representante_rfc ?? '',
    representante_curp: e.representante_curp ?? '',
    codigo_postal: e.codigo_postal ?? '',
    estado: e.estado ?? null,
    municipio: e.municipio ?? null,
    ciudad: e.ciudad ?? '',
    colonia: e.colonia ?? '',
    calle: e.calle ?? '',
    numero_exterior: e.numero_exterior ?? '',
    numero_interior: e.numero_interior ?? '',
    correo: e.correo ?? '',
    telefono_trabajo: e.telefono_trabajo ?? '',
    extension: e.extension ?? '',
    telefono_personal: e.telefono_personal ?? '',
});
const esMoral = computed(() => form.tipo_persona === 'MORAL');

// RFC y CURP siempre en mayúsculas y sin espacios.
const claveFiscal = (campo) => { form[campo] = String(form[campo] || '').toUpperCase().replace(/\s+/g, ''); };
const soloDigitos = (campo, max) => { form[campo] = String(form[campo] || '').replace(/\D/g, '').slice(0, max); };

// ===== Dirección: catálogo de estados/municipios y búsqueda por código postal =====
const dir = useDireccionMx();
onMounted(async () => {
    await dir.cargarEstados();
    if (form.estado) dir.cargarMunicipios(form.estado);
    if (form.codigo_postal?.length === 5) dir.buscarCP(form.codigo_postal);
});
const onEstado = (estado) => {
    form.municipio = null;
    dir.cargarMunicipios(estado);
};
const onCP = async () => {
    soloDigitos('codigo_postal', 5);
    if (form.codigo_postal.length !== 5) return;
    const datos = await dir.buscarCP(form.codigo_postal);
    if (!datos) return;
    form.estado = datos.estado;
    await dir.cargarMunicipios(datos.estado);
    form.municipio = datos.municipio;
    form.ciudad = datos.municipio || datos.ciudad || '';
    if (dir.colonias.value.length === 1) form.colonia = dir.colonias.value[0].nombre;
};
const opcionesColonia = computed(() => dir.colonias.value.map((c) => ({ value: c.nombre, label: c.tipo ? `${c.nombre} · ${c.tipo}` : c.nombre })));

const err = (campo) => ({ 'validate-status': form.errors[campo] ? 'error' : '', help: form.errors[campo] });

const guardar = () => {
    form.transform((d) => ({ ...d, ciudad: d.municipio || d.ciudad || '' }));
    if (esEdicion.value) form.put(route('empresas.update', props.empresa.id), { preserveScroll: true });
    else form.post(route('empresas.store'), { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader :title="esEdicion ? `Editar ${empresa.nombre_empresa}` : 'Nuevo condominio'"
                subtitle="Datos generales, dirección y contacto del condominio" :icon="esEdicion ? EditOutlined : ApartmentOutlined">
                <template #extra>
                    <Link :href="route('empresas.index')"><a-button size="large"><ArrowLeftOutlined /> Regresar</a-button></Link>
                </template>
            </PageHeader>

            <div v-if="!esEdicion" class="vt-alert info mb-3">
                <InfoCircleOutlined />
                <span>Al registrarlo se crea su catálogo de cuentas contables y, si no eres superusuario, quedas asignado a él para poder operarlo.</span>
            </div>

            <a-form layout="vertical" :model="form" @finish="guardar">
                <!-- Dos columnas parejas: cada fila junta dos tarjetas de la misma altura -->
                <div class="em-grid">
                    <div class="vt-card">
                        <div class="vt-seccion-t"><IdcardOutlined /> Identificación</div>
                        <div class="vt-form-grid">
                            <a-form-item class="full" label="Nombre del condominio" required v-bind="err('nombre_empresa')">
                                <a-input v-model:value="form.nombre_empresa" :maxlength="150" placeholder="Ej. Condominio Las Flores" />
                            </a-form-item>
                            <a-form-item label="RFC" required v-bind="err('rfc')">
                                <a-input v-model:value="form.rfc" :maxlength="13" placeholder="12 o 13 caracteres" @blur="claveFiscal('rfc')" />
                            </a-form-item>
                            <a-form-item label="Clave interna" v-bind="err('clave')">
                                <a-input v-model:value="form.clave" :maxlength="20" placeholder="Ej. FLORES" />
                            </a-form-item>
                            <a-form-item label="Tipo de persona" required>
                                <a-segmented v-model:value="form.tipo_persona" block :options="[{ value: 'MORAL', label: 'Persona moral' }, { value: 'FISICA', label: 'Persona física' }]" />
                            </a-form-item>
                            <a-form-item label="Régimen fiscal" v-bind="err('regimen')">
                                <a-input v-model:value="form.regimen" :maxlength="50" placeholder="Ej. Sin fines de lucro" />
                            </a-form-item>
                        </div>
                    </div>

                    <div class="vt-card">
                        <div class="vt-seccion-t"><EnvironmentOutlined /> Dirección</div>
                        <div class="vt-form-grid">
                            <a-form-item label="Código postal" v-bind="err('codigo_postal')" :help="form.errors.codigo_postal || dir.cpMensaje.value || 'Llena estado, municipio y colonias.'">
                                <a-input v-model:value="form.codigo_postal" :maxlength="5" placeholder="62000" @input="onCP">
                                    <template #suffix><a-spin v-if="dir.cargandoCP.value" size="small" /><SearchOutlined v-else class="vt-muted" /></template>
                                </a-input>
                            </a-form-item>
                            <a-form-item label="Estado" v-bind="err('estado')">
                                <a-select v-model:value="form.estado" show-search allow-clear placeholder="Selecciona…" :options="dir.estados.value.map((x) => ({ value: x, label: x }))" @change="onEstado" />
                            </a-form-item>
                            <a-form-item label="Municipio" v-bind="err('municipio')">
                                <a-select v-model:value="form.municipio" show-search allow-clear :loading="dir.cargandoMunicipios.value" :disabled="!form.estado"
                                    :placeholder="form.estado ? 'Selecciona…' : 'Primero el estado'" :options="dir.municipios.value.map((x) => ({ value: x, label: x }))" />
                            </a-form-item>
                            <a-form-item label="Colonia" v-bind="err('colonia')">
                                <a-auto-complete v-model:value="form.colonia" :options="opcionesColonia" :maxlength="100" placeholder="Escribe o elige del CP"
                                    :filter-option="(input, o) => o.value.toLowerCase().includes(input.toLowerCase())" />
                            </a-form-item>
                            <a-form-item class="full" label="Calle" v-bind="err('calle')">
                                <a-input v-model:value="form.calle" :maxlength="100" placeholder="Ej. Av. de las Flores" />
                            </a-form-item>
                            <a-form-item label="No. exterior" v-bind="err('numero_exterior')">
                                <a-input v-model:value="form.numero_exterior" :maxlength="20" />
                            </a-form-item>
                            <a-form-item label="No. interior" v-bind="err('numero_interior')">
                                <a-input v-model:value="form.numero_interior" :maxlength="20" />
                            </a-form-item>
                        </div>
                    </div>

                    <div v-if="esMoral" class="vt-card">
                        <div class="vt-seccion-t"><UserOutlined /> Representante legal</div>
                        <div class="vt-form-grid">
                            <a-form-item class="full" label="Nombre(s)" required v-bind="err('representante_nombre')">
                                <a-input v-model:value="form.representante_nombre" :maxlength="100" />
                            </a-form-item>
                            <a-form-item label="Apellido paterno" required v-bind="err('representante_apellido_paterno')">
                                <a-input v-model:value="form.representante_apellido_paterno" :maxlength="50" />
                            </a-form-item>
                            <a-form-item label="Apellido materno" v-bind="err('representante_apellido_materno')">
                                <a-input v-model:value="form.representante_apellido_materno" :maxlength="50" />
                            </a-form-item>
                            <a-form-item label="RFC del representante" required v-bind="err('representante_rfc')">
                                <a-input v-model:value="form.representante_rfc" :maxlength="13" @blur="claveFiscal('representante_rfc')" />
                            </a-form-item>
                            <a-form-item label="CURP" v-bind="err('representante_curp')">
                                <a-input v-model:value="form.representante_curp" :maxlength="18" @blur="claveFiscal('representante_curp')" />
                            </a-form-item>
                        </div>
                    </div>

                    <div class="vt-card" :class="{ 'em-ancho': !esMoral && !esEdicion }">
                        <div class="vt-seccion-t"><PhoneOutlined /> Contacto</div>
                        <div class="vt-form-grid">
                            <a-form-item class="full" label="Correo electrónico" v-bind="err('correo')">
                                <a-input v-model:value="form.correo" type="email" :maxlength="100" placeholder="administracion@condominio.mx" />
                            </a-form-item>
                            <a-form-item label="Teléfono de oficina" v-bind="err('telefono_trabajo')">
                                <a-input v-model:value="form.telefono_trabajo" :maxlength="10" placeholder="10 dígitos" @input="soloDigitos('telefono_trabajo', 10)" />
                            </a-form-item>
                            <a-form-item label="Extensión" v-bind="err('extension')">
                                <a-input v-model:value="form.extension" :maxlength="10" @input="soloDigitos('extension', 10)" />
                            </a-form-item>
                            <a-form-item class="full" label="Celular" v-bind="err('telefono_personal')">
                                <a-input v-model:value="form.telefono_personal" :maxlength="10" placeholder="10 dígitos" @input="soloDigitos('telefono_personal', 10)" />
                            </a-form-item>
                        </div>
                    </div>

                    <!-- Persona física al editar: el resumen ocupa el lugar del representante -->
                    <div v-if="!esMoral && esEdicion" class="vt-card">
                        <div class="vt-seccion-t"><ApartmentOutlined /> Resumen</div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="vt-dato"><span>Casas</span><span>{{ empresa.casas_count ?? '—' }}</span></div>
                            <div class="vt-dato"><span>Usuarios</span><span>{{ empresa.usuarios_count ?? '—' }}</span></div>
                            <div class="vt-dato"><span>Estado</span><span>{{ empresa.activo ? 'Activo' : 'Inactivo' }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="vt-card mt-4 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex gap-6 flex-wrap">
                        <template v-if="esEdicion">
                            <div v-if="esMoral" class="vt-dato"><span>Casas · usuarios</span><span>{{ empresa.casas_count ?? 0 }} · {{ empresa.usuarios_count ?? 0 }}</span></div>
                            <Registro etiqueta="Registro" :fecha="empresa.created_at" />
                            <Registro etiqueta="Última modificación" :fecha="empresa.updated_at" />
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('empresas.index')"><a-button size="large">Cancelar</a-button></Link>
                        <a-button type="primary" size="large" html-type="submit" :loading="form.processing">
                            <SaveOutlined /> {{ esEdicion ? 'Guardar cambios' : 'Registrar condominio' }}
                        </a-button>
                    </div>
                </div>
            </a-form>
        </div>
    </AppLayout>
</template>

<style scoped>
.em-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: stretch; }
.em-ancho { grid-column: 1 / -1; }
@media (max-width: 1023px) {
    .em-grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
