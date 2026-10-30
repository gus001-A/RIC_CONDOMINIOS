<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cuenta;
use App\Models\Poliza;
use App\Models\MovimientoPoliza;
use App\Models\AbonoPoliza;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!Gate::allows('ver-reportes')) {
            return redirect()->route('dashboard')
                ->with('error', 'No tienes permiso para ver reportes');
        }

        $userId = $user->id_usuario;
        
        $empresaIds = DB::table('empresas_usuarios')
            ->where('id_usuario', $userId)
            ->pluck('id_empresa')
            ->toArray();
        
        if (!empty($empresaIds)) {
            $empresas = \App\Models\Empresa::whereIn('id', $empresaIds)
                ->where('activo', true)
                ->get();
        } else {
            $empresas = collect();
        }
        
        $empresaId = $request->input('empresa_id');
        
        if (!$empresaId) {
            $empresaId = session('empresa_seleccionada');
        }
        
        if ($empresaId) {
            $tieneAcceso = DB::table('empresas_usuarios')
                ->where('id_empresa', $empresaId)
                ->where('id_usuario', $userId)
                ->exists();
                
            if (!$tieneAcceso) {
                $empresaId = null;
            }
        }
        
        if (!$empresaId && $empresas->count() > 0) {
            $empresaId = $empresas->first()->id;
        }
        
        if ($empresaId) {
            session(['empresa_seleccionada' => $empresaId]);
        }
        
        return Inertia::render('Reportes/Index', [
            'empresas' => $empresas,
            'empresa_seleccionada' => $empresaId,
            'reporte' => [
                'data' => [],
                'fondeadoras' => []
            ],
            'filtros' => $request->only(['fecha_desde', 'fecha_hasta']),
            'vista' => $request->get('vista', 'por_cuenta')
        ]);
    }

    public function getMovimientos(Request $request)
    {
        // También lo usa Transparencia (condóminos): mismo cálculo, sólo lectura.
        if (!Gate::allows('ver-reportes') && !Gate::allows('ver-transparencia')) {
            return response()->json(['success' => false, 'message' => 'Sin permiso para ver reportes'], 403);
        }

        $empresaId = $request->input('empresa_id');

        // Sólo empresas / condominios asignados al usuario.
        if ($empresaId && !auth()->user()->puedeVerCondominio($empresaId)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esa empresa'], 403);
        }
        $vista = $request->input('vista', 'por_cuenta');
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');
        $tipoFiltro = $request->input('tipo_filtro', 'todas');

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'Se requiere una empresa'
            ], 422);
        }

        try {
            // 🔥 PRIMERO: OBTENER IDs DE PÓLIZAS QUE CUMPLEN EL FILTRO
            $polizaQuery = Poliza::where('id_empresa', $empresaId)
                ->where('es_por_pagar', false)
                ->when($fechaDesde, function($q) use ($fechaDesde) {
                    return $q->whereDate('fecha_poliza', '>=', $fechaDesde);
                })
                ->when($fechaHasta, function($q) use ($fechaHasta) {
                    return $q->whereDate('fecha_poliza', '<=', $fechaHasta);
                });

            if ($tipoFiltro === 'fiscales') {
                $polizaQuery->where('categoria', 'FISCAL');
            } elseif ($tipoFiltro === 'no_fiscales') {
                $polizaQuery->where('categoria', '!=', 'FISCAL');
            }

            $polizaIds = $polizaQuery->pluck('id')->toArray();

            if (empty($polizaIds)) {
                // Sin movimientos en el periodo/filtro no hay tabla de "movimientos",
                // pero el catálogo de cuentas de resultados (con $0.00) SÍ debe verse
                // — antes se devolvía vacío y el modal de Resultados quedaba en blanco.
                $cuentasResultadosVacio = $this->getCuentasResultadosOptimizado(
                    $empresaId, $fechaDesde, $fechaHasta, $tipoFiltro, collect()
                );

                return response()->json([
                    'success' => true,
                    'data' => [],
                    'fondeadoras' => $this->getCuentasFondeadorasConSaldo($empresaId),
                    'cuentas_resultados' => $cuentasResultadosVacio['cuentas'] ?? [],
                    'resultado_utilidad' => 0,
                    'totales_ingresos' => 0,
                    'totales_egresos' => 0,
                    'total_iva' => 0,
                    'total_iva_ingresos' => 0,
                    'total_iva_egresos' => 0,
                    'balance_iva' => 0,
                    'totales_iva_resultados' => $cuentasResultadosVacio['totales_iva'] ?? 0,
                    'tipo_filtro' => $tipoFiltro,
                ]);
            }

            // 🔥 OBTENER MOVIMIENTOS SOLO DE PÓLIZAS QUE CUMPLEN EL FILTRO
            $movimientos = MovimientoPoliza::with([
                'poliza.persona',
                'poliza.abonos',
                'cuenta',
                'cuentaFondeadora'
            ])
            ->whereIn('id_poliza', $polizaIds)
            ->get();

            if ($vista === 'por_cuenta') {
                $data = $this->agruparPorCuenta($movimientos);
            } else {
                $data = $this->agruparPorPersona($movimientos);
            }

            // 🔥 CALCULAR TOTALES Y IVA
            $totalIngresos = 0;
            $totalEgresos = 0;
            $totalIva = 0;
            $totalIvaIngresos = 0;
            $totalIvaEgresos = 0;

            foreach ($movimientos as $mov) {
                $monto = (float) $mov->monto;
                
                // 🔥 CALCULAR IVA DEL MOVIMIENTO
                $iva = 0;
                if (isset($mov->monto_iva) && $mov->monto_iva != 0) {
                    $iva = (float) $mov->monto_iva;
                } elseif (isset($mov->iva_dieciseis) && $mov->iva_dieciseis != 0) {
                    $iva = (float) $mov->iva_dieciseis;
                }
                
                if ($iva == 0 && $mov->poliza && $mov->poliza->categoria === 'FISCAL') {
                    $iva = abs($monto) * 0.16;
                }
                
                if ($iva != 0) {
                    if ($monto > 0) {
                        $totalIvaIngresos += abs($iva);
                    } else {
                        $totalIvaEgresos += abs($iva);
                    }
                    $totalIva += abs($iva);
                }
                
                if ($monto > 0) {
                    $totalIngresos += $monto;
                } else {
                    $totalEgresos += abs($monto);
                }
            }

            $cuentasResultadosData = $this->getCuentasResultadosOptimizado(
                $empresaId, 
                $fechaDesde, 
                $fechaHasta, 
                $tipoFiltro,
                $movimientos
            );

            $cuentasResultados = $cuentasResultadosData['cuentas'] ?? [];
            $totalesIvaResultados = $cuentasResultadosData['totales_iva'] ?? 0;

            $fondeadoras = $this->getCuentasFondeadorasConSaldo($empresaId);

            $resultadoUtilidad = $totalIngresos - $totalEgresos;
            $balanceIva = $totalIvaIngresos - $totalIvaEgresos;

            return response()->json([
                'success' => true,
                'data' => $data,
                'fondeadoras' => $fondeadoras,
                'cuentas_resultados' => $cuentasResultados,
                'resultado_utilidad' => $resultadoUtilidad,
                'totales_ingresos' => $totalIngresos,
                'totales_egresos' => $totalEgresos,
                'total_iva' => $totalIva,
                'total_iva_ingresos' => $totalIvaIngresos,
                'total_iva_egresos' => $totalIvaEgresos,
                'balance_iva' => $balanceIva,
                'totales_iva_resultados' => $totalesIvaResultados,
                'tipo_filtro' => $tipoFiltro
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener reporte:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 📊 AGRUPAR POR CUENTA
    // ============================================
    private function agruparPorCuenta($movimientos)
    {
        $grupos = [];

        foreach ($movimientos as $mov) {
            $idCuenta = $mov->id_cuenta ?? 'sin_cuenta';
            $nombreCuenta = $mov->cuenta ? $mov->cuenta->nombre_cuenta : 'Sin cuenta';
            $codigoCuenta = $mov->cuenta ? $mov->cuenta->codigo_cuenta : '---';
            $persona = $mov->poliza->persona ? $mov->poliza->persona->nombre_completo : 'Sin persona';
            
            $fondeo = 'Sin fondeo';
            if ($mov->cuentaFondeadora) {
                $fondeo = $mov->cuentaFondeadora->nombre_cuenta;
            } else if ($mov->cuenta && $mov->cuenta->fondeo_c == 1) {
                $fondeo = $mov->cuenta->nombre_cuenta;
            }

            $key = $idCuenta . '|' . $persona . '|' . $fondeo;

            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'id' => $key,
                    'id_cuenta' => $idCuenta,
                    'nombre' => $nombreCuenta,
                    'codigo' => $codigoCuenta,
                    'persona' => $persona,
                    'fondeo' => $fondeo,
                    'ingreso' => 0,
                    'egreso' => 0
                ];
            }

            if ($mov->monto > 0) {
                $grupos[$key]['ingreso'] += abs($mov->monto);
            } else {
                $grupos[$key]['egreso'] += abs($mov->monto);
            }
        }

        usort($grupos, function($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });

        return array_values($grupos);
    }

    // ============================================
    // 📊 AGRUPAR POR PERSONA
    // ============================================
    private function agruparPorPersona($movimientos)
    {
        $grupos = [];

        foreach ($movimientos as $mov) {
            $persona = $mov->poliza->persona ? $mov->poliza->persona->nombre_completo : 'Sin persona';
            
            $fondeo = 'Sin fondeo';
            if ($mov->cuentaFondeadora) {
                $fondeo = $mov->cuentaFondeadora->nombre_cuenta;
            } else if ($mov->cuenta && $mov->cuenta->fondeo_c == 1) {
                $fondeo = $mov->cuenta->nombre_cuenta;
            }

            $key = $persona . '|' . $fondeo;

            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'id' => $key,
                    'nombre' => $persona,
                    'fondeo' => $fondeo,
                    'ingreso' => 0,
                    'egreso' => 0
                ];
            }

            if ($mov->monto > 0) {
                $grupos[$key]['ingreso'] += abs($mov->monto);
            } else {
                $grupos[$key]['egreso'] += abs($mov->monto);
            }
        }

        usort($grupos, function($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });

        return array_values($grupos);
    }

    private function getCuentasFondeadorasConSaldo($empresaId)
    {
        $cuentas = Cuenta::where('id_empresa', $empresaId)
            ->where('en_uso', true)
            ->where('fondeo_c', 1)
            ->orderBy('codigo_cuenta')
            ->get(['id_cuenta', 'codigo_cuenta', 'nombre_cuenta', 'saldo_inicial']);

        if ($cuentas->isEmpty()) {
            return [];
        }

        $resultado = [];
        foreach ($cuentas as $cuenta) {
            $saldo = (float) ($cuenta->saldo_inicial ?? 0);
            
            $resultado[] = [
                'id_cuenta' => $cuenta->id_cuenta,
                'codigo_cuenta' => $cuenta->codigo_cuenta,
                'nombre_cuenta' => $cuenta->nombre_cuenta,
                'saldo' => $saldo
            ];
        }

        return $resultado;
    }

    // ============================================
    // 📊 GET CUENTAS DE RESULTADOS OPTIMIZADO CON ORDEN INGRESOS/EGRESOS
    // ============================================
    private function getCuentasResultadosOptimizado($empresaId, $fechaDesde = null, $fechaHasta = null, $tipoFiltro = 'todas', $movimientos = null)
    {
        // ============================================================
        // 1) PÓLIZAS QUE CUMPLEN EL FILTRO (fecha + fiscal / no fiscal)
        // ============================================================
        $polizaQuery = Poliza::where('id_empresa', $empresaId)
            ->where('es_por_pagar', false)
            ->when($fechaDesde, fn($q) => $q->whereDate('fecha_poliza', '>=', $fechaDesde))
            ->when($fechaHasta, fn($q) => $q->whereDate('fecha_poliza', '<=', $fechaHasta));

        if ($tipoFiltro === 'fiscales') {
            $polizaQuery->where('categoria', 'FISCAL');
        } elseif ($tipoFiltro === 'no_fiscales') {
            $polizaQuery->where('categoria', '!=', 'FISCAL');
        }

        $polizaIds = $polizaQuery->pluck('id')->toArray();

        // ============================================================
        // 2) TODAS LAS CUENTAS DE RESULTADOS DE LA EMPRESA
        //    `es_cuenta_resultados` (bandera) NO es confiable por sí sola: hay
        //    cuentas reales de gasto/ingreso con `tipo_cuenta='RESULTADO'` pero
        //    `es_cuenta_resultados=0` (quedaban fuera del estado de resultados
        //    aunque tuvieran movimiento real) — el campo bueno es `tipo_cuenta`.
        //    Se EXCLUYEN explícitamente las FONDEADORAS: cualquier cuenta con
        //    `fondeo_c = 1` (o `tipo_cuenta='FONDEADORA'`), p. ej. cajas/bancos,
        //    aunque además tengan `es_cuenta_resultados=1` o `tipo_cuenta='RESULTADO'`
        //    — no son cuentas de ingreso/egreso, son de flujo de efectivo.
        //    También se excluye la cuenta RAÍZ del catálogo completo de cada
        //    empresa (nivel 0, con `id_cuenta_madre` apuntando a sí misma, p.
        //    ej. la cuenta "RIC2" de la empresa RIC2): por defecto queda con
        //    tipo_cuenta='RESULTADO' aunque no es una línea de resultados real,
        //    sino el nodo raíz de TODO el árbol de cuentas de la empresa.
        // ============================================================
        $cuentas = Cuenta::where('id_empresa', $empresaId)
            ->where('en_uso', true)
            ->where('tipo_cuenta', '!=', 'FONDEADORA')
            ->where(function ($q) {
                $q->where('fondeo_c', '!=', 1)->orWhereNull('fondeo_c');
            })
            ->where(function ($q) {
                $q->whereNull('id_cuenta_madre')
                  ->orWhereColumn('id_cuenta_madre', '!=', 'id_cuenta');
            })
            ->where(function ($q) {
                $q->where('tipo_cuenta', 'RESULTADO')
                  ->orWhere('es_cuenta_resultados', 1);
            })
            ->orderBy('codigo_cuenta')
            ->get(['id_cuenta', 'codigo_cuenta', 'nombre_cuenta', 'nivel', 'id_cuenta_madre', 'cuenta_resultados'])
            ->keyBy('id_cuenta');

        if ($cuentas->isEmpty()) {
            return ['cuentas' => [], 'totales_iva' => 0];
        }

        $idsCuentas = $cuentas->keys()->all();

        // ============================================================
        // 3) MOVIMIENTOS DE CADA CUENTA DE RESULTADOS
        //    (antes sólo se consultaban las "hijas" y una cuenta de
        //     resultados con movimientos propios no aparecía)
        //    OJO: una cuenta de resultados puede además ser FONDEADORA
        //    (p. ej. una caja marcada como "cuenta de resultados" a la vez
        //    que `fondeo_c=1`). El monto de un movimiento se aplica tanto
        //    a `id_cuenta` como a `id_caja_fondo` (son el mismo registro),
        //    así que había que sumar POR AMBOS ROLES — antes sólo se leía
        //    `id_cuenta`, y como casi toda la actividad de esas cuentas pasa
        //    por el rol de fondeadora (sobre todo los INGRESOS que reciben
        //    dinero), esas cuentas aparecían casi siempre en negativo
        //    (sólo se veían sus egresos directos) o en $0.
        // ============================================================
        $movsPorCuenta = [];
        if (!empty($polizaIds)) {
            $filasMov = DB::table('movimientos_poliza as mp')
                ->where(function ($q) use ($idsCuentas) {
                    $q->whereIn('mp.id_cuenta', $idsCuentas)
                      ->orWhereIn('mp.id_caja_fondo', $idsCuentas);
                })
                ->whereIn('mp.id_poliza', $polizaIds)
                ->select('mp.id_cuenta', 'mp.id_caja_fondo', 'mp.monto', 'mp.monto_iva', 'mp.iva_dieciseis', 'mp.monto_base')
                ->get();

            $idsCuentasSet = array_flip($idsCuentas);
            $acumular = function ($idCuenta, $fila) use (&$movsPorCuenta) {
                if (!isset($movsPorCuenta[$idCuenta])) {
                    $movsPorCuenta[$idCuenta] = (object) ['total' => 0.0, 'total_iva' => 0.0, 'total_iva_calculado' => 0.0, 'total_base' => 0.0];
                }
                $acc = $movsPorCuenta[$idCuenta];
                $acc->total += (float) $fila->monto;
                $acc->total_iva += (float) $fila->monto_iva;
                $acc->total_iva_calculado += (float) $fila->iva_dieciseis;
                $acc->total_base += (float) $fila->monto_base;
            };

            foreach ($filasMov as $fila) {
                if (isset($idsCuentasSet[$fila->id_cuenta])) {
                    $acumular($fila->id_cuenta, $fila);
                }
                if ($fila->id_caja_fondo && isset($idsCuentasSet[$fila->id_caja_fondo])) {
                    $acumular($fila->id_caja_fondo, $fila);
                }
            }
        }
        $movsPorCuenta = collect($movsPorCuenta);

        $esFiscal = ($tipoFiltro === 'fiscales');

        // saldo + IVA directo de una cuenta
        $calcular = function ($idCuenta) use ($movsPorCuenta, $esFiscal) {
            $mov = $movsPorCuenta[$idCuenta] ?? null;
            $saldo = $mov ? (float) $mov->total : 0.0;
            $iva = 0.0;
            if ($mov) {
                $iva = (float) $mov->total_iva;
                if ($iva == 0) {
                    $iva = (float) $mov->total_iva_calculado;
                }
            }
            if ($iva == 0 && $esFiscal && $saldo != 0) {
                $iva = abs($saldo) * 0.16;
            }
            return ['saldo' => $saldo, 'iva' => abs($iva)];
        };

        // ============================================================
        // 4) DETERMINAR PADRE DE CADA CUENTA
        //    Una cuenta es "hija" si su cuenta madre (id_cuenta_madre) — o,
        //    para setups viejos, `cuenta_resultados` — apunta a OTRA cuenta
        //    de resultados de la empresa. Si no, es raíz.
        //    OJO: `cuenta_resultados` suele valer 1 (bandera "es de resultados"),
        //    no un id de padre; por eso se prioriza `id_cuenta_madre`.
        // ============================================================
        $padreDe = [];
        foreach ($cuentas as $id => $c) {
            $padreId = null;
            foreach ([$c->id_cuenta_madre, $c->cuenta_resultados] as $ref) {
                $ref = (int) ($ref ?? 0);
                if ($ref > 0 && $ref !== (int) $id && $cuentas->has($ref)) {
                    $padreId = $ref;
                    break;
                }
            }
            $padreDe[$id] = $padreId;
        }

        $hijasDirectasDe = [];
        foreach ($padreDe as $id => $padreId) {
            if ($padreId !== null) {
                $hijasDirectasDe[$padreId][] = $id;
            }
        }

        // Aplanar: todas las descendientes (hijas, nietas, ...) de una raíz se
        // muestran como hijas directas para no perder ningún nivel del árbol.
        $descendientesDe = function ($rootId) use (&$hijasDirectasDe) {
            $acc = [];
            $pila = $hijasDirectasDe[$rootId] ?? [];
            while ($pila) {
                $cur = array_pop($pila);
                if (isset($acc[$cur])) {
                    continue;
                }
                $acc[$cur] = true;
                foreach ($hijasDirectasDe[$cur] ?? [] as $sub) {
                    $pila[] = $sub;
                }
            }
            return array_keys($acc);
        };

        $ordenarPorSaldoYcodigo = function (&$lista) {
            usort($lista, function ($a, $b) {
                $aIng = ($a['subtotal'] ?? 0) >= 0;
                $bIng = ($b['subtotal'] ?? 0) >= 0;
                if ($aIng && !$bIng) return -1;
                if (!$aIng && $bIng) return 1;
                return strcmp($a['codigo_cuenta'] ?? '', $b['codigo_cuenta'] ?? '');
            });
        };

        // ============================================================
        // 5) CONSTRUIR EL ÁRBOL (raíces + hijas)
        // ============================================================
        $resultado = [];
        $totalIvaGeneral = 0.0;

        foreach ($cuentas as $id => $c) {
            if ($padreDe[$id] !== null) {
                continue; // se procesa dentro de su padre
            }

            $propio = $calcular($id);
            $hijasData = [];
            $subtotal = $propio['saldo'];
            $subtotalIva = $propio['iva'];

            foreach ($descendientesDe($id) as $hijaId) {
                $h = $cuentas[$hijaId] ?? null;
                if (!$h) {
                    continue;
                }
                $hv = $calcular($hijaId);
                $subtotal += $hv['saldo'];
                $subtotalIva += $hv['iva'];
                // Sólo se listan las hijas que SÍ tuvieron movimiento en el
                // periodo — a pedido: "si es 0 no las muestres".
                if ($hv['saldo'] == 0 && $hv['iva'] == 0) {
                    continue;
                }
                $hijasData[] = [
                    'id_cuenta' => $hijaId,
                    'codigo_cuenta' => $h->codigo_cuenta,
                    'nombre_cuenta' => $h->nombre_cuenta,
                    'nivel' => (int) ($h->nivel ?? 3),
                    'id_cuenta_madre' => $h->id_cuenta_madre,
                    'saldo' => $hv['saldo'],
                    'iva' => $hv['iva'],
                    'es_madre' => false,
                    'subtotal' => $hv['saldo'],
                    'hijas' => [],
                    // El badge "FISCAL" sólo se marca si la cuenta realmente
                    // tuvo movimiento fiscal en el periodo (no en filas en $0).
                    'es_fiscal' => $esFiscal && ($hv['saldo'] != 0 || $hv['iva'] != 0),
                ];
            }

            // Sólo se incluye la cuenta raíz si ella o alguna hija tuvo
            // movimiento en el periodo — a pedido: "si es 0 no las muestres".
            $tieneMovimiento = ($propio['saldo'] != 0 || $propio['iva'] != 0 || !empty($hijasData));
            if (!$tieneMovimiento) {
                continue;
            }

            $ordenarPorSaldoYcodigo($hijasData);
            $totalIvaGeneral += $subtotalIva;

            $resultado[] = [
                'id_cuenta' => $id,
                'codigo_cuenta' => $c->codigo_cuenta,
                'nombre_cuenta' => $c->nombre_cuenta,
                'nivel' => (int) ($c->nivel ?? 2),
                'id_cuenta_madre' => $c->id_cuenta_madre,
                'saldo' => $subtotal,
                'iva' => $subtotalIva,
                'es_madre' => true,
                'subtotal' => $subtotal,
                'hijas' => $hijasData,
                'es_fiscal' => $esFiscal && ($subtotal != 0 || $subtotalIva != 0),
            ];
        }

        $ordenarPorSaldoYcodigo($resultado);

        return [
            'cuentas' => $resultado,
            'totales_iva' => $totalIvaGeneral,
        ];
    }

    public function getMovimientosCuenta(Request $request)
    {
        if (!Gate::allows('ver-reportes')) {
            return response()->json(['success' => false, 'message' => 'Sin permiso para ver reportes'], 403);
        }

        $empresaId = $request->input('empresa_id');
        $idCuenta = $request->input('id_cuenta');
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');
        $tipoFiltro = $request->input('tipo_filtro', 'todas');

        if (!$empresaId || !$idCuenta) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros requeridos'
            ], 422);
        }

        try {
            $cuenta = Cuenta::where('id_empresa', $empresaId)
                ->where('id_cuenta', $idCuenta)
                ->where('en_uso', true)
                ->first();

            if (!$cuenta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuenta no encontrada'
                ], 404);
            }

            // 🔥 DETECTAR SI ES CUENTA DE RESULTADOS
            // `tipo_cuenta='RESULTADO'` es el dato confiable; `es_cuenta_resultados`
            // es una bandera que en producción puede venir en 0 aunque la cuenta
            // sí sea de resultados (y con movimiento real) — se usa como refuerzo,
            // no como único criterio, y siempre excluyendo FONDEADORAS.
            $esRaizDelArbol = empty($cuenta->id_cuenta_madre) ? false : ((int) $cuenta->id_cuenta_madre === (int) $cuenta->id_cuenta);
            $esCuentaResultados = !$esRaizDelArbol
                && $cuenta->tipo_cuenta !== 'FONDEADORA'
                && $cuenta->fondeo_c != 1
                && ($cuenta->tipo_cuenta === 'RESULTADO' || $cuenta->es_cuenta_resultados == 1);
            $idsCuentas = [(int) $idCuenta];

            if ($esCuentaResultados) {
                // Si es una cuenta MADRE (p. ej. "RESULTADOS"), hay que incluir
                // TODAS sus hijas (y nietas) para que al hacer clic en ella
                // aparezcan las pólizas de sus hijas, no sólo las propias.
                // OJO: `cuenta_resultados` normalmente es una bandera (=1), NO
                // un id de padre — el parentesco real va por `id_cuenta_madre`.
                // Se excluye la cuenta raíz del árbol (nivel 0, madre = sí misma)
                // y cualquier fondeadora (fondeo_c = 1).
                $todasResultados = Cuenta::where('id_empresa', $empresaId)
                    ->where('en_uso', true)
                    ->where('tipo_cuenta', '!=', 'FONDEADORA')
                    ->where(function ($q) {
                        $q->where('fondeo_c', '!=', 1)->orWhereNull('fondeo_c');
                    })
                    ->where(function ($q) {
                        $q->whereNull('id_cuenta_madre')
                          ->orWhereColumn('id_cuenta_madre', '!=', 'id_cuenta');
                    })
                    ->where(function ($q) {
                        $q->where('tipo_cuenta', 'RESULTADO')
                          ->orWhere('es_cuenta_resultados', 1);
                    })
                    ->get(['id_cuenta', 'id_cuenta_madre', 'cuenta_resultados']);

                $hijasDirectasDe = [];
                foreach ($todasResultados as $c) {
                    $padreId = null;
                    foreach ([$c->id_cuenta_madre, $c->cuenta_resultados] as $ref) {
                        $ref = (int) ($ref ?? 0);
                        if ($ref > 0 && $ref !== (int) $c->id_cuenta && $todasResultados->contains('id_cuenta', $ref)) {
                            $padreId = $ref;
                            break;
                        }
                    }
                    if ($padreId !== null) {
                        $hijasDirectasDe[$padreId][] = $c->id_cuenta;
                    }
                }

                $pila = $hijasDirectasDe[(int) $idCuenta] ?? [];
                while ($pila) {
                    $cur = array_pop($pila);
                    if (in_array($cur, $idsCuentas, true)) {
                        continue;
                    }
                    $idsCuentas[] = $cur;
                    foreach ($hijasDirectasDe[$cur] ?? [] as $sub) {
                        $pila[] = $sub;
                    }
                }
            }

            // 🔥 PRIMERO: OBTENER IDs DE PÓLIZAS QUE CUMPLEN EL FILTRO
            $polizaQuery = Poliza::where('id_empresa', $empresaId)
                ->where('es_por_pagar', false)
                ->when($fechaDesde, function($q) use ($fechaDesde) {
                    return $q->whereDate('fecha_poliza', '>=', $fechaDesde);
                })
                ->when($fechaHasta, function($q) use ($fechaHasta) {
                    return $q->whereDate('fecha_poliza', '<=', $fechaHasta);
                });

            if ($tipoFiltro === 'fiscales') {
                $polizaQuery->where('categoria', 'FISCAL');
            } elseif ($tipoFiltro === 'no_fiscales') {
                $polizaQuery->where('categoria', '!=', 'FISCAL');
            }

            $polizaIds = $polizaQuery->pluck('id')->toArray();

            if (empty($polizaIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total_ingresos' => 0,
                    'total_egresos' => 0,
                    'total_movimientos' => 0,
                    'saldo' => 0,
                    'resultado' => 0,
                    'es_cuenta_resultados' => $esCuentaResultados,
                    'tipo_filtro' => $tipoFiltro
                ]);
            }

            // 🔥 OBTENER MOVIMIENTOS SOLO DE PÓLIZAS QUE CUMPLEN EL FILTRO
            $query = MovimientoPoliza::with([
                'poliza.persona',
                'poliza.abonos',
                'cuenta',
                'cuentaFondeadora'
            ])->whereIn('id_cuenta', $idsCuentas)
            ->whereIn('id_poliza', $polizaIds);

            $movimientos = $query->get();

            if ($movimientos->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total_ingresos' => 0,
                    'total_egresos' => 0,
                    'total_movimientos' => 0,
                    'saldo' => 0,
                    'resultado' => 0,
                    'es_cuenta_resultados' => $esCuentaResultados,
                    'tipo_filtro' => $tipoFiltro
                ]);
            }

            // 🔥 AGRUPAR MOVIMIENTOS POR PÓLIZA
            $movimientosAgrupados = [];
            $idsProcesados = [];
            $totalIngresos = 0;
            $totalEgresos = 0;
            $totalIva = 0;

            foreach ($movimientos as $mov) {
                $polizaId = $mov->id_poliza;
                
                if (in_array($polizaId, $idsProcesados)) {
                    continue;
                }
                
                $movimientosPoliza = MovimientoPoliza::where('id_poliza', $polizaId)->get();
                $totalIngresoPoliza = 0;
                $totalEgresoPoliza = 0;
                $montoIvaPoliza = 0;
                $montoBasePoliza = 0;
                
                foreach ($movimientosPoliza as $mp) {
                    if ($mp->monto > 0) {
                        $totalIngresoPoliza += abs($mp->monto);
                    } else {
                        $totalEgresoPoliza += abs($mp->monto);
                    }
                    
                    if (isset($mp->monto_iva) && $mp->monto_iva != 0) {
                        $montoIvaPoliza += abs((float) $mp->monto_iva);
                    } elseif (isset($mp->iva_dieciseis) && $mp->iva_dieciseis != 0) {
                        $montoIvaPoliza += abs((float) $mp->iva_dieciseis);
                    } else {
                        if ($mov->poliza && $mov->poliza->categoria === 'FISCAL') {
                            $montoIvaPoliza += abs($mp->monto) * 0.16;
                        }
                    }
                    $montoBasePoliza += abs((float) ($mp->monto_base ?? 0));
                }
                
                $tipoMovimiento = ($totalIngresoPoliza > $totalEgresoPoliza) ? 'INGRESO' : 'EGRESO';
                $montoTotal = $totalIngresoPoliza - $totalEgresoPoliza;
                $categoria = $mov->poliza->categoria ?? 'NO FISCAL';
                $persona = $mov->poliza->persona ? $mov->poliza->persona->nombre_completo : 'Sin persona';
                
                if ($montoTotal != 0) {
                    if ($montoTotal > 0) {
                        $totalIngresos += $montoTotal;
                    } else {
                        $totalEgresos += abs($montoTotal);
                    }
                    $totalIva += $montoIvaPoliza;
                    
                    $movimientosAgrupados[] = [
                        'id_movimiento' => $mov->id,
                        'id_poliza' => $polizaId,
                        'folio' => $mov->poliza->folio ?? 'N/A',
                        'fecha_poliza' => $mov->poliza->fecha_poliza ? $mov->poliza->fecha_poliza->format('Y-m-d') : null,
                        'persona' => $persona,
                        'cuenta' => $mov->cuenta ? $mov->cuenta->nombre_cuenta : 'N/A',
                        'cuenta_fondeadora' => $mov->cuentaFondeadora ? $mov->cuentaFondeadora->nombre_cuenta : 'N/A',
                        'monto' => $montoTotal,
                        'tipo' => $tipoMovimiento,
                        'nota' => $mov->poliza->nota ?? null,
                        'categoria' => $categoria,
                        'monto_iva' => $montoIvaPoliza,
                        'monto_base' => $montoBasePoliza
                    ];
                }
                
                $idsProcesados[] = $polizaId;
            }

            // 🔥 ORDENAR POR FECHA DESCENDENTE
            usort($movimientosAgrupados, function($a, $b) {
                return strcmp($b['fecha_poliza'] ?? '', $a['fecha_poliza'] ?? '');
            });

            $balance = $totalIngresos - $totalEgresos;

            return response()->json([
                'success' => true,
                'data' => $movimientosAgrupados,
                'total_ingresos' => $totalIngresos,
                'total_egresos' => $totalEgresos,
                'total_movimientos' => count($movimientosAgrupados),
                'saldo' => $balance,
                'resultado' => $balance,
                'total_iva' => $totalIva,
                'es_cuenta_resultados' => $esCuentaResultados,
                'tipo_filtro' => $tipoFiltro
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener movimientos de cuenta:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los movimientos: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 📊 EXPORTAR EXCEL
    // ============================================
    public function exportExcel(Request $request)
    {
        if (!Gate::allows('ver-reportes')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para exportar reportes');
        }

        $empresaId = $request->input('empresa_id');
        $vista = $request->input('vista', 'por_cuenta');
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');
        $tipoFiltro = $request->input('tipo_filtro', 'todas');

        if (!$empresaId) {
            return back()->with('error', 'Se requiere una empresa para exportar');
        }

        try {
            $polizaQuery = Poliza::where('id_empresa', $empresaId)
                ->where('es_por_pagar', false)
                ->when($fechaDesde, function($q) use ($fechaDesde) {
                    return $q->whereDate('fecha_poliza', '>=', $fechaDesde);
                })
                ->when($fechaHasta, function($q) use ($fechaHasta) {
                    return $q->whereDate('fecha_poliza', '<=', $fechaHasta);
                });

            if ($tipoFiltro === 'fiscales') {
                $polizaQuery->where('categoria', 'FISCAL');
            } elseif ($tipoFiltro === 'no_fiscales') {
                $polizaQuery->where('categoria', '!=', 'FISCAL');
            }

            $polizaIds = $polizaQuery->pluck('id')->toArray();

            if (empty($polizaIds)) {
                return back()->with('error', 'No hay datos para exportar');
            }

            $movimientos = MovimientoPoliza::with([
                'poliza.persona',
                'poliza.abonos',
                'cuenta',
                'cuentaFondeadora'
            ])
            ->whereIn('id_poliza', $polizaIds)
            ->get();

            if ($vista === 'por_cuenta') {
                $data = $this->agruparPorCuenta($movimientos);
            } else {
                $data = $this->agruparPorPersona($movimientos);
            }

            $fondeadoras = $this->getCuentasFondeadorasConSaldo($empresaId);
            $cuentasResultadosData = $this->getCuentasResultadosOptimizado($empresaId, $fechaDesde, $fechaHasta, $tipoFiltro, $movimientos);
            $cuentasResultados = $cuentasResultadosData['cuentas'] ?? [];
            $ivaTotal = $cuentasResultadosData['totales_iva'] ?? 0;

            $totalIvaIngresos = 0;
            $totalIvaEgresos = 0;
            foreach ($movimientos as $mov) {
                $monto = (float) $mov->monto;
                $iva = 0;
                if (isset($mov->monto_iva) && $mov->monto_iva != 0) {
                    $iva = (float) $mov->monto_iva;
                } elseif (isset($mov->iva_dieciseis) && $mov->iva_dieciseis != 0) {
                    $iva = (float) $mov->iva_dieciseis;
                }
                if ($iva == 0 && $mov->poliza && $mov->poliza->categoria === 'FISCAL') {
                    $iva = abs($monto) * 0.16;
                }
                if ($iva != 0) {
                    if ($monto > 0) {
                        $totalIvaIngresos += abs($iva);
                    } else {
                        $totalIvaEgresos += abs($iva);
                    }
                }
            }
            $balanceIva = $totalIvaIngresos - $totalIvaEgresos;

            $totalIngresos = array_sum(array_column($data, 'ingreso'));
            $totalEgresos = array_sum(array_column($data, 'egreso'));
            $diferencia = $totalIngresos - $totalEgresos;

            $empresa = \App\Models\Empresa::find($empresaId);
            $nombreEmpresa = $empresa ? $empresa->nombre_empresa : 'Sin empresa';

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Reporte');

            $sheet->setCellValue('A1', 'REPORTE DE MOVIMIENTOS');
            $sheet->setCellValue('A2', 'Empresa: ' . $nombreEmpresa);
            $sheet->setCellValue('A3', 'Fecha: ' . ($fechaDesde ?: 'Inicio') . ' - ' . ($fechaHasta ?: 'Actual'));
            $sheet->setCellValue('A4', 'Vista: ' . ($vista === 'por_cuenta' ? 'Por Cuenta' : 'Por Persona'));
            $sheet->setCellValue('A5', 'Filtro: ' . ucfirst($tipoFiltro));
            
            $sheet->getStyle('A1:A5')->getFont()->setBold(true);
            
            $row = 7;
            if ($vista === 'por_cuenta') {
                $sheet->setCellValue('A' . $row, 'Cuenta');
                $sheet->setCellValue('B' . $row, 'Código');
                $sheet->setCellValue('C' . $row, 'Persona');
                $sheet->setCellValue('D' . $row, 'Fondeo');
                $sheet->setCellValue('E' . $row, 'Ingresos');
                $sheet->setCellValue('F' . $row, 'Egresos');
                $sheet->setCellValue('G' . $row, 'Balance');
            } else {
                $sheet->setCellValue('A' . $row, 'Persona');
                $sheet->setCellValue('B' . $row, 'Fondeo');
                $sheet->setCellValue('C' . $row, 'Ingresos');
                $sheet->setCellValue('D' . $row, 'Egresos');
                $sheet->setCellValue('E' . $row, 'Balance');
            }

            $headerStyle = [
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ];
            $sheet->getStyle('A' . $row . ':' . ($vista === 'por_cuenta' ? 'G' : 'E') . $row)->applyFromArray($headerStyle);
            
            $row++;

            foreach ($data as $item) {
                if ($vista === 'por_cuenta') {
                    $sheet->setCellValue('A' . $row, $item['nombre']);
                    $sheet->setCellValue('B' . $row, $item['codigo'] ?? '---');
                    $sheet->setCellValue('C' . $row, $item['persona']);
                    $sheet->setCellValue('D' . $row, $item['fondeo']);
                    $sheet->setCellValue('E' . $row, $item['ingreso']);
                    $sheet->setCellValue('F' . $row, $item['egreso']);
                    $sheet->setCellValue('G' . $row, $item['ingreso'] - $item['egreso']);
                } else {
                    $sheet->setCellValue('A' . $row, $item['nombre']);
                    $sheet->setCellValue('B' . $row, $item['fondeo']);
                    $sheet->setCellValue('C' . $row, $item['ingreso']);
                    $sheet->setCellValue('D' . $row, $item['egreso']);
                    $sheet->setCellValue('E' . $row, $item['ingreso'] - $item['egreso']);
                }
                $row++;
            }

            $sheet->setCellValue('A' . $row, 'TOTALES');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            if ($vista === 'por_cuenta') {
                $sheet->setCellValue('E' . $row, $totalIngresos);
                $sheet->setCellValue('F' . $row, $totalEgresos);
                $sheet->setCellValue('G' . $row, $diferencia);
            } else {
                $sheet->setCellValue('C' . $row, $totalIngresos);
                $sheet->setCellValue('D' . $row, $totalEgresos);
                $sheet->setCellValue('E' . $row, $diferencia);
            }
            $sheet->getStyle('A' . $row . ':' . ($vista === 'por_cuenta' ? 'G' : 'E') . $row)->getFont()->setBold(true);

            $lastRow = $row;
            for ($i = 7; $i <= $lastRow; $i++) {
                if ($vista === 'por_cuenta') {
                    $sheet->getStyle('E' . $i . ':G' . $i)->getNumberFormat()->setFormatCode('$#,##0.00');
                } else {
                    $sheet->getStyle('C' . $i . ':E' . $i)->getNumberFormat()->setFormatCode('$#,##0.00');
                }
            }

            foreach (range('A', $vista === 'por_cuenta' ? 'G' : 'E') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            if ($tipoFiltro === 'fiscales') {
                $sheetIva = $spreadsheet->createSheet();
                $sheetIva->setTitle('IVA Fiscal');
                $rowIva = 1;
                
                $sheetIva->setCellValue('A' . $rowIva, 'RESUMEN DE IVA');
                $sheetIva->setCellValue('A' . ($rowIva + 1), 'Empresa: ' . $nombreEmpresa);
                $sheetIva->setCellValue('A' . ($rowIva + 2), 'Período: ' . ($fechaDesde ?: 'Inicio') . ' - ' . ($fechaHasta ?: 'Actual'));
                $sheetIva->getStyle('A' . $rowIva . ':A' . ($rowIva + 2))->getFont()->setBold(true);
                
                $rowIva += 4;
                
                $sheetIva->setCellValue('A' . $rowIva, 'Concepto');
                $sheetIva->setCellValue('B' . $rowIva, 'Monto');
                $sheetIva->getStyle('A' . $rowIva . ':B' . $rowIva)->applyFromArray($headerStyle);
                $rowIva++;
                
                $sheetIva->setCellValue('A' . $rowIva, 'IVA Ingresos');
                $sheetIva->setCellValue('B' . $rowIva, $totalIvaIngresos);
                $sheetIva->getStyle('B' . $rowIva)->getNumberFormat()->setFormatCode('$#,##0.00');
                $rowIva++;
                
                $sheetIva->setCellValue('A' . $rowIva, 'IVA Egresos');
                $sheetIva->setCellValue('B' . $rowIva, $totalIvaEgresos);
                $sheetIva->getStyle('B' . $rowIva)->getNumberFormat()->setFormatCode('$#,##0.00');
                $rowIva++;
                
                $sheetIva->setCellValue('A' . $rowIva, 'Balance IVA');
                $sheetIva->setCellValue('B' . $rowIva, $balanceIva);
                $sheetIva->getStyle('A' . $rowIva . ':B' . $rowIva)->getFont()->setBold(true);
                $sheetIva->getStyle('B' . $rowIva)->getNumberFormat()->setFormatCode('$#,##0.00');
                
                foreach (range('A', 'B') as $col) {
                    $sheetIva->getColumnDimension($col)->setAutoSize(true);
                }
            }

            if (!empty($cuentasResultados)) {
                $sheetResultados = $spreadsheet->createSheet();
                $sheetResultados->setTitle('Cuentas de Resultados');
                $rowResultados = 1;
                
                $sheetResultados->setCellValue('A' . $rowResultados, 'CUENTAS DE RESULTADOS');
                $sheetResultados->setCellValue('A' . ($rowResultados + 1), 'Empresa: ' . $nombreEmpresa);
                $sheetResultados->setCellValue('A' . ($rowResultados + 2), 'Filtro: ' . ucfirst($tipoFiltro));
                if ($tipoFiltro === 'fiscales') {
                    $sheetResultados->setCellValue('A' . ($rowResultados + 3), 'Total IVA: $' . number_format($ivaTotal, 2));
                }
                $sheetResultados->getStyle('A' . $rowResultados . ':A' . ($rowResultados + 3))->getFont()->setBold(true);
                
                $rowResultados += 5;
                
                $sheetResultados->setCellValue('A' . $rowResultados, 'Nombre de la Cuenta');
                $sheetResultados->setCellValue('B' . $rowResultados, 'Saldo');
                if ($tipoFiltro === 'fiscales') {
                    $sheetResultados->setCellValue('C' . $rowResultados, 'IVA');
                }
                $sheetResultados->setCellValue(($tipoFiltro === 'fiscales' ? 'D' : 'C') . $rowResultados, 'Nivel');
                
                $headerStyleResultados = [
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ];
                $lastColumn = $tipoFiltro === 'fiscales' ? 'D' : 'C';
                $sheetResultados->getStyle('A' . $rowResultados . ':' . $lastColumn . $rowResultados)->applyFromArray($headerStyleResultados);
                
                $rowResultados++;
                
                // 🔥 PASAR LAS CUENTAS YA ORDENADAS
                $this->renderCuentasJerarquicasExcel($sheetResultados, $cuentasResultados, $rowResultados, 0, $tipoFiltro);
                
                foreach (range('A', $lastColumn) as $col) {
                    $sheetResultados->getColumnDimension($col)->setAutoSize(true);
                }
            }

            if (!empty($fondeadoras)) {
                $sheetFondeadoras = $spreadsheet->createSheet();
                $sheetFondeadoras->setTitle('Fondeadoras');
                $rowFon = 1;
                
                $sheetFondeadoras->setCellValue('A' . $rowFon, 'CUENTAS FONDEADORAS');
                $sheetFondeadoras->setCellValue('A' . ($rowFon + 1), 'Empresa: ' . $nombreEmpresa);
                $sheetFondeadoras->getStyle('A' . $rowFon . ':A' . ($rowFon + 1))->getFont()->setBold(true);
                
                $rowFon += 3;
                
                $sheetFondeadoras->setCellValue('A' . $rowFon, 'Código');
                $sheetFondeadoras->setCellValue('B' . $rowFon, 'Nombre');
                $sheetFondeadoras->setCellValue('C' . $rowFon, 'Saldo');
                
                $headerStyleFon = [
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ];
                $sheetFondeadoras->getStyle('A' . $rowFon . ':C' . $rowFon)->applyFromArray($headerStyleFon);
                
                $rowFon++;
                
                foreach ($fondeadoras as $fon) {
                    $sheetFondeadoras->setCellValue('A' . $rowFon, $fon['codigo_cuenta']);
                    $sheetFondeadoras->setCellValue('B' . $rowFon, $fon['nombre_cuenta']);
                    $sheetFondeadoras->setCellValue('C' . $rowFon, $fon['saldo']);
                    $sheetFondeadoras->getStyle('C' . $rowFon)->getNumberFormat()->setFormatCode('$#,##0.00');
                    $rowFon++;
                }
                
                foreach (range('A', 'C') as $col) {
                    $sheetFondeadoras->getColumnDimension($col)->setAutoSize(true);
                }
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'reporte_completo_' . date('Y-m-d_H-i-s') . '.xlsx';

            $tempPath = storage_path('app/temp/' . $filename);
            if (!is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0777, true);
            }
            $writer->save($tempPath);

            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Error al exportar Excel:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Error al exportar Excel: ' . $e->getMessage());
        }
    }

    /**
     * Renderiza cuentas jerárquicas en Excel con orden INGRESOS/EGRESOS
     */
    private function renderCuentasJerarquicasExcel($sheet, $cuentas, &$row, $nivel, $tipoFiltro = 'todas')
    {
        // 🔥 ORDENAR: INGRESOS (saldo >= 0) ARRIBA, EGRESOS (saldo < 0) ABAJO
        usort($cuentas, function($a, $b) {
            $aEsIngreso = ($a['subtotal'] ?? 0) >= 0;
            $bEsIngreso = ($b['subtotal'] ?? 0) >= 0;
            
            if ($aEsIngreso && !$bEsIngreso) return -1;
            if (!$aEsIngreso && $bEsIngreso) return 1;
            return strcmp($a['codigo_cuenta'] ?? '', $b['codigo_cuenta'] ?? '');
        });

        foreach ($cuentas as $cuenta) {
            $indent = str_repeat('  ', $nivel);
            
            $sheet->setCellValue('A' . $row, $indent . $cuenta['nombre_cuenta']);
            $sheet->setCellValue('B' . $row, $cuenta['subtotal']);
            if ($tipoFiltro === 'fiscales') {
                $sheet->setCellValue('C' . $row, $cuenta['iva'] ?? 0);
                $sheet->setCellValue('D' . $row, $cuenta['nivel']);
            } else {
                $sheet->setCellValue('C' . $row, $cuenta['nivel']);
            }
            
            if ($cuenta['es_madre']) {
                $lastColumn = $tipoFiltro === 'fiscales' ? 'D' : 'C';
                $sheet->getStyle('A' . $row . ':' . $lastColumn . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F4FF']],
                ]);
            }
            
            if ($tipoFiltro === 'fiscales') {
                $sheet->getStyle('B' . $row . ':C' . $row)
                    ->getNumberFormat()
                    ->setFormatCode('$#,##0.00');
            } else {
                $sheet->getStyle('B' . $row)
                    ->getNumberFormat()
                    ->setFormatCode('$#,##0.00');
            }
            
            $row++;
            
            if (!empty($cuenta['hijas'])) {
                // 🔥 ORDENAR HIJAS TAMBIÉN
                $hijasOrdenadas = $cuenta['hijas'];
                usort($hijasOrdenadas, function($a, $b) {
                    $aEsIngreso = ($a['subtotal'] ?? 0) >= 0;
                    $bEsIngreso = ($b['subtotal'] ?? 0) >= 0;
                    
                    if ($aEsIngreso && !$bEsIngreso) return -1;
                    if (!$aEsIngreso && $bEsIngreso) return 1;
                    return strcmp($a['codigo_cuenta'] ?? '', $b['codigo_cuenta'] ?? '');
                });
                
                $this->renderCuentasJerarquicasExcel($sheet, $hijasOrdenadas, $row, $nivel + 1, $tipoFiltro);
            }
        }
    }

    // ============================================
    // 🔥 EXPORTAR PDF DE RESULTADOS
    // ============================================
    public function exportPdfResultados(Request $request)
    {
        if (!Gate::allows('ver-reportes')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para exportar reportes');
        }

        $empresaId = $request->input('empresa_id');
        $vista = $request->input('vista', 'por_cuenta');
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');
        $tipoFiltro = $request->input('tipo_filtro', 'todas');

        if (!$empresaId) {
            return back()->with('error', 'Se requiere una empresa para exportar');
        }

        try {
            $movimientos = null;
            $cuentasResultadosData = $this->getCuentasResultadosOptimizado(
                $empresaId, 
                $fechaDesde, 
                $fechaHasta, 
                $tipoFiltro,
                $movimientos
            );
            $cuentasResultados = $cuentasResultadosData['cuentas'] ?? [];
            $ivaTotal = $cuentasResultadosData['totales_iva'] ?? 0;

            if ($tipoFiltro === 'fiscales' && $ivaTotal == 0) {
                $ivaTotal = $this->calcularIvaDirecto($empresaId, $fechaDesde, $fechaHasta);
            }

            if (empty($cuentasResultados)) {
                return back()->with('error', 'No hay cuentas de resultados para exportar');
            }

            $empresa = \App\Models\Empresa::find($empresaId);
            $nombreEmpresa = $empresa ? $empresa->nombre_empresa : 'Sin empresa';

            $totalCuentas = 0;
            $saldoTotal = 0;
            $totalIngresos = 0;
            $totalEgresos = 0;
            
            foreach ($cuentasResultados as $padre) {
                $totalCuentas += count($padre['hijas']);
                $saldo = (float) ($padre['subtotal'] ?? 0);
                $saldoTotal += $saldo;
                
                if ($saldo > 0) {
                    $totalIngresos += $saldo;
                } else {
                    $totalEgresos += abs($saldo);
                }
            }

            $data = [
                'empresa' => $nombreEmpresa,
                'cuentas' => $cuentasResultados, // YA VIENEN ORDENADAS DEL MÉTODO
                'fecha_desde' => $fechaDesde ?: 'Inicio',
                'fecha_hasta' => $fechaHasta ?: 'Actual',
                'tipo_filtro' => $tipoFiltro,
                'total_cuentas' => $totalCuentas,
                'saldo_total' => $saldoTotal,
                'total_ingresos' => $totalIngresos,
                'total_egresos' => $totalEgresos,
                'iva_total' => $ivaTotal,
                'fecha_exportacion' => now()->format('d/m/Y H:i:s')
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.resultados-pdf', $data);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_left' => 15,
                'margin_right' => 15,
            ]);

            $filename = 'cuentas_resultados_' . date('Y-m-d_H-i-s') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            \Log::error('Error al exportar PDF de resultados:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Error al exportar PDF: ' . $e->getMessage());
        }
    }

    // ============================================
    // 🔥 MÉTODO PARA CALCULAR IVA DIRECTAMENTE
    // ============================================
    private function calcularIvaDirecto($empresaId, $fechaDesde = null, $fechaHasta = null)
    {
        $query = MovimientoPoliza::whereHas('poliza', function($q) use ($empresaId, $fechaDesde, $fechaHasta) {
            $q->where('id_empresa', $empresaId)
              ->where('es_por_pagar', false)
              ->where('categoria', 'FISCAL');
            
            if ($fechaDesde) {
                $q->whereDate('fecha_poliza', '>=', $fechaDesde);
            }
            if ($fechaHasta) {
                $q->whereDate('fecha_poliza', '<=', $fechaHasta);
            }
        });

        $result = $query->select(
            DB::raw('SUM(ABS(monto_iva)) as total_monto_iva'),
            DB::raw('SUM(ABS(iva_dieciseis)) as total_iva_dieciseis')
        )->first();

        $ivaTotal = 0;
        
        if ($result) {
            $ivaTotal = (float) ($result->total_monto_iva ?? 0);
            if ($ivaTotal == 0) {
                $ivaTotal = (float) ($result->total_iva_dieciseis ?? 0);
            }
        }

        return $ivaTotal;
    }
}