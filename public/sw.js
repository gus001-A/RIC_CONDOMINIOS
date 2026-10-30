/*
 * Service worker de RIC Condominios (app instalable).
 *
 *  - Archivos de la app (/build, íconos, lector de placas): se guardan en caché
 *    la primera vez; así la app abre rápido y gasta menos datos.
 *  - Páginas y datos: SIEMPRE se piden al servidor (son privados y cambian);
 *    nunca se guardan en el teléfono. Si no hay conexión se muestra offline.html.
 *
 * Al cambiar algo aquí, sube VERSION para que los teléfonos tomen la nueva.
 */
const VERSION = 'v5';
const CACHE_ESTATICOS = `ric-estaticos-${VERSION}`;
const BASE = new URL('./', self.location).pathname; // funciona también en subcarpeta
const SIN_CONEXION = `${BASE}offline.html`;

const PRECARGA = [
    SIN_CONEXION,
    `${BASE}app/icono-192.png`,
    `${BASE}app/icono-512.png`,
    `${BASE}manifest.webmanifest`,
];

// Rutas que no cambian sin cambiar de nombre (Vite les pone hash) o que casi nunca cambian.
const ES_ESTATICO = (url) => url.origin === self.location.origin && (
    url.pathname.startsWith(`${BASE}build/`)
    || url.pathname.startsWith(`${BASE}app/`)
    || url.pathname.startsWith(`${BASE}vendor/tesseract/`)
    || url.pathname.startsWith(`${BASE}logos/`)
);
const ES_FUENTE = (url) => url.hostname === 'fonts.bunny.net';

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE_ESTATICOS).then((c) => c.addAll(PRECARGA)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((llaves) => Promise.all(llaves.filter((k) => k.startsWith('ric-') && k !== CACHE_ESTATICOS).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

async function primeroCache(peticion) {
    const cache = await caches.open(CACHE_ESTATICOS);
    const guardada = await cache.match(peticion);
    if (guardada) return guardada;
    const respuesta = await fetch(peticion);
    if (respuesta.ok || respuesta.type === 'opaque') {
        await cache.put(peticion, respuesta.clone());
        recortar(cache);
    }
    return respuesta;
}

// Cada versión nueva del sistema trae archivos con otro nombre; se borran los
// más viejos para que la caché no crezca sin límite en el teléfono.
const MAXIMO = 220;
async function recortar(cache) {
    const llaves = await cache.keys();
    const sobran = llaves.length - MAXIMO;
    for (let i = 0; i < sobran; i++) {
        if (!PRECARGA.includes(new URL(llaves[i].url).pathname)) await cache.delete(llaves[i]);
    }
}

self.addEventListener('fetch', (e) => {
    const { request } = e;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);

    if (ES_ESTATICO(url) || ES_FUENTE(url)) {
        e.respondWith(primeroCache(request));
        return;
    }

    // Navegación (abrir una pantalla): siempre del servidor; sin red, la pantalla "sin conexión".
    if (request.mode === 'navigate') {
        e.respondWith(fetch(request).catch(() => caches.match(SIN_CONEXION)));
    }
    // Todo lo demás (datos, Inertia, fotos privadas) va directo a la red, sin guardarse.
});

// Permite que la página pida activar una versión nueva al momento.
self.addEventListener('message', (e) => {
    if (e.data === 'activar') self.skipWaiting();
});

// Notificación push del servidor (visitas, alertas, mensajes): llega aunque la app esté cerrada.
self.addEventListener('push', (e) => {
    let d = {};
    try { d = e.data ? e.data.json() : {}; } catch (err) { d = { titulo: 'RIC Condominios', cuerpo: e.data?.text() }; }

    e.waitUntil((async () => {
        // Si la app está abierta y a la vista, ella misma avisa (sonido y tarjeta);
        // sólo lo urgente (visitas y alertas) también va a la barra de notificaciones.
        const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const aLaVista = ventanas.some((v) => v.visibilityState === 'visible' && v.focused);
        if (aLaVista && !d.urgente) return;

        await self.registration.showNotification(d.titulo || 'RIC Condominios', {
            body: d.cuerpo || '',
            tag: d.tag || undefined,          // el mismo tag que el aviso de la app: no se duplica
            renotify: !!d.tag,
            icon: `${BASE}app/icono-192.png`,
            badge: `${BASE}app/icono-192.png`,
            data: { url: d.url || BASE },
            requireInteraction: !!d.urgente,  // visitas y alertas se quedan hasta que las toquen
            vibrate: d.urgente ? [250, 120, 250, 120, 500] : [120],
        });
    })());
});

// Al tocar una notificación del sistema, abrir (o enfocar) la app en esa pantalla.
self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const destino = new URL(e.notification.data?.url || BASE, self.location.origin).href;
    e.waitUntil((async () => {
        const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const abierta = ventanas.find((v) => v.url.startsWith(self.location.origin));
        if (abierta) {
            await abierta.focus();
            return abierta.navigate(destino);
        }
        return self.clients.openWindow(destino);
    })());
});
