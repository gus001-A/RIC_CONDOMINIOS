<script setup>
import { ref, reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { FileTextOutlined, ArrowLeftOutlined, EditOutlined, FilePdfOutlined, WarningOutlined, SaveOutlined, EyeOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import VisorArchivo from '@/Components/VisorArchivo.vue';

const props = defineProps({
    documento: Object,
    html: String,
    puedeEditar: Boolean,
    faltantes: Number,
});

const editando = ref(false);
const form = useForm({ titulo: props.documento.titulo, contenido: props.documento.contenido });
const editar = () => {
    form.defaults({ titulo: props.documento.titulo, contenido: props.documento.contenido });
    form.reset();
    editando.value = true;
};
const guardar = () => form.post(route('documentos.update', props.documento.id), { preserveScroll: true, onSuccess: () => (editando.value = false) });

// PDF en el visor (modal), igual que los demás archivos.
const visor = reactive({ open: false });
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader :title="documento.titulo" :icon="FileTextOutlined">
                <template #subtitulo>
                    Casa {{ documento.casa?.identificador }}
                    <template v-if="documento.solicitud"> · Solicitud {{ documento.solicitud.folio }}</template>
                    <template v-if="documento.plantilla"> · Plantilla: {{ documento.plantilla.nombre }}</template>
                </template>
                <template #extra>
                    <Link v-if="documento.solicitud" :href="route('servicios.show', documento.id_solicitud)"><a-button size="large"><ArrowLeftOutlined /> Solicitud</a-button></Link>
                    <Link v-else-if="puedeEditar" :href="route('documentos.plantillas')"><a-button size="large"><ArrowLeftOutlined /> Documentos</a-button></Link>
                    <a-button v-if="puedeEditar && !editando" size="large" @click="editar"><EditOutlined /> Editar texto</a-button>
                    <a-button type="primary" size="large" @click="visor.open = true"><FilePdfOutlined /> Ver PDF</a-button>
                </template>
            </PageHeader>

            <div v-if="faltantes && !editando" class="vt-alert">
                <WarningOutlined />
                <span>Hay {{ faltantes }} dato(s) sin llenar (resaltados en amarillo).
                    <template v-if="puedeEditar">Corrígelos con «Editar texto» o completa los datos de la casa y genera de nuevo.</template></span>
            </div>

            <div v-if="editando" class="vt-card">
                <a-form layout="vertical">
                    <a-form-item label="Título"><a-input v-model:value="form.titulo" :maxlength="200" /></a-form-item>
                    <a-form-item label="Texto" :validate-status="form.errors.contenido ? 'error' : ''" :help="form.errors.contenido">
                        <textarea v-model="form.contenido" name="contenido" class="editor-texto"></textarea>
                    </a-form-item>
                    <div class="flex justify-end gap-2">
                        <a-button size="large" @click="editando = false">Cancelar</a-button>
                        <a-button type="primary" size="large" :loading="form.processing" @click="guardar"><SaveOutlined /> Guardar</a-button>
                    </div>
                </a-form>
            </div>

            <div v-else class="hoja" v-html="html"></div>

            <div class="flex justify-center gap-6 flex-wrap mt-4">
                <Registro etiqueta="Generó" :usuario="documento.registro?.nombre_usuario" :fecha="documento.created_at" en-linea />
                <Registro v-if="documento.updated_at !== documento.created_at" etiqueta="Modificó" :usuario="documento.modifico?.nombre_usuario" :fecha="documento.updated_at" en-linea />
            </div>
        </div>
        <VisorArchivo v-model:open="visor.open" :url="route('documentos.pdf', documento.id)" :nombre="documento.titulo + '.pdf'" mime="application/pdf" />
    </AppLayout>
</template>

<style scoped>
.hoja {
    max-width: 820px; margin: 0 auto; background: #fff; padding: 56px 64px; border-radius: 6px;
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08); border: 1px solid #e5e7eb;
    font-family: Georgia, 'Times New Roman', serif; font-size: 14px; line-height: 1.65; color: #111827;
}
@media (max-width: 640px) { .hoja { padding: 24px 18px; } }
.hoja :deep(h1) { font-size: 17px; font-weight: 700; text-align: center; text-transform: uppercase; margin-bottom: 16px; letter-spacing: 0.4px; }
.hoja :deep(h2) { font-size: 14px; font-weight: 700; margin: 18px 0 6px; text-transform: uppercase; }
.hoja :deep(p) { margin-bottom: 10px; text-align: justify; }
.hoja :deep(mark) { background: #fef08a; padding: 0 2px; }
.editor-texto { width: 100%; height: 60vh; border: 1px solid #d5dee8; border-radius: 12px; padding: 12px; font-family: ui-monospace, Consolas, monospace; font-size: 12.5px; line-height: 1.55; text-transform: none; }
.aviso-viejo { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: #fefce8; border: 1px solid #fde047; color: #854d0e; border-radius: 10px; padding: 10px 14px; font-size: 13px; }
</style>
