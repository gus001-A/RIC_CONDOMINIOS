import { reactive, watch } from 'vue';
import { conBase } from '@/base';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

/**
 * "Pulso" global compartido por toda la app (sin websockets).
 *
 * Consulta /pulso: alertas vecinales activas, visitas que esperan que la casa
 * autorice y mensajes sin leer. Con la pestaña a la vista consulta cada pocos
 * segundos; en segundo plano, más espaciado (para seguir avisando con
 * notificaciones del navegador sin cargar el servidor). Al volver a la
 * pestaña consulta de inmediato.
 *
 * Cuando aparece algo nuevo suena un aviso y, si el usuario dio permiso, se
 * muestra una notificación del navegador.
 */
const VISIBLE_MS = 6000;
const OCULTA_MS = 20000;

export const pulso = reactive({
    listo: false,
    alertas: [],
    visitasEsperando: [],
    nuevasAlertas: [],        // ids de alertas que llegaron en este ciclo
    mensajesNoLeidos: 0,      // conversaciones con mensajes sin leer
    mensajesNuevos: [],       // mensajes que llegaron en este ciclo (para el aviso flotante)
    conversacionAbierta: null, // la que el usuario está viendo en Mensajes: no se avisa de ella
});

let timer = null;
let usuarios = 0;
let vistos = null;            // ids ya conocidos (null = primera carga: no avisar de lo viejo)
let consultando = false;

const sonar = (tipo) => {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const tonos = { alerta: [880, 660, 880, 660], visita: [660, 880], mensaje: [740, 990] }[tipo] || [660];
        tonos.forEach((f, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = f;
            osc.connect(gain);
            gain.connect(ctx.destination);
            const t = ctx.currentTime + i * (tipo === 'mensaje' ? 0.12 : 0.22);
            gain.gain.setValueAtTime(tipo === 'mensaje' ? 0.1 : 0.18, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.18);
            osc.start(t);
            osc.stop(t + 0.18);
        });
    } catch (e) { /* el navegador bloqueó el audio: no pasa nada */ }
};

const notificar = async (titulo, cuerpo, url, tag) => {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    const opciones = { body: cuerpo, tag: tag || titulo + cuerpo, icon: '/app/icono-192.png', badge: '/app/icono-192.png', data: { url } };
    // En la app instalada (y en Android) sólo se permiten desde el service worker;
    // al tocarla, sw.js abre la pantalla indicada.
    try {
        const registro = await navigator.serviceWorker?.getRegistration();
        if (registro) {
            await registro.showNotification(titulo, opciones);
            return;
        }
    } catch (e) { /* sin service worker: se intenta la notificación normal */ }
    try {
        const n = new Notification(titulo, opciones);
        n.onclick = () => { window.focus(); if (url) window.location.href = conBase(url); };
    } catch (e) { /* el navegador no permite notificaciones aquí */ }
};

export const pedirPermisoNotificaciones = () => {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
};

// El número de no leídos también va en el título de la pestaña: "(2) RIC - …".
const actualizarTitulo = () => {
    const base = document.title.replace(/^\(\d+\)\s*/, '');
    document.title = pulso.mensajesNoLeidos ? `(${pulso.mensajesNoLeidos}) ${base}` : base;
};
// Al instante cuando cambia el contador (p. ej. al abrir una conversación)…
watch(() => pulso.mensajesNoLeidos, actualizarTitulo);
// …y después de cada navegación de Inertia, que reescribe el <title>.
router.on('navigate', () => setTimeout(actualizarTitulo, 60));

export const consultarPulso = async () => {
    if (consultando) return;
    consultando = true;
    try {
        const { data } = await axios.get(route('pulso'));
        const ids = new Set([
            ...data.alertas.map((a) => `a${a.id}`),
            ...data.visitas_esperando.map((v) => `v${v.id}`),
            ...data.mensajes.recientes.map((m) => `m${m.id}`),
        ]);

        if (vistos) {
            const alertasNuevas = data.alertas.filter((a) => !vistos.has(`a${a.id}`) && !a.es_mia);
            const visitasNuevas = data.visitas_esperando.filter((v) => !vistos.has(`v${v.id}`));
            // De la conversación que el usuario tiene a la vista no se avisa: ya la está leyendo.
            const viendo = !document.hidden ? pulso.conversacionAbierta : null;
            const mensajesNuevos = data.mensajes.recientes.filter((m) => !vistos.has(`m${m.id}`) && m.id_conversacion !== viendo);

            pulso.nuevasAlertas = alertasNuevas.map((a) => a.id);
            pulso.mensajesNuevos = mensajesNuevos;

            if (alertasNuevas.length) {
                sonar('alerta');
                alertasNuevas.forEach((a) => notificar(`🚨 Alerta: ${a.tipo_texto}`, a.ubicacion || a.descripcion || 'Revisa la alerta en RIC', route('seguridad.alertas'), `alerta-${a.id}`));
            }
            if (visitasNuevas.length) {
                sonar('visita');
                visitasNuevas.forEach((v) => notificar('Visita en caseta', `${v.nombre_visitante}${v.placas ? ' · ' + v.placas : ''} quiere entrar a tu casa`, route('seguridad.mis-visitas'), `visita-${v.id}`));
            }
            if (mensajesNuevos.length) {
                sonar('mensaje');
                mensajesNuevos.forEach((m) => notificar(
                    `💬 ${m.autor}`,
                    `${m.asunto}: ${m.texto}`,
                    route('mensajes.index', { conversacion: m.id_conversacion }),
                    `conv-${m.id_conversacion}`,
                ));
            }
        }

        vistos = ids;
        pulso.alertas = data.alertas;
        pulso.visitasEsperando = data.visitas_esperando;
        pulso.mensajesNoLeidos = data.mensajes.no_leidos;
        pulso.listo = true;
        actualizarTitulo();
    } catch (e) {
        /* sin conexión: se reintenta en el siguiente ciclo */
    } finally {
        consultando = false;
    }
};

const programar = () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        await consultarPulso();
        if (usuarios > 0) programar();
    }, document.hidden ? OCULTA_MS : VISIBLE_MS);
};

// Al volver a la pestaña: consultar ya y retomar el ritmo rápido.
const alVolver = () => {
    if (!document.hidden && usuarios > 0) {
        consultarPulso();
        programar();
    }
};

/** Lo llama el layout al montarse; hay un solo temporizador aunque se monte varias veces. */
export const iniciarPulso = () => {
    usuarios++;
    if (usuarios > 1) return;
    document.addEventListener('visibilitychange', alVolver);
    window.addEventListener('focus', alVolver);
    consultarPulso();
    programar();
};

export const detenerPulso = () => {
    usuarios = Math.max(0, usuarios - 1);
    if (usuarios === 0) {
        clearTimeout(timer);
        timer = null;
        document.removeEventListener('visibilitychange', alVolver);
        window.removeEventListener('focus', alVolver);
    }
};
