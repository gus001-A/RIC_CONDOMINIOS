import { ref } from 'vue';

/**
 * Lee la placa de un vehículo en una foto (OCR con Tesseract.js).
 * Todo corre en el navegador: la foto no se manda a ningún servicio externo.
 * Los archivos del lector se sirven desde /vendor/tesseract (scripts/copiar-ocr.mjs)
 * y se cargan sólo la primera vez que se usa.
 *
 *   const { placa, candidatos } = await leerPlaca(file);
 */
export const progreso = ref(0); // 0–100, para mostrar avance al cargar / leer

// Placas mexicanas: sin I, O, Q ni Ñ, así se evitan confusiones con 1 y 0.
const PERMITIDOS = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789-';

let trabajador = null;
const obtenerTrabajador = () => {
    trabajador ??= import('tesseract.js').then(({ createWorker }) => {
        const base = `${(window.__ricBase || `${window.location.origin}/`).replace(/\/$/, '')}/vendor/tesseract`;
        return createWorker('eng', 1, {
            workerPath: `${base}/worker.min.js`,
            corePath: base,
            langPath: base,
            gzip: true,
            logger: (m) => { if (typeof m.progress === 'number') progreso.value = Math.round(m.progress * 100); },
        });
    }).catch((e) => {
        trabajador = null;
        throw e;
    });
    return trabajador;
};

const cargarImagen = (file) => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('No se pudo abrir la imagen.')); };
    img.src = url;
});

/** Recorta (en proporción 0–1), escala y pasa a escala de grises con más contraste. */
function prepararLienzo(img, recorte = { x: 0, y: 0, w: 1, h: 1 }, ancho = 1200) {
    const sx = img.naturalWidth * recorte.x;
    const sy = img.naturalHeight * recorte.y;
    const sw = img.naturalWidth * recorte.w;
    const sh = img.naturalHeight * recorte.h;
    const escala = Math.min(3, ancho / sw);
    const c = document.createElement('canvas');
    c.width = Math.round(sw * escala);
    c.height = Math.round(sh * escala);
    const g = c.getContext('2d', { willReadFrequently: true });
    g.drawImage(img, sx, sy, sw, sh, 0, 0, c.width, c.height);

    const datos = g.getImageData(0, 0, c.width, c.height);
    const p = datos.data;
    let min = 255;
    let max = 0;
    for (let i = 0; i < p.length; i += 4) {
        const gris = 0.299 * p[i] + 0.587 * p[i + 1] + 0.114 * p[i + 2];
        p[i] = gris;
        if (gris < min) min = gris;
        if (gris > max) max = gris;
    }
    const rango = Math.max(1, max - min);
    for (let i = 0; i < p.length; i += 4) {
        const v = ((p[i] - min) / rango) * 255;
        p[i] = p[i + 1] = p[i + 2] = v;
    }
    g.putImageData(datos, 0, 0);
    return c;
}

/** De todo el texto leído, saca lo que parece placa (5 a 8 caracteres con letras y números). */
export function extraerCandidatos(texto) {
    const vistos = new Map();
    const lineas = texto.toUpperCase().split(/\n+/);
    for (const linea of lineas) {
        const limpia = linea.replace(/[^A-Z0-9\- ]/g, ' ').trim();
        if (!limpia) continue;
        // La línea completa sin espacios ("ABC 123 D") y cada pedazo por separado.
        const piezas = [limpia.replace(/[\s-]/g, ''), ...limpia.split(/\s+/).map((x) => x.replace(/-/g, ''))];
        for (const pieza of piezas) {
            if (pieza.length < 5 || pieza.length > 8) continue;
            const letras = (pieza.match(/[A-Z]/g) || []).length;
            const numeros = (pieza.match(/[0-9]/g) || []).length;
            if (!letras || numeros < 2) continue;
            const puntos = (pieza.length === 6 || pieza.length === 7 ? 3 : 1) + Math.min(letras, 3) + Math.min(numeros, 4);
            if (!vistos.has(pieza) || vistos.get(pieza) < puntos) vistos.set(pieza, puntos);
        }
    }
    return [...vistos.entries()].sort((a, b) => b[1] - a[1]).map(([placa]) => placa);
}

/**
 * Lee la placa de una foto. Primero intenta en la zona central (donde la
 * guía de la cámara pide encuadrarla) y, si no encuentra nada, en toda la foto.
 */
export async function leerPlaca(file) {
    progreso.value = 0;
    const img = await cargarImagen(file);
    const worker = await obtenerTrabajador();

    const intentos = [
        { recorte: { x: 0.12, y: 0.28, w: 0.76, h: 0.44 }, modo: '7' }, // una línea, zona de la guía
        { recorte: { x: 0.12, y: 0.28, w: 0.76, h: 0.44 }, modo: '6' },
        { recorte: { x: 0, y: 0, w: 1, h: 1 }, modo: '11' },            // texto suelto en toda la foto
    ];
    let texto = '';
    for (const intento of intentos) {
        await worker.setParameters({ tessedit_char_whitelist: PERMITIDOS, tessedit_pageseg_mode: intento.modo });
        const { data } = await worker.recognize(prepararLienzo(img, intento.recorte));
        texto += `\n${data.text}`;
        const candidatos = extraerCandidatos(texto);
        if (candidatos.length) return { placa: candidatos[0], candidatos: candidatos.slice(0, 4), texto };
    }
    return { placa: null, candidatos: [], texto };
}
