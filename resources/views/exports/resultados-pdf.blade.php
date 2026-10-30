<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cuentas de Resultados - {{ $empresa }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
            font-size: 9.5px;
            padding: 15px 20px;
            background: #ffffff;
            color: #1a202c;
        }

        .reporte-container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            padding: 15px 20px;
        }

        /* ===== HEADER ===== */
        .header {
            display: table;
            width: 100%;
            padding-bottom: 12px;
            margin-bottom: 14px;
            border-bottom: 2px solid #e8edf4;
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 140px;
            text-align: left;
        }
        .header-left img {
            max-width: 130px;
            max-height: 80px;
            object-fit: contain;
        }
        .header-center {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding: 0 10px;
        }
        .header-center h1 {
            font-size: 18px;
            font-weight: 700;
            color: #1a365d;
            letter-spacing: 0.5px;
        }
        .header-center .empresa {
            font-size: 13px;
            font-weight: 600;
            color: #2d3748;
        }
        .header-center .subtitle {
            font-size: 10px;
            color: #718096;
        }
        .header-center .fecha {
            font-size: 9px;
            color: #a0aec0;
            margin-top: 2px;
        }
        .header-center .fecha span {
            font-weight: 600;
            color: #4a5568;
        }
        .header-right {
            display: table-cell;
            vertical-align: middle;
            width: 80px;
            text-align: right;
        }
        .header-right .badge {
            background: #1a365d;
            color: white;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            display: inline-block;
        }

        /* ===== FILTROS ===== */
        .filtros {
            background: #f8fafc;
            padding: 8px 14px;
            border-radius: 6px;
            margin-bottom: 14px;
            border: 1px solid #e9edf4;
            font-size: 8.5px;
            color: #4a5568;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 16px;
        }
        .filtros strong {
            color: #1a202c;
            font-weight: 700;
        }
        .filtros .item {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
        }
        .filtros .item .label {
            font-weight: 600;
            color: #718096;
        }
        .filtros .item .value {
            font-weight: 600;
            color: #2d3748;
        }
        .filtros .item .value.destacado {
            color: #2b6cb0;
            font-weight: 700;
        }
        .filtros .item .value.iva {
            color: #7c3aed;
            font-weight: 700;
        }

        /* ===== RESUMEN DE INGRESOS Y EGRESOS ===== */
        .resumen-seccion {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 14px;
            margin-bottom: 14px;
        }
        .resumen-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e9edf4;
            padding: 10px 14px;
        }
        .resumen-card .titulo {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e9edf4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .resumen-card .titulo .total {
            font-size: 14px;
            font-weight: 700;
        }
        .resumen-card .titulo .total.ingreso { color: #2b6cb0; }
        .resumen-card .titulo .total.egreso { color: #e53e3e; }

        .resumen-card .items {
            margin-top: 6px;
        }
        .resumen-card .item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px dashed #f0f2f5;
            font-size: 8.5px;
        }
        .resumen-card .item:last-child {
            border-bottom: none;
        }
        .resumen-card .item .nombre {
            color: #4a5568;
        }
        .resumen-card .item .monto {
            font-weight: 600;
        }
        .resumen-card .item .monto.ingreso { color: #2b6cb0; }
        .resumen-card .item .monto.egreso { color: #e53e3e; }

        .resumen-card.ingresos-card .titulo {
            border-bottom-color: #2b6cb0;
        }
        .resumen-card.egresos-card .titulo {
            border-bottom-color: #e53e3e;
        }

        /* ===== TABLA ===== */
        .tabla-wrapper {
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #e9edf4;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }
        table thead {
            background: #1a365d;
            color: white;
        }
        table thead th {
            padding: 7px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border: none;
        }
        table thead th.text-left {
            text-align: left !important;
        }
        table thead th.text-right {
            text-align: right !important;
        }
        table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid #f0f2f5;
            text-align: center;
            color: #2d3748;
        }
        table tbody td.text-left {
            text-align: left !important;
        }
        table tbody td.text-right {
            text-align: right !important;
        }
        table tbody tr:last-child td {
            border-bottom: none;
        }
        table tbody tr:nth-child(even) {
            background: #fafbfc;
        }

        /* ===== FILAS MADRE ===== */
        .row-madre {
            background: #f0f7ff !important;
            font-weight: 700;
        }
        .row-madre td {
            border-top: 1px solid #d6e8ff !important;
            border-bottom: 1px solid #d6e8ff !important;
            padding: 6px 10px !important;
        }
        .row-madre td:first-child {
            border-left: 3px solid #3182ce;
            padding-left: 12px !important;
        }

        /* ===== FILAS HIJA ===== */
        .row-hija td:first-child {
            padding-left: 30px !important;
            text-align: left !important;
        }
        .row-hija .nivel-2 {
            padding-left: 42px !important;
        }
        .row-hija .nivel-3 {
            padding-left: 54px !important;
        }
        .row-hija .nivel-4 {
            padding-left: 66px !important;
        }

        /* ===== MONTOS ===== */
        .monto-positive { color: #2b6cb0; font-weight: 600; }
        .monto-negative { color: #e53e3e; font-weight: 600; }
        .monto-neutral { color: #a0aec0; }
        .monto-iva { color: #7c3aed; font-weight: 600; }

        /* ===== BADGES ===== */
        .fiscal-badge {
            display: inline-block;
            padding: 1px 10px;
            background: #48bb78;
            color: white;
            border-radius: 10px;
            font-size: 6.5px;
            font-weight: 700;
            margin-left: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ===== TOTALES ===== */
        .totales {
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .total-card {
            background: #fafbfc;
            border-radius: 6px;
            padding: 5px 14px;
            border: 1px solid #e9edf4;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .total-card .label {
            font-size: 7px;
            font-weight: 700;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .total-card .value {
            font-size: 14px;
            font-weight: 700;
            color: #1a202c;
        }
        .total-card .value.ingreso { color: #2b6cb0; }
        .total-card .value.egreso { color: #e53e3e; }
        .total-card .value.iva { color: #7c3aed; }
        .total-card .value.neutro { color: #1a202c; }

        .total-card.ingresos {
            border-left: 4px solid #2b6cb0;
        }
        .total-card.egresos {
            border-left: 4px solid #e53e3e;
        }
        .total-card.iva {
            border-left: 4px solid #7c3aed;
        }
        .total-card.destacada {
            background: #1a365d;
            border-color: #1a365d;
        }
        .total-card.destacada .label {
            color: rgba(255, 255, 255, 0.7);
        }
        .total-card.destacada .value {
            color: white;
        }
        .separador {
            color: #d1d5db;
            font-size: 16px;
            font-weight: 300;
        }

        /* ===== UTILIDADES ===== */
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }

        /* ===== IMPRESIÓN ===== */
        @media print {
            body { padding: 10px; }
            .reporte-container { padding: 10px 12px; }
            .header-left img { max-width: 100px; max-height: 60px; }
            .header-center h1 { font-size: 16px; }
            table { font-size: 7.5px; }
            table thead th { padding: 4px 6px; font-size: 7px; }
            table tbody td { padding: 3px 6px; }
            .total-card { padding: 3px 10px; }
            .total-card .value { font-size: 12px; }
            .badge { background: #1a365d !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .fiscal-badge { background: #48bb78 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .row-madre { background: #f0f7ff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .filtros { background: #f8fafc !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .total-card { background: #fafbfc !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .total-card.destacada { background: #1a365d !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .resumen-card { background: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .resumen-seccion { display: grid !important; }
        }

        @media (max-width: 768px) {
            .totales { flex-wrap: wrap; }
            .separador { display: none; }
            .header { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; }
            .header-left { display: block; width: auto; text-align: center; }
            .header-center { display: block; width: 100%; }
            .header-right { display: block; width: auto; text-align: center; }
            .filtros { gap: 4px 10px; }
            .resumen-seccion { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="reporte-container">
        <!-- ===== HEADER ===== -->
        <div class="header">
            <div class="header-left">
                @php
                    $logoPath = public_path('logos/logo.png');
                    $logoBase64 = '';
                    if (file_exists($logoPath)) {
                        $logoData = file_get_contents($logoPath);
                        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
                    }
                @endphp
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Logo">
                @endif
            </div>
            <div class="header-center">
                <h1>Cuentas de Resultados</h1>
                <div class="empresa">{{ $empresa }}</div>
                <div class="subtitle">Reporte de Cuentas de Resultados</div>
                <div class="fecha">
                    Generado: <span>{{ $fecha_exportacion }}</span> &nbsp;|&nbsp; Total de cuentas: <span>{{ $total_cuentas }}</span>
                </div>
            </div>
            <div class="header-right">
                <span class="badge">{{ $total_cuentas }}</span>
            </div>
        </div>

        <!-- ===== FILTROS ===== -->
        <div class="filtros">
            <strong>Filtros:</strong>
            <span class="item">
                <span class="label">Fecha:</span>
                <span class="value">{{ $fecha_desde ?: 'Inicio' }} - {{ $fecha_hasta ?: 'Actual' }}</span>
            </span>
            <span class="item">
                <span class="label">Tipo:</span>
                <span class="value">{{ ucfirst($tipo_filtro) }}</span>
            </span>
            <span class="item">
                <span class="label">Cuentas:</span>
                <span class="value">{{ $total_cuentas }}</span>
            </span>
            <span class="item">
                <span class="label">Saldo:</span>
                <span class="value destacado">${{ number_format(abs($saldo_total), 2) }}</span>
            </span>
            @if($tipo_filtro === 'fiscales')
                <span class="item">
                    <span class="label">IVA:</span>
                    <span class="value iva">${{ number_format($iva_total ?? 0, 2) }}</span>
                </span>
            @endif
        </div>

        <!-- ===== RESUMEN DE INGRESOS Y EGRESOS ===== -->
        <div class="resumen-seccion">
            <!-- INGRESOS -->
            <div class="resumen-card ingresos-card">
                <div class="titulo">
                    <span>Total Ingresos</span>
                    <span class="total ingreso">${{ number_format($total_ingresos ?? 0, 2) }}</span>
                </div>
                <div class="items">
                    @php
                        $cuentasIngreso = collect($cuentas)->filter(function($padre) {
                            return ($padre['subtotal'] ?? 0) > 0;
                        })->sortByDesc('subtotal')->take(10);
                    @endphp
                    @forelse($cuentasIngreso as $cuenta)
                        <div class="item">
                            <span class="nombre">{{ $cuenta['nombre_cuenta'] }}</span>
                            <span class="monto ingreso">${{ number_format(abs($cuenta['subtotal'] ?? 0), 2) }}</span>
                        </div>
                    @empty
                        <div class="item" style="color: #a0aec0; font-style: italic;">
                            <span>No hay ingresos registrados</span>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- EGRESOS -->
            <div class="resumen-card egresos-card">
                <div class="titulo">
                    <span>Total Egresos</span>
                    <span class="total egreso">${{ number_format($total_egresos ?? 0, 2) }}</span>
                </div>
                <div class="items">
                    @php
                        $cuentasEgreso = collect($cuentas)->filter(function($padre) {
                            return ($padre['subtotal'] ?? 0) < 0;
                        })->sortBy('subtotal')->take(10);
                    @endphp
                    @forelse($cuentasEgreso as $cuenta)
                        <div class="item">
                            <span class="nombre">{{ $cuenta['nombre_cuenta'] }}</span>
                            <span class="monto egreso">${{ number_format(abs($cuenta['subtotal'] ?? 0), 2) }}</span>
                        </div>
                    @empty
                        <div class="item" style="color: #a0aec0; font-style: italic;">
                            <span>No hay egresos registrados</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ===== TABLA DE CUENTAS ===== -->
        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60%;" class="text-left">Nombre de la Cuenta</th>
                        <th style="width: 20%;" class="text-right">Saldo</th>
                        @if($tipo_filtro === 'fiscales')
                            <th style="width: 20%;" class="text-right">IVA</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalIvaGeneral = 0;
                    @endphp
                    @forelse($cuentas as $padre)
                        @php
                            $saldoPadre = (float) ($padre['subtotal'] ?? 0);
                            $saldoPadreAbs = abs($saldoPadre);
                            $claseSaldoPadre = $saldoPadre >= 0 ? 'monto-positive' : 'monto-negative';
                            $tieneHijas = !empty($padre['hijas']);
                            $ivaPadre = (float) ($padre['iva'] ?? 0);
                            $totalIvaGeneral += $ivaPadre;
                        @endphp

                        <tr class="row-madre">
                            <td class="text-left">
                                <strong>{{ $padre['nombre_cuenta'] }}</strong>
                                @if($tipo_filtro === 'fiscales')
                                    <span class="fiscal-badge">FISCAL</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <span class="{{ $claseSaldoPadre }}">${{ number_format($saldoPadreAbs, 2) }}</span>
                            </td>
                            @if($tipo_filtro === 'fiscales')
                                <td class="text-right">
                                    <span class="monto-iva">${{ number_format($ivaPadre, 2) }}</span>
                                </td>
                            @endif
                        </tr>

                        @if($tieneHijas)
                            @foreach($padre['hijas'] as $hija)
                                @php
                                    $saldoHija = (float) ($hija['saldo'] ?? 0);
                                    $saldoHijaAbs = abs($saldoHija);
                                    $claseSaldoHija = $saldoHija >= 0 ? 'monto-positive' : 'monto-negative';
                                    $nivel = (int) ($hija['nivel'] ?? 2);
                                    $claseNivel = 'nivel-' . min($nivel, 4);
                                    $ivaHija = (float) ($hija['iva'] ?? 0);
                                @endphp
                                <tr class="row-hija">
                                    <td class="text-left {{ $claseNivel }}">
                                        {{ $hija['nombre_cuenta'] }}
                                        @if(isset($hija['es_fiscal']) && $hija['es_fiscal'])
                                            <span class="fiscal-badge">FISCAL</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <span class="{{ $claseSaldoHija }}">${{ number_format($saldoHijaAbs, 2) }}</span>
                                    </td>
                                    @if($tipo_filtro === 'fiscales')
                                        <td class="text-right">
                                            <span class="monto-iva">${{ number_format($ivaHija, 2) }}</span>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ $tipo_filtro === 'fiscales' ? 3 : 2 }}" style="text-align: center; padding: 30px; color: #a0aec0; font-size: 12px;">
                                No hay cuentas de resultados para mostrar
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($tipo_filtro === 'fiscales' && !empty($cuentas))
                    <tfoot>
                        <tr style="background: #f0f7ff; font-weight: 700; border-top: 2px solid #3182ce;">
                            <td class="text-right" style="padding: 8px 10px; font-size: 9px; text-transform: uppercase; color: #1a365d;">
                                Total IVA General
                            </td>
                            <td class="text-right" style="padding: 8px 10px;"></td>
                            <td class="text-right" style="padding: 8px 10px; font-size: 11px; color: #7c3aed;">
                                ${{ number_format($totalIvaGeneral, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <!-- ===== TOTALES ===== -->
        <div class="totales">
            <div class="total-card">
                <span class="label">Cuentas</span>
                <span class="value neutro">{{ $total_cuentas }}</span>
            </div>
            <span class="separador">|</span>
            <div class="total-card ingresos">
                <span class="label">Total Ingresos</span>
                <span class="value ingreso">${{ number_format($total_ingresos ?? 0, 2) }}</span>
            </div>
            <span class="separador">|</span>
            <div class="total-card egresos">
                <span class="label">Total Egresos</span>
                <span class="value egreso">${{ number_format($total_egresos ?? 0, 2) }}</span>
            </div>
            @if($tipo_filtro === 'fiscales')
                <span class="separador">|</span>
                <div class="total-card iva">
                    <span class="label">Total IVA</span>
                    <span class="value iva">${{ number_format($iva_total ?? 0, 2) }}</span>
                </div>
            @endif
            <span class="separador">|</span>
            <div class="total-card destacada">
                <span class="label">Saldo Total</span>
                <span class="value">${{ number_format(abs($saldo_total), 2) }}</span>
            </div>
        </div>
    </div>
</body>
</html>