/**
 * Tema de Ant Design para RIC Condominios. Misma estructura visual que VenTrack
 * y SURVICOS (tarjetas, tablas con filtros al pie, modales) con la paleta de RIC.
 */
export const palette = {
    navy: '#0F2A47',   // texto y barras oscuras
    blue: '#1A5A96',   // primario (azul RIC más vivo que el #1a3a5c del logo)
    teal: '#1597A5',
    green: '#1FAF5C',
    amber: '#F0A202',
    red: '#D9363E',
    purple: '#6E4BC2',
    bg: '#F3F6FA',
    line: '#E1E8F0',
    muted: '#5D7085',
};

export const theme = {
    token: {
        colorPrimary: palette.blue,
        colorInfo: palette.blue,
        colorSuccess: palette.green,
        colorWarning: palette.amber,
        colorError: palette.red,
        colorLink: palette.blue,
        colorTextBase: palette.navy,
        colorBgLayout: palette.bg,
        colorBorderSecondary: palette.line,
        borderRadius: 10,
        fontFamily: "Figtree, system-ui, -apple-system, 'Segoe UI', sans-serif",
        fontSize: 14,
        controlHeight: 40,
        controlHeightSM: 32,
        controlHeightLG: 46,
    },
};
