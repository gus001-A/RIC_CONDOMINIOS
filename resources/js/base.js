/**
 * Publicación en una carpeta del dominio (p. ej. https://ric.sistema-sicad.site/condominios).
 * El servidor indica la carpeta en <meta name="base-path">; en local viene vacía y nada cambia.
 * Las rutas fijas que empiezan con "/" (enlaces, visitas de Inertia, axios, fetch, imágenes…)
 * reciben el prefijo automáticamente, así el código puede seguir escribiendo "/dashboard".
 */
export const BASE = (document.querySelector('meta[name="base-path"]')?.getAttribute('content') ?? '').replace(/\/+$/, '');

const tieneBase = (u) => u === BASE || u.startsWith(`${BASE}/`) || u.startsWith(`${BASE}?`);

/** "/dashboard" → "/condominios/dashboard" (las URL completas o ya prefijadas no se tocan). */
export function conBase(u) {
    if (!BASE || typeof u !== 'string' || !u.startsWith('/') || u.startsWith('//') || tieneBase(u)) return u;
    return `${BASE}${u}`;
}

/** "/condominios/dashboard" → "/dashboard" (para comparar la página actual con las rutas del menú). */
export function sinBase(u) {
    if (!BASE || typeof u !== 'string' || !tieneBase(u)) return u;
    return u.slice(BASE.length) || '/';
}

const ATRIBUTO = { A: 'href', IMG: 'src', IFRAME: 'src', SOURCE: 'src', FORM: 'action', LINK: 'href' };
const SELECTOR = 'a[href^="/"],img[src^="/"],iframe[src^="/"],source[src^="/"],form[action^="/"]';

export function instalarBase(router, axios) {
    if (!BASE) return;

    axios.interceptors.request.use((c) => ({ ...c, url: conBase(c.url) }));

    for (const m of ['visit', 'prefetch']) {
        const original = router[m]?.bind(router);
        if (original) router[m] = (href, ...resto) => original(typeof href === 'string' ? conBase(href) : href, ...resto);
    }

    const fetchOriginal = window.fetch.bind(window);
    window.fetch = (entrada, opciones) => fetchOriginal(typeof entrada === 'string' ? conBase(entrada) : entrada, opciones);
    const openOriginal = window.open.bind(window);
    window.open = (url, ...resto) => openOriginal(typeof url === 'string' ? conBase(url) : url, ...resto);

    // Enlaces e imágenes con ruta fija escritos en las plantillas
    const fijar = (el) => {
        const atr = ATRIBUTO[el.tagName];
        const v = atr && el.getAttribute(atr);
        if (v && conBase(v) !== v) el.setAttribute(atr, conBase(v));
    };
    const recorrer = (n) => {
        if (!(n instanceof Element)) return;
        fijar(n);
        n.querySelectorAll(SELECTOR).forEach(fijar);
    };
    new MutationObserver((cambios) => cambios.forEach((c) => (c.type === 'attributes' ? fijar(c.target) : c.addedNodes.forEach(recorrer))))
        .observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['href', 'src', 'action'] });
    recorrer(document.documentElement);
}
