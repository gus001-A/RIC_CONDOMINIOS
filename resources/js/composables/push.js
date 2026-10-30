import { reactive } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { app } from './appInstalable';

/**
 * Notificaciones push: el celular recibe visitas, alertas y mensajes aunque la
 * app esté cerrada. El navegador crea la suscripción con la llave pública del
 * servidor y aquí se registra en /push/suscribir.
 *
 * iPhone: sólo funciona con la app instalada en la pantalla de inicio (iOS 16.4+).
 */
const soportado = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

export const push = reactive({
    soportado,
    permiso: soportado ? Notification.permission : 'denied', // default | granted | denied
    activo: false,
    trabajando: false,
    // En iPhone con Safari normal no se puede: primero hay que instalar la app.
    get requiereInstalar() { return app.esIOS && !app.instalada; },
});

const aBytes = (base64) => {
    const relleno = '='.repeat((4 - (base64.length % 4)) % 4);
    const crudo = atob((base64 + relleno).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...crudo].map((c) => c.charCodeAt(0)));
};

const llave = () => usePage().props.push_llave;

async function registro() {
    return navigator.serviceWorker.ready;
}

async function suscripcionActual() {
    if (!soportado) return null;
    return (await registro()).pushManager.getSubscription();
}

async function enviarAlServidor(s) {
    await axios.post(route('push.suscribir'), s.toJSON());
}

/**
 * Al entrar al sistema: si ya dio permiso, asegura que este celular esté
 * registrado para el usuario actual (p. ej. si cambió de usuario).
 */
export async function sincronizarPush() {
    if (!soportado || !llave() || Notification.permission !== 'granted') {
        push.activo = false;
        return;
    }
    try {
        let s = await suscripcionActual();
        if (!s) {
            s = await (await registro()).pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: aBytes(llave()) });
        }
        await enviarAlServidor(s);
        push.activo = true;
    } catch (e) {
        push.activo = false;
    }
}

/** Pide permiso, se suscribe y manda un aviso de prueba. Regresa true si quedó activo. */
export async function activarPush() {
    if (!soportado || !llave()) return false;
    push.trabajando = true;
    try {
        push.permiso = await Notification.requestPermission();
        if (push.permiso !== 'granted') return false;
        const reg = await registro();
        const s = (await reg.pushManager.getSubscription())
            || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: aBytes(llave()) });
        await enviarAlServidor(s);
        push.activo = true;
        axios.post(route('push.prueba')).catch(() => {});
        return true;
    } finally {
        push.trabajando = false;
    }
}

export async function desactivarPush() {
    push.trabajando = true;
    try {
        const s = await suscripcionActual();
        if (s) {
            await axios.post(route('push.desuscribir'), { endpoint: s.endpoint }).catch(() => {});
            await s.unsubscribe();
        }
        push.activo = false;
    } finally {
        push.trabajando = false;
    }
}
