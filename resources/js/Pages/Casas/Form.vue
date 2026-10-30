<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { HomeOutlined, EditOutlined, ArrowLeftOutlined, SaveOutlined, IdcardOutlined, CompassOutlined, EnvironmentOutlined, WarningOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import MapaGps from '@/Components/MapaGps.vue';
import Registro from '@/Components/Registro.vue';

const props = defineProps({
    casa: Object,
    condominios: Array,
    centros: Object,
    tiposPropiedad: Object,
    estatusOcupacion: Object,
});

const esEdicion = computed(() => !!props.casa);
const c = props.casa || {};
const regresar = computed(() => (esEdicion.value ? route('casas.show', props.casa.id) : route('casas.index')));

const form = useForm({
    id_empresa: c.id_empresa ?? (props.condominios.length === 1 ? props.condominios[0].id : null),
    identificador: c.identificador ?? '',
    tipo_propiedad: c.tipo_propiedad ?? 'CASA',
    manzana: c.manzana ?? '',
    lote: c.lote ?? '',
    calle: c.calle ?? '',
    numero_exterior: c.numero_exterior ?? '',
    numero_interior: c.numero_interior ?? '',
    referencias: c.referencias ?? '',
    latitud: c.latitud !== undefined && c.latitud !== null ? Number(c.latitud) : null,
    longitud: c.longitud !== undefined && c.longitud !== null ? Number(c.longitud) : null,
    estatus_ocupacion: c.estatus_ocupacion ?? 'HABITADA',
    cuota_mantenimiento: c.cuota_mantenimiento !== undefined && c.cuota_mantenimiento !== null && c.cuota_mantenimiento !== '' ? Number(c.cuota_mantenimiento) : null,
    notas: c.notas ?? '',
});

const opciones = (obj) => Object.entries(obj).map(([value, label]) => ({ value, label }));

const guardar = () => {
    if (esEdicion.value) form.put(route('casas.update', props.casa.id), { preserveScroll: true });
    else form.post(route('casas.store'), { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader :title="esEdicion ? `Editar casa ${casa.identificador}` : 'Nueva casa'"
                subtitle="Identificación, dirección dentro del condominio y ubicación GPS" :icon="esEdicion ? EditOutlined : HomeOutlined">
                <template #extra>
                    <Link :href="regresar"><a-button size="large"><ArrowLeftOutlined /> Regresar</a-button></Link>
                </template>
            </PageHeader>

            <div v-if="!condominios.length" class="vt-alert mb-3"><WarningOutlined /> No tienes condominios asignados. Pide a un superusuario que te asigne uno en Usuarios.</div>

            <a-form layout="vertical" :model="form" @finish="guardar">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="vt-card">
                        <div class="vt-seccion">
                            <div class="vt-seccion-t"><IdcardOutlined /> Identificación</div>
                            <div class="vt-form-grid">
                                <a-form-item class="full" label="Condominio" required :validate-status="form.errors.id_empresa ? 'error' : ''" :help="form.errors.id_empresa">
                                    <a-select v-model:value="form.id_empresa" placeholder="Selecciona…" show-search option-filter-prop="label"
                                        :options="condominios.map((x) => ({ value: x.id, label: x.nombre_empresa }))" />
                                </a-form-item>
                                <a-form-item label="Identificador" required :validate-status="form.errors.identificador ? 'error' : ''"
                                    :help="form.errors.identificador || 'Clave única de la casa dentro del condominio.'">
                                    <a-input v-model:value="form.identificador" :maxlength="30" placeholder="Ej. M3-L12 o Casa 45" />
                                </a-form-item>
                                <a-form-item label="Tipo de propiedad" required>
                                    <a-select v-model:value="form.tipo_propiedad" :options="opciones(tiposPropiedad)" />
                                </a-form-item>
                                <a-form-item label="Ocupación" required>
                                    <a-select v-model:value="form.estatus_ocupacion" :options="opciones(estatusOcupacion)" />
                                </a-form-item>
                                <a-form-item label="Cuota de mantenimiento" :validate-status="form.errors.cuota_mantenimiento ? 'error' : ''"
                                    :help="form.errors.cuota_mantenimiento || 'Mensual. Sirve para el control de deudores.'">
                                    <a-input-number v-model:value="form.cuota_mantenimiento" :min="0" :step="50" :precision="2" prefix="$" style="width: 100%" placeholder="0.00" />
                                </a-form-item>
                            </div>
                        </div>

                        <div class="vt-seccion">
                            <div class="vt-seccion-t"><CompassOutlined /> Dirección dentro del condominio</div>
                            <div class="vt-form-grid" style="grid-template-columns: repeat(4, minmax(0, 1fr))">
                                <a-form-item label="Manzana"><a-input v-model:value="form.manzana" :maxlength="20" /></a-form-item>
                                <a-form-item label="Lote"><a-input v-model:value="form.lote" :maxlength="20" /></a-form-item>
                                <a-form-item label="No. exterior"><a-input v-model:value="form.numero_exterior" :maxlength="20" /></a-form-item>
                                <a-form-item label="No. interior"><a-input v-model:value="form.numero_interior" :maxlength="20" /></a-form-item>
                            </div>
                            <a-form-item label="Calle / privada / andador"><a-input v-model:value="form.calle" :maxlength="100" /></a-form-item>
                            <a-form-item label="Referencias"><a-textarea v-model:value="form.referencias" :rows="2" placeholder="Color de la fachada, portón, frente al parque…" /></a-form-item>
                            <a-form-item label="Notas internas" style="margin-bottom: 0"><a-textarea v-model:value="form.notas" :rows="2" /></a-form-item>
                        </div>
                    </div>

                    <div class="vt-card">
                        <div class="vt-seccion">
                            <div class="vt-seccion-t"><EnvironmentOutlined /> Ubicación GPS</div>
                            <!-- Mientras no haya GPS, el mapa se recentra al cambiar de condominio. -->
                            <MapaGps :key="form.latitud === null ? `c${form.id_empresa}` : 'fijo'" v-model:lat="form.latitud" v-model:lng="form.longitud"
                                :centro="centros?.[form.id_empresa] || null" editable height="430px" />
                            <div class="vt-form-grid mt-3">
                                <a-form-item label="Latitud" :validate-status="form.errors.latitud ? 'error' : ''" :help="form.errors.latitud">
                                    <a-input-number v-model:value="form.latitud" :step="0.000001" :precision="7" style="width: 100%" />
                                </a-form-item>
                                <a-form-item label="Longitud" :validate-status="form.errors.longitud ? 'error' : ''" :help="form.errors.longitud">
                                    <a-input-number v-model:value="form.longitud" :step="0.000001" :precision="7" style="width: 100%" />
                                </a-form-item>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="vt-card mt-4 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex gap-6 flex-wrap">
                        <template v-if="esEdicion">
                            <Registro etiqueta="Registró" :usuario="casa.registro?.nombre_usuario" :fecha="casa.created_at" />
                            <Registro etiqueta="Modificó" :usuario="casa.modifico?.nombre_usuario" :fecha="casa.updated_at" />
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="regresar"><a-button size="large">Cancelar</a-button></Link>
                        <a-button type="primary" size="large" html-type="submit" :loading="form.processing" :disabled="!condominios.length">
                            <SaveOutlined /> {{ esEdicion ? 'Guardar cambios' : 'Registrar casa' }}
                        </a-button>
                    </div>
                </div>
            </a-form>
        </div>
    </AppLayout>
</template>
