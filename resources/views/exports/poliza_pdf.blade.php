<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Póliza {{ $movimiento->poliza->folio ?? 'Sin folio' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
            font-size: 10px;
            color: #1a202c;
            padding: 18px 26px;
            background: #ffffff;
        }

        /* ===== HEADER ===== */
        .header {
            display: table;
            width: 100%;
            padding-bottom: 14px;
            margin-bottom: 16px;
            border-bottom: 3px solid #1a3a5c;
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 110px;
        }
        .header-left img {
            max-width: 100px;
            max-height: 60px;
            object-fit: contain;
        }
        .header-center {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding: 0 10px;
        }
        .header-center .empresa {
            font-size: 15px;
            font-weight: 700;
            color: #1a3a5c;
        }
        .header-center .razon-social {
            font-size: 9px;
            color: #718096;
            margin-top: 1px;
        }
        .header-center .titulo {
            font-size: 11px;
            font-weight: 600;
            color: #4a5568;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-right {
            display: table-cell;
            vertical-align: middle;
            width: 150px;
            text-align: right;
        }
        .header-right .folio-label {
            font-size: 8px;
            color: #a0aec0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-right .folio-value {
            font-size: 20px;
            font-weight: 700;
            color: #1a3a5c;
        }
        .header-right .fecha {
            font-size: 9px;
            color: #718096;
            margin-top: 2px;
        }

        /* ===== BADGES DE ESTADO ===== */
        .badges-row {
            text-align: right;
            margin-bottom: 16px;
        }
        .badge {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 6px;
        }
        .badge-tipo-ingreso { background: #dbeafe; color: #1e40af; }
        .badge-tipo-egreso { background: #fee2e2; color: #991b1b; }
        .badge-tipo-traspaso { background: #ede9fe; color: #5b21b6; }
        .badge-fiscal { background: #dcfce7; color: #166534; }
        .badge-estatus-pendiente { background: #fef3c7; color: #92400e; }
        .badge-estatus-revisado { background: #dbeafe; color: #1e40af; }
        .badge-estatus-autorizado { background: #dcfce7; color: #166534; }
        .badge-estatus-abonado { background: #e0e7ff; color: #3730a3; }
        .badge-estatus-liquidado { background: #dcfce7; color: #166534; }
        .badge-estatus-cerrado { background: #f1f5f9; color: #475569; }
        .badge-estatus-rechazado { background: #fee2e2; color: #991b1b; }
        .badge-vencido { background: #fecaca; color: #991b1b; }

        /* ===== TARJETAS DE SECCIÓN ===== */
        .card {
            border: 1px solid #e8edf4;
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: hidden;
        }
        .card-title {
            background: #f8fafc;
            padding: 6px 14px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #1a3a5c;
            border-bottom: 1px solid #e8edf4;
        }
        .card-body {
            padding: 10px 14px;
        }

        /* ===== FILAS DE DATOS (tabla 2 columnas) ===== */
        table.datos {
            width: 100%;
            border-collapse: collapse;
        }
        table.datos td {
            padding: 4px 0;
            font-size: 9.5px;
            vertical-align: top;
        }
        table.datos td.label {
            width: 34%;
            color: #718096;
            font-weight: 600;
        }
        table.datos td.value {
            color: #1a202c;
            font-weight: 600;
        }

        /* ===== CUENTAS DE TRASPASO ===== */
        .traspaso-flujo {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .traspaso-flujo .caja {
            display: table-cell;
            text-align: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px;
            width: 42%;
        }
        .traspaso-flujo .flecha {
            display: table-cell;
            text-align: center;
            width: 16%;
            font-size: 18px;
            font-weight: 700;
            color: #1a3a5c;
        }
        .traspaso-flujo .caja .label {
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #718096;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 3px;
        }
        .traspaso-flujo .caja .value {
            font-size: 10px;
            font-weight: 700;
            color: #1a202c;
        }

        /* ===== MONTOS ===== */
        .monto-total-box {
            background: #1a3a5c;
            border-radius: 8px;
            padding: 12px 16px;
            text-align: center;
            margin-bottom: 10px;
        }
        .monto-total-box .label {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #a8c4e0;
        }
        .monto-total-box .value {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            margin-top: 2px;
        }
        table.montos {
            width: 100%;
            border-collapse: collapse;
        }
        table.montos td {
            padding: 4px 0;
            font-size: 9.5px;
            border-bottom: 1px solid #f1f5f9;
        }
        table.montos td.label { color: #718096; font-weight: 600; }
        table.montos td.value { text-align: right; font-weight: 600; color: #1a202c; }
        table.montos tr.total-final td {
            border-bottom: none;
            border-top: 2px solid #1a3a5c;
            padding-top: 6px;
            font-weight: 700;
        }
        table.montos tr.total-final td.value { font-size: 13px; color: #1a3a5c; }

        /* ===== ABONOS ===== */
        table.abonos {
            width: 100%;
            border-collapse: collapse;
        }
        table.abonos th {
            background: #f8fafc;
            padding: 5px 8px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            text-align: left;
            border-bottom: 1px solid #e8edf4;
        }
        table.abonos td {
            padding: 5px 8px;
            font-size: 9px;
            border-bottom: 1px solid #f1f5f9;
            color: #1a202c;
        }
        table.abonos td.monto { text-align: right; font-weight: 600; }

        /* ===== OBSERVACIÓN ===== */
        .nota-box {
            background: #f8fafc;
            border-left: 3px solid #1a3a5c;
            padding: 8px 12px;
            font-size: 9.5px;
            color: #2d3748;
            font-style: italic;
            border-radius: 0 6px 6px 0;
        }

        .uuid-text {
            font-size: 7.5px;
            color: #4a5568;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.3px;
        }

        /* ===== FOOTER / FIRMAS ===== */
        .firmas {
            display: table;
            width: 100%;
            margin-top: 30px;
        }
        .firmas .firma-item {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 0 20px;
        }
        .firmas .firma-item .linea {
            border-top: 1px solid #1a202c;
            margin-top: 24px;
            padding-top: 4px;
        }
        .firmas .firma-item .nombre {
            font-size: 9px;
            font-weight: 700;
            color: #1a202c;
        }
        .firmas .firma-item .label {
            font-size: 7.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e8edf4;
            text-align: center;
            font-size: 8px;
            color: #a0aec0;
        }

        @media print {
            .monto-total-box { background: #1a3a5c !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .card-title { background: #f8fafc !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .traspaso-flujo .caja { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('logos/logo.png');
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $estatusClaseMap = [
            'PENDIENTE' => 'badge-estatus-pendiente',
            'CAPTURADO' => 'badge-estatus-pendiente',
            'REVISADO' => 'badge-estatus-revisado',
            'AUTORIZADO' => 'badge-estatus-autorizado',
            'ABONADO' => 'badge-estatus-abonado',
            'LIQUIDADO' => 'badge-estatus-liquidado',
            'CERRADO' => 'badge-estatus-cerrado',
            'RECHAZADO' => 'badge-estatus-rechazado',
        ];
        $estatusActual = $movimiento->poliza->estatus ?? 'PENDIENTE';
        $estatusClase = $estatusClaseMap[$estatusActual] ?? 'badge-estatus-pendiente';

        $tipoPolizaClase = $esTraspaso ? 'badge-tipo-traspaso' : (($movimiento->poliza->tipo_poliza ?? '') === 'EGRESO' ? 'badge-tipo-egreso' : 'badge-tipo-ingreso');

        $vencido = $movimiento->poliza->es_por_pagar
            && $movimiento->poliza->fecha_vencimiento
            && strtotime($movimiento->poliza->fecha_vencimiento) < time();
    @endphp

    <!-- ========== HEADER ========== -->
    <div class="header">
        <div class="header-left">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="Logo">
            @endif
        </div>
        <div class="header-center">
            <div class="empresa">{{ $empresa->nombre_empresa ?? 'EMPRESA' }}</div>
            <div class="razon-social">{{ $empresa->razon_social ?? '' }}</div>
            <div class="titulo">{{ $esTraspaso ? 'Póliza de Traspaso' : 'Póliza de ' . ($movimiento->poliza->tipo_poliza ?? '') }}</div>
        </div>
        <div class="header-right">
            <div class="folio-label">Folio</div>
            <div class="folio-value">{{ $movimiento->poliza->folio ?? '—' }}</div>
            <div class="fecha">{{ $movimiento->poliza->fecha_poliza ? date('d/m/Y', strtotime($movimiento->poliza->fecha_poliza)) : '—' }}</div>
        </div>
    </div>

    <!-- ========== BADGES ========== -->
    <div class="badges-row">
        <span class="badge {{ $tipoPolizaClase }}">{{ $esTraspaso ? 'Traspaso' : ($movimiento->poliza->tipo_poliza ?? '—') }}</span>
        @if($movimiento->poliza->categoria == 'FISCAL')
            <span class="badge badge-fiscal">Fiscal</span>
        @endif
        <span class="badge {{ $estatusClase }}">{{ $movimiento->poliza->estatus_texto ?? $estatusActual }}</span>
        @if($vencido)
            <span class="badge badge-vencido">Vencido</span>
        @endif
    </div>

    <!-- ========== DATOS GENERALES ========== -->
    <div class="card">
        <div class="card-title">Datos Generales</div>
        <div class="card-body">
            @if(!$esTraspaso)
            <table class="datos">
                <tr>
                    <td class="label">Persona</td>
                    <td class="value">{{ $movimiento->poliza->persona->nombre_completo ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Cuenta</td>
                    <td class="value">{{ $movimiento->cuenta->nombre_cuenta ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Cuenta Fondeadora</td>
                    <td class="value">{{ $movimiento->cuentaFondeadora->nombre_cuenta ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Marcador</td>
                    <td class="value">{{ $movimiento->poliza->marcador->nombre_marcador ?? '—' }}</td>
                </tr>
                @if($movimiento->poliza->referencia)
                <tr>
                    <td class="label">Referencia</td>
                    <td class="value">{{ $movimiento->poliza->referencia }}</td>
                </tr>
                @endif
                @if($movimiento->poliza->es_por_pagar)
                <tr>
                    <td class="label">Fecha Vencimiento</td>
                    <td class="value">{{ $movimiento->poliza->fecha_vencimiento ? date('d/m/Y', strtotime($movimiento->poliza->fecha_vencimiento)) : '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Por Pagar</td>
                    <td class="value">{{ $movimiento->poliza->es_por_pagar ? 'Sí' : 'No' }}</td>
                </tr>
                @endif
            </table>
            @else
            <div class="traspaso-flujo">
                <div class="caja">
                    <span class="label">Cuenta Origen</span>
                    <span class="value">{{ $cuentaOrigenNombre ?? '—' }}</span>
                </div>
                <div class="flecha">&raquo;</div>
                <div class="caja">
                    <span class="label">Cuenta Destino</span>
                    <span class="value">{{ $cuentaDestinoNombre ?? '—' }}</span>
                </div>
            </div>
            <table class="datos" style="margin-top: 8px;">
                <tr>
                    <td class="label">Marcador</td>
                    <td class="value">{{ $movimiento->poliza->marcador->nombre_marcador ?? '—' }}</td>
                </tr>
                @if($movimiento->poliza->referencia)
                <tr>
                    <td class="label">Referencia</td>
                    <td class="value">{{ $movimiento->poliza->referencia }}</td>
                </tr>
                @endif
            </table>
            @endif
        </div>
    </div>

    <!-- ========== MONTOS ========== -->
    <div class="card">
        <div class="card-title">Montos</div>
        <div class="card-body">
            <div class="monto-total-box">
                <div class="label">{{ $esTraspaso ? 'Monto del Traspaso' : 'Total' }}</div>
                <div class="value">${{ number_format(abs($montoMostrar), 2) }}</div>
            </div>
            <table class="montos">
                <tr>
                    <td class="label">Base Gravable</td>
                    <td class="value">${{ number_format(abs($movimiento->monto_base ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td class="label">IVA</td>
                    <td class="value">${{ number_format(abs($movimiento->monto_iva ?? 0), 2) }}</td>
                </tr>
                <tr class="total-final">
                    <td class="label">Total con IVA</td>
                    <td class="value">${{ number_format(abs($montoMostrar), 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- ========== DETALLE IVA (DOBLE IVA) ========== -->
    @if(($movimiento->monto_iva_cero ?? 0) != 0 || ($movimiento->monto_iva_dieciseis ?? 0) != 0 || ($movimiento->iva_dieciseis ?? 0) != 0)
    <div class="card">
        <div class="card-title">Desglose de IVA</div>
        <div class="card-body">
            <table class="montos">
                @if(($movimiento->monto_iva_cero ?? 0) != 0)
                <tr>
                    <td class="label">IVA 0% (Exento)</td>
                    <td class="value">${{ number_format(abs($movimiento->monto_iva_cero), 2) }}</td>
                </tr>
                @endif
                @if(($movimiento->monto_iva_dieciseis ?? 0) != 0)
                <tr>
                    <td class="label">IVA 16% (Base)</td>
                    <td class="value">${{ number_format(abs($movimiento->monto_iva_dieciseis), 2) }}</td>
                </tr>
                <tr>
                    <td class="label">IVA 16% (Calculado)</td>
                    <td class="value">${{ number_format(abs($movimiento->iva_dieciseis), 2) }}</td>
                </tr>
                @endif
                <tr class="total-final">
                    <td class="label">Total IVA</td>
                    <td class="value">${{ number_format(abs(($movimiento->monto_iva_cero ?? 0) + ($movimiento->monto_iva_dieciseis ?? 0) + ($movimiento->iva_dieciseis ?? 0)), 2) }}</td>
                </tr>
            </table>
        </div>
    </div>
    @endif

    <!-- ========== SALDOS Y ABONOS (SI ES POR PAGAR) ========== -->
    @if($movimiento->poliza->es_por_pagar)
    <div class="card">
        <div class="card-title">Saldo</div>
        <div class="card-body">
            <table class="montos">
                <tr>
                    <td class="label">Total Abonado</td>
                    <td class="value">${{ number_format(abs($totalAbonado), 2) }}</td>
                </tr>
                <tr class="total-final">
                    <td class="label">Saldo Pendiente</td>
                    <td class="value">${{ number_format(abs($saldoPendiente), 2) }}</td>
                </tr>
            </table>

            @if($abonos->count() > 0)
            <table class="abonos" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Referencia</th>
                        <th style="text-align: right;">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($abonos as $abono)
                    <tr>
                        <td>{{ $abono->fecha_abono ? date('d/m/Y', strtotime($abono->fecha_abono)) : '—' }}</td>
                        <td>{{ $abono->referencia ?? '—' }}</td>
                        <td class="monto">${{ number_format(abs($abono->monto_abonado), 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @endif

    <!-- ========== FACTURACIÓN ========== -->
    @if($movimiento->poliza->categoria === 'FISCAL')
    <div class="card">
        <div class="card-title">Facturación</div>
        <div class="card-body">
            <table class="datos">
                <tr>
                    <td class="label">Fecha Factura</td>
                    <td class="value">{{ $movimiento->poliza->fecha_factura ? date('d/m/Y', strtotime($movimiento->poliza->fecha_factura)) : '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Número</td>
                    <td class="value">{{ $movimiento->poliza->numero_factura ?? '—' }}</td>
                </tr>
                @if($movimiento->poliza->serie_factura)
                <tr>
                    <td class="label">Serie</td>
                    <td class="value">{{ $movimiento->poliza->serie_factura }}</td>
                </tr>
                @endif
                @if($movimiento->poliza->folio_factura)
                <tr>
                    <td class="label">Folio Fiscal</td>
                    <td class="value">{{ $movimiento->poliza->folio_factura }}</td>
                </tr>
                @endif
                @if($movimiento->poliza->uuid_factura)
                <tr>
                    <td class="label">UUID</td>
                    <td class="value"><span class="uuid-text">{{ $movimiento->poliza->uuid_factura }}</span></td>
                </tr>
                @endif
                <tr>
                    <td class="label">Documentos</td>
                    <td class="value">
                        PDF: {{ $movimiento->poliza->ruta_pdf ? 'Adjunto' : '—' }}
                        &nbsp;&nbsp;|&nbsp;&nbsp;
                        XML: {{ $movimiento->poliza->ruta_xml ? 'Adjunto' : '—' }}
                    </td>
                </tr>
            </table>
        </div>
    </div>
    @endif

    <!-- ========== OBSERVACIONES ========== -->
    @if($movimiento->poliza->nota)
    <div class="card">
        <div class="card-title">Observaciones</div>
        <div class="card-body">
            <div class="nota-box">{{ $movimiento->poliza->nota }}</div>
        </div>
    </div>
    @endif

    <!-- ========== FIRMAS ========== -->
    <div class="firmas">
        <div class="firma-item">
            <div class="linea">
                <div class="nombre">{{ Auth::user()->nombre_completo ?? '—' }}</div>
                <div class="label">Nombre y firma de quien entrega</div>
            </div>
        </div>
        <div class="firma-item">
            <div class="linea">
                <div class="nombre">{{ $movimiento->poliza->persona->nombre_completo ?? '—' }}</div>
                <div class="label">Nombre y firma de quien recibe</div>
            </div>
        </div>
    </div>

    <!-- ========== FOOTER ========== -->
    <div class="footer">
        Documento generado por RIC — Red Informática Contable<br>
        Impreso por {{ Auth::user()->nombre_completo ?? 'Sistema' }} el {{ $fecha_exportacion }}
    </div>
</body>
</html>
