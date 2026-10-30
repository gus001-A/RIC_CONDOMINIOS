<template>
    <!-- Alertas vecinales activas: franja roja visible en cualquier pantalla -->
    <div v-if="alertasVisibles.length" class="franja-alerta" :class="{ nueva: hayNueva }">
        <div class="max-w-[96rem] mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center gap-3 flex-wrap">
            <span class="franja-ic"><WarningOutlined /></span>
            <div class="flex-1 min-w-0 text-sm">
                <b>Alerta: {{ alertasVisibles[0].tipo_texto }}</b>
                <span v-if="alertasVisibles[0].ubicacion"> · {{ alertasVisibles[0].ubicacion }}</span>
                <span class="opacity-80"> · {{ hace(alertasVisibles[0].created_at) }}</span>
                <span v-if="alertasVisibles.length > 1" class="opacity-80"> (+{{ alertasVisibles.length - 1 }} más)</span>
            </div>
            <Link :href="route('seguridad.alertas')" class="franja-btn">Ver alertas</Link>
            <button type="button" class="opacity-80 hover:opacity-100" title="Ocultar (siguen activas)" @click="ocultar"><CloseOutlined /></button>
        </div>
    </div>

    <!-- Visitas en caseta esperando que la casa autorice -->
    <div v-if="pulso.visitasEsperando.length" class="visitas-flotantes">
        <div v-for="v in pulso.visitasEsperando" :key="v.id" class="visita-card">
            <div class="flex items-center gap-2 mb-1">
                <span class="visita-icono"><SafetyOutlined /></span>
                <div class="min-w-0">
                    <div class="text-[11px] font-bold text-amber-700">Visita en caseta · Casa {{ v.casa }}</div>
                    <div class="font-semibold text-slate-900 truncate">{{ v.nombre_visitante }}</div>
                </div>
            </div>
            <div class="text-xs text-slate-600 mb-2">
                <span v-if="v.placas"><CarOutlined /> {{ v.placas }}<template v-if="v.vehiculo"> · {{ v.vehiculo }}</template> · </span>
                {{ v.num_personas }} persona(s)<template v-if="v.motivo"> · {{ v.motivo }}</template>
            </div>
            <img v-if="v.foto_url" :src="v.foto_url" alt="Foto de la visita" class="visita-foto" @click="foto = v.foto_url" />
            <div class="flex gap-2">
                <a-button type="primary" class="flex-1" :loading="enviando === v.id" @click="responder(v, 'autorizar')"><CheckOutlined /> Autorizar</a-button>
                <a-button danger class="flex-1" :disabled="enviando === v.id" @click="responder(v, 'rechazar')"><CloseOutlined /> Rechazar</a-button>
            </div>
        </div>
    </div>

    <!-- Mensajes nuevos (en cualquier pantalla, salvo la conversación que se está viendo) -->
    <TransitionGroup tag="div" name="aviso" class="mensajes-flotantes" :class="{ 'con-visitas': pulso.visitasEsperando.length }">
        <Link v-for="m in avisosMensaje" :key="m.id" :href="route('mensajes.index', { conversacion: m.id_conversacion })" class="mensaje-card" @click="descartar(m.id)">
            <span class="mensaje-icono"><MessageOutlined /></span>
            <div class="min-w-0 flex-1">
                <div class="text-[11px] font-bold text-blue-700 truncate">{{ m.autor }} · {{ m.usuario }}</div>
                <div class="text-xs font-semibold text-slate-800 truncate">{{ m.asunto }}</div>
                <div class="text-xs text-slate-600 line-clamp-2">{{ m.texto }}</div>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-700 self-start" title="Cerrar" @click.prevent.stop="descartar(m.id)"><CloseOutlined style="font-size: 11px" /></button>
        </Link>
    </TransitionGroup>

    <VisorArchivo :open="!!foto" :url="foto" nombre="Visita en caseta" mime="image/jpeg" @update:open="(v) => !v && (foto = null)" />
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { WarningOutlined, CloseOutlined, SafetyOutlined, CarOutlined, CheckOutlined, MessageOutlined } from '@ant-design/icons-vue';
import VisorArchivo from './VisorArchivo.vue';
import { pulso, iniciarPulso, detenerPulso, consultarPulso, pedirPermisoNotificaciones } from '@/composables/usePulso';
import { useNotify } from '@/composables/useNotify';

const notify = useNotify();
const enviando = ref(null);
const foto = ref(null);

// ===== Mensajes nuevos: tarjetas que se quitan solas a los 8 s =====
const avisosMensaje = ref([]);
const descartar = (id) => { avisosMensaje.value = avisosMensaje.value.filter((m) => m.id !== id); };
watch(() => pulso.mensajesNuevos, (nuevos) => {
    // Del más viejo al más nuevo: así el más reciente de cada conversación es el que queda.
    for (const m of [...nuevos].sort((a, b) => a.id - b.id)) {
        const previo = avisosMensaje.value.find((a) => a.id_conversacion === m.id_conversacion);
        if (previo && previo.id >= m.id) continue;
        // Una tarjeta por conversación: el mensaje más reciente reemplaza al anterior.
        avisosMensaje.value = [m, ...avisosMensaje.value.filter((a) => a.id_conversacion !== m.id_conversacion)].slice(0, 3);
        setTimeout(() => descartar(m.id), 8000);
    }
});

// Ocultar la franja hasta que llegue una alerta distinta (se guarda por sesión).
const LLAVE = 'ric_alertas_ocultas';
const leerOcultas = () => { try { return JSON.parse(sessionStorage.getItem(LLAVE) || '[]'); } catch (e) { return []; } };
const ocultas = ref(leerOcultas());
const alertasVisibles = computed(() => pulso.alertas.filter((a) => !ocultas.value.includes(a.id)));
const hayNueva = computed(() => pulso.nuevasAlertas.length > 0);
const ocultar = () => {
    ocultas.value = [...ocultas.value, ...alertasVisibles.value.map((a) => a.id)];
    try { sessionStorage.setItem(LLAVE, JSON.stringify(ocultas.value)); } catch (e) { /* sin almacenamiento */ }
};

const hace = (f) => {
    const min = Math.round((Date.now() - new Date(f).getTime()) / 60000);
    if (min < 1) return 'ahora';
    if (min < 60) return `hace ${min} min`;
    return `hace ${Math.round(min / 60)} h`;
};

const responder = async (v, accion) => {
    enviando.value = v.id;
    try {
        await axios.post(route('seguridad.visitas.responder', v.id), { accion });
        notify.success(accion === 'autorizar' ? `Autorizaste a ${v.nombre_visitante}. Caseta ya lo ve.` : `Rechazaste a ${v.nombre_visitante}.`);
        await consultarPulso();
    } catch (e) {
        notify.error(e?.response?.data?.message || 'No se pudo responder.');
        consultarPulso();
    } finally {
        enviando.value = null;
    }
};

onMounted(() => {
    iniciarPulso();
    // El permiso de notificaciones se pide tras una interacción del usuario.
    window.addEventListener('click', pedirPermisoNotificaciones, { once: true });
});
onBeforeUnmount(detenerPulso);
</script>

<style scoped>
.franja-alerta { background: linear-gradient(90deg, #b42318, #d9363e); color: #fff; position: sticky; top: 64px; z-index: 40; box-shadow: 0 4px 14px rgba(185, 28, 28, 0.35); }
.franja-alerta.nueva { animation: destello 0.8s ease-in-out 4; }
@keyframes destello { 50% { filter: brightness(1.25); } }
.franja-btn { background: #fff; color: #b42318; font-weight: 700; font-size: 12.5px; padding: 5px 14px; border-radius: 99px; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15); transition: transform 0.15s; }
.franja-btn:hover { transform: translateY(-1px); }
.franja-ic { width: 28px; height: 28px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); display: grid; place-items: center; font-size: 15px; flex: none; animation: franja-pulso 1.8s ease-in-out infinite; }
@keyframes franja-pulso { 50% { box-shadow: 0 0 0 6px rgba(255, 255, 255, 0.15); } }

.visitas-flotantes { position: fixed; right: 16px; bottom: 16px; z-index: 60; display: flex; flex-direction: column; gap: 10px; width: 320px; max-width: calc(100vw - 32px); }
.visita-card { background: #fff; border: 1.5px solid #f0a202; border-radius: 14px; padding: 12px 14px; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18); animation: entra 0.3s ease-out; }
.visita-icono { width: 34px; height: 34px; border-radius: 10px; background: #fef3c7; color: #b45309; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.visita-foto { width: 100%; height: 120px; object-fit: cover; border-radius: 10px; margin-bottom: 8px; cursor: zoom-in; }
@keyframes entra { from { transform: translateY(20px); opacity: 0; } }

.mensajes-flotantes { position: fixed; right: 16px; bottom: 16px; z-index: 59; display: flex; flex-direction: column; gap: 8px; width: 320px; max-width: calc(100vw - 32px); }
/* Si hay visitas por autorizar, los mensajes se apilan a la izquierda para no taparlas. */
.mensajes-flotantes.con-visitas { right: 352px; }
@media (max-width: 720px) { .mensajes-flotantes.con-visitas { right: 16px; bottom: auto; top: 76px; } }
.mensaje-card { display: flex; gap: 10px; background: #fff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 10px 12px; box-shadow: 0 10px 28px rgba(15, 23, 42, 0.16); }
.mensaje-card:hover { background: #f8fbff; }
.mensaje-icono { width: 32px; height: 32px; border-radius: 10px; background: #dbeafe; color: #1d4ed8; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
/* En el celular, por encima de la barra inferior de navegación. */
@media (max-width: 767px) {
    .visitas-flotantes, .mensajes-flotantes { bottom: calc(84px + env(safe-area-inset-bottom)); }
}
.aviso-enter-active, .aviso-leave-active { transition: all 0.25s ease; }
.aviso-enter-from, .aviso-leave-to { opacity: 0; transform: translateX(30px); }
</style>
