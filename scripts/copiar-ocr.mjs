// Copia a public/vendor/tesseract lo que necesita el lector de placas (OCR),
// para que funcione sin internet y sin mandar fotos a servicios externos.
import { cpSync, mkdirSync } from 'node:fs';

const destino = 'public/vendor/tesseract';
mkdirSync(destino, { recursive: true });

const archivos = [
    ['node_modules/tesseract.js/dist/worker.min.js', 'worker.min.js'],
    ['node_modules/tesseract.js-core/tesseract-core-lstm.wasm.js', 'tesseract-core-lstm.wasm.js'],
    ['node_modules/tesseract.js-core/tesseract-core-simd-lstm.wasm.js', 'tesseract-core-simd-lstm.wasm.js'],
    ['node_modules/tesseract.js-core/tesseract-core-relaxedsimd-lstm.wasm.js', 'tesseract-core-relaxedsimd-lstm.wasm.js'],
    ['node_modules/@tesseract.js-data/eng/4.0.0/eng.traineddata.gz', 'eng.traineddata.gz'],
];
for (const [origen, nombre] of archivos) {
    try {
        cpSync(origen, `${destino}/${nombre}`);
    } catch (e) {
        if (!nombre.includes('relaxedsimd')) throw e; // sólo existe en versiones recientes del núcleo
    }
}
console.log('OCR listo en', destino);
