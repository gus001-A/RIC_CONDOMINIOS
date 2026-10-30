<script setup>
/**
 * Barra de navegación inferior en el celular (como una app nativa).
 * Muestra las 4 pantallas principales del rol y "Más" abre el menú completo.
 *   items: [{ label, href, activo, globo }]  (del menú de condominios del layout)
 */
import { ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    HomeOutlined, SafetyOutlined, IdcardOutlined, ToolOutlined, BellOutlined, MessageOutlined, ShopOutlined, InboxOutlined,
    FileTextOutlined, EyeOutlined, WalletOutlined, AppstoreOutlined, MenuOutlined, SwapOutlined, BankOutlined, TeamOutlined,
    ApartmentOutlined, BarChartOutlined, DashboardOutlined, MobileOutlined, LogoutOutlined,
} from '@ant-design/icons-vue';
import { app } from '@/composables/appInstalable';
import { push } from '@/composables/push';

defineProps({
    items: { type: Array, default: () => [] },  // los 4 accesos de la barra
    todos: { type: Array, default: () => [] },  // todo el menú, para el panel "Más"
});
const emit = defineEmits(['instalar', 'push']);
const page = usePage();

// "Más": panel que sube desde abajo con todo el menú (como en las apps nativas).
const mas = ref(false);
watch(() => page.url, () => (mas.value = false));
const salir = () => router.post('/logout');

const ICONOS = {
    '/mi-casa': HomeOutlined,
    '/casas': HomeOutlined,
    '/seguridad/caseta': SafetyOutlined,
    '/mis-visitas': IdcardOutlined,
    '/incidencias': ToolOutlined,
    '/seguridad/alertas': BellOutlined,
    '/mensajes': MessageOutlined,
    '/servicios': ShopOutlined,
    '/inventario': InboxOutlined,
    '/documentos/plantillas': FileTextOutlined,
    '/transparencia': EyeOutlined,
    '/cuotas': WalletOutlined,
    '/dashboard': DashboardOutlined,
    '/movimientos': SwapOutlined,
    '/cuentas': BankOutlined,
    '/usuarios': TeamOutlined,
    '/empresas': ApartmentOutlined,
    '/reportes': BarChartOutlined,
};
</script>

<template>
    <nav class="bi md:hidden" aria-label="Navegación principal">
        <Link v-for="i in items" :key="i.href" :href="i.href" class="bi-item" :class="{ activo: i.activo }">
            <span class="bi-ic">
                <component :is="ICONOS[i.href] || AppstoreOutlined" />
                <span v-if="i.globo" class="bi-globo">{{ i.globo > 99 ? '99+' : i.globo }}</span>
            </span>
            <span class="bi-txt">{{ i.label }}</span>
        </Link>
        <button type="button" class="bi-item" :class="{ activo: mas }" @click="mas = !mas">
            <span class="bi-ic"><MenuOutlined /></span>
            <span class="bi-txt">Más</span>
        </button>
    </nav>

    <a-drawer v-model:open="mas" placement="bottom" height="auto" :closable="false" class="bi-panel" root-class-name="md:hidden">
        <div class="bi-asa"></div>
        <div class="bi-usuario">
            <a-avatar :size="42" style="background: #1a5a96; font-weight: 700">{{ (page.props.auth?.user?.nombre_usuario || '?').slice(0, 2) }}</a-avatar>
            <div class="min-w-0">
                <b class="block truncate">{{ page.props.auth?.user?.nombre_completo || page.props.auth?.user?.nombre_usuario }}</b>
                <small class="vt-muted">{{ page.props.auth?.user?.nombre_usuario }}</small>
            </div>
        </div>
        <div class="bi-rejilla">
            <Link v-for="i in todos" :key="i.href" :href="i.href" class="bi-mosaico" :class="{ activo: i.activo }">
                <span class="bi-ic">
                    <component :is="ICONOS[i.href] || AppstoreOutlined" />
                    <span v-if="i.globo" class="bi-globo">{{ i.globo > 99 ? '99+' : i.globo }}</span>
                </span>
                <span>{{ i.label }}</span>
            </Link>
        </div>
        <div class="bi-acciones">
            <div v-if="push.soportado && page.props.push_llave && !push.requiereInstalar" class="bi-switch">
                <span class="bi-switch-ic" :class="{ on: push.activo }"><BellOutlined /></span>
                <span class="flex-1"><b>Notificaciones</b><small>Visitas, alertas y mensajes con la app cerrada</small></span>
                <a-switch :checked="push.activo" :loading="push.trabajando" @change="emit('push')" />
            </div>
            <a-button v-if="!app.instalada" block size="large" @click="mas = false; emit('instalar')"><MobileOutlined /> Instalar app</a-button>
            <a-button block size="large" danger @click="salir"><LogoutOutlined /> Cerrar sesión</a-button>
        </div>
    </a-drawer>
</template>

<style>
.bi {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 56;
    display: flex; justify-content: space-around; align-items: stretch;
    padding: 6px 4px calc(6px + env(safe-area-inset-bottom));
    background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(12px);
    border-top: 1px solid var(--vt-line, #e1e8f0);
    box-shadow: 0 -6px 24px rgba(15, 42, 71, 0.08);
}
.bi-item { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 4px 2px; border-radius: 12px; color: #6b7c90; -webkit-tap-highlight-color: transparent; }
.bi-ic { position: relative; width: 44px; height: 30px; border-radius: 15px; display: grid; place-items: center; font-size: 20px; transition: background 0.2s, color 0.2s; }
.bi-txt { font-size: 10.5px; font-weight: 600; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.bi-item.activo { color: var(--vt-blue, #1a5a96); }
.bi-item.activo .bi-ic { background: #e3eefa; }
.bi-item:active .bi-ic { transform: scale(0.94); }
.bi-panel .ant-drawer-content { border-radius: 22px 22px 0 0; }
.bi-panel .ant-drawer-body { padding: 8px 16px calc(16px + env(safe-area-inset-bottom)); }
.bi-asa { width: 42px; height: 5px; border-radius: 3px; background: #d5dee8; margin: 4px auto 12px; }
.bi-usuario { display: flex; align-items: center; gap: 12px; padding: 4px 4px 14px; border-bottom: 1px solid var(--vt-line, #e1e8f0); margin-bottom: 12px; }
.bi-usuario b { color: var(--vt-navy, #0f2a47); }
.bi-rejilla { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.bi-mosaico { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 12px 4px; border-radius: 14px; background: #f6f9fd; color: var(--vt-navy, #0f2a47); font-size: 12px; font-weight: 600; text-align: center; }
.bi-mosaico .bi-ic { background: #fff; box-shadow: 0 2px 6px rgba(15, 42, 71, 0.08); width: 44px; height: 44px; border-radius: 14px; color: var(--vt-blue, #1a5a96); }
.bi-mosaico.activo { background: #e3eefa; }
.bi-acciones { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; }
.bi-switch { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--vt-line, #e1e8f0); border-radius: 14px; }
.bi-switch b { display: block; color: var(--vt-navy, #0f2a47); font-size: 14px; }
.bi-switch small { display: block; color: var(--vt-muted, #5d7085); font-size: 12px; }
.bi-switch-ic { width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; font-size: 17px; background: #fff4e0; color: #b97800; flex: none; }
.bi-switch-ic.on { background: #e6f7ee; color: #15803d; }
.bi-globo { position: absolute; top: -3px; right: 2px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: #d9363e; color: #fff; font-size: 10px; font-weight: 700; display: grid; place-items: center; border: 2px solid #fff; }
</style>
