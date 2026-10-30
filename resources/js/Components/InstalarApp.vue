<script setup>
/**
 * Invitación a instalar la app en el celular (franja inferior en móvil) y el
 * modal con los pasos. También se abre desde el menú del usuario.
 *   <InstalarApp ref="instalador" />   →   instalador.value.abrir()
 */
import { ref, computed } from 'vue';
import { message } from 'ant-design-vue';
import { MobileOutlined, CloseOutlined, DownloadOutlined, ExportOutlined, PlusSquareOutlined, CheckCircleOutlined } from '@ant-design/icons-vue';
import { app, instalar } from '@/composables/appInstalable';

const LLAVE = 'ric_instalar_oculto';
const leerOculto = () => { try { return Number(localStorage.getItem(LLAVE) || 0); } catch (e) { return 0; } };
// Si la cierra, no se vuelve a mostrar en 14 días.
const oculto = ref(Date.now() - leerOculto() < 14 * 86400000);
const franja = computed(() => app.disponible && !oculto.value);

const modal = ref(false);
const abrir = () => { modal.value = true; };
defineExpose({ abrir });

const cerrarFranja = () => {
    oculto.value = true;
    try { localStorage.setItem(LLAVE, String(Date.now())); } catch (e) { /* sin almacenamiento */ }
};

async function instalarAhora() {
    if (app.puedeInstalar) {
        if (await instalar()) {
            message.success('¡Listo! Encontrarás RIC Condominios en tu pantalla de inicio.');
            modal.value = false;
        }
    } else {
        modal.value = true;
    }
}
</script>

<template>
    <!-- Franja en el celular (sobre la barra inferior) -->
    <transition name="ia">
        <div v-if="franja" class="ia-franja md:hidden">
            <span class="ia-ic"><MobileOutlined /></span>
            <div class="flex-1 min-w-0">
                <b>Instala la app</b>
                <small>Acceso directo desde tu pantalla de inicio</small>
            </div>
            <a-button type="primary" size="small" @click="instalarAhora"><DownloadOutlined /> Instalar</a-button>
            <a-button type="text" size="small" class="ia-x" aria-label="Cerrar" @click="cerrarFranja"><CloseOutlined /></a-button>
        </div>
    </transition>

    <a-modal v-model:open="modal" width="440px" centered :footer="null">
        <template #title>
            <div class="vt-modal-title"><span class="vt-head-icon"><MobileOutlined /></span><div>Instalar RIC Condominios<small>Como una app, con su propio icono</small></div></div>
        </template>

        <div v-if="app.instalada" class="vt-alert ok mb-0"><CheckCircleOutlined /> <span>Ya estás usando la app instalada.</span></div>

        <template v-else-if="app.puedeInstalar">
            <p class="vt-muted">Se agregará a tu pantalla de inicio y abrirá en pantalla completa, sin la barra del navegador.</p>
            <a-button type="primary" size="large" block @click="instalarAhora"><DownloadOutlined /> Instalar ahora</a-button>
        </template>

        <template v-else-if="app.esIOS">
            <p class="vt-muted">En iPhone y iPad se instala desde <b>Safari</b>:</p>
            <ol class="ia-pasos">
                <li><span class="ia-num">1</span> Toca el botón <b>Compartir</b> <ExportOutlined class="ia-sym" /> en la barra de Safari.</li>
                <li><span class="ia-num">2</span> Elige <b>Agregar a inicio</b> <PlusSquareOutlined class="ia-sym" />.</li>
                <li><span class="ia-num">3</span> Toca <b>Agregar</b>. El icono de RIC aparecerá en tu pantalla de inicio.</li>
            </ol>
        </template>

        <template v-else>
            <p class="vt-muted">Abre el sistema en <b>Chrome</b> (Android) o <b>Safari</b> (iPhone) desde tu celular:</p>
            <ol class="ia-pasos">
                <li><span class="ia-num">1</span> Entra con tu usuario como siempre.</li>
                <li><span class="ia-num">2</span> Toca el menú del navegador (⋮ en Chrome, Compartir en Safari).</li>
                <li><span class="ia-num">3</span> Elige <b>Instalar app</b> o <b>Agregar a inicio</b>.</li>
            </ol>
            <div class="vt-alert info mb-0"><MobileOutlined /> <span>Para instalarla, el sistema debe abrirse con una dirección segura (https).</span></div>
        </template>
    </a-modal>
</template>

<style>
.ia-franja {
    position: fixed; left: 10px; right: 10px; z-index: 55;
    bottom: calc(74px + env(safe-area-inset-bottom));
    display: flex; align-items: center; gap: 10px; padding: 10px 8px 10px 12px;
    background: #fff; border: 1px solid #c6dbf3; border-radius: 16px;
    box-shadow: 0 14px 34px rgba(15, 42, 71, 0.2);
}
.ia-franja b { display: block; color: var(--vt-navy); font-size: 14px; }
.ia-franja small { display: block; color: var(--vt-muted); font-size: 12px; }
.ia-ic { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; font-size: 19px; color: #fff; background: linear-gradient(135deg, #1a3a5c, #1a5a96); flex: none; }
.ia-x { color: var(--vt-muted) !important; }
.ia-enter-active, .ia-leave-active { transition: all 0.3s ease; }
.ia-enter-from, .ia-leave-to { opacity: 0; transform: translateY(20px); }
.ia-pasos { list-style: none; padding: 0; margin: 0 0 14px; display: flex; flex-direction: column; gap: 10px; }
.ia-pasos li { display: flex; align-items: center; gap: 10px; background: #f6f9fd; border: 1px solid var(--vt-line); border-radius: 12px; padding: 10px 12px; color: var(--vt-navy); }
.ia-num { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; background: var(--vt-blue); color: #fff; font-weight: 700; font-size: 13px; flex: none; }
.ia-sym { color: var(--vt-blue); }
</style>
