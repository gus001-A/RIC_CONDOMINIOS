<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { FileAddOutlined, InfoCircleOutlined, FormOutlined, ExclamationCircleOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    visible: Boolean,
    plantillas: Array,
    idCasa: { type: Number, default: null },        // fija (desde una solicitud)
    idEmpresa: { type: Number, default: null },
    idSolicitud: { type: Number, default: null },
    casas: { type: Array, default: () => [] },       // para elegir (desde Documentos)
});
const emit = defineEmits(['update:visible']);

const casa = ref(null);
const plantilla = ref(null);
const titulo = ref('');
const campos = ref([]);
const valores = reactive({});
const preparando = ref(false);
const enviando = ref(false);
const error = ref('');

const casaFinal = computed(() => props.idCasa || casa.value);
const empresaFinal = computed(() => props.idEmpresa || props.casas.find((c) => c.id === casa.value)?.id_empresa);
// Plantillas generales + las del condominio de la casa.
const plantillasDisponibles = computed(() => props.plantillas.filter((p) => p.activo !== false && (!p.id_empresa || p.id_empresa === empresaFinal.value)));

const preparar = async () => {
    campos.value = [];
    error.value = '';
    if (!plantilla.value || !casaFinal.value) return;
    preparando.value = true;
    try {
        const { data } = await axios.get(route('documentos.preparar', plantilla.value), { params: { id_casa: casaFinal.value } });
        campos.value = data.campos;
        for (const c of data.campos) if (!(c in valores)) valores[c] = '';
    } catch (e) {
        error.value = e?.response?.data?.message || 'No se pudo leer la plantilla.';
    } finally {
        preparando.value = false;
    }
};
watch([casa, plantilla], preparar);

const generar = async () => {
    enviando.value = true;
    error.value = '';
    try {
        const campoValores = Object.fromEntries(campos.value.map((c) => [c, valores[c] || '']));
        const { data } = await axios.post(route('documentos.generar', plantilla.value), {
            id_casa: casaFinal.value,
            id_solicitud: props.idSolicitud,
            titulo: titulo.value,
            campos: campoValores,
        });
        router.visit(data.url);
    } catch (e) {
        error.value = Object.values(e?.response?.data?.errors || {})[0]?.[0] || e?.response?.data?.message || 'No se pudo generar.';
    } finally {
        enviando.value = false;
    }
};

watch(() => props.visible, (v) => {
    if (v) {
        plantilla.value = null;
        titulo.value = '';
        campos.value = [];
        error.value = '';
        if (!props.idCasa) casa.value = null;
    }
});
</script>

<template>
    <a-modal :open="visible" width="680px" centered :confirm-loading="enviando" ok-text="Generar" cancel-text="Cancelar"
        :ok-button-props="{ disabled: !plantilla || !casaFinal }" @ok="generar" @update:open="emit('update:visible', $event)">
        <template #title>
            <div class="vt-modal-title"><span class="vt-head-icon"><FileAddOutlined /></span><div>Generar documento<small>Se llena con los datos del condominio, la casa y su propietario</small></div></div>
        </template>
        <a-form layout="vertical">
            <a-form-item v-if="!idCasa" label="Casa" required>
                <a-select v-model:value="casa" show-search option-filter-prop="label" placeholder="Selecciona la casa"
                    :options="casas.map((c) => ({ value: c.id, label: c.identificador }))" />
            </a-form-item>
            <a-form-item label="Plantilla" required>
                <a-select v-model:value="plantilla" show-search option-filter-prop="label" placeholder="Selecciona…"
                    :options="plantillasDisponibles.map((p) => ({ value: p.id, label: p.nombre + (p.id_empresa ? '' : ' (general)') }))" />
            </a-form-item>
            <a-form-item label="Título del documento"><a-input v-model:value="titulo" :maxlength="200" placeholder="Por defecto: plantilla · casa" /></a-form-item>

            <a-spin :spinning="preparando">
                <div v-if="campos.length" class="vt-seccion">
                    <div class="vt-seccion-t"><FormOutlined /> Datos que pide la plantilla</div>
                    <div class="vt-form-grid">
                        <a-form-item v-for="c in campos" :key="c" :label="c"><a-input v-model:value="valores[c]" :maxlength="1000" /></a-form-item>
                    </div>
                </div>
                <div v-else-if="plantilla && !preparando" class="vt-alert info mb-0"><InfoCircleOutlined /> Esta plantilla se llena sola con los datos de la casa. Podrás editar el resultado.</div>
            </a-spin>
            <div v-if="error" class="vt-alert error mt-3 mb-0"><ExclamationCircleOutlined /> <span>{{ error }}</span></div>
        </a-form>
    </a-modal>
</template>
