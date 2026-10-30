import {
    FilePdfOutlined, FileWordOutlined, FileExcelOutlined, FilePptOutlined, FileImageOutlined, FileOutlined, VideoCameraOutlined,
} from '@ant-design/icons-vue';

/** Tipos de archivo, iconos y colores (igual que VenTrack / SURVICOS). */
export function tipoArchivo(nombre = '', mime = null) {
    const ext = (String(nombre).split('.').pop() || '').toLowerCase();
    if (ext === 'pdf' || mime === 'application/pdf') return 'pdf';
    if (['doc', 'docx'].includes(ext)) return 'word';
    if (['xls', 'xlsx'].includes(ext)) return 'excel';
    if (['ppt', 'pptx'].includes(ext)) return 'powerpoint';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'].includes(ext) || mime?.startsWith('image/')) return 'imagen';
    if (['mp4', 'mov', 'webm', '3gp'].includes(ext) || mime?.startsWith('video/')) return 'video';
    return 'otro';
}

export const ICONO_TIPO = {
    pdf: FilePdfOutlined, word: FileWordOutlined, excel: FileExcelOutlined, powerpoint: FilePptOutlined,
    imagen: FileImageOutlined, video: VideoCameraOutlined, otro: FileOutlined,
};

export const COLOR_TIPO = {
    pdf: '#D9363E', word: '#2B5CD8', excel: '#1D8A4E', powerpoint: '#D35230', imagen: '#7C4DDB', video: '#0E7D89', otro: '#5D7085',
};

export const ETIQUETA_TIPO = {
    pdf: 'PDF', word: 'Word', excel: 'Excel', powerpoint: 'PowerPoint', imagen: 'Imagen', video: 'Video', otro: 'Archivo',
};

/** 1536 → "1.5 KB" */
export function tamano(bytes) {
    if (bytes === null || bytes === undefined) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
