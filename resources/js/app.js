import '../css/app.css';
import '../css/ui.css';
import 'primeicons/primeicons.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import axios from 'axios';
import { instalarBase, sinBase } from './base';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';

// Ant Design Vue: componentes de las pantallas de condominios (estilo VenTrack / SURVICOS).
// PrimeVue se conserva para las pantallas originales de RIC.
import Antd, { ConfigProvider } from 'ant-design-vue';
import esES from 'ant-design-vue/es/locale/es_ES';
import dayjs from 'dayjs';
import 'dayjs/locale/es';
import { theme } from './theme';

dayjs.locale('es');

// Si el sistema está publicado en una carpeta del dominio (/condominios), las rutas fijas reciben su prefijo
instalarBase(router, axios);

// PrimeVue
import PrimeVue from 'primevue/config';
import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/aura';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';

// Preset de tema RIC: azul corporativo #1a3a5c como color primario
const RicPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#eef4fb',
            100: '#d5e3f3',
            200: '#adc7e6',
            300: '#7ba3d3',
            400: '#4a7db8',
            500: '#1a3a5c',
            600: '#173352',
            700: '#132a44',
            800: '#0f2136',
            900: '#0b1929',
            950: '#070f1a',
        },
    },
});

createInertiaApp({
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue')
    ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(ConfigProvider, { theme, locale: esES }, () => h(App, props)) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Antd)
            .use(PrimeVue, {
                theme: {
                    preset: RicPreset,
                    options: {
                        darkModeSelector: false,
                    },
                },
                locale: {
                    emptyMessage: 'Sin resultados',
                    emptySelectionMessage: 'Sin selección',
                    emptySearchMessage: 'Sin coincidencias',
                    accept: 'Sí',
                    reject: 'No',
                    choose: 'Elegir',
                    upload: 'Subir',
                    cancel: 'Cancelar',
                    dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                    dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                    dayNamesMin: ['D', 'L', 'M', 'X', 'J', 'V', 'S'],
                    monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                    monthNamesShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    today: 'Hoy',
                    clear: 'Limpiar',
                },
            })
            .use(ToastService)
            .use(ConfirmationService);

        app.directive('tooltip', Tooltip);
        // $page.url trae la carpeta; los menús comparan contra la ruta sin ella
        app.config.globalProperties.$sinBase = sinBase;

        return app.mount(el);
    },
});
