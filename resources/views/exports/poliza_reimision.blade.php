<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reimisión {{ $movimiento->poliza->folio ?? 'Sin folio' }}</title>
    <style>
        /* ===== RESET TOTAL ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #ffffff;
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            padding: 0;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        /* ===== CONTENEDOR QUE OCUPA TODA LA HOJA ===== */
        .remision {
            width: 100%;
            max-width: 100%;
            padding: 16px 20px 20px 20px;
            background: #ffffff;
            font-family: 'Courier New', Courier, monospace;
            color: #000000;
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ===== TEXTO PLANO ===== */
        .remision .linea {
            padding: 2px 0;
            font-size: 11px;
        }

        .remision .linea .label {
            display: inline-block;
            min-width: 150px;
            font-weight: normal;
        }

        .remision .linea .valor {
            font-weight: normal;
        }

        /* ===== NEGRITAS SELECTIVAS ===== */
        .remision .negrita {
            font-weight: bold;
        }

        .remision .label-negrita {
            font-weight: bold;
            display: inline-block;
            min-width: 150px;
        }

        /* ===== LOGO ===== */
        .logo-area {
            text-align: center;
            padding: 4px 0 10px 0;
        }
        .logo-area img {
            max-height: 60px;
            width: auto;
        }

        /* ===== TÍTULO (sin estrellas) ===== */
        .titulo {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
            padding: 6px 0 12px 0;
            text-transform: uppercase;
            border-bottom: 1px solid #cccccc;
            margin-bottom: 12px;
        }

        /* ===== MONTOS EN UNA SOLA LÍNEA ===== */
        .montos-linea {
            padding: 6px 0 4px 0;
            font-size: 11px;
        }
        .montos-linea span {
            margin-right: 18px;
        }
        .montos-linea .num {
            font-weight: bold;
        }

        /* ===== DESGLOSE FISCAL ===== */
        .desglose {
            padding: 4px 0 4px 16px;
            font-size: 10px;
            border-left: 1px solid #bbbbbb;
            margin: 4px 0 6px 0;
        }
        .desglose .fila {
            padding: 1px 0;
        }
        .desglose .total-fila {
            padding: 3px 0 0 0;
            border-top: 1px solid #cccccc;
            margin-top: 2px;
            font-weight: bold;
        }

        /* ===== OBSERVACIÓN ===== */
        .observacion {
            padding: 6px 0 6px 12px;
            font-style: italic;
            border-left: 1px solid #aaaaaa;
            margin: 6px 0 8px 0;
            font-size: 10px;
        }

        /* ===== METADATOS ===== */
        .metadatos {
            padding: 4px 0 8px 0;
            font-size: 10px;
            color: #222222;
            border-bottom: 1px solid #dddddd;
            margin-bottom: 14px;
        }
        .metadatos span {
            margin-right: 24px;
        }
        .metadatos .negrita {
            font-weight: bold;
        }

        /* ===== FIRMAS ===== */
        .firmas {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 8px;
        }
        .firma-item {
            flex: 1 1 200px;
            text-align: center;
            padding: 20px 6px 4px 6px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .firma-linea {
            border-top: 1px solid #aaaaaa;
            padding-top: 12px;
            font-weight: normal;
        }

        /* ===== ESPACIADOR ===== */
        .spacer {
            height: 20px;
        }

        /* ===== IMPRESIÓN ===== */
        @media print {
            body {
                padding: 0;
                margin: 0;
                background: #ffffff;
            }
            .remision {
                padding: 12px 18px 18px 18px;
                min-height: 100vh;
                width: 100%;
                max-width: 100%;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            .titulo {
                border-bottom: 1px solid #cccccc;
            }
            .metadatos {
                border-bottom: 1px solid #dddddd;
            }
            .desglose {
                border-left: 1px solid #bbbbbb;
            }
            .observacion {
                border-left: 1px solid #aaaaaa;
            }
            .firma-linea {
                border-top: 1px solid #aaaaaa;
            }
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
            }
            .remision {
                min-height: 100vh;
            }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .remision {
                padding: 10px 12px;
            }
            .remision .linea .label,
            .remision .label-negrita {
                min-width: 100px;
            }
            .montos-linea span {
                display: inline-block;
                margin-right: 10px;
            }
            .firmas {
                flex-direction: column;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="remision">

        <!-- ===== LOGO ===== -->
        <div class="logo-area">
            @php
                $logoPath = public_path('logos/logo.png');
                $logoData = null;
                if (file_exists($logoPath)) {
                    $imageData = file_get_contents($logoPath);
                    $logoData = base64_encode($imageData);
                }
            @endphp
            @if($logoData)
                <img src="data:image/png;base64,{{ $logoData }}" alt="logo">
            @endif
        </div>

        <!-- ===== TÍTULO (sin estrellas) ===== -->
        <div class="titulo">REIMISIÓN DE PÓLIZA</div>

        <!-- ===== DATOS ===== -->
        <div class="linea">
            <span class="label-negrita">EMPRESA:</span>
            <span class="valor">{{ $empresa->nombre_empresa ?? 'EMPRESA' }}</span>
        </div>
        <div class="linea">
            <span class="label-negrita">NÚMERO DE FACTURA:</span>
            <span class="valor">{{ $movimiento->poliza->folio ?? 'SIN FOLIO' }}</span>
        </div>
        <div class="linea">
            <span class="label-negrita">FECHA DE FACTURA:</span>
            <span class="valor">{{ $movimiento->poliza->fecha_poliza ? date('d/m/Y', strtotime($movimiento->poliza->fecha_poliza)) : '—' }}</span>
        </div>
        <div class="linea">
            <span class="label-negrita">TIPO DE FACTURA:</span>
            <span class="valor">
                {{ $esTraspaso ? 'TRASPASO' : ($movimiento->poliza->tipo_poliza ?? '—') }}
                @if($movimiento->poliza->categoria == 'FISCAL') <span class="negrita">FISCAL</span> @endif
                @if($movimiento->poliza->es_por_pagar) <span class="negrita">POR PAGAR</span> @endif
                @if($movimiento->poliza->es_por_pagar && $movimiento->poliza->fecha_vencimiento && strtotime($movimiento->poliza->fecha_vencimiento) < time()) <span class="negrita">VENCIDO</span> @endif
            </span>
        </div>
        <div class="linea">
            <span class="label-negrita">PERSONA:</span>
            <span class="valor">{{ $movimiento->poliza->persona->nombre_completo ?? '—' }}</span>
        </div>
        <div class="linea">
            <span class="label-negrita">CUENTA:</span>
            <span class="valor">{{ $movimiento->cuenta->nombre_cuenta ?? '—' }}</span>
        </div>

        <!-- ===== MONTOS ===== -->
        @php
            $montoBase = abs($movimiento->monto_base ?? 0);
            $montoIvaTotal = abs($movimiento->monto_iva ?? 0);
            $montoIvaCero = abs($movimiento->monto_iva_cero ?? 0);
            $montoIvaDieciseis = abs($movimiento->monto_iva_dieciseis ?? 0);
            $ivaDieciseis = abs($movimiento->iva_dieciseis ?? 0);
            $montoMostrar = abs($movimiento->monto ?? 0);
            $totalFactura = $montoIvaCero + $montoIvaDieciseis;
            if ($totalFactura == 0) {
                $totalFactura = $montoBase;
            }
            $ivaTexto = 'EXCENTO';
            $porcentajeIva = 0;
            if ($montoIvaDieciseis > 0 && $ivaDieciseis > 0) {
                $porcentajeIva = round(($ivaDieciseis / $montoIvaDieciseis) * 100);
                $ivaTexto = $porcentajeIva . '%';
            } elseif ($montoBase > 0 && $montoIvaTotal > 0) {
                $porcentajeIva = round(($montoIvaTotal / $montoBase) * 100);
                $ivaTexto = $porcentajeIva . '%';
            } elseif ($montoIvaTotal == 0 && $montoIvaCero > 0) {
                $ivaTexto = 'EXCENTO';
            }
            $tieneDesgloseFiscal = ($montoIvaCero > 0 || $montoIvaDieciseis > 0 || $ivaDieciseis > 0);
            $esFiscal = $movimiento->poliza->categoria === 'FISCAL';
        @endphp

        <div class="montos-linea">
            <span><span class="negrita">MONTO:</span> <span class="num">${{ number_format($montoMostrar, 2) }}</span></span>
            <span><span class="negrita">IVA:</span> <span class="num">{{ $ivaTexto }}</span></span>
            <span><span class="negrita">TOTAL IVA:</span> <span class="num">${{ number_format($montoIvaTotal, 2) }}</span></span>
            <span><span class="negrita">TOTAL:</span> <span class="num">${{ number_format($montoMostrar, 2) }}</span></span>
        </div>

        <!-- ===== DESGLOSE FISCAL ===== -->
        @if($esFiscal && $tieneDesgloseFiscal)
            <div class="desglose">
                <div class="fila"><span class="negrita">DESGLOSE FISCAL</span></div>
                @if($montoIvaCero > 0)
                    <div class="fila"><span class="negrita">IVA 0% (Exento):</span> ${{ number_format($montoIvaCero, 2) }}</div>
                @endif
                @if($montoIvaDieciseis > 0)
                    <div class="fila"><span class="negrita">IVA 16% (Base):</span> ${{ number_format($montoIvaDieciseis, 2) }}</div>
                @endif
                @if($ivaDieciseis > 0)
                    <div class="fila"><span class="negrita">IVA 16% (Impuesto):</span> ${{ number_format($ivaDieciseis, 2) }}</div>
                @endif
                <div class="fila total-fila"><span class="negrita">TOTAL FACTURA:</span> ${{ number_format($totalFactura, 2) }}</div>
            </div>
        @endif

        <!-- ===== CUENTA DE FONDO ===== -->
        <div class="linea">
            <span class="label-negrita">CUENTA DE FONDO:</span>
            <span class="valor">{{ $movimiento->cuentaFondeadora->nombre_cuenta ?? '—' }}</span>
        </div>

        <!-- ===== MARCADOR ===== -->
        <div class="linea">
            <span class="label-negrita">MARCADOR:</span>
            <span class="valor">{{ $movimiento->poliza->marcador->nombre_marcador ?? '—' }}</span>
        </div>

        <!-- ===== OBSERVACIÓN ===== -->
        @if($movimiento->poliza->nota)
            <div class="observacion">
                <span class="negrita">OBSERVACIÓN:</span> {{ $movimiento->poliza->nota }}
            </div>
        @endif

        <!-- ===== METADATOS ===== -->
        <div class="metadatos">
            <span><span class="negrita">IMPRIME:</span> {{ Auth::user()->nombre_completo ?? 'Sistema' }}</span>
            <span><span class="negrita">FECHA Y HORA:</span> {{ $fecha_exportacion }}</span>
        </div>

        <!-- ===== FIRMAS ===== -->
        <div class="firmas">
            <div class="firma-item">
                <div class="firma-linea"><span class="negrita">NOMBRE Y FIRMA DE QUIEN ENTREGA</span></div>
            </div>
            <div class="firma-item">
                <div class="firma-linea"><span class="negrita">NOMBRE Y FIRMA DE QUIEN RECIBE</span></div>
            </div>
        </div>

        <!-- ===== ESPACIADOR ===== -->
        <div class="spacer"></div>

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