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
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            color: #000;
            background: #fff;
            padding: 12px;
            max-width: 380px;
            margin: 0 auto;
            line-height: 1.4;
        }

        .ticket {
            border: 1px solid #000;
            padding: 14px 14px 12px;
            background: #fff;
        }

        /* HEADER */
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .header .logo-container {
            margin-bottom: 4px;
        }

        .header .logo {
            max-width: 70px;
            max-height: 45px;
            display: block;
            margin: 0 auto;
        }

        .header .empresa {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .header .empresa-line {
            font-size: 9px;
            letter-spacing: 0.5px;
            color: #333;
        }

        .header .folio {
            font-size: 12px;
            font-weight: 700;
            margin-top: 6px;
            background: #000;
            color: #fff;
            padding: 2px 14px;
            display: inline-block;
            letter-spacing: 1px;
        }

        .header .tipo {
            font-size: 11px;
            font-weight: 700;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        .header .fecha {
            font-size: 10px;
            color: #333;
            margin-top: 2px;
        }

        .header .status {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 4px;
            padding: 2px 12px;
            display: inline-block;
            border: 1px solid #000;
            letter-spacing: 0.5px;
        }

        .tag-fiscal {
            font-size: 7px;
            font-weight: 700;
            padding: 1px 6px;
            border: 1px solid #000;
            margin-left: 4px;
        }

        .badge-por-pagar {
            font-size: 7px;
            font-weight: 700;
            padding: 1px 6px;
            border: 1px solid #000;
            margin-left: 4px;
        }

        .badge-vencido {
            font-size: 7px;
            font-weight: 700;
            padding: 1px 6px;
            border: 1px solid #000;
            margin-left: 4px;
        }

        /* SECTIONS */
        .section {
            margin-bottom: 8px;
        }

        .section-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
            margin-bottom: 5px;
        }

        /* ROWS */
        .row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            border-bottom: 1px dotted #ccc;
        }

        .row:last-child {
            border-bottom: none;
        }

        .row .label {
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .row .value {
            font-weight: 600;
            text-align: right;
            font-size: 10px;
        }

        /* MONTO DESTACADO */
        .monto-destacado {
            text-align: center;
            padding: 8px 0;
            margin: 4px 0 6px;
            border: 1px solid #000;
            background: #f4f4f4;
        }

        .monto-destacado .label {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }

        .monto-destacado .value {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* CUENTAS TRASPASO */
        .cuentas-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 8px;
            border: 1px solid #000;
            margin: 4px 0 6px;
            background: #f4f4f4;
        }

        .cuentas-box .cuenta {
            text-align: center;
            padding: 2px 4px;
            flex: 1;
        }

        .cuentas-box .cuenta .label {
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }

        .cuentas-box .cuenta .value {
            font-size: 9px;
            font-weight: 600;
            margin-top: 2px;
        }

        .cuentas-box .flecha {
            font-size: 16px;
            font-weight: 700;
            padding: 0 6px;
        }

        /* ABONOS */
        .abono-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            font-size: 9px;
            border-bottom: 1px dotted #ccc;
        }

        .abono-row .fecha {
            flex: 1;
        }
        .abono-row .monto {
            flex: 1;
            text-align: right;
            font-weight: 600;
        }
        .abono-row .ref {
            flex: 1;
            text-align: center;
            font-size: 8px;
            color: #333;
        }

        .abono-total {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-weight: 700;
            border-top: 2px solid #000;
            margin-top: 2px;
        }

        .abono-total .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .abono-total .value {
            font-size: 12px;
        }

        /* IVA ROWS */
        .iva-row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            font-size: 8px;
            color: #000;
        }

        /* FOOTER */
        .footer {
            text-align: center;
            border-top: 2px dashed #000;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 9px;
        }

        .footer .line {
            margin: 3px 0;
        }

        .footer .firma {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #000;
            display: flex;
            justify-content: space-between;
        }

        .footer .firma .firma-item {
            text-align: center;
            flex: 1;
            padding: 0 4px;
        }

        .footer .firma .firma-item .linea {
            display: block;
            width: 100%;
            border-bottom: 1px solid #000;
            margin-top: 18px;
            padding-bottom: 2px;
        }

        .footer .firma .firma-item .label {
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .footer .firma .firma-item .nombre {
            font-size: 8px;
            font-weight: 600;
        }

        .nota-text {
            font-size: 9px;
            padding: 4px 8px;
            border-left: 2px solid #000;
            margin: 4px 0;
            font-style: italic;
            background: #f4f4f4;
        }

        .uuid-text {
            font-size: 7px;
            background: #eee;
            padding: 1px 6px;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.3px;
        }

        .separator-dots {
            text-align: center;
            font-size: 9px;
            letter-spacing: 3px;
            margin: 6px 0 4px;
            color: #333;
        }

        .impreso-por {
            font-size: 8px;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        /* PRINT */
        @media print {
            body {
                padding: 6px;
                max-width: 100%;
                margin: 0;
            }

            .ticket {
                border: 1px solid #000;
                max-width: 380px;
                margin: 0 auto;
            }

            .header .folio {
                background: #000 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .monto-destacado,
            .cuentas-box,
            .nota-text,
            .uuid-text {
                background: #f4f4f4 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .header .logo {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <!-- ========== HEADER ========== -->
        <div class="header">
            @php
                $logoPath = public_path('logos/logo.png');
                $logoData = null;
                if (file_exists($logoPath)) {
                    $imageData = file_get_contents($logoPath);
                    $logoData = base64_encode($imageData);
                }
            @endphp

            @if($logoData)
            <div class="logo-container">
                <img src="data:image/png;base64,{{ $logoData }}" alt="Logo" class="logo">
            </div>
            @endif

            <div class="empresa">{{ $empresa->nombre_empresa ?? 'EMPRESA' }}</div>
            <div class="empresa-line">{{ $empresa->razon_social ?? '—' }}</div>
            <div class="folio">FOLIO: {{ $movimiento->poliza->folio ?? 'SIN FOLIO' }}</div>
            <div class="tipo">
                {{ $esTraspaso ? 'TRASPASO' : ($movimiento->poliza->tipo_poliza ?? '—') }}
                @if($movimiento->poliza->categoria == 'FISCAL')
                    <span class="tag-fiscal">FISCAL</span>
                @endif
                @if($movimiento->poliza->es_por_pagar)
                    <span class="badge-por-pagar">POR PAGAR</span>
                @endif
            </div>
            <div class="fecha">{{ $movimiento->poliza->fecha_poliza ? date('d/m/Y', strtotime($movimiento->poliza->fecha_poliza)) : '—' }}</div>
            <div>
                <span class="status">
                    {{ $movimiento->poliza->estatus_texto ?? $movimiento->poliza->estatus ?? 'PENDIENTE' }}
                </span>
            </div>
        </div>

        <!-- ========== DATOS ========== -->
        <div class="section">
            <div class="section-title">Datos</div>

            @if(!$esTraspaso)
            <div class="row">
                <span class="label">Persona</span>
                <span class="value">{{ $movimiento->poliza->persona->nombre_completo ?? '—' }}</span>
            </div>
            <div class="row">
                <span class="label">Cuenta</span>
                <span class="value">{{ $movimiento->cuenta->nombre_cuenta ?? '—' }}</span>
            </div>
            <div class="row">
                <span class="label">Cta. Fondeadora</span>
                <span class="value">{{ $movimiento->cuentaFondeadora->nombre_cuenta ?? '—' }}</span>
            </div>
            @endif

            @if($esTraspaso)
            <div class="cuentas-box">
                <div class="cuenta">
                    <span class="label">Origen</span>
                    <span class="value">{{ $movimiento->cuentaFondeadora->nombre_cuenta ?? '—' }}</span>
                </div>
                <div class="flecha">→</div>
                <div class="cuenta">
                    <span class="label">Destino</span>
                    <span class="value">{{ $movimiento->cuenta->nombre_cuenta ?? '—' }}</span>
                </div>
            </div>
            @endif

            <div class="row">
                <span class="label">Marcador</span>
                <span class="value">{{ $movimiento->poliza->marcador->nombre_marcador ?? '—' }}</span>
            </div>
            @if($movimiento->poliza->referencia)
            <div class="row">
                <span class="label">Referencia</span>
                <span class="value">{{ $movimiento->poliza->referencia }}</span>
            </div>
            @endif
            @if($movimiento->poliza->es_por_pagar)
            <div class="row">
                <span class="label">Vencimiento</span>
                <span class="value">
                    {{ $movimiento->poliza->fecha_vencimiento ? date('d/m/Y', strtotime($movimiento->poliza->fecha_vencimiento)) : '—' }}
                    @if($movimiento->poliza->fecha_vencimiento && strtotime($movimiento->poliza->fecha_vencimiento) < time())
                        <span class="badge-vencido">VENCIDO</span>
                    @endif
                </span>
            </div>
            @endif
        </div>

        <!-- ========== MONTOS ========== -->
        <div class="section">
            <div class="section-title">Montos</div>

            <div class="monto-destacado">
                <span class="label">{{ $esTraspaso ? 'MONTO TRASPASO' : 'TOTAL' }}</span>
                <span class="value">
                    ${{ number_format(abs($montoMostrar), 2) }}
                </span>
            </div>

            <div class="row">
                <span class="label">Base Gravable</span>
                <span class="value">${{ number_format(abs($movimiento->monto_base ?? 0), 2) }}</span>
            </div>
            <div class="row">
                <span class="label">IVA</span>
                <span class="value">${{ number_format(abs($movimiento->monto_iva ?? 0), 2) }}</span>
            </div>
            <div class="row" style="border-top:1px solid #000;padding-top:4px;margin-top:2px;">
                <span class="label" style="font-weight:700;">Total con IVA</span>
                <span class="value" style="font-weight:700;font-size:12px;">
                    ${{ number_format(abs($montoMostrar), 2) }}
                </span>
            </div>
        </div>

        <!-- ========== DESGLOSE IVA ========== -->
        @if(($movimiento->monto_iva_cero ?? 0) != 0 || ($movimiento->monto_iva_dieciseis ?? 0) != 0 || ($movimiento->iva_dieciseis ?? 0) != 0)
        <div class="section">
            <div class="section-title">Desglose IVA</div>
            @if(($movimiento->monto_iva_cero ?? 0) != 0)
            <div class="iva-row">
                <span>IVA 0% (Exento)</span>
                <span>${{ number_format(abs($movimiento->monto_iva_cero), 2) }}</span>
            </div>
            @endif
            @if(($movimiento->monto_iva_dieciseis ?? 0) != 0)
            <div class="iva-row">
                <span>IVA 16% (Base)</span>
                <span>${{ number_format(abs($movimiento->monto_iva_dieciseis), 2) }}</span>
            </div>
            <div class="iva-row">
                <span>IVA 16% (Calculado)</span>
                <span>${{ number_format(abs($movimiento->iva_dieciseis), 2) }}</span>
            </div>
            @endif
            <div class="iva-row" style="border-top:1px solid #000;padding-top:2px;font-weight:700;">
                <span>TOTAL</span>
                <span>${{ number_format(abs(($movimiento->monto_iva_cero ?? 0) + ($movimiento->monto_iva_dieciseis ?? 0) + ($movimiento->iva_dieciseis ?? 0)), 2) }}</span>
            </div>
        </div>
        @endif

        <!-- ========== SALDOS (POR PAGAR) ========== -->
        @if($movimiento->poliza->es_por_pagar)
        <div class="section">
            <div class="section-title">Saldos</div>
            <div class="row">
                <span class="label">Total Abonado</span>
                <span class="value">${{ number_format(abs($totalAbonado ?? 0), 2) }}</span>
            </div>
            <div class="row" style="border-top:1px solid #000;padding-top:4px;margin-top:2px;">
                <span class="label" style="font-weight:700;">Saldo Pendiente</span>
                <span class="value" style="font-weight:700;font-size:12px;">
                    ${{ number_format(abs($saldoPendiente ?? 0), 2) }}
                </span>
            </div>
        </div>
        @endif

        <!-- ========== ABONOS ========== -->
        @if($movimiento->poliza->es_por_pagar && isset($abonos) && $abonos->count() > 0)
        <div class="section">
            <div class="section-title">Abonos</div>
            @foreach($abonos as $abono)
            <div class="abono-row">
                <span class="fecha">{{ $abono->fecha_abono ? date('d/m/Y', strtotime($abono->fecha_abono)) : '—' }}</span>
                <span class="ref">{{ $abono->referencia ?? '—' }}</span>
                <span class="monto">${{ number_format(abs($abono->monto_abonado), 2) }}</span>
            </div>
            @endforeach
            <div class="abono-total">
                <span class="label">Saldo Pendiente</span>
                <span class="value">
                    ${{ number_format(abs($saldoPendiente ?? 0), 2) }}
                </span>
            </div>
        </div>
        @endif

        <!-- ========== FACTURACIÓN ========== -->
        @if($movimiento->poliza->categoria === 'FISCAL')
        <div class="section">
            <div class="section-title">Facturación</div>
            <div class="row">
                <span class="label">Fecha Factura</span>
                <span class="value">{{ $movimiento->poliza->fecha_factura ? date('d/m/Y', strtotime($movimiento->poliza->fecha_factura)) : '—' }}</span>
            </div>
            <div class="row">
                <span class="label">Número</span>
                <span class="value">{{ $movimiento->poliza->numero_factura ?? '—' }}</span>
            </div>
            @if($movimiento->poliza->serie_factura)
            <div class="row">
                <span class="label">Serie</span>
                <span class="value">{{ $movimiento->poliza->serie_factura }}</span>
            </div>
            @endif
            @if($movimiento->poliza->folio_factura)
            <div class="row">
                <span class="label">Folio</span>
                <span class="value">{{ $movimiento->poliza->folio_factura }}</span>
            </div>
            @endif
            @if($movimiento->poliza->uuid_factura)
            <div class="row">
                <span class="label">UUID</span>
                <span class="value"><span class="uuid-text">{{ $movimiento->poliza->uuid_factura }}</span></span>
            </div>
            @endif
            <div class="row" style="border:none;padding:2px 0;">
                <span class="label">PDF</span>
                <span class="value">{{ $movimiento->poliza->ruta_pdf ? '✓ Adjunto' : '—' }}</span>
            </div>
            <div class="row" style="border:none;padding:2px 0;">
                <span class="label">XML</span>
                <span class="value">{{ $movimiento->poliza->ruta_xml ? '✓ Adjunto' : '—' }}</span>
            </div>
        </div>
        @endif

        <!-- ========== OBSERVACIONES ========== -->
        @if($movimiento->poliza->nota)
        <div class="section">
            <div class="section-title">Observación</div>
            <div class="nota-text">{{ $movimiento->poliza->nota }}</div>
        </div>
        @endif

        <!-- ========== SEPARADOR ========== -->
        <div class="separator-dots">• • • • • • • • • • • • • • • • • • • • •</div>

        <!-- ========== FOOTER ========== -->
        <div class="footer">
            <div class="impreso-por">
                RIC | IMPRIME: {{ Auth::user()->nombre_completo ?? 'Sistema' }}
            </div>
            <div class="line">{{ $fecha_exportacion }}</div>

            <div class="firma">
                <div class="firma-item">
                    <span class="nombre">{{ Auth::user()->nombre_completo ?? '—' }}</span>
                    <span class="linea"></span>
                    <span class="label">NOMBRE Y FIRMA DE QUIEN ENTREGA</span>
                </div>
                <div class="firma-item">
                    <span class="nombre">{{ $movimiento->poliza->persona->nombre_completo ?? '—' }}</span>
                    <span class="linea"></span>
                    <span class="label">NOMBRE Y FIRMA DE QUIEN RECIBE</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>