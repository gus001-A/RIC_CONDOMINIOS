<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket {{ $movimiento->poliza->folio ?? 'Sin folio' }}</title>
    <style>
        /* ============================================================ */
        /* ESTILOS SIMPLONES Y MÁS GRANDES                              */
        /* ============================================================ */
        body {
            font-family: 'Courier New', Courier, monospace;
            background: #fff;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        * {
            font-family: 'Courier New', Courier, monospace;
        }

        #div_ticket {
            width: 280px;
            max-width: 280px;
            background: #fff;
            padding: 20px 15px;
            font-size: 12px;
            color: #000;
            line-height: 1.6;
        }

        #div_ticket .logo_i {
            text-align: center;
            padding: 0 0 15px 0;
        }

        #div_ticket .logo_i img {
            height: 60px;
            max-height: 60px;
        }

        #div_ticket .empresa_i {
            font-weight: bold;
            font-size: 14px;
            padding: 2px 0;
        }

        #div_ticket .no_factura_i {
            font-weight: bold;
            font-size: 13px;
            padding: 2px 0;
        }

        #div_ticket .fecha_factura_i {
            font-size: 12px;
            padding: 2px 0;
        }

        #div_ticket .tipo_factura_i {
            font-weight: bold;
            font-size: 12px;
            padding: 2px 0;
        }

        #div_ticket .persona_i {
            font-weight: bold;
            font-size: 12px;
            padding: 2px 0;
        }

        #div_ticket .cuenta_i {
            font-size: 12px;
            padding: 2px 0;
        }

        /* ============================================================ */
        /* SECCIÓN DE MONTOS                                            */
        /* ============================================================ */
        #div_ticket .montos-table {
            width: 100%;
            margin: 8px 0;
            border-collapse: collapse;
        }

        #div_ticket .montos-table td {
            padding: 3px 0;
            font-size: 12px;
            vertical-align: middle;
        }

        #div_ticket .montos-table .label {
            font-weight: 600;
            padding-right: 10px;
        }

        #div_ticket .montos-table .value {
            font-weight: 700;
            text-align: right;
        }

        #div_ticket .montos-table .monto_i {
            font-weight: bold;
            font-size: 13px;
        }

        #div_ticket .montos-table .iva_i {
            font-weight: bold;
            font-size: 13px;
        }

        #div_ticket .montos-table .iva_total_i {
            font-weight: bold;
            font-size: 13px;
        }

        #div_ticket .montos-table .total_i {
            font-weight: bold;
            font-size: 15px;
        }

        /* ============================================================ */
        /* DESGLOSE FISCAL                                              */
        /* ============================================================ */
        #div_ticket .desglose-fiscal {
            margin: 10px 0;
            padding: 10px 12px;
            background: #f5f5f5;
            border-left: 3px solid #000;
        }

        #div_ticket .desglose-fiscal .titulo {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }

        #div_ticket .desglose-fiscal .fila {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            font-size: 11px;
        }

        #div_ticket .desglose-fiscal .fila .label {
            font-weight: 600;
        }

        #div_ticket .desglose-fiscal .fila .value {
            font-weight: 700;
        }

        #div_ticket .desglose-fiscal .fila-total {
            display: flex;
            justify-content: space-between;
            padding: 4px 0 0 0;
            font-size: 12px;
            font-weight: 700;
            border-top: 2px solid #000;
            margin-top: 4px;
        }

        /* ============================================================ */
        /* OTROS CAMPOS - TODOS IGUALES                                */
        /* ============================================================ */
        #div_ticket .cuenta_fondo_i {
            font-size: 12px;
            padding: 2px 0;
        }

        #div_ticket .marcador_i {
            font-size: 12px;
            padding: 2px 0;
        }

        #div_ticket .observacio_i {
            font-size: 12px;
            padding: 2px 0;
            font-style: normal;
        }

        #div_ticket .usuario_i {
            font-size: 11px;
            padding: 2px 0;
            color: #333;
        }

        #div_ticket .fecha_hora_i {
            font-size: 11px;
            padding: 2px 0;
            color: #333;
        }

        /* ============================================================ */
        /* FIRMAS - VERTICAL (UNA ARRIBA, UNA ABAJO)                   */
        /* ============================================================ */
        #div_ticket .firma-entrega {
            text-align: center;
            font-size: 10px;
            text-transform: uppercase;
            color: #333;
            padding-top: 30px;
            margin-top: 15px;
            border-top: 1px solid #000;
            min-height: 50px;
        }

        #div_ticket .firma-recibe {
            text-align: center;
            font-size: 10px;
            text-transform: uppercase;
            color: #333;
            padding-top: 30px;
            margin-top: 10px;
            border-top: 1px solid #000;
            min-height: 50px;
        }

        /* ============================================================ */
        /* TAG FISCAL                                                   */
        /* ============================================================ */
        #div_ticket .tag-fiscal {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 8px;
            border: 1px solid #000;
            margin-left: 6px;
            text-transform: uppercase;
        }

        /* ============================================================ */
        /* SEPARADORES                                                  */
        /* ============================================================ */
        #div_ticket .separador {
            border: none;
            border-top: 1px dashed #ccc;
            margin: 8px 0;
        }

        /* ============================================================ */
        /* IMPRESIÓN                                                    */
        /* ============================================================ */
        @media print {
            body {
                padding: 10px;
                margin: 0;
                background: #fff;
            }
            
            #div_ticket {
                width: 280px !important;
                max-width: 280px !important;
                margin: 0 auto !important;
                padding: 15px !important;
                border: none !important;
            }

            #div_ticket .desglose-fiscal {
                background: #f5f5f5 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        /* ============================================================ */
        /* RESPONSIVE                                                   */
        /* ============================================================ */
        @media screen and (max-width: 320px) {
            #div_ticket {
                width: 100% !important;
                max-width: 100% !important;
                padding: 15px 10px !important;
            }
        }
    </style>
</head>
<body>
    <div id="div_ticket">
        <!-- ============================================================ -->
        <!-- LOGO                                                          -->
        <!-- ============================================================ -->
        @php
            $logoPath = public_path('logos/logo.png');
            $logoData = null;
            if (file_exists($logoPath)) {
                $imageData = file_get_contents($logoPath);
                $logoData = base64_encode($imageData);
            }
        @endphp

        @if($logoData)
            <div class="logo_i">
                <img src="data:image/png;base64,{{ $logoData }}" alt="Logo">
            </div>
        @endif

        <!-- ============================================================ -->
        <!-- DATOS DEL TICKET                                             -->
        <!-- ============================================================ -->
        <div class="empresa_i">EMPRESA: {{ $empresa->nombre_empresa ?? 'EMPRESA' }}</div>
        <div class="no_factura_i">NÚMERO DE FACTURA: {{ $movimiento->poliza->folio ?? 'SIN FOLIO' }}</div>
        <div class="fecha_factura_i">FECHA DE FACTURA: {{ $movimiento->poliza->fecha_poliza ? date('d/m/Y', strtotime($movimiento->poliza->fecha_poliza)) : '—' }}</div>
        <div class="tipo_factura_i">
            TIPO DE FACTURA: {{ $esTraspaso ? 'TRASPASO' : ($movimiento->poliza->tipo_poliza ?? '—') }}
            @if($movimiento->poliza->categoria == 'FISCAL')
                <span class="tag-fiscal">FISCAL</span>
            @endif
        </div>
        <div class="persona_i">PERSONA: {{ $movimiento->poliza->persona->nombre_completo ?? '—' }}</div>
        <div class="cuenta_i">CUENTA: {{ $movimiento->cuenta->nombre_cuenta ?? '—' }}</div>

        <hr class="separador">

        <!-- ============================================================ -->
        <!-- MONTOS                                                        -->
        <!-- ============================================================ -->
        @php
            // ============================================
            // 🔥 CALCULAMOS LOS VALORES CORRECTOS
            // ============================================
            $montoBase = abs($movimiento->monto_base ?? 0);
            $montoIvaTotal = abs($movimiento->monto_iva ?? 0);
            $montoIvaCero = abs($movimiento->monto_iva_cero ?? 0);
            $montoIvaDieciseis = abs($movimiento->monto_iva_dieciseis ?? 0);
            $ivaDieciseis = abs($movimiento->iva_dieciseis ?? 0);
            
            // 🔥 EL TOTAL FINAL ES EL CAMPO "monto"
            $montoMostrar = abs($movimiento->monto ?? 0);
            
            // 🔥 CALCULAR TOTAL FACTURA (SUMA DE BASES)
            $totalFactura = $montoIvaCero + $montoIvaDieciseis;
            if ($totalFactura == 0) {
                $totalFactura = $montoBase;
            }
            
            // 🔥 DETERMINAR TEXTO DE IVA - CORREGIDO A 16%
            $ivaTexto = 'EXCENTO';
            
            // Si tiene IVA 16% (el más común)
            if ($montoIvaDieciseis > 0 || $ivaDieciseis > 0) {
                $ivaTexto = '16%';
            } 
            // Si solo tiene IVA 0% (exento)
            elseif ($montoIvaCero > 0 && $montoIvaDieciseis == 0 && $ivaDieciseis == 0) {
                $ivaTexto = 'EXCENTO';
            } 
            // Si tiene IVA pero no está categorizado
            elseif ($montoBase > 0 && $montoIvaTotal > 0) {
                $ivaTexto = '16%';
            }
            
            // 🔥 DETERMINAR SI TIENE DESGLOSE FISCAL
            $tieneDesgloseFiscal = ($montoIvaCero > 0 || $montoIvaDieciseis > 0 || $ivaDieciseis > 0);
            $esFiscal = $movimiento->poliza->categoria === 'FISCAL';
            $tieneIva = ($montoIvaTotal > 0 || $ivaDieciseis > 0);
        @endphp

        <!-- ============================================================ -->
        <!-- 1. MONTOS PRINCIPALES                                        -->
        <!-- ============================================================ -->
        <table class="montos-table">
            <tr>
                <td class="label">MONTO:</td>
                <td class="value monto_i">${{ number_format($montoMostrar, 2) }}</td>
                <td class="label" style="padding-left:15px;">IVA:</td>
                <td class="value iva_i">{{ $ivaTexto }}</td>
            </tr>
            <tr>
                <td class="label">TOTAL IVA:</td>
                <td class="value iva_total_i">${{ number_format($montoIvaTotal, 2) }}</td>
                <td class="label" style="padding-left:15px;">TOTAL:</td>
                <td class="value total_i">${{ number_format($montoMostrar, 2) }}</td>
            </tr>
        </table>

        <!-- ============================================================ -->
        <!-- 2. DESGLOSE FISCAL (SOLO SI ES FISCAL)                      -->
        <!-- ============================================================ -->
        @if($esFiscal && $tieneDesgloseFiscal)
            <div class="desglose-fiscal">
                <div class="titulo">Desglose Fiscal</div>

                @if($montoIvaCero > 0)
                    <div class="fila">
                        <span class="label">IVA 0% (Exento)</span>
                        <span class="value">${{ number_format($montoIvaCero, 2) }}</span>
                    </div>
                @endif

                @if($montoIvaDieciseis > 0)
                    <div class="fila">
                        <span class="label">IVA 16% (Base)</span>
                        <span class="value">${{ number_format($montoIvaDieciseis, 2) }}</span>
                    </div>
                @endif

                @if($ivaDieciseis > 0)
                    <div class="fila">
                        <span class="label">IVA 16% (Impuesto)</span>
                        <span class="value">${{ number_format($ivaDieciseis, 2) }}</span>
                    </div>
                @endif

                <div class="fila-total">
                    <span class="label">Total Factura</span>
                    <span class="value">${{ number_format($totalFactura, 2) }}</span>
                </div>
            </div>
        @endif

        <hr class="separador">

        <!-- ============================================================ -->
        <!-- OTROS DATOS - TODOS CON EL MISMO ESTILO                     -->
        <!-- ============================================================ -->
        <div class="cuenta_fondo_i">CUENTA DE FONDO: {{ $movimiento->cuentaFondeadora->nombre_cuenta ?? '—' }}</div>
        <div class="marcador_i">MARCADOR: {{ $movimiento->poliza->marcador->nombre_marcador ?? '—' }}</div>
        <div class="observacio_i">OBSERVACIÓN: {{ $movimiento->poliza->nota ?? '' }}</div>
        <div class="usuario_i">IMPRIME: {{ Auth::user()->nombre_completo ?? 'Sistema' }}</div>
        <div class="fecha_hora_i">FECHA Y HORA: {{ $fecha_exportacion }}</div>

        <!-- ============================================================ -->
        <!-- FIRMAS - UNA ARRIBA Y UNA ABAJO                             -->
        <!-- ============================================================ -->
        <div class="firma-entrega">NOMBRE Y FIRMA DE QUIEN ENTREGA</div>
        <div class="firma-recibe">NOMBRE Y FIRMA DE QUIEN RECIBE</div>
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