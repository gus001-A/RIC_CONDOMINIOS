<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $r['titulo'] }}</title>
    <style>
        @page { margin: 26px 24px 40px 24px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #0f2a47; }
        .encabezado { border-bottom: 2px solid #1a5a96; padding-bottom: 8px; margin-bottom: 10px; }
        .encabezado h1 { font-size: 16px; margin: 0 0 2px; color: #0f2a47; }
        .encabezado p { margin: 0; color: #5d7085; font-size: 9px; }
        .resumen { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 10px; }
        .resumen td { background: #f3f6fa; border: 1px solid #e1e8f0; border-radius: 6px; padding: 6px 8px; }
        .resumen .lbl { color: #5d7085; font-size: 7.5px; text-transform: uppercase; }
        .resumen .val { font-size: 12px; font-weight: bold; margin-top: 2px; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th { background: #1a3a5c; color: #fff; font-size: 7.8px; text-align: left; padding: 5px 4px; }
        table.datos td { padding: 4px; border-bottom: 1px solid #e1e8f0; vertical-align: top; }
        table.datos tr:nth-child(even) td { background: #f7f9fc; }
        table.datos .der { text-align: right; white-space: nowrap; }
        table.datos tr.total td { background: #e8f0fa; font-weight: bold; border-top: 1.5px solid #1a3a5c; }
        .vacio { text-align: center; color: #5d7085; padding: 20px; }
        .pie { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7.5px; color: #8193a6; }
        .pie .num:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="encabezado">
        <h1>{{ $r['titulo'] }}</h1>
        <p>{{ $r['subtitulo'] }}</p>
    </div>

    @if (count($r['resumen']))
        <table class="resumen">
            <tr>
                @foreach ($r['resumen'] as $k)
                    <td>
                        <div class="lbl">{{ $k['label'] }}</div>
                        <div class="val" style="color: {{ $k['color'] ?? '#0f2a47' }}">{{ $fmt($k['valor'], $k['tipo'] ?? 'texto') }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="datos">
        <thead>
            <tr>
                @foreach ($r['columnas'] as $c)
                    <th class="{{ in_array($c['tipo'], ['moneda', 'numero', 'porcentaje']) ? 'der' : '' }}">{{ $c['titulo'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($r['filas'] as $f)
                <tr>
                    @foreach ($r['columnas'] as $c)
                        <td class="{{ in_array($c['tipo'], ['moneda', 'numero', 'porcentaje']) ? 'der' : '' }}">{{ $fmt($f[$c['key']] ?? null, $c['tipo']) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($r['columnas']) }}" class="vacio">Sin registros para este periodo.</td></tr>
            @endforelse
            @if (count($r['totales']) && count($r['filas']))
                <tr class="total">
                    @foreach ($r['columnas'] as $i => $c)
                        <td class="{{ in_array($c['tipo'], ['moneda', 'numero', 'porcentaje']) ? 'der' : '' }}">
                            @if ($i === 0) TOTAL
                            @elseif (array_key_exists($c['key'], $r['totales'])) {{ $fmt($r['totales'][$c['key']], $c['tipo']) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    <div class="pie">
        RIC Condominios · Generó {{ $usuario }} el {{ $generado }} · {{ count($r['filas']) }} registro(s) · Página <span class="num"></span>
    </div>
</body>
</html>
