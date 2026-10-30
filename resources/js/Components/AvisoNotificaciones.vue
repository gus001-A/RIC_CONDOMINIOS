<script setup>
/**
 * Invitación a activar las notificaciones push (visitas, alertas y mensajes
 * aunque la app esté cerrada). Aparece arriba del contenido mientras el
 * permiso no se haya decidido; si la cierran, vuelve en 7 días.
 */
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { message } from 'ant-design-vue';
import { BellOutlined, CloseOutlined, MobileOutlined } from '@ant-design/icons-vue';
import { push, activarPush } from '@/composables/push';

const emit = defineEmits(['instalar']);
const page = usePage();
const LLAVE = 'ric_push_oculto';
const leer = () => { try { return Number(localStorage.getItem(LLAVE) || 0); } catch (e) { return 0; } };
const oculto = ref(Date.now() - leer() < 7 * 86400000);

const visible = computed(() => !oculto.value && !!page.props.push_llave
    && (push.requiereInstalar || (push.soportado && push.permiso === 'default')));

const cerrar = () => {
    oculto.value = true;
    try { localStorage.setItem(LLAVE, String(Date.now())); } catch (e) { /* sin almacenamiento */ }
};

async function activar() {
    if (await activarPush()) {
        message.success('Notificaciones activadas. Te llegará un aviso de prueba.');
    } else if (push.permiso === 'denied') {
        message.warning('Bloqueaste las notificaciones. Puedes permitirlas en los ajustes del navegador para este sitio.');
        cerrar();
    }
}
</script>

<template>
    <div v-if="visible" class="vt-wrap an-wrap">
        <div class="an-tarjeta">
            <span class="an-ic"><BellOutlined /></span>
            <div class="flex-1 min-w-0">
                <template v-if="push.requiereInstalar">
                    <b>Recibe avisos en tu iPhone</b>
                    <small>Primero instala la app en tu pantalla de inicio; después podrás activar los avisos de visitas, alertas y mensajes.</small>
                </template>
                <template v-else>
                    <b>Activa los avisos en este dispositivo</b>
                    <small>Te avisamos de visitas en caseta, alertas vecinales y mensajes aunque la app esté cerrada.</small>
                </template>
            </div>
            <div class="an-botones">
                <a-button v-if="push.requiereInstalar" type="primary" @click="emit('instalar')"><MobileOutlined /> Instalar app</a-button>
                <a-button v-else type="primary" :loading="push.trabajando" @click="activar"><BellOutlined /> Activar</a-button>
                <a-button type="text" class="an-x" aria-label="Ahora no" @click="cerrar"><CloseOutlined /></a-button>
            </div>
        </div>
    </div>
</template>

<style>
.an-wrap { padding-top: 0 !important; padding-bottom: 0 !important; margin-bottom: 4px; }
.an-tarjeta {
    display: flex; align-items: center; gap: 12px; padding: 12px 12px 12px 14px;
    background: linear-gradient(135deg, #f2f7fd, #ffffff); border: 1px solid #c6dbf3; border-radius: 16px;
    box-shadow: 0 6px 18px rgba(26, 90, 150, 0.08);
}
.an-tarjeta b { display: block; color: var(--vt-navy); font-size: 14.5px; }
.an-tarjeta small { display: block; color: var(--vt-muted); font-size: 12.5px; line-height: 1.35; }
.an-ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; flex: none; font-size: 19px; color: #fff; background: linear-gradient(135deg, #f0a202, #e07b00); box-shadow: 0 6px 14px rgba(240, 162, 2, 0.3); animation: an-timbre 2.4s ease-in-out infinite; }
@keyframes an-timbre { 0%, 70%, 100% { transform: rotate(0); } 75% { transform: rotate(-14deg); } 80% { transform: rotate(12deg); } 85% { transform: rotate(-8deg); } 90% { transform: rotate(5deg); } }
.an-botones { display: flex; align-items: center; gap: 4px; flex: none; }
.an-x { color: var(--vt-muted) !important; }
@media (max-width: 640px) {
    .an-tarjeta { flex-wrap: wrap; }
    .an-botones { width: 100%; justify-content: flex-end; }
}
</style>
