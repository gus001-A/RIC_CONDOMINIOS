import { reactive } from 'vue';

/**
 * Estado de la app instalable (PWA).
 *  - Android / Chrome / Edge: el navegador avisa que se puede instalar
 *    (evento capturado en app.blade.php) y aquí se lanza con instalar().
 *  - iPhone / iPad: Safari no tiene ese aviso; se muestran los pasos
 *    (Compartir → Agregar a inicio).
 */
const ua = navigator.userAgent || '';
const esIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const MODOS_APP = ['standalone', 'minimal-ui', 'fullscreen'];
const enApp = () => MODOS_APP.some((m) => window.matchMedia?.(`(display-mode: ${m})`).matches) || window.navigator.standalone === true;

export const app = reactive({
    instalada: enApp(),
    // Abierta como app (ventana propia), no en una pestaña del navegador.
    abierta: enApp(),
    puedeInstalar: !!window.__eventoInstalar,
    esIOS,
    // En iPhone se puede "instalar" desde Safari aunque no haya evento.
    get disponible() { return !this.instalada && (this.puedeInstalar || this.esIOS); },
});

MODOS_APP.forEach((m) => window.matchMedia?.(`(display-mode: ${m})`).addEventListener?.('change', () => { app.abierta = enApp(); }));
window.addEventListener('ric:instalable', () => { app.puedeInstalar = true; });
window.addEventListener('appinstalled', () => {
    app.instalada = true;
    app.puedeInstalar = false;
    window.__eventoInstalar = null;
});

/** Lanza el aviso del navegador. Regresa true si la persona aceptó. */
export async function instalar() {
    const evento = window.__eventoInstalar;
    if (!evento) return false;
    evento.prompt();
    const { outcome } = await evento.userChoice;
    window.__eventoInstalar = null;
    app.puedeInstalar = false;
    return outcome === 'accepted';
}
