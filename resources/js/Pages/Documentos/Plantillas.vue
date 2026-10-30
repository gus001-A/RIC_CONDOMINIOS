<script setup>
import { ref, computed, nextTick } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    FileTextOutlined, PlusOutlined, FileAddOutlined, CopyOutlined, FolderOpenOutlined, FileOutlined, FilePdfOutlined, EditOutlined,
    EyeOutlined, RightOutlined, SaveOutlined, HomeOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Registro from '@/Components/Registro.vue';
import GenerarDocumento from '@/Components/GenerarDocumento.vue';

const props = defineProps({
    plantillas: Array,
    condominios: Array,
    casas: Array,
    tipos: Object,
    variables: Object,
    documentos: Array,
    esSuperusuario: Boolean,
});

const puedeEditar = (p) => p.id_empresa !== null || props.esSuperusuario;

// ===== Editor =====
const dialogoEditor = ref(false);
const editando = ref(null);
const soloLectura = computed(() => editando.value && !puedeEditar(editando.value));
const areaTexto = ref(null);
const panel = ref('variables');
const vista = ref('');

const form = useForm({ nombre: '', tipo: 'OTRO', descripcion: '', contenido: '', id_empresa: null, activo: true });

const abrirEditor = (p = null) => {
    editando.value = p;
    form.clearErrors();
    form.defaults({
        nombre: p?.nombre ?? '',
        tipo: p?.tipo ?? 'CONTRATO_ARRENDAMIENTO',
        descripcion: p?.descripcion ?? '',
        contenido: p?.contenido ?? '',
        id_empresa: p ? p.id_empresa : (props.condominios[0]?.id ?? null),
        activo: p?.activo ?? true,
    });
    form.reset();
    panel.value = 'variables';
    dialogoEditor.value = true;
};

// "{{clave}}" armado en JS: escrito literal en el template rompe la interpolación de Vue.
const llaves = (clave) => '{' + '{' + clave + '}' + '}';

const insertar = async (texto) => {
    const el = areaTexto.value;
    const ini = el?.selectionStart ?? form.contenido.length;
    const fin = el?.selectionEnd ?? ini;
    form.contenido = form.contenido.slice(0, ini) + texto + form.contenido.slice(fin);
    await nextTick();
    el?.focus();
    el?.setSelectionRange(ini + texto.length, ini + texto.length);
};

// Dato libre: se pide su nombre en un modal pequeño (en lugar de window.prompt).
const modalCampo = ref(false);
const nombreCampo = ref('');
const insertarCampo = () => { nombreCampo.value = ''; modalCampo.value = true; };
const confirmarCampo = () => {
    if (nombreCampo.value.trim()) insertar(llaves(`campo:${nombreCampo.value.trim()}`));
    modalCampo.value = false;
};

const verVista = async () => {
    const { data } = await axios.post(route('documentos.previsualizar'), { contenido: form.contenido });
    vista.value = data.html;
};

const guardar = () => {
    const url = editando.value ? route('documentos.plantillas.update', editando.value.id) : route('documentos.plantillas.store');
    form.post(url, { preserveScroll: true, onSuccess: () => { dialogoEditor.value = false; } });
};

// ===== Generar =====
const dialogoGenerar = ref(false);
</script>

<template>
    <AppLayout>
        <div class="vt-page vt-wrap">
            <PageHeader title="Contratos y documentos" subtitle="Plantillas que se llenan solas con los datos del condominio, la casa y su propietario" :icon="FileTextOutlined">
                <template #extra>
                    <a-button size="large" @click="abrirEditor()"><PlusOutlined /> Nueva plantilla</a-button>
                    <a-button type="primary" size="large" :disabled="!plantillas.length" @click="dialogoGenerar = true"><FileAddOutlined /> Generar documento</a-button>
                </template>
            </PageHeader>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="vt-card">
                    <div class="vt-card-title"><h3><CopyOutlined /> Plantillas <span class="vt-tag" style="background: #eef2f7; color: #44566c">{{ plantillas.length }}</span></h3></div>
                    <div v-if="!plantillas.length" class="vt-empty">No hay plantillas.</div>
                    <div class="flex flex-col gap-2">
                        <div v-for="p in plantillas" :key="p.id" class="fila" :class="{ inactiva: !p.activo }">
                            <span class="fila-ic"><FileOutlined /></span>
                            <div class="flex-1 min-w-0">
                                <b class="block truncate" style="color: var(--vt-navy)">{{ p.nombre }}</b>
                                <div class="vt-small vt-muted">
                                    {{ tipos[p.tipo] }} · {{ p.condominio?.nombre_empresa || 'General (todos los condominios)' }}
                                    <span v-if="!p.activo" class="vt-tag solido ml-1" style="background: #94a3b8">Inactiva</span>
                                </div>
                                <Registro etiqueta="Modificó" :usuario="p.modifico?.nombre_usuario || p.registro?.nombre_usuario" :fecha="p.updated_at" en-linea />
                            </div>
                            <a-tooltip :title="puedeEditar(p) ? 'Editar' : 'Ver (sólo el superusuario edita las generales)'">
                                <a-button class="vt-ab" :class="puedeEditar(p) ? 'naranja' : 'azul'" @click="abrirEditor(p)"><component :is="puedeEditar(p) ? EditOutlined : EyeOutlined" /></a-button>
                            </a-tooltip>
                        </div>
                    </div>
                </div>

                <div class="vt-card">
                    <div class="vt-card-title"><h3><FolderOpenOutlined /> Documentos generados</h3></div>
                    <div v-if="!documentos.length" class="vt-empty">Aún no se ha generado ningún documento.</div>
                    <div class="flex flex-col gap-2">
                        <Link v-for="d in documentos" :key="d.id" :href="route('documentos.show', d.id)" class="fila">
                            <span class="fila-ic" style="color: #d9363e; background: #fdecec"><FilePdfOutlined /></span>
                            <div class="flex-1 min-w-0">
                                <b class="block truncate" style="color: var(--vt-navy)">{{ d.titulo }}</b>
                                <div class="vt-small vt-muted"><HomeOutlined /> Casa <b>{{ d.casa?.identificador }}</b><template v-if="d.solicitud"> · {{ d.solicitud.folio }}</template></div>
                                <Registro :usuario="d.registro?.nombre_usuario" :fecha="d.created_at" en-linea />
                            </div>
                            <RightOutlined class="vt-muted" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= Editor de plantilla ================= -->
        <a-modal v-model:open="dialogoEditor" width="1180px" centered :footer="null">
            <template #title>
                <div class="vt-modal-title">
                    <span class="vt-head-icon"><component :is="editando ? EditOutlined : PlusOutlined" /></span>
                    <div>{{ editando ? (soloLectura ? 'Plantilla (sólo lectura)' : 'Editar plantilla') : 'Nueva plantilla' }}<small v-if="editando">{{ editando.nombre }}</small></div>
                </div>
            </template>
            <a-form layout="vertical">
                <div class="vt-form-grid vt-g3">
                    <a-form-item label="Nombre" required :validate-status="form.errors.nombre ? 'error' : ''" :help="form.errors.nombre">
                        <a-input v-model:value="form.nombre" :maxlength="150" :disabled="soloLectura" />
                    </a-form-item>
                    <a-form-item label="Tipo">
                        <a-select v-model:value="form.tipo" :disabled="soloLectura" :options="Object.entries(tipos).map(([value, label]) => ({ value, label }))" />
                    </a-form-item>
                    <a-form-item label="Condominio" :validate-status="form.errors.id_empresa ? 'error' : ''" :help="form.errors.id_empresa">
                        <a-select v-model:value="form.id_empresa" :disabled="soloLectura"
                            :options="[...(esSuperusuario ? [{ value: null, label: 'General (todos)' }] : []), ...condominios.map((c) => ({ value: c.id, label: c.nombre_empresa }))]" />
                    </a-form-item>
                </div>

                <div class="editor">
                    <div class="editor-col">
                        <div class="flex items-center justify-between mb-1 flex-wrap gap-1">
                            <span class="vt-flabel">Texto</span>
                            <span class="vt-small vt-muted"># Título · ## Subtítulo · **negritas** · línea vacía = nuevo párrafo</span>
                        </div>
                        <textarea ref="areaTexto" v-model="form.contenido" name="contenido" class="editor-texto" :readonly="soloLectura" spellcheck="true"></textarea>
                        <span v-if="form.errors.contenido" class="vt-small" style="color: #d9363e">{{ form.errors.contenido }}</span>
                    </div>
                    <div class="editor-col">
                        <a-tabs v-model:active-key="panel" size="small" class="editor-tabs" @change="(k) => k === 'vista' && verVista()">
                            <a-tab-pane key="variables" tab="Variables">
                                <div class="panel">
                                    <p class="vt-small vt-muted mb-2">Clic para insertar donde está el cursor.</p>
                                    <button v-for="(desc, clave) in variables" :key="clave" type="button" class="variable" :disabled="soloLectura" @click="insertar(llaves(clave))">
                                        <code v-text="llaves(clave)"></code><span>{{ desc }}</span>
                                    </button>
                                    <button type="button" class="variable" :disabled="soloLectura" @click="insertarCampo">
                                        <code v-text="llaves('campo:…')"></code><span>Dato libre que se pide al generar (ej. nombre del arrendatario)</span>
                                    </button>
                                </div>
                            </a-tab-pane>
                            <a-tab-pane key="vista" tab="Vista previa">
                                <div class="panel hoja" v-html="vista"></div>
                            </a-tab-pane>
                        </a-tabs>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 mt-4 flex-wrap">
                    <a-checkbox v-if="editando" v-model:checked="form.activo" :disabled="soloLectura">Plantilla activa</a-checkbox>
                    <span v-else></span>
                    <div class="flex gap-2">
                        <a-button size="large" @click="dialogoEditor = false">Cerrar</a-button>
                        <a-button v-if="!soloLectura" type="primary" size="large" :loading="form.processing" @click="guardar"><SaveOutlined /> Guardar plantilla</a-button>
                    </div>
                </div>
            </a-form>
        </a-modal>

        <!-- Nombre del dato libre -->
        <a-modal v-model:open="modalCampo" width="460px" centered title="Dato libre" ok-text="Insertar" cancel-text="Cancelar" :ok-button-props="{ disabled: !nombreCampo.trim() }" @ok="confirmarCampo">
            <p class="vt-small vt-muted">¿Qué dato se pedirá al generar el documento?</p>
            <a-input v-model:value="nombreCampo" placeholder="Ej. Nombre del arrendatario" :maxlength="80" @press-enter="confirmarCampo" />
        </a-modal>

        <GenerarDocumento v-model:visible="dialogoGenerar" :plantillas="plantillas" :casas="casas" />
    </AppLayout>
</template>

<style scoped>
.fila { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--vt-line); border-radius: 12px; background: #fff; }
.fila-ic { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; background: #e8f0fa; color: #1a5a96; flex: none; }
a.fila:hover { background: #f5f9fe; }
.fila.inactiva { opacity: 0.6; }
.editor { display: grid; grid-template-columns: 1.4fr 1fr; gap: 14px; }
@media (max-width: 900px) { .editor { grid-template-columns: 1fr; } }
.editor-col { display: flex; flex-direction: column; min-width: 0; }
.editor-texto { width: 100%; height: 52vh; min-height: 320px; border: 1px solid #d5dee8; border-radius: 12px; padding: 12px; font-family: ui-monospace, Consolas, monospace; font-size: 12.5px; line-height: 1.55; resize: vertical; text-transform: none; }
.editor-texto:focus { border-color: var(--vt-blue); box-shadow: 0 0 0 3px rgba(26, 90, 150, 0.12); outline: none; }
.editor-tabs .ant-tabs-nav { margin-bottom: 6px !important; }
.panel { height: 52vh; min-height: 320px; overflow-y: auto; border: 1px solid var(--vt-line); border-radius: 12px; padding: 10px; background: #f8fafc; }
.pestana { padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; color: #64748b; }
.pestana.activa { background: #1a3a5c; color: #fff; }
.variable { display: flex; flex-direction: column; align-items: flex-start; gap: 1px; width: 100%; text-align: left; padding: 6px 8px; border-radius: 6px; border: 1px solid transparent; }
.variable:hover:not(:disabled) { background: #fff; border-color: #cbd5e1; }
.variable code { font-size: 11.5px; color: #1d4ed8; font-weight: 600; }
.variable span { font-size: 11px; color: #64748b; }
.hoja { background: #fff; font-family: Georgia, serif; font-size: 13px; line-height: 1.6; color: #111827; }
.hoja :deep(h1) { font-size: 16px; font-weight: 700; text-align: center; text-transform: uppercase; margin-bottom: 10px; }
.hoja :deep(h2) { font-size: 13.5px; font-weight: 700; margin: 12px 0 4px; text-transform: uppercase; }
.hoja :deep(p) { margin-bottom: 8px; text-align: justify; }
.hoja :deep(mark) { background: #fef08a; }
</style>
