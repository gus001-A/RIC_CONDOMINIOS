import {
    UserDeleteOutlined, ShoppingOutlined, WarningOutlined, EyeOutlined, FireOutlined, MedicineBoxOutlined, NotificationOutlined, BellOutlined,
} from '@ant-design/icons-vue';

/** Icono de Ant Design por tipo de alerta vecinal (AlertaSeguridad::TIPOS). */
const ICONOS = {
    INTRUSO: UserDeleteOutlined,
    ROBO: ShoppingOutlined,
    AGRESION: WarningOutlined,
    SOSPECHOSO: EyeOutlined,
    INCENDIO: FireOutlined,
    EMERGENCIA_MEDICA: MedicineBoxOutlined,
    OTRO: NotificationOutlined,
};

export const iconoAlerta = (tipo) => ICONOS[tipo] || BellOutlined;

export const colorAlerta = (estatus) => ({ ACTIVA: '#d9363e', ATENDIDA: '#1faf5c' }[estatus] || '#94a3b8');
