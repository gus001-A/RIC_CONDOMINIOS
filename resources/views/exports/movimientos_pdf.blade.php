<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Movimientos - {{ $empresa }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 9px;
            padding: 20px;
            background: #fff;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            border-bottom: 3px solid #1a3a5c;
            padding-bottom: 14px;
        }
        .header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 110px;
        }
        .header-logo img {
            max-width: 100px;
            max-height: 60px;
            object-fit: contain;
        }
        .header-texto {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }
        .header h1 {
            font-size: 18px;
            color: #1a3a5c;
            margin-bottom: 3px;
        }
        .header .subtitle {
            font-size: 11px;
            color: #64748b;
        }
        .header .empresa {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
        }
        .header .fecha {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 5px;
        }
        .header-espaciador {
            display: table-cell;
            width: 110px;
        }
        .filtros-info {
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            font-size: 9px;
            color: #475569;
        }
        .filtros-info strong {
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        table thead {
            background: #1a3a5c;
            color: white;
        }
        table thead th {
            padding: 8px 6px;
            text-align: center;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #1a3a5c;
        }
        table tbody td {
            padding: 6px 6px;
            border: 1px solid #e2e8f0;
            text-align: center;
            color: #0f172a;
        }
        table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        table tbody tr:hover {
            background: #f1f5f9;
        }
        .text-left {
            text-align: left !important;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .monto-positive {
            color: #2563eb;
            font-weight: 600;
        }
        .monto-negative {
            color: #dc2626;
            font-weight: 600;
        }
        .monto-neutral {
            color: #94a3b8;
        }
        .saldo-positive {
            color: #dc2626;
            font-weight: 700;
        }
        .saldo-zero {
            color: #10b981;
            font-weight: 700;
        }
        .estatus-pendiente {
            color: #92400e;
            background: #fef3c7;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            font-weight: 700;
            font-size: 8px;
        }
        .estatus-abonado {
            color: #1e40af;
            background: #dbeafe;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            font-weight: 700;
            font-size: 8px;
        }
        .estatus-liquidado {
            color: #166534;
            background: #dcfce7;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            font-weight: 700;
            font-size: 8px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
        }
        .total-row {
            font-weight: 700;
            background: #f1f4f9 !important;
        }
        .total-row td {
            border-top: 2px solid #1a3a5c !important;
        }
        .badge-vencido {
            background: #fecaca;
            color: #991b1b;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 8px;
            display: inline-block;
        }
        .badge-vigente {
            background: #bbf7d0;
            color: #166534;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 8px;
            display: inline-block;
        }
        .row-vencida td {
            background: #fef2f2 !important;
            color: #991b1b !important;
        }
        .row-vencida .monto-negative {
            color: #991b1b !important;
        }
        .row-vencida .monto-positive {
            color: #991b1b !important;
        }
        .row-vencida .saldo-positive {
            color: #991b1b !important;
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    @php
        $logoPath = public_path('logos/logo.png');
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }
    @endphp
    <div class="header">
        <div class="header-logo">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="RIC">
            @endif
        </div>
        <div class="header-texto">
            <h1>Reporte de Movimientos</h1>
            <div class="empresa">{{ $empresa }}</div>
            <div class="subtitle">
                {{ $es_diferidas ? 'Polizas Diferidas' : 'Polizas Normales' }}
            </div>
            <div class="fecha">
                Generado: {{ $fecha_exportacion }} | Total de registros: {{ count($data) }}
            </div>
        </div>
        <div class="header-espaciador"></div>
    </div>

    <!-- FILTROS APLICADOS -->
    @if(count(array_filter($filtros)) > 0)
    <div class="filtros-info">
        <strong>Filtros aplicados:</strong>
        @foreach($filtros as $key => $value)
            @if(!empty($value) && !in_array($key, ['vista', 'mostrar_todos', 'sort_by', 'sort_order']))
                <span style="margin: 0 8px;">
                    <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}
                </span>
            @endif
        @endforeach
    </div>
    @endif

    <!-- TABLA -->
    <table>
        <thead>
            <tr>
                @if($es_diferidas)
                    <th>Vencimiento</th>
                    <th class="text-left">Persona</th>
                    <th class="text-left">Nota</th>
                    <th>Monto</th>
                    <th>Abonado</th>
                    <th>Saldo Pendiente</th>
                    <th>Usuario</th>
                @else
                    <th>Referencia</th>
                    <th>Fecha Poliza</th>
                    <th>Estatus</th>
                    <th class="text-left">Persona</th>
                    <th class="text-left">Cuenta</th>
                    <th class="text-left">Cta. Fondeo</th>
                    <th class="text-left">Nota</th>
                    <th>Monto</th>
                    <th>Usuario</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                @php
                    $isVencida = isset($row['vencimiento']) && $row['vencimiento'] !== '—' && \Carbon\Carbon::createFromFormat('d/m/Y', $row['vencimiento'])->isPast();
                @endphp
                <tr @if($isVencida) class="row-vencida" @endif>
                    @if($es_diferidas)
                        <td>
                            {{ $row['vencimiento'] }}
                            @if($isVencida)
                                <span class="badge-vencido">Vencido</span>
                            @elseif($row['vencimiento'] !== '—')
                                <span class="badge-vigente">Vigente</span>
                            @endif
                        </td>
                        <td class="text-left">{{ $row['persona'] }}</td>
                        <td class="text-left">{{ $row['nota'] }}</td>
                        <td class="text-right {{ strpos($row['monto'], '-') !== false ? 'monto-negative' : 'monto-positive' }}">
                            {{ $row['monto'] }}
                        </td>
                        <td class="text-right monto-positive">{{ $row['abonado'] }}</td>
                        <td class="text-right {{ $row['saldo_pendiente'] === '$0.00' ? 'saldo-zero' : 'saldo-positive' }}">
                            {{ $row['saldo_pendiente'] }}
                        </td>
                        <td>{{ $row['usuario'] }}</td>
                    @else
                        <td><strong>{{ $row['referencia'] }}</strong></td>
                        <td>{{ $row['fecha_poliza'] }}</td>
                        <td>
                            <span class="estatus-{{ strtolower($row['estatus']) }}">
                                {{ $row['estatus'] }}
                            </span>
                        </td>
                        <td class="text-left">{{ $row['persona'] }}</td>
                        <td class="text-left">{{ $row['cuenta'] }}</td>
                        <td class="text-left">{{ $row['cuenta_fondeadora'] }}</td>
                        <td class="text-left">{{ $row['nota'] }}</td>
                        <td class="text-right {{ strpos($row['monto'], '-') !== false ? 'monto-negative' : 'monto-positive' }}">
                            {{ $row['monto'] }}
                        </td>
                        <td>{{ $row['usuario'] }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="100" style="text-align: center; padding: 30px; color: #94a3b8; font-size: 14px;">
                        No hay registros para mostrar
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        <p>
            Reporte generado automaticamente desde RIC - Sistema de Movimientos Contables
            <br>
            {{ $empresa }} - {{ now()->format('Y') }}
        </p>
    </div>
</body>
</html>