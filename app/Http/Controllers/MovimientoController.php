<?php

namespace App\Http\Controllers;

use App\Models\MovimientoPoliza;
use App\Models\Poliza;
use App\Models\Cuenta;
use App\Models\TipoIva;
use App\Models\Persona;
use App\Models\Marcador;
use App\Models\AbonoPoliza;
use App\Models\PolizaArchivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Carbon\Carbon; 
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class MovimientoController extends Controller
{

public function index(Request $request)
{
    if (!Gate::allows('ver-movimientos')) {
        return redirect()->route('dashboard')
            ->with('error', 'No tienes permiso para ver movimientos');
    }

    // Por defecto el índice abre con la fecha de HOY (fecha de póliza), a menos
    // que se pida otra fecha, se haya quitado a propósito (sin_fecha), se esté
    // paginando o se trate de la vista de diferidas.
    $traeFecha = $request->hasAny([
        'fecha_desde', 'fecha_hasta', 'factura_desde', 'factura_hasta', 'registro_desde', 'registro_hasta',
    ]);
    if (!$traeFecha && !$request->boolean('sin_fecha') && !$request->has('page')
        && $request->get('vista', 'normal') !== 'diferidas') {
        $hoy = now()->toDateString();
        $request->merge(['fecha_desde' => $hoy, 'fecha_hasta' => $hoy]);
    }

    $empresas = auth()->user()->empresas()->where('activo', true)->get();

    if ($empresas->isEmpty()) {
        return Inertia::render('Movimientos/Index', [
            'movimientos' => [
                'data' => [],
                'from' => 0,
                'to' => 0,
                'total' => 0,
                'links' => []
            ],
            'empresas' => [],
            'filtros' => [],
            'empresa_seleccionada' => null,
            'saldo_total' => null,
            'vista' => $request->get('vista', 'normal'),
            'contadores' => [
                'capturados' => 0,
                'revisados' => 0,
                'autorizados' => 0,
                'abonados' => 0,
                'liquidados' => 0,
            ]
        ]);
    }

    // Obtener empresa
    $empresaId = $request->input('empresa_id');
    if (!$empresaId) {
        $empresaId = session('empresa_seleccionada');
    }
    if ($empresaId && !$empresas->contains('id', $empresaId)) {
        $empresaId = null;
    }
    if (!$empresaId && $empresas->count() > 0) {
        $empresaId = $empresas->first()->id;
    }
    if ($empresaId) {
        // Mantener AMBAS claves sincronizadas: create/edit/store/nomina/abono
        // leen 'empresa_movimientos', y si no se actualiza aquí queda apuntando
        // a la empresa anterior (se veían las cuentas fondeadoras de otra empresa).
        session([
            'empresa_seleccionada' => $empresaId,
            'empresa_movimientos'  => $empresaId,
        ]);
    }

    $vista = $request->get('vista', 'normal');
    $soloFiscales = $request->boolean('solo_fiscales', false);

    // ============================================
    // 🔥 VISTA DE TRASPASOS - AGRUPAR POR PÓLIZA
    // ============================================
    if ($vista === 'traspasos') {
        return $this->obtenerTraspasosAgrupados($request, $empresaId, $empresas);
    }

    // ============================================
    // VISTAS: NORMAL, DIFERIDAS, PENDIENTES, AUTORIZADAS
    // ============================================
    $query = MovimientoPoliza::with([
        'poliza.persona',
        'poliza.usuarioCreador',
        'poliza.usuarioRevisor',
        'poliza.usuarioAutorizador',
        'poliza.archivos.usuario',
        'cuenta' => function($q) {
            $q->where('en_uso', true);
        },
        'cuentaFondeadora' => function($q) {
            $q->where('en_uso', true);
        },
        'poliza.marcador',
        'poliza.abonos'
    ]);

    $query->whereHas('cuenta', function($q) {
        $q->where('en_uso', true);
    });

    $query->whereHas('poliza', function($q) use ($empresaId) {
        $q->where('id_empresa', $empresaId)
            ->whereHas('empresa', function($sub) {
                $sub->where('activo', true);
            });
    });

    // El CAPTURISTA sólo ve las pólizas que él capturó.
    if (!Gate::allows('ver-todos-movimientos')) {
        $query->whereHas('poliza', function($q) {
            $q->where('id_usuario_creador', auth()->id());
        });
    }

    // 🔥 EXCLUIR TRASPASOS EN TODAS LAS VISTAS EXCEPTO "TRASPASOS"
    $query->whereHas('poliza', function($q) {
        $q->where('tipo_poliza', '!=', 'TRASPASO');
    });

    if ($soloFiscales) {
        $query->whereHas('poliza', function($q) {
            $q->where('categoria', 'FISCAL');
        });
    }

    // Calendario de fecha de factura (fiscales)
    $query->whereHas('poliza', function($q) use ($request) {
        $this->aplicarFiltrosRegistroYFactura($q, $request);
    });

    // Filtros específicos por vista
    if ($vista === 'diferidas') {
        // 🔥 Las diferidas que ya se liquidaron por completo dejan de mostrarse
        // aquí (no se borran, solo se ocultan de esta vista).
        // Filtro "Estado de pago": por defecto sólo lo que aún se debe;
        // "pagadas" muestra las ya liquidadas.
        $verPagadas = $request->get('estado_deuda') === 'pagadas';
        $query->whereHas('poliza', function($q) use ($verPagadas) {
            $q->where('es_por_pagar', true);
            $verPagadas
                ? $q->where('estatus', 'LIQUIDADO')
                : $q->where('estatus', '!=', 'LIQUIDADO');
        });
    } elseif ($vista === 'pendientes') {
        $query->whereHas('poliza', function($q) {
            $q->whereIn('estatus', ['PENDIENTE', 'CAPTURADO', 'REVISADO']);
        });
    } elseif ($vista === 'autorizadas') {
        $query->whereHas('poliza', function($q) {
            $q->where('estatus', 'AUTORIZADO');
        });
    } elseif ($vista === 'normal') {
        $query->whereHas('poliza', function($q) {
            $q->where('es_por_pagar', false);
        });
    }

    // Filtros de búsqueda
    if ($request->filled('fecha_desde')) {
        $query->whereHas('poliza', function($q) use ($request) {
            $q->whereDate('fecha_poliza', '>=', $request->fecha_desde);
        });
    }
    if ($request->filled('fecha_hasta')) {
        $query->whereHas('poliza', function($q) use ($request) {
            $q->whereDate('fecha_poliza', '<=', $request->fecha_hasta);
        });
    }
    if ($request->filled('referencia')) {
        $query->whereHas('poliza', function($q) use ($request) {
            $q->where(function($sub) use ($request) {
                $sub->where('folio', 'LIKE', '%' . $request->referencia . '%')
                    ->orWhere('referencia', 'LIKE', '%' . $request->referencia . '%');
            });
        });
    }
    if ($request->filled('persona')) {
        // Cada palabra debe aparecer en alguno de los campos del nombre, así
        // "JUAN LOPEZ" encuentra a quien tiene Nombre "JUAN" y Paterno "LOPEZ".
        $palabras = preg_split('/\s+/', trim($request->persona), -1, PREG_SPLIT_NO_EMPTY);
        $query->whereHas('poliza.persona', function($q) use ($palabras) {
            foreach ($palabras as $palabra) {
                $q->where(function($sub) use ($palabra) {
                    $sub->where('Nombre', 'LIKE', '%' . $palabra . '%')
                        ->orWhere('Paterno', 'LIKE', '%' . $palabra . '%')
                        ->orWhere('Materno', 'LIKE', '%' . $palabra . '%');
                });
            }
        });
    }
    if ($request->filled('cuenta')) {
        $query->whereHas('cuenta', function($q) use ($request) {
            $q->where('en_uso', true)
                ->where(function($sub) use ($request) {
                    $sub->where('nombre_cuenta', 'LIKE', '%' . $request->cuenta . '%')
                        ->orWhere('codigo_cuenta', 'LIKE', '%' . $request->cuenta . '%');
                });
        });
    }
    if ($request->filled('estatus')) {
        $query->whereHas('poliza', function($q) use ($request) {
            $q->where('estatus', $request->estatus);
        });
    }
    if ($request->filled('tipo_poliza')) {
        $query->whereHas('poliza', function($q) use ($request) {
            $q->where('tipo_poliza', $request->tipo_poliza);
        });
    }
    if ($request->filled('cuenta_fondeadora')) {
        $query->whereHas('cuentaFondeadora', function($q) use ($request) {
            $q->where('en_uso', true)
                ->where(function($sub) use ($request) {
                    $sub->where('nombre_cuenta', 'LIKE', '%' . $request->cuenta_fondeadora . '%')
                        ->orWhere('codigo_cuenta', 'LIKE', '%' . $request->cuenta_fondeadora . '%');
                });
        });
    }
    if ($request->filled('nota')) {
        $query->whereHas('poliza', function($q) use ($request) {
            $q->where('nota', 'LIKE', '%' . $request->nota . '%');
        });
    }
    if ($request->filled('usuario')) {
        $query->whereHas('poliza.usuarioCreador', function($q) use ($request) {
            $q->where(function($sub) use ($request) {
                $sub->where('nombre_usuario', 'LIKE', '%' . $request->usuario . '%')
                    ->orWhere('nombre_completo', 'LIKE', '%' . $request->usuario . '%');
            });
        });
    }

    // Ordenamiento
    // Por defecto las pólizas van por REFERENCIA (folio) descendente: de la más
    // alta a la más baja (0010, 0009, ..., 0001).
    $sortBy = $request->get('sort_by', 'referencia');
    $sortOrder = $request->get('sort_order', 'desc');
    $sortMap = [
        'id' => 'polizas.id',
        'fecha_poliza' => 'polizas.fecha_poliza',
        'fecha_vencimiento' => 'polizas.fecha_vencimiento',
        'referencia' => 'polizas.folio',
        'estatus' => 'polizas.estatus',
        'monto' => 'movimientos_poliza.monto',
        'persona' => 'polizas.id_persona',
        'cuenta' => 'movimientos_poliza.id_cuenta'
    ];

    if (isset($sortMap[$sortBy])) {
        if (strpos($sortMap[$sortBy], '.') !== false) {
            $query->join('polizas', 'movimientos_poliza.id_poliza', '=', 'polizas.id')
                ->select('movimientos_poliza.*');
            if ($sortBy === 'referencia') {
                // El folio es VARCHAR ("0001"); ordenar numéricamente y luego como
                // texto para que folios legacy no numéricos no rompan el orden.
                $query->orderByRaw('CAST(polizas.folio AS UNSIGNED) ' . ($sortOrder === 'desc' ? 'desc' : 'asc'))
                      ->orderBy('polizas.folio', $sortOrder === 'desc' ? 'desc' : 'asc');
            } else {
                $query->orderBy($sortMap[$sortBy], $sortOrder);
            }
        } else {
            $query->orderBy($sortMap[$sortBy], $sortOrder);
        }
    } else {
        $query->join('polizas', 'movimientos_poliza.id_poliza', '=', 'polizas.id')
            ->select('movimientos_poliza.*')
            ->orderBy('polizas.id', 'desc');
    }

    // Diferidas: "Más adeudo" / "Menos adeudo" ordenan por saldo pendiente
    // (monto - abonos), de mayor a menor o de menor a mayor.
    if ($vista === 'diferidas' && in_array($request->get('estado_deuda'), ['mayor', 'menor'], true)) {
        $dir = $request->get('estado_deuda') === 'mayor' ? 'desc' : 'asc';
        $query->reorder()->orderByRaw(
            'ABS(movimientos_poliza.monto) - COALESCE((SELECT SUM(a.monto_abonado) FROM abonos_poliza a WHERE a.id_poliza = movimientos_poliza.id_poliza), 0) ' . $dir
        )->orderBy('movimientos_poliza.id', 'desc');
    }

    $perPage = $request->get('per_page', 15);

    // 🔥 Resumen de totales sobre TODO lo filtrado (fecha, estatus, persona…),
    // no solo sobre la página que se está viendo. Se calcula ANTES de paginar:
    // paginate() deja LIMIT/OFFSET en el builder y en la página 2+ el SUM
    // saldría en 0 (el offset salta la única fila del agregado).
    $queryTotales = (clone $query)->reorder();
    $resumenTotales = [
        'ingresos' => round((float) (clone $queryTotales)->where('movimientos_poliza.monto', '>', 0)->sum('movimientos_poliza.monto'), 2),
        'egresos' => round(abs((float) (clone $queryTotales)->where('movimientos_poliza.monto', '<', 0)->sum('movimientos_poliza.monto')), 2),
        'traspasos' => 0,
        'pendiente' => 0,
    ];

    // "Total pendiente" de Diferidas: saldo por abonar de TODAS las diferidas
    // filtradas (monto - abonos), no solo de las filas de la página visible.
    if ($vista === 'diferidas') {
        $filasDiferidas = (clone $queryTotales)->setEagerLoads([])
            ->get(['movimientos_poliza.id_poliza', 'movimientos_poliza.monto']);
        $abonosPorPoliza = AbonoPoliza::whereIn('id_poliza', $filasDiferidas->pluck('id_poliza'))
            ->selectRaw('id_poliza, SUM(monto_abonado) as total')
            ->groupBy('id_poliza')
            ->pluck('total', 'id_poliza');
        $resumenTotales['pendiente'] = round($filasDiferidas->sum(function ($fila) use ($abonosPorPoliza) {
            return abs((float) $fila->monto) - (float) ($abonosPorPoliza[$fila->id_poliza] ?? 0);
        }), 2);
    }

    // withQueryString() conserva TODOS los filtros/empresa/orden en los enlaces
    // de paginación; sin esto, al pasar a la página 2 se pierden los filtros.
    $movimientos = $query->paginate($perPage)->withQueryString();

    // Anotaciones de transparencia (condominios) de las pólizas de esta página.
    $anotacionesPorPoliza = \App\Models\PolizaAnotacion::raiz()
        ->whereIn('id_poliza', $movimientos->getCollection()->pluck('id_poliza')->unique())
        ->selectRaw('id_poliza, COUNT(*) as total, SUM(resuelta = 0) as abiertas')
        ->groupBy('id_poliza')
        ->get()
        ->keyBy('id_poliza');

    // Transformar datos
    $movimientosData = $movimientos->through(function($movimiento) use ($anotacionesPorPoliza) {
        $anotaciones = $anotacionesPorPoliza->get($movimiento->id_poliza);
        $abonos = $movimiento->poliza->abonos ?? collect();
        $totalAbonado = $abonos->sum('monto_abonado');
        $montoMostrar = $movimiento->monto;
        $saldoPendiente = abs($montoMostrar) - $totalAbonado;

        $pdfUrl = null;
        if ($movimiento->poliza->categoria === 'FISCAL' && !empty($movimiento->poliza->ruta_pdf)) {
            $pdfUrl = route('movimientos.documento.fiscal', [
                'id' => $movimiento->id_poliza,
                'tipo' => 'pdf'
            ]);
        }

        $recurso = $movimiento->poliza->archivos->first();
        $tieneRecurso = $recurso ? true : false;
        $recursoUrl = $recurso ? route('movimientos.archivos.ver', $recurso->id) : null;
        $recursoTipo = $recurso ? $recurso->tipo_archivo : null;
        $recursoId = $recurso ? $recurso->id : null;
        $recursoNombre = $recurso ? $recurso->nombre_original : null;

        return [
            'id_movimiento' => $movimiento->id,
            'id_poliza' => $movimiento->id_poliza,
            'referencia' => $movimiento->poliza->folio ?? null,
            'referencia_adicional' => $movimiento->poliza->referencia ?? null,
            'fecha_poliza' => $movimiento->poliza->fecha_poliza ?? null,
            'fecha_factura' => $movimiento->poliza?->fecha_factura?->format('Y-m-d'),
            'fecha_vencimiento' => $movimiento->poliza->fecha_vencimiento ?? null,
            'estatus' => $movimiento->poliza->estatus ?? null,
            'persona' => $movimiento->poliza->persona ? $movimiento->poliza->persona->nombre_completo : null,
            'cuenta' => $movimiento->cuenta && $movimiento->cuenta->en_uso ? $movimiento->cuenta->nombre_cuenta : null,
            'cuenta_fondeadora' => $movimiento->cuentaFondeadora && $movimiento->cuentaFondeadora->en_uso ? $movimiento->cuentaFondeadora->nombre_cuenta : null,
            'nota' => $movimiento->poliza->nota ?? null,
            'monto' => $montoMostrar,
            'abonado' => $totalAbonado,
            'saldo_pendiente' => $saldoPendiente,
            'es_fiscal' => $movimiento->poliza->categoria === 'FISCAL',
            'es_traspaso' => false, // Siempre false en vistas que no son traspasos
            'puede_editar_esta' => $this->puedeEditarPoliza($movimiento->poliza),
            'es_por_pagar' => $movimiento->poliza->es_por_pagar ?? false,
            'tipo_poliza' => $movimiento->poliza->tipo_poliza ?? null,
            'tiene_pdf_fiscal' => !empty($movimiento->poliza->ruta_pdf),
            'pdf_url' => $pdfUrl,
            'tiene_recurso' => $tieneRecurso,
            'recurso_url' => $recursoUrl,
            'recurso_tipo' => $recursoTipo,
            'recurso_id' => $recursoId,
            'recurso_nombre' => $recursoNombre,
            ...$this->datosSubidaRecurso($recurso, $movimiento->poliza),
            'usuario' => $movimiento->poliza->usuarioCreador ? $movimiento->poliza->usuarioCreador->nombre_usuario : null,
            'created_at' => $movimiento->created_at,
            'anotaciones_total' => (int) ($anotaciones->total ?? 0),
            'anotaciones_abiertas' => (int) ($anotaciones->abiertas ?? 0),
        ];
    });

    // Calcular saldo total
    // (usa los totales ya calculados antes de paginar; después de paginate()
    // el builder trae LIMIT/OFFSET y daba resultados incorrectos en página 2+)
    $saldoTotal = $vista === 'diferidas'
        ? $resumenTotales['pendiente']
        : round($resumenTotales['ingresos'] - $resumenTotales['egresos'], 2);

    // Filtros para el frontend
    $filtros = $request->only([
        'fecha_desde', 'fecha_hasta', 'referencia', 
        'estatus', 'tipo_poliza', 'persona', 'cuenta', 
        'cuenta_fondeadora', 'nota', 'usuario',
        'factura_desde', 'factura_hasta', 'registro_desde', 'registro_hasta', 'estado_deuda',
        'sort_by', 'sort_order', 'vista', 'mostrar_todos', 'solo_fiscales'
    ]);

    // Contadores (respetan el alcance del CAPTURISTA: sólo lo que él capturó)
    $soloMios = !Gate::allows('ver-todos-movimientos');
    $baseContador = fn () => Poliza::where('id_empresa', $empresaId)
        ->when($soloMios, fn ($q) => $q->where('id_usuario_creador', auth()->id()));
    $contadores = [
        'capturados' => $baseContador()->where('estatus', 'PENDIENTE')->count(),
        'revisados' => $baseContador()->where('estatus', 'REVISADO')->count(),
        'autorizados' => $baseContador()->where('estatus', 'AUTORIZADO')->count(),
        'abonados' => $baseContador()->where('estatus', 'ABONADO')->count(),
        'liquidados' => $baseContador()->where('estatus', 'LIQUIDADO')->count(),
        'traspasos' => $baseContador()->where('tipo_poliza', 'TRASPASO')->count(),
    ];

    // 🔥 CORREGIDO: Usar fondeo_c en lugar de es_fondeadora
    // Debe filtrarse por empresa; sin este where salían las fondeadoras de TODAS las empresas.
    $cuentasFondeadoras = Cuenta::where('id_empresa', $empresaId)
        ->fondeadorasAsignadas($empresaId)
        ->where(function($q) {
            $q->where('fondeo_c', 1)
              ->orWhere('tipo_cuenta', 'FONDEADORA');
        })
        ->where('en_uso', true)
        ->get()
        ->map(function($cuenta) {
            return [
                'id' => $cuenta->id_cuenta,
                'nombre' => $cuenta->nombre_cuenta,
                'codigo' => $cuenta->codigo_cuenta,
            ];
        });

    return Inertia::render('Movimientos/Index', [
        'movimientos' => $movimientosData,
        'empresas' => $empresas,
        'filtros' => $filtros,
        'empresa_seleccionada' => (int) $empresaId,
        'saldo_total' => $saldoTotal !== null ? (float) $saldoTotal : null,
        'resumen_totales' => $resumenTotales,
        'vista' => $vista,
        'contadores' => $contadores,
        'cuentas_fondeadoras' => $cuentasFondeadoras,
        'sin_fondeadoras' => $this->sinFondeadorasEnEmpresa($empresaId),
    ]);
}

/**
 * 🔥 OBTENER TRASPASOS AGRUPADOS POR PÓLIZA
 */
private function obtenerTraspasosAgrupados(Request $request, $empresaId, $empresas)
{
    $sortBy = $request->get('sort_by', 'referencia');
    $sortOrder = $request->get('sort_order', 'desc');
    $perPage = $request->get('per_page', 15);

    // Mapear el sort del frontend a columnas reales de `polizas`.
    $sortColumnMap = [
        'id' => 'id',
        'referencia' => 'folio',
        'fecha_poliza' => 'fecha_poliza',
        'fecha_vencimiento' => 'fecha_vencimiento',
        'estatus' => 'estatus',
    ];
    $sortColumn = $sortColumnMap[$sortBy] ?? 'folio';

    $polizas = Poliza::where('id_empresa', $empresaId)
        ->where('tipo_poliza', 'TRASPASO')
        ->when(!Gate::allows('ver-todos-movimientos'), function($q) {
            $q->where('id_usuario_creador', auth()->id());
        })
        ->with([
            'movimientos.cuenta',
            'movimientos.cuentaFondeadora',
            'persona',
            'usuarioCreador',
            'archivos.usuario'
        ])
        // Filtros
        ->when($request->filled('fecha_desde'), function($q) use ($request) {
            return $q->whereDate('fecha_poliza', '>=', $request->fecha_desde);
        })
        ->when($request->filled('fecha_hasta'), function($q) use ($request) {
            return $q->whereDate('fecha_poliza', '<=', $request->fecha_hasta);
        })
        ->where(function($q) use ($request) {
            $this->aplicarFiltrosRegistroYFactura($q, $request);
        })
        ->when($request->filled('referencia'), function($q) use ($request) {
            return $q->where(function($sub) use ($request) {
                $sub->where('folio', 'LIKE', '%' . $request->referencia . '%')
                    ->orWhere('referencia', 'LIKE', '%' . $request->referencia . '%');
            });
        })
        ->when($request->filled('estatus'), function($q) use ($request) {
            return $q->where('estatus', $request->estatus);
        })
        ->when($request->filled('persona'), function($q) use ($request) {
            $palabras = preg_split('/\s+/', trim($request->persona), -1, PREG_SPLIT_NO_EMPTY);
            return $q->whereHas('persona', function($sub) use ($palabras) {
                foreach ($palabras as $palabra) {
                    $sub->where(function($inner) use ($palabra) {
                        $inner->where('Nombre', 'LIKE', '%' . $palabra . '%')
                            ->orWhere('Paterno', 'LIKE', '%' . $palabra . '%')
                            ->orWhere('Materno', 'LIKE', '%' . $palabra . '%');
                    });
                }
            });
        })
        ->when($request->filled('nota'), function($q) use ($request) {
            return $q->where('nota', 'LIKE', '%' . $request->nota . '%');
        })
        ->when($request->filled('usuario'), function($q) use ($request) {
            return $q->whereHas('usuarioCreador', function($sub) use ($request) {
                $sub->where(function($inner) use ($request) {
                    $inner->where('nombre_usuario', 'LIKE', '%' . $request->usuario . '%')
                        ->orWhere('nombre_completo', 'LIKE', '%' . $request->usuario . '%');
                });
            });
        })
        ->when($request->filled('solo_fiscales'), function($q) use ($request) {
            if ($request->boolean('solo_fiscales')) {
                return $q->where('categoria', 'FISCAL');
            }
            return $q;
        })
        // Ordenamiento
        ->when($sortColumn === 'folio', function ($q) use ($sortOrder) {
            $dir = $sortOrder === 'desc' ? 'desc' : 'asc';
            $q->orderByRaw('CAST(folio AS UNSIGNED) ' . $dir)->orderBy('folio', $dir);
        }, function ($q) use ($sortColumn, $sortOrder) {
            $q->orderBy($sortColumn, $sortOrder);
        });

    // Total de traspasos de TODO lo filtrado (no solo de la página actual).
    // Se calcula ANTES de paginar: paginate() deja LIMIT/OFFSET en el builder.
    $totalTraspasosFiltrados = round(abs((float) MovimientoPoliza::whereIn(
        'id_poliza',
        (clone $polizas)->reorder()->select('id')
    )->where('monto', '<', 0)->sum('monto')), 2);

    $polizasPaginadas = $polizas->paginate($perPage)->withQueryString();

    // Transformar cada póliza en un solo registro
    $movimientosData = $polizasPaginadas->through(function($poliza) {
        $movimientoOrigen = $poliza->movimientos->firstWhere('monto', '<', 0);
        $movimientoDestino = $poliza->movimientos->firstWhere('monto', '>', 0);

        $cuentaOrigen = $movimientoOrigen ? $movimientoOrigen->cuenta : null;
        $cuentaDestino = $movimientoDestino ? $movimientoDestino->cuenta : null;
        $monto = $movimientoOrigen ? abs($movimientoOrigen->monto) : 0;

        $recurso = $poliza->archivos->first();
        $tieneRecurso = $recurso ? true : false;
        $recursoUrl = $recurso ? route('movimientos.archivos.ver', $recurso->id) : null;
        $recursoTipo = $recurso ? $recurso->tipo_archivo : null;
        $recursoId = $recurso ? $recurso->id : null;
        $recursoNombre = $recurso ? $recurso->nombre_original : null;

        $pdfUrl = null;
        if ($poliza->categoria === 'FISCAL' && !empty($poliza->ruta_pdf)) {
            $pdfUrl = route('movimientos.documento.fiscal', [
                'id' => $poliza->id,
                'tipo' => 'pdf'
            ]);
        }

        return [
            'id_movimiento' => $movimientoOrigen ? $movimientoOrigen->id : null,
            'id_poliza' => $poliza->id,
            'referencia' => $poliza->folio ?? null,
            'tipo_poliza' => $poliza->tipo_poliza ?? 'TRASPASO',
            'fecha_poliza' => $poliza->fecha_poliza ?? null,
            'fecha_factura' => $poliza?->fecha_factura?->format('Y-m-d'),
            'fecha_vencimiento' => $poliza->fecha_vencimiento ?? null,
            'estatus' => $poliza->estatus ?? null,
            'persona' => $poliza->persona ? $poliza->persona->nombre_completo : null,
            'cuenta' => $cuentaOrigen ? $cuentaOrigen->nombre_cuenta : null,
            'cuenta_destino' => $cuentaDestino ? $cuentaDestino->nombre_cuenta : null,
            'nota' => $poliza->nota ?? null,
            'monto' => $monto,
            'es_fiscal' => $poliza->categoria === 'FISCAL',
            'es_traspaso' => true,
            'puede_editar_esta' => $this->puedeEditarPoliza($poliza),
            'tiene_recurso' => $tieneRecurso,
            'recurso_url' => $recursoUrl,
            'recurso_tipo' => $recursoTipo,
            'recurso_id' => $recursoId,
            'recurso_nombre' => $recursoNombre,
            ...$this->datosSubidaRecurso($recurso, $poliza),
            'tiene_pdf_fiscal' => !empty($poliza->ruta_pdf),
            'pdf_url' => $pdfUrl,
            'usuario' => $poliza->usuarioCreador ? $poliza->usuarioCreador->nombre_usuario : null,
            'created_at' => $poliza->created_at,
            // Campos adicionales para que el frontend no falle
            'abonado' => 0,
            'saldo_pendiente' => 0,
            'es_por_pagar' => false,
            'cuenta_fondeadora' => null,
        ];
    });

    $filtros = $request->only([
        'fecha_desde', 'fecha_hasta', 'referencia', 
        'estatus', 'persona', 'nota', 'usuario',
        'factura_desde', 'factura_hasta', 'registro_desde', 'registro_hasta',
        'sort_by', 'sort_order', 'vista', 'solo_fiscales'
    ]);

    $soloMios = !Gate::allows('ver-todos-movimientos');
    $baseContador = fn () => Poliza::where('id_empresa', $empresaId)
        ->when($soloMios, fn ($q) => $q->where('id_usuario_creador', auth()->id()));
    $contadores = [
        'capturados' => $baseContador()->where('estatus', 'PENDIENTE')->count(),
        'revisados' => $baseContador()->where('estatus', 'REVISADO')->count(),
        'autorizados' => $baseContador()->where('estatus', 'AUTORIZADO')->count(),
        'abonados' => $baseContador()->where('estatus', 'ABONADO')->count(),
        'liquidados' => $baseContador()->where('estatus', 'LIQUIDADO')->count(),
        'traspasos' => $baseContador()->where('tipo_poliza', 'TRASPASO')->count(),
    ];

    // 🔥 CORREGIDO: Usar fondeo_c en lugar de es_fondeadora
    // Debe filtrarse por empresa; sin este where salían las fondeadoras de TODAS las empresas.
    $cuentasFondeadoras = Cuenta::where('id_empresa', $empresaId)
        ->fondeadorasAsignadas($empresaId)
        ->where(function($q) {
            $q->where('fondeo_c', 1)
              ->orWhere('tipo_cuenta', 'FONDEADORA');
        })
        ->where('en_uso', true)
        ->get()
        ->map(function($cuenta) {
            return [
                'id' => $cuenta->id_cuenta,
                'nombre' => $cuenta->nombre_cuenta,
                'codigo' => $cuenta->codigo_cuenta,
            ];
        });

    return Inertia::render('Movimientos/Index', [
        'movimientos' => $movimientosData,
        'empresas' => $empresas,
        'filtros' => $filtros,
        'empresa_seleccionada' => (int) $empresaId,
        'saldo_total' => null,
        'resumen_totales' => [
            'ingresos' => $totalTraspasosFiltrados,
            'egresos' => 0,
            'traspasos' => $totalTraspasosFiltrados,
        ],
        'vista' => 'traspasos',
        'contadores' => $contadores,
        'cuentas_fondeadoras' => $cuentasFondeadoras,
        'sin_fondeadoras' => $this->sinFondeadorasEnEmpresa($empresaId),
    ]);
}

public function create()
{
    if (!Gate::allows('crear-movimientos')) {
        return redirect()->route('movimientos.index')
            ->with('error', 'No tienes permiso para crear movimientos');
    }

    // 🔥 OBTENER EMPRESA DE SESIÓN (usando ambas claves)
    $empresaId = session('empresa_movimientos') ?? session('empresa_seleccionada');
    
    if (!$empresaId) {
        $empresa = auth()->user()->empresas()->first();
        $empresaId = $empresa ? $empresa->id : null;
    }

    if (!$empresaId) {
        return redirect()->route('movimientos.index')
            ->with('error', 'No tienes una empresa seleccionada.');
    }

    // Guardar en sesión para consistencia
    session(['empresa_movimientos' => $empresaId]);
    session(['empresa_seleccionada' => $empresaId]);

    // Sin ninguna cuenta fondeadora asignada no se puede generar ninguna póliza.
    if ($this->sinFondeadorasEnEmpresa($empresaId)) {
        return redirect()->route('movimientos.index')->with('error', $this->mensajeSinFondeadoras());
    }

    // 🔥 CUENTAS FONDEADORAS (solo fondeo_c = 1 y activas)
    $cuentasFondeadoras = Cuenta::where('id_empresa', $empresaId)
        ->fondeadorasAsignadas($empresaId)
        ->where('en_uso', true)
        ->where('fondeo_c', 1)
        ->orderBy('nombre_cuenta')
        ->get()
        ->map(function($cuenta) {
            return [
                'id_cuenta' => $cuenta->id_cuenta,
                'nombre_cuenta' => $cuenta->nombre_cuenta,
                'codigo_cuenta' => $cuenta->codigo_cuenta,
                'fondeo_c' => (int) ($cuenta->fondeo_c ?? 0),
                'saldo' => (float) ($cuenta->saldo_inicial ?? 0),
                'Naturaleza' => $cuenta->Naturaleza,
                'tipo' => 'fondeadora'
            ];
        });

    
    
        // 🔥 CUENTAS PARA EGRESOS (DÉBITO) - NO fondeadoras
    $cuentasEgreso = Cuenta::where('id_empresa', $empresaId)
        ->where('en_uso', true)
        ->where(function($query) {
            // Excluir fondeadoras
            $query->where('fondeo_c', '!=', 1)
                  ->orWhereNull('fondeo_c');
        })
        ->where(function($query) {
            // Pueden recibir débito
            $query->where('es_cuenta_resultados', true)
                  ->orWhereNull('Naturaleza')
                  ->orWhere('Naturaleza', 'DEUDORA');
        })
        ->orderBy('nombre_cuenta')
        ->get()
        ->map(function($cuenta) {
            return [
                'id_cuenta' => $cuenta->id_cuenta,
                'nombre_cuenta' => $cuenta->nombre_cuenta,
                'codigo_cuenta' => $cuenta->codigo_cuenta,
                'Naturaleza' => $cuenta->Naturaleza ?? 'SIN NATURALEZA',
                'es_cuenta_resultados' => (bool) $cuenta->es_cuenta_resultados,
                'fondeo_c' => (int) ($cuenta->fondeo_c ?? 0),
                'saldo_inicial' => (float) ($cuenta->saldo_inicial ?? 0),
                'tipo_movimiento' => 'egreso'
            ];
        });

    // 🔥 CUENTAS PARA INGRESOS (CRÉDITO) - NO fondeadoras
    $cuentasIngreso = Cuenta::where('id_empresa', $empresaId)
        ->where('en_uso', true)
        ->where(function($query) {
            // Excluir fondeadoras
            $query->where('fondeo_c', '!=', 1)
                  ->orWhereNull('fondeo_c');
        })
        ->where(function($query) {
            // Pueden recibir crédito
            $query->where('es_cuenta_resultados', true)
                  ->orWhereNull('Naturaleza')
                  ->orWhere('Naturaleza', 'ACREEDORA');
        })
        ->orderBy('nombre_cuenta')
        ->get()
        ->map(function($cuenta) {
            return [
                'id_cuenta' => $cuenta->id_cuenta,
                'nombre_cuenta' => $cuenta->nombre_cuenta,
                'codigo_cuenta' => $cuenta->codigo_cuenta,
                'Naturaleza' => $cuenta->Naturaleza ?? 'SIN NATURALEZA',
                'es_cuenta_resultados' => (bool) $cuenta->es_cuenta_resultados,
                'fondeo_c' => (int) ($cuenta->fondeo_c ?? 0),
                'saldo_inicial' => (float) ($cuenta->saldo_inicial ?? 0),
                'tipo_movimiento' => 'ingreso'
            ];
        });

    // 🔥 OBTENER PERSONAS - CORREGIDO CON LOS NOMBRES CORRECTOS DE COLUMNAS
    $personas = \App\Models\Persona::where('activo', true)
        ->orderBy('Nombre')  // ← CORREGIDO: 'Nombre' con mayúscula
        ->get(['id_persona', 'Nombre', 'Paterno', 'Materno'])  // ← CORREGIDO: nombres de columnas correctos
        ->map(function($persona) {
            // Construir nombre completo
            $nombreCompleto = $persona->Nombre;
            if ($persona->Paterno) {
                $nombreCompleto .= ' ' . $persona->Paterno;
            }
            if ($persona->Materno) {
                $nombreCompleto .= ' ' . $persona->Materno;
            }
            
            return [
                'id_persona' => $persona->id_persona,
                'nombre_completo' => $nombreCompleto,
                'nombre' => $persona->Nombre,
                'paterno' => $persona->Paterno,
                'materno' => $persona->Materno
            ];
        });

    $marcadores = Marcador::where('activo', true)
        ->orderBy('nombre_marcador')
        ->get()
        ->map(function($marcador) {
            return [
                'id' => $marcador->id,
                'nombre_marcador' => $marcador->nombre_marcador,
                'descripcion' => $marcador->descripcion
            ];
        });

    $tiposIva = TipoIva::where('activo', true)
        ->orderBy('porcentaje')
        ->get()
        ->map(function($iva) {
            return [
                'id' => $iva->id,
                'nombre' => $iva->nombre,
                'porcentaje' => $iva->porcentaje,
                'porcentaje_formateado' => $iva->porcentaje . '%'
            ];
        });

    return Inertia::render('Movimientos/Create', [
        'empresa_id' => (int) $empresaId,
        'cuentas_egreso' => $cuentasEgreso,
        'cuentas_ingreso' => $cuentasIngreso,
        'cuentas_fondeadoras' => $cuentasFondeadoras,
        'tipos_iva' => $tiposIva,
        'marcadores' => $marcadores,
        'personas' => $personas
    ]);
}
   // ============================================
// 📄 STORE - CREAR PÓLIZA CON VALIDACIÓN DE NATURALEZA
// ============================================
public function store(Request $request)
{
    if (!Gate::allows('crear-movimientos')) {
        return $this->respuestaErrorStore($request, 'No tienes permiso para crear movimientos', [], 403);
    }

    $rules = [
        'tipo_poliza' => 'required|in:INGRESO,EGRESO',
        'fecha_poliza' => 'required|date',
        'id_persona' => 'nullable|exists:personas,id_persona',
        'id_cuenta' => 'required|exists:cuentas,id_cuenta',
        'es_por_pagar' => 'nullable|in:true,false,1,0,on,off',
        'fecha_vencimiento' => 'nullable|date|after_or_equal:today',
        'es_fiscal' => 'nullable|in:true,false,1,0,on,off',
        'id_marcador' => 'nullable|exists:marcadores,id',
        'total_factura' => 'required|numeric|min:0.01',
        'fecha_factura' => 'nullable|date',
        'numero_factura' => 'nullable|string|max:50',
        'nota' => 'nullable|string',
        'referencia' => 'nullable|string|max:100',
        'id_cuenta_fondeadora' => 'required|exists:cuentas,id_cuenta',
        'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
        'uuid_factura' => 'nullable|string|max:50',
        'serie_factura' => 'nullable|string|max:20',
        'folio_factura' => 'nullable|string|max:20',
        'modo_iva' => 'nullable|in:CON_IVA,SIN_IVA',
        'monto_directo' => 'nullable|numeric|min:0',
        'ivas' => 'nullable|array|min:1|max:2',
        'ivas.*.id' => 'nullable|exists:tipos_iva,id',
        'ivas.*.monto' => 'numeric|min:0',
    ];

    $messages = [
        'fecha_poliza.required' => 'La fecha de póliza es obligatoria',
        'fecha_poliza.date' => 'La fecha de póliza debe ser una fecha válida',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
        \Log::error('Validación store fallida:', $validator->errors()->toArray());
        return $this->respuestaErrorStore($request, $validator->errors()->first(), $validator->errors()->toArray());
    }

    if ($request->filled('id_cuenta_fondeadora') && !$this->fondeadoraPermitida($request->id_cuenta_fondeadora)) {
        $mensaje = $this->mensajeSinFondeadoras();
        return $this->respuestaErrorStore($request, $mensaje, ['id_cuenta_fondeadora' => [$mensaje]], 403);
    }

    try {
        DB::beginTransaction();

        // ============================================================
        // 🔥 VALIDACIÓN DE NATURALEZA DE LA CUENTA SEGÚN TIPO DE PÓLIZA
        // ============================================================
        $cuenta = Cuenta::find($request->id_cuenta);
        if (!$cuenta || !$cuenta->en_uso) {
            throw new \Exception('Cuenta no encontrada o inactiva');
        }

        $tipoPoliza = $request->tipo_poliza;
        $esPorPagar = filter_var($request->es_por_pagar, FILTER_VALIDATE_BOOLEAN);

        // ✅ Validar según el tipo de póliza
        if ($tipoPoliza === 'EGRESO') {
            // Para EGRESO, la cuenta debe poder recibir DÉBITOS
            if (!$cuenta->puedeRecibirDebito()) {
                $naturalezaTexto = $cuenta->Naturaleza ?? 'sin naturaleza definida';
                $nombreCuenta = $cuenta->nombre_cuenta;
                
                if ($cuenta->es_cuenta_resultados) {
                    throw new \Exception("La cuenta de resultados '{$nombreCuenta}' tiene naturaleza '{$naturalezaTexto}'. Las cuentas de resultados pueden recibir ambos movimientos, pero para EGRESOS se recomienda usar cuentas DEUDORAS.");
                }
                
                throw new \Exception("La cuenta '{$nombreCuenta}' tiene naturaleza '{$naturalezaTexto}' y NO puede recibir EGRESOS (débitos). Solo cuentas DEUDORAS o sin naturaleza pueden recibir egresos.");
            }
        } elseif ($tipoPoliza === 'INGRESO') {
            // Para INGRESO, la cuenta debe poder recibir CRÉDITOS
            if (!$cuenta->puedeRecibirCredito()) {
                $naturalezaTexto = $cuenta->Naturaleza ?? 'sin naturaleza definida';
                $nombreCuenta = $cuenta->nombre_cuenta;
                
                if ($cuenta->es_cuenta_resultados) {
                    throw new \Exception("La cuenta de resultados '{$nombreCuenta}' tiene naturaleza '{$naturalezaTexto}'. Las cuentas de resultados pueden recibir ambos movimientos, pero para INGRESOS se recomienda usar cuentas ACREEDORAS.");
                }
                
                throw new \Exception("La cuenta '{$nombreCuenta}' tiene naturaleza '{$naturalezaTexto}' y NO puede recibir INGRESOS (créditos). Solo cuentas ACREEDORAS o sin naturaleza pueden recibir ingresos.");
            }
        }

        // ✅ Validar cuenta fondeadora
        $cuentaFondeadora = Cuenta::find($request->id_cuenta_fondeadora);
        if (!$cuentaFondeadora || !$cuentaFondeadora->en_uso) {
            throw new \Exception('Cuenta fondeadora no encontrada o inactiva');
        }

        // Si es EGRESO y NO es por pagar, validar saldo
        if ($tipoPoliza === 'EGRESO' && !$esPorPagar) {
            $saldoFondeadora = (float) ($cuentaFondeadora->saldo_inicial ?? 0);
            $montoTotal = (float) $request->total_factura;
            
            // Obtener el monto total con IVA
            $modoIva = $request->modo_iva ?? 'CON_IVA';
            $totalConIva = $montoTotal;
            
            if ($modoIva === 'CON_IVA') {
                $ivas = $request->input('ivas', []);
                $totalIva = 0;
                foreach ($ivas as $ivaData) {
                    if (isset($ivaData['id']) && isset($ivaData['monto'])) {
                        $tipoIva = TipoIva::find($ivaData['id']);
                        if ($tipoIva) {
                            $totalIva += round($ivaData['monto'] * ($tipoIva->porcentaje / 100), 2);
                        }
                    }
                }
                $totalConIva = $montoTotal + $totalIva;
            }
            
            if ($saldoFondeadora < $totalConIva) {
                throw new \Exception("Saldo insuficiente en la cuenta fondeadora '{$cuentaFondeadora->nombre_cuenta}'. Disponible: $" . number_format($saldoFondeadora, 2) . ", Necesario: $" . number_format($totalConIva, 2));
            }
        }

        $empresaId = session('empresa_movimientos');
        
        if (!$empresaId) {
            $empresa = auth()->user()->empresas()->first();
            $empresaId = $empresa ? $empresa->id : null;
        }

        if (!$empresaId) {
            throw new \Exception('No se pudo determinar la empresa para la póliza');
        }

        $esFiscal = filter_var($request->es_fiscal, FILTER_VALIDATE_BOOLEAN);
        $categoria = $esFiscal ? 'FISCAL' : 'NO_FISCAL';
        $modoIva = $request->modo_iva ?? 'CON_IVA';

        $rutaPdf = null;
        $nombrePdf = null;
        $nombreXml = null;
        $rutaXml = null;

        if ($request->hasFile('pdf_file')) {
            $pdfFile = $request->file('pdf_file');
            if ($pdfFile->isValid()) {
                $nombrePdf = $pdfFile->getClientOriginalName();
                $rutaPdf = $pdfFile->store('documentos_fiscales/pdfs', 'public');
                \Log::info('PDF guardado:', ['ruta' => $rutaPdf]);
            }
        }


        $fechaPoliza = $request->fecha_poliza ? Carbon::parse($request->fecha_poliza) : now();

        $poliza = Poliza::create([
            'id_empresa' => $empresaId,
            'tipo_poliza' => $request->tipo_poliza,
            'fecha_poliza' => $fechaPoliza,
            'fecha_vencimiento' => $esPorPagar ? $request->fecha_vencimiento : null,
            'categoria' => $categoria,
            'estatus' => $esPorPagar ? 'PENDIENTE' : 'CAPTURADO',
            'es_por_pagar' => $esPorPagar,
            'referencia' => $request->referencia,
            'nota' => $request->nota,
            'id_persona' => $request->id_persona,
            'id_usuario_creador' => auth()->id(),
            'id_usuario_autorizador' => null,
            'fecha_autorizacion' => null,
            'fecha_factura' => $request->fecha_factura,
            'numero_factura' => $request->numero_factura,
            'id_marcador' => $request->id_marcador,
            'fecha_creacion' => now(),
            'ruta_pdf' => $rutaPdf,
            'nombre_pdf' => $nombrePdf,
            'nombre_xml' => $nombreXml,
            'uuid_factura' => $request->uuid_factura,
            'serie_factura' => $request->serie_factura,
            'folio_factura' => $request->folio_factura,
        ]);

        $poliza->refresh();

        $signo = $request->tipo_poliza === 'EGRESO' ? -1 : 1;
        $ivas = $request->input('ivas', []);
        
        $totalBase = 0;
        $totalIva = 0;
        $ivaIds = [];
        $montoCero = 0;
        $montoDieciseis = 0;
        $ivaDieciseisCalc = 0;

        if ($modoIva === 'SIN_IVA') {
            $montoDirecto = (float) $request->monto_directo;
            $totalBase = $montoDirecto;
            $totalIva = 0;
            $montoTotal = $montoDirecto;
            
            \Log::info('Modo SIN IVA:', [
                'montoDirecto' => $montoDirecto,
                'totalBase' => $totalBase,
                'montoTotal' => $montoTotal
            ]);
        } else {
            foreach ($ivas as $ivaId => $ivaData) {
                $id = isset($ivaData['id']) ? $ivaData['id'] : $ivaId;
                $monto = isset($ivaData['monto']) ? (float) $ivaData['monto'] : 0;
                
                if ($id && $monto > 0) {
                    $tipoIva = TipoIva::find($id);
                    if ($tipoIva) {
                        $montoBase = round($monto, 2);
                        $montoIva = round($montoBase * ($tipoIva->porcentaje / 100), 2);
                        
                        $totalBase += $montoBase;
                        $totalIva += $montoIva;
                        $ivaIds[] = $id;

                        if ($tipoIva->porcentaje == 0) {
                            $montoCero += $montoBase;
                        } elseif ($tipoIva->porcentaje == 16) {
                            $montoDieciseis += $montoBase;
                            $ivaDieciseisCalc += $montoIva;
                        }
                    }
                }
            }
            
            $montoTotal = $totalBase;
        }

        $primerIvaId = !empty($ivaIds) ? $ivaIds[0] : null;

        $movimiento = MovimientoPoliza::create([
            'id_poliza' => $poliza->id,
            'id_cuenta' => $request->id_cuenta,
            'id_caja_fondo' => $request->id_cuenta_fondeadora,
            'id_tipo_iva' => $primerIvaId,
            'monto' => round($montoTotal * $signo, 2),
            'monto_base' => round($totalBase * $signo, 2),
            'monto_iva' => round($totalIva * $signo, 2),
            'monto_traspaso' => null,
            'monto_iva_cero' => round($montoCero * $signo, 2),
            'monto_iva_dieciseis' => round($montoDieciseis * $signo, 2),
            'iva_dieciseis' => round($ivaDieciseisCalc * $signo, 2),
        ]);

        \Log::info('Movimiento creado:', [
            'monto' => $movimiento->monto,
            'monto_base' => $movimiento->monto_base,
            'monto_iva' => $movimiento->monto_iva,
            'modo_iva' => $modoIva,
            'fecha_poliza' => $fechaPoliza->toDateString(),
            'cuenta_usada' => $cuenta->nombre_cuenta,
            'naturaleza_cuenta' => $cuenta->Naturaleza,
            'tipo_poliza' => $tipoPoliza
        ]);

        if (!$esPorPagar) {
            if ($request->id_cuenta_fondeadora) {
                $this->actualizarSaldoCuenta($request->id_cuenta_fondeadora);
            }
            
            if ($request->id_cuenta && $request->id_cuenta != $request->id_cuenta_fondeadora) {
                $this->actualizarSaldoCuenta($request->id_cuenta);
            }
        }

        DB::commit();

        $mensaje = 'Póliza creada exitosamente. Folio: ' . $poliza->folio . ' | Fecha: ' . $fechaPoliza->format('d/m/Y') . ' | Estado: ' . $this->getEstatusTexto($poliza->estatus);
        $mensaje .= ' | Total: $' . number_format($totalBase, 2) . ' | IVA: $' . number_format($totalIva, 2);
        
        // 🔥 Mostrar información de la naturaleza
        $naturalezaTexto = $cuenta->Naturaleza ?? 'Sin naturaleza';
        $mensaje .= " | Cuenta: {$cuenta->nombre_cuenta} ({$naturalezaTexto})";
        
        if ($modoIva === 'SIN_IVA') {
            $mensaje .= ' (Sin IVA)';
        }
        
        if ($esPorPagar) {
            $mensaje .= ' (Póliza diferida - no afecta saldos hasta que se liquide)';
        }
        if ($esFiscal) {
            $mensaje .= ' Documentos fiscales: ';
            if ($rutaPdf) $mensaje .= 'PDF ✓ ';
            if ($nombreXml) $mensaje .= 'XML ✓';
        }

        // Redirigir a la lista filtrada por la fecha de la póliza para que
        // SIEMPRE se vea la póliza recién creada (aunque no sea de hoy).
        return redirect()->route('movimientos.index', [
                'fecha_desde' => $fechaPoliza->toDateString(),
                'fecha_hasta' => $fechaPoliza->toDateString(),
            ])
            ->with('success', $mensaje);

    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        return $this->respuestaErrorStore(
            $request,
            'Error de validación: ' . implode(', ', \Illuminate\Support\Arr::flatten($e->errors())),
            $e->errors()
        );
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error en store:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return $this->respuestaErrorStore($request, 'Error al crear la póliza: ' . $e->getMessage(), [], 500);
    }
}

    // ============================================
    // 🔧 FUNCIONES AUXILIARES
    // ============================================
    
    private function getMontoIvaPorPorcentaje($ivas, $porcentaje)
    {
        foreach ($ivas as $ivaData) {
            if (isset($ivaData['id'])) {
                $tipoIva = TipoIva::find($ivaData['id']);
                if ($tipoIva && $tipoIva->porcentaje == $porcentaje) {
                    return round($ivaData['monto'] ?? 0, 2);
                }
            }
        }
        return 0;
    }

    private function getIvaCalculadoPorPorcentaje($ivas, $porcentaje)
    {
        foreach ($ivas as $ivaData) {
            if (isset($ivaData['id'])) {
                $tipoIva = TipoIva::find($ivaData['id']);
                if ($tipoIva && $tipoIva->porcentaje == $porcentaje) {
                    $monto = round($ivaData['monto'] ?? 0, 2);
                    return round($monto * ($porcentaje / 100), 2);
                }
            }
        }
        return 0;
    }

    private function getEstatusTexto($estatus)
    {
        $map = [
            'CAPTURADO' => 'Capturado',
            'REVISADO' => 'Revisado',
            'AUTORIZADO' => 'Autorizado',
            'ABONADO' => 'Abonado',
            'LIQUIDADO' => 'Liquidado',
            'PENDIENTE' => 'Pendiente',
            'CERRADO' => 'Cerrado',
        ];
        return $map[$estatus] ?? $estatus;
    }

    private function actualizarEstatusPoliza($poliza)
    {
        $totalMovimientos = $poliza->movimientos()->sum('monto');
        $totalAbonado = $poliza->abonos()->sum('monto_abonado');

        if (in_array($poliza->estatus, ['CAPTURADO', 'REVISADO', 'AUTORIZADO', 'PENDIENTE'])) {
            if ($totalAbonado > 0) {
                $poliza->estatus = 'ABONADO';
                $poliza->fecha_abono = now();
            }
            if ($totalAbonado >= abs($totalMovimientos) && $totalMovimientos != 0) {
                $poliza->estatus = 'LIQUIDADO';
                $poliza->fecha_liquidacion = now();
            }
        } elseif ($poliza->estatus === 'ABONADO') {
            if ($totalAbonado >= abs($totalMovimientos) && $totalMovimientos != 0) {
                $poliza->estatus = 'LIQUIDADO';
                $poliza->fecha_liquidacion = now();
            }
        }

        $poliza->save();
        return $poliza;
    }


    /**
 * 🔥 ACTUALIZAR SALDOS DE TRASPASO - FUNCIÓN EXCLUSIVA
 * Esta función actualiza los saldos de las cuentas de origen y destino de un traspaso
 * de manera independiente, sin duplicar movimientos.
 */
private function actualizarSaldosTraspaso($idPoliza, $idCuentaOrigen, $idCuentaDestino)
{
    \Log::info('=== INICIO actualizarSaldosTraspaso ===', [
        'id_poliza' => $idPoliza,
        'id_cuenta_origen' => $idCuentaOrigen,
        'id_cuenta_destino' => $idCuentaDestino
    ]);

    try {
        // 🔥 OBTENER TODOS LOS MOVIMIENTOS DE LA PÓLIZA DE TRASPASO
        $movimientos = MovimientoPoliza::where('id_poliza', $idPoliza)->get();
        
        // 🔥 IDENTIFICAR MOVIMIENTO DE ORIGEN (SALIDA - MONTO NEGATIVO)
        $movOrigen = $movimientos->firstWhere(function($mov) {
            return $mov->monto < 0;
        });
        
        // 🔥 IDENTIFICAR MOVIMIENTO DE DESTINO (ENTRADA - MONTO POSITIVO)
        $movDestino = $movimientos->firstWhere(function($mov) {
            return $mov->monto > 0;
        });

        if (!$movOrigen || !$movDestino) {
            \Log::error('❌ No se encontraron movimientos de origen o destino en el traspaso', [
                'id_poliza' => $idPoliza,
                'movOrigen' => $movOrigen ? 'encontrado' : 'no encontrado',
                'movDestino' => $movDestino ? 'encontrado' : 'no encontrado'
            ]);
            return false;
        }

        // 🔥 CALCULAR SALDO DE LA CUENTA DE ORIGEN
        // Suma todos los movimientos donde es cuenta principal O fondeadora
        $saldoOrigen = $this->calcularSaldoCuenta($idCuentaOrigen);
        $saldoDestino = $this->calcularSaldoCuenta($idCuentaDestino);

        // 🔥 ACTUALIZAR CUENTA DE ORIGEN
        $cuentaOrigen = Cuenta::find($idCuentaOrigen);
        if ($cuentaOrigen) {
            $cuentaOrigen->saldo_inicial = $saldoOrigen;
            $cuentaOrigen->save();
            
            \Log::info('✅ Saldo cuenta ORIGEN actualizado:', [
                'cuenta_id' => $idCuentaOrigen,
                'cuenta_nombre' => $cuentaOrigen->nombre_cuenta,
                'nuevo_saldo' => $saldoOrigen,
                'es_fondeadora' => $cuentaOrigen->fondeo_c ? 'Sí' : 'No'
            ]);
        }

        // 🔥 ACTUALIZAR CUENTA DE DESTINO
        $cuentaDestino = Cuenta::find($idCuentaDestino);
        if ($cuentaDestino) {
            $cuentaDestino->saldo_inicial = $saldoDestino;
            $cuentaDestino->save();
            
            \Log::info('✅ Saldo cuenta DESTINO actualizado:', [
                'cuenta_id' => $idCuentaDestino,
                'cuenta_nombre' => $cuentaDestino->nombre_cuenta,
                'nuevo_saldo' => $saldoDestino,
                'es_fondeadora' => $cuentaDestino->fondeo_c ? 'Sí' : 'No'
            ]);
        }

        // 🔥 REGISTRAR EL TRASPASO EN EL LOG PARA AUDITORÍA
        \Log::info('✅ Traspaso registrado en auditoría:', [
            'id_poliza' => $idPoliza,
            'cuenta_origen' => $idCuentaOrigen,
            'cuenta_destino' => $idCuentaDestino,
            'monto' => abs($movOrigen->monto ?? 0),
            'fecha' => now()->toDateTimeString()
        ]);

        return true;

    } catch (\Exception $e) {
        \Log::error('❌ Error en actualizarSaldosTraspaso:', [
            'id_poliza' => $idPoliza,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

/**
 * 🔥 CALCULAR SALDO DE UNA CUENTA - FUNCIÓN AUXILIAR
 * Esta función calcula el saldo de una cuenta sumando todos sus movimientos
 * sin duplicar (id_cuenta + id_caja_fondo)
 */
private function calcularSaldoCuenta($idCuenta)
{
    if (!$idCuenta) return 0;

    try {
        // 🔥 Las pólizas diferidas (es_por_pagar = true) NO afectan el saldo.
        $soloPolizasQueAfectanSaldo = function ($q) {
            $q->where(function ($sub) {
                $sub->where('es_por_pagar', false)
                    ->orWhereNull('es_por_pagar');
            });
        };

        // 🔥 OBTENER MOVIMIENTOS DONDE ES CUENTA PRINCIPAL
        $movimientosCuenta = MovimientoPoliza::where('id_cuenta', $idCuenta)
            ->whereHas('poliza', $soloPolizasQueAfectanSaldo)
            ->get();

        // 🔥 OBTENER MOVIMIENTOS DONDE ES CUENTA FONDEADORA
        $movimientosFondeadora = MovimientoPoliza::where('id_caja_fondo', $idCuenta)
            ->whereHas('poliza', $soloPolizasQueAfectanSaldo)
            ->get();

        // 🔥 UNIR Y SUMAR SIN DUPLICAR
        $ids = collect();
        $saldoTotal = 0;
        
        foreach ($movimientosCuenta as $mov) {
            $key = $mov->id . '-cuenta';
            if (!$ids->contains($key)) {
                $ids->push($key);
                $saldoTotal += (float) $mov->monto;
            }
        }
        
        foreach ($movimientosFondeadora as $mov) {
            $key = $mov->id . '-fondeadora';
            if (!$ids->contains($key)) {
                $ids->push($key);
                $saldoTotal += (float) $mov->monto;
            }
        }
        
        \Log::info('💰 Saldo calculado:', [
            'cuenta_id' => $idCuenta,
            'total_cuenta' => $movimientosCuenta->sum('monto'),
            'total_fondeadora' => $movimientosFondeadora->sum('monto'),
            'saldo_calculado' => $saldoTotal
        ]);
        
        return $saldoTotal;

    } catch (\Exception $e) {
        \Log::error('❌ Error al calcular saldo de cuenta:', [
            'cuenta_id' => $idCuenta,
            'error' => $e->getMessage()
        ]);
        return 0;
    }
}

/**
 * 🔥 ACTUALIZAR SALDOS DE TODAS LAS CUENTAS INVOLUCRADAS EN UN TRASPASO
 * Útil para cuando se edita o elimina un traspaso
 */
private function actualizarSaldosCuentasTraspaso($idPoliza)
{
    \Log::info('=== INICIO actualizarSaldosCuentasTraspaso ===', [
        'id_poliza' => $idPoliza
    ]);

    try {
        // 🔥 OBTENER TODOS LOS MOVIMIENTOS DE LA PÓLIZA
        $movimientos = MovimientoPoliza::where('id_poliza', $idPoliza)->get();
        
        $cuentasAfectadas = [];
        
        foreach ($movimientos as $mov) {
            // 🔥 RECOLECTAR TODAS LAS CUENTAS INVOLUCRADAS (id_cuenta + id_caja_fondo)
            if ($mov->id_cuenta) {
                $cuentasAfectadas[] = $mov->id_cuenta;
            }
            if ($mov->id_caja_fondo) {
                $cuentasAfectadas[] = $mov->id_caja_fondo;
            }
        }
        
        // 🔥 ELIMINAR DUPLICADOS
        $cuentasAfectadas = array_unique($cuentasAfectadas);
        
        \Log::info('Cuentas afectadas por el traspaso:', [
            'cuentas' => $cuentasAfectadas,
            'total_cuentas' => count($cuentasAfectadas)
        ]);
        
        // 🔥 ACTUALIZAR CADA CUENTA AFECTADA
        $resultados = [];
        foreach ($cuentasAfectadas as $idCuenta) {
            $saldo = $this->actualizarSaldoCuenta($idCuenta);
            $cuenta = Cuenta::find($idCuenta);
            $resultados[] = [
                'id_cuenta' => $idCuenta,
                'nombre' => $cuenta ? $cuenta->nombre_cuenta : 'Desconocida',
                'saldo' => $saldo
            ];
        }
        
        \Log::info('✅ Saldos actualizados correctamente', [
            'resultados' => $resultados
        ]);
        
        return $resultados;

    } catch (\Exception $e) {
        \Log::error('❌ Error al actualizar saldos de cuentas en traspaso:', [
            'id_poliza' => $idPoliza,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}
   /**
     * 🔧 ACTUALIZAR SALDO DE CUENTA - VERSIÓN MEJORADA SIN DUPLICADOS
     */
    private function actualizarSaldoCuenta($idCuenta)
    {
        if (!$idCuenta) return 0;

        try {
            // 🔥 LAS PÓLIZAS DIFERIDAS (es_por_pagar = true) NO AFECTAN EL SALDO.
            // Solo mueven dinero las pólizas normales y las pólizas de abono
            // (que se crean como es_por_pagar = false). Por eso excluimos siempre
            // los movimientos cuya póliza está "por pagar".
            $soloPolizasQueAfectanSaldo = function ($q) {
                $q->where(function ($sub) {
                    $sub->where('es_por_pagar', false)
                        ->orWhereNull('es_por_pagar');
                });
            };

            // 🔥 OBTENER MOVIMIENTOS DONDE ES CUENTA PRINCIPAL
            $movimientosCuenta = MovimientoPoliza::where('id_cuenta', $idCuenta)
                ->whereHas('poliza', $soloPolizasQueAfectanSaldo)
                ->get();

            // 🔥 OBTENER MOVIMIENTOS DONDE ES CUENTA FONDEADORA
            $movimientosFondeadora = MovimientoPoliza::where('id_caja_fondo', $idCuenta)
                ->whereHas('poliza', $soloPolizasQueAfectanSaldo)
                ->get();

            // 🔥 UNIR Y SUMAR SIN DUPLICAR (evita que un movimiento se sume dos veces)
            $ids = collect();
            $saldoTotal = 0;
            
            foreach ($movimientosCuenta as $mov) {
                $key = $mov->id . '-cuenta';
                if (!$ids->contains($key)) {
                    $ids->push($key);
                    $saldoTotal += (float) $mov->monto;
                }
            }
            
            foreach ($movimientosFondeadora as $mov) {
                $key = $mov->id . '-fondeadora';
                if (!$ids->contains($key)) {
                    $ids->push($key);
                    $saldoTotal += (float) $mov->monto;
                }
            }
            
            // 🔥 ACTUALIZAR EL SALDO EN LA CUENTA
            $cuenta = Cuenta::find($idCuenta);
            if ($cuenta) {
                $cuenta->saldo_inicial = $saldoTotal;
                $cuenta->save();
                
                \Log::info('✅ Saldo actualizado correctamente:', [
                    'cuenta_id' => $idCuenta,
                    'cuenta_nombre' => $cuenta->nombre_cuenta,
                    'total_cuenta' => $movimientosCuenta->sum('monto'),
                    'total_fondeadora' => $movimientosFondeadora->sum('monto'),
                    'saldo_calculado' => $saldoTotal,
                    'nuevo_saldo' => $cuenta->saldo_inicial
                ]);
            }
            
            return $saldoTotal;

        } catch (\Exception $e) {
            \Log::error('❌ Error al actualizar saldo de cuenta:', [
                'cuenta_id' => $idCuenta,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    private function calcularDesgloseExacto($totalFactura, $porcentajeIva)
    {
        if ($porcentajeIva == 0) {
            return [
                'monto_base' => $totalFactura,
                'monto_iva' => 0,
                'total' => $totalFactura
            ];
        }

        $totalCentavos = round($totalFactura * 100);
        $factor = 100 + $porcentajeIva;
        
        $baseCentavos = round($totalCentavos * 100 / $factor);
        $ivaCentavos = $totalCentavos - $baseCentavos;
        
        $montoBase = $baseCentavos / 100;
        $montoIva = $ivaCentavos / 100;
        
        $suma = $montoBase + $montoIva;
        if (abs($suma - $totalFactura) > 0.001) {
            $diferencia = $totalFactura - $suma;
            $montoIva = round(($montoIva + $diferencia) * 100) / 100;
        }
        
        return [
            'monto_base' => round($montoBase, 2),
            'monto_iva' => round($montoIva, 2),
            'total' => round($montoBase + $montoIva, 2)
        ];
    }

    private function formatFechaExcel($fecha)
    {
        if (!$fecha) return '—';
        if ($fecha instanceof \DateTime) {
            return $fecha->format('d/m/Y');
        }
        return date('d/m/Y', strtotime($fecha));
    }

    private function formatMonto($monto)
    {
        if ($monto === null || $monto === '') return '$0.00';
        $signo = $monto < 0 ? '-' : '';
        return $signo . '$' . number_format(abs($monto), 2);
    }
    // ============================================
    // 🔄 STORE TRASPASO - SIN VALIDACIÓN DE NATURALEZA
    // ============================================
    public function storeTraspaso(Request $request)
    {
        \Log::info('=== INICIO storeTraspaso ===');
        \Log::info('Datos recibidos:', $request->all());

        if (!Gate::allows('crear-movimientos')) {
            \Log::warning('Permiso denegado para crear traspasos');
            return $this->respuestaErrorStore($request, 'No tienes permiso para crear traspasos', [], 403);
        }

        $rules = [
            'tipo_poliza' => 'required|in:TRASPASO',
            'fecha_poliza' => 'required|date',
            'id_cuenta_origen' => 'required|exists:cuentas,id_cuenta|different:id_cuenta_destino',
            'id_cuenta_destino' => 'required|exists:cuentas,id_cuenta|different:id_cuenta_origen',
            'monto_directo' => 'required|numeric|min:0.01',
            'es_fiscal' => 'nullable|in:true,false,1,0,on,off',
            'id_marcador' => 'nullable|exists:marcadores,id',
            'referencia' => 'nullable|string|max:100',
            'nota' => 'nullable|string',
            'fecha_factura' => 'nullable|date',
            'numero_factura' => 'nullable|string|max:50',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
            'uuid_factura' => 'nullable|string|max:50',
            'serie_factura' => 'nullable|string|max:20',
            'folio_factura' => 'nullable|string|max:20',
            'modo_iva' => 'nullable|in:CON_IVA,SIN_IVA',
            'ivas' => 'nullable|array|min:1|max:2',
            'ivas.*.id' => 'nullable|exists:tipos_iva,id',
            'ivas.*.monto' => 'numeric|min:0',
        ];

        $esFiscal = filter_var($request->es_fiscal, FILTER_VALIDATE_BOOLEAN);
        if ($esFiscal) {
            $rules['pdf_file'] = 'required|file|mimes:pdf|max:10240';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            \Log::error('Validación fallida:', $validator->errors()->toArray());
            return $this->respuestaErrorStore($request, $validator->errors()->first(), $validator->errors()->toArray());
        }

        $empresaTraspaso = Cuenta::find($request->id_cuenta_origen)?->id_empresa;
        if ($this->sinFondeadorasEnEmpresa($empresaTraspaso)) {
            return $this->respuestaErrorStore($request, $this->mensajeSinFondeadoras(), [], 403);
        }

        try {
            DB::beginTransaction();

            // ============================================================
            // 🔥 OBTENER CUENTAS DE ORIGEN Y DESTINO (SIN VALIDAR NATURALEZA)
            // ============================================================
            $cuentaOrigen = Cuenta::find($request->id_cuenta_origen);
            if (!$cuentaOrigen || !$cuentaOrigen->en_uso) {
                throw new \Exception('Cuenta de origen no encontrada o inactiva');
            }

            $cuentaDestino = Cuenta::find($request->id_cuenta_destino);
            if (!$cuentaDestino || !$cuentaDestino->en_uso) {
                throw new \Exception('Cuenta de destino no encontrada o inactiva');
            }

            // ✅ Validar saldo de origen (SIEMPRE verificar saldo)
            $saldoOrigen = (float) ($cuentaOrigen->saldo_inicial ?? 0);
            $monto = round($request->monto_directo, 2);

            if ($saldoOrigen < $monto) {
                return $this->respuestaErrorStore($request, "Saldo insuficiente en la cuenta origen. Disponible: $" . number_format($saldoOrigen, 2));
            }

            // ✅ Obtener empresa
            $empresaId = session('empresa_movimientos');
            if (!$empresaId) {
                $empresa = auth()->user()->empresas()->first();
                $empresaId = $empresa ? $empresa->id : null;
            }

            if (!$empresaId) {
                throw new \Exception('No se pudo determinar la empresa');
            }

            // ✅ Categoría fiscal
            $categoria = $esFiscal ? 'FISCAL' : 'NO_FISCAL';

            // ✅ Procesar archivos fiscales
            $rutaPdf = null;
            $nombrePdf = null;
            $nombreXml = null;
            $rutaXml = null;

            if ($esFiscal) {
                if ($request->hasFile('pdf_file')) {
                    $pdfFile = $request->file('pdf_file');
                    if ($pdfFile->isValid()) {
                        $nombrePdf = $pdfFile->getClientOriginalName();
                        $rutaPdf = $pdfFile->store('documentos_fiscales/pdfs', 'public');
                        \Log::info('PDF guardado en traspaso:', ['ruta' => $rutaPdf]);
                    }
                }

                if ($request->hasFile('xml_file')) {
                    $xmlFile = $request->file('xml_file');
                    if ($xmlFile->isValid()) {
                        $nombreXml = $xmlFile->getClientOriginalName();
                        $rutaXml = $xmlFile->store('documentos_fiscales/xmls', 'public');
                        \Log::info('XML guardado en traspaso:', ['ruta' => $rutaXml]);
                    }
                }
            }

            // ✅ Crear póliza
            $fechaPoliza = $request->fecha_poliza ? Carbon::parse($request->fecha_poliza) : now();

            $poliza = Poliza::create([
                'id_empresa' => $empresaId,
                'tipo_poliza' => 'TRASPASO',
                'fecha_poliza' => $fechaPoliza,
                'fecha_vencimiento' => null,
                'categoria' => $categoria,
                'estatus' => 'CAPTURADO',
                'es_por_pagar' => false,
                'referencia' => $request->referencia ?? 'TRASPASO-' . date('YmdHis'),
                'nota' => $request->nota,
                'id_persona' => null,
                'id_usuario_creador' => auth()->id(),
                'id_usuario_autorizador' => null,
                'id_usuario_revisor' => null,
                'fecha_creacion' => now(),
                'fecha_autorizacion' => null,
                'fecha_revision' => null,
                'fecha_factura' => $request->fecha_factura,
                'numero_factura' => $request->numero_factura,
                'id_marcador' => $request->id_marcador,
                'ruta_pdf' => $rutaPdf,
                'nombre_pdf' => $nombrePdf,
                'nombre_xml' => $nombreXml,
                'uuid_factura' => $request->uuid_factura,
                'serie_factura' => $request->serie_factura,
                'folio_factura' => $request->folio_factura,
            ]);

            $poliza->refresh();
            \Log::info('Póliza de traspaso creada:', ['id' => $poliza->id, 'folio' => $poliza->folio]);

            // ✅ Procesar IVAs
            $ivas = $request->input('ivas', []);
            $modoIva = $request->modo_iva ?? 'SIN_IVA';
            
            $totalBase = 0;
            $totalIva = 0;
            $ivaIds = [];
            $montoIvaCero = 0;
            $montoIvaDieciseis = 0;
            $ivaDieciseisCalc = 0;

            if ($modoIva === 'SIN_IVA' || empty($ivas)) {
                $totalBase = $monto;
                $totalIva = 0;
                \Log::info('Traspaso sin IVA:', ['monto' => $monto]);
            } else {
                foreach ($ivas as $ivaData) {
                    $id = isset($ivaData['id']) ? (int) $ivaData['id'] : null;
                    $montoConIva = isset($ivaData['monto']) ? (float) $ivaData['monto'] : 0;
                    
                    if ($id && $montoConIva > 0) {
                        $tipoIva = TipoIva::find($id);
                        if ($tipoIva) {
                            $montoBase = round($montoConIva, 2);
                            $montoIva = round($montoBase * ($tipoIva->porcentaje / 100), 2);
                            
                            $totalBase += $montoBase;
                            $totalIva += $montoIva;
                            $ivaIds[] = $id;

                            if ($tipoIva->porcentaje == 0) {
                                $montoIvaCero += $montoBase;
                            } elseif ($tipoIva->porcentaje == 16) {
                                $montoIvaDieciseis += $montoBase;
                                $ivaDieciseisCalc += $montoIva;
                            }
                        }
                    }
                }
                
                if ($totalBase == 0) {
                    $totalBase = $monto;
                    $modoIva = 'SIN_IVA';
                }
                
                \Log::info('Traspaso con IVA:', [
                    'totalBase' => $totalBase,
                    'totalIva' => $totalIva,
                    'ivas' => $ivaIds
                ]);
            }

            $primerIvaId = !empty($ivaIds) ? $ivaIds[0] : null;

            // ✅ Movimiento de salida (origen) - DÉBITO (NEGATIVO)
            MovimientoPoliza::create([
                'id_poliza' => $poliza->id,
                'id_cuenta' => $request->id_cuenta_origen,
                'id_caja_fondo' => null,
                'id_tipo_iva' => $primerIvaId,
                'monto' => -$monto,
                'monto_base' => -$totalBase,
                'monto_iva' => -$totalIva,
                'monto_traspaso' => $monto,
                'monto_iva_cero' => -$montoIvaCero,
                'monto_iva_dieciseis' => -$montoIvaDieciseis,
                'iva_dieciseis' => -$ivaDieciseisCalc,
            ]);

            \Log::info('Movimiento de origen creado:', [
                'id_cuenta' => $request->id_cuenta_origen,
                'monto' => -$monto
            ]);

            // ✅ Movimiento de entrada (destino) - CRÉDITO (POSITIVO)
            MovimientoPoliza::create([
                'id_poliza' => $poliza->id,
                'id_cuenta' => $request->id_cuenta_destino,
                'id_caja_fondo' => null,
                'id_tipo_iva' => $primerIvaId,
                'monto' => $monto,
                'monto_base' => $totalBase,
                'monto_iva' => $totalIva,
                'monto_traspaso' => $monto,
                'monto_iva_cero' => $montoIvaCero,
                'monto_iva_dieciseis' => $montoIvaDieciseis,
                'iva_dieciseis' => $ivaDieciseisCalc,
            ]);

            \Log::info('Movimiento de destino creado:', [
                'id_cuenta' => $request->id_cuenta_destino,
                'monto' => $monto
            ]);

            // ✅ Actualizar saldos
            $this->actualizarSaldoCuenta($request->id_cuenta_origen);
            $this->actualizarSaldoCuenta($request->id_cuenta_destino);

            DB::commit();
            \Log::info('=== storeTraspaso COMPLETADO EXITOSAMENTE ===');

            // ✅ Mensaje de éxito
            $mensaje = "✅ Traspaso creado exitosamente. Folio: {$poliza->folio} | Estado: CAPTURADO";
            $mensaje .= " | Origen: {$cuentaOrigen->nombre_cuenta} → Destino: {$cuentaDestino->nombre_cuenta}";
            $mensaje .= " | Monto: $" . number_format($monto, 2);
            
            if ($modoIva === 'CON_IVA' && $totalIva > 0) {
                $mensaje .= " | IVA: $" . number_format($totalIva, 2);
            } else {
                $mensaje .= " | Sin IVA";
            }
            
            if ($esFiscal) {
                $mensaje .= ' | Documentos fiscales: ';
                if ($rutaPdf) $mensaje .= 'PDF ✓ ';
                if ($rutaXml) $mensaje .= 'XML ✓';
            }

            return redirect()->route('movimientos.index', [
                    'vista' => 'traspasos',
                    'fecha_desde' => $fechaPoliza->toDateString(),
                    'fecha_hasta' => $fechaPoliza->toDateString(),
                ])
                ->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('=== ERROR EN storeTraspaso ===');
            \Log::error('Mensaje:', ['message' => $e->getMessage()]);
            \Log::error('Trace:', ['trace' => $e->getTraceAsString()]);
            return $this->respuestaErrorStore($request, 'Error al crear el traspaso: ' . $e->getMessage(), [], 500);
        }
    }

    // ============================================
    // 📄 SHOW - MOSTRAR MOVIMIENTO
    // ============================================
    public function show(string $id)
    {
        if (!Gate::allows('ver-movimientos')) {
            return redirect()->route('movimientos.index')
                ->with('error', 'No tienes permiso para ver movimientos');
        }

        $movimiento = MovimientoPoliza::with([
            'poliza.persona',
            'poliza.usuarioCreador',
            'poliza.usuarioRevisor',
            'poliza.usuarioAutorizador',
            'poliza.marcador',
            'poliza.archivos',  // ✅ ESTA RELACIÓN YA ESTÁ, PERO NO LA USAMOS
            'cuenta' => function($q) {
                $q->where('en_uso', true);
            },
            'cuentaFondeadora' => function($q) {
                $q->where('en_uso', true);
            },
            'poliza.abonos'
        ])->find($id);

        if (!$movimiento) {
            return redirect()->route('movimientos.index')
                ->with('error', 'Movimiento no encontrado');
        }

        // ============================================
        // 🔥 RECUPERAR EL RECURSO DE LA PÓLIZA
        // ============================================
        $recurso = $movimiento->poliza->archivos->first();
        $tieneRecurso = $recurso ? true : false;
        $recursoUrl = $recurso ? route('movimientos.archivos.ver', $recurso->id) : null;
        $recursoTipo = $recurso ? $recurso->tipo_archivo : null;
        $recursoId = $recurso ? $recurso->id : null;
        $recursoNombre = $recurso ? $recurso->nombre_original : null;

        $abonos = $movimiento->poliza->abonos ?? collect();
        $totalAbonado = $abonos->sum('monto_abonado');
        
        $tipoPoliza = $movimiento->poliza->tipo_poliza;
        $esTraspaso = $tipoPoliza === 'TRASPASO';
        $esIngresoEgreso = in_array($tipoPoliza, ['INGRESO', 'EGRESO']);
        $esFiscal = $movimiento->poliza->categoria === 'FISCAL';
        $esPorPagar = $movimiento->poliza->es_por_pagar ?? false;

        if ($esTraspaso) {
            $montoMostrar = $movimiento->monto_traspaso ?? abs($movimiento->monto);
            $saldoPendiente = $montoMostrar - $totalAbonado;
        } else {
            $montoMostrar = $movimiento->monto;
            $saldoPendiente = abs($montoMostrar) - $totalAbonado;
        }

        $pdfUrl = null;
        if ($esFiscal && !empty($movimiento->poliza->ruta_pdf)) {
            $pdfUrl = route('movimientos.documento.fiscal', [
                'id' => $movimiento->id_poliza,
                'tipo' => 'pdf'
            ]);
        }

        $xmlUrl = null;
        if ($esFiscal && !empty($movimiento->poliza->ruta_xml)) {
            $xmlUrl = route('movimientos.documento.fiscal', [
                'id' => $movimiento->id_poliza,
                'tipo' => 'xml'
            ]);
        }

        $montoIvaCero = $movimiento->monto_iva_cero ?? 0;
        $montoIvaDieciseis = $movimiento->monto_iva_dieciseis ?? 0;
        $ivaDieciseis = $movimiento->iva_dieciseis ?? 0;
        $tieneDobleIva = ($montoIvaCero != 0 || $montoIvaDieciseis != 0 || $ivaDieciseis != 0);

        $cuentaOrigen = null;
        $cuentaDestino = null;
        $montoTraspaso = null;
        $cuentaOrigenId = null;
        $cuentaDestinoId = null;
        
        if ($esTraspaso) {
            $movimientosTraspaso = MovimientoPoliza::where('id_poliza', $movimiento->id_poliza)->get();
            
            $movOrigen = $movimientosTraspaso->firstWhere(function($m) {
                return $m->monto < 0;
            });
            
            $movDestino = $movimientosTraspaso->firstWhere(function($m) {
                return $m->monto > 0;
            });
            
            if ($movOrigen) {
                $cuentaOrigenId = $movOrigen->id_cuenta;
                if ($movOrigen->cuenta) {
                    $cuentaOrigen = $movOrigen->cuenta->nombre_cuenta;
                }
            }
            
            if ($movDestino) {
                $cuentaDestinoId = $movDestino->id_cuenta;
                if ($movDestino->cuenta) {
                    $cuentaDestino = $movDestino->cuenta->nombre_cuenta;
                }
            }
            
            $montoTraspaso = $movimiento->monto_traspaso ?? abs($movimiento->monto);
        }

        $user = auth()->user();
        
        $esSuperUsuario = $user->esSuperUsuario();
        $esAdministrador = $user->esAdministrador();
        $esAuditor = $user->esAuditor();
        $esCapturista = $user->esCapturista();
        $esLector = $user->esLector();
        
        $estatus = $movimiento->poliza->estatus;
        
        $permisos = [
            'puede_ver' => true,
            // El LECTOR sólo ve Movimientos; el CAPTURISTA además Personas.
            'puede_ver_personas' => $esCapturista || $esAdministrador || $esAuditor || $esSuperUsuario,
            'puede_ver_cuentas' => $esAdministrador || $esAuditor || $esSuperUsuario,
            'puede_ver_usuarios' => $esAdministrador || $esAuditor || $esSuperUsuario,
            'puede_ver_empresas' => $esSuperUsuario,
            'puede_ver_reportes' => $esAdministrador || $esAuditor || $esSuperUsuario,
            'puede_ver_pdf' => true,
            // Revisar: administrador (y super). Autorizar: auditor (y super), NO el administrador.
            'puede_revisar' => ($esAdministrador || $esSuperUsuario) && in_array($estatus, ['CAPTURADO', 'PENDIENTE']),
            'puede_autorizar' => ($esAuditor || $esSuperUsuario) && $estatus === 'REVISADO',
            'puede_cerrar' => ($esCapturista || $esAdministrador || $esSuperUsuario) &&
                            !$esAuditor &&
                            !$esLector &&
                            !in_array($estatus, ['LIQUIDADO', 'AUTORIZADO', 'CERRADO']),
            'puede_reabrir' => ($esAdministrador || $esSuperUsuario) && $estatus === 'CERRADO',
            // Editar póliza: administrador, auditor y super (NO capturista, NO lector).
            // Se puede editar en cualquier estado salvo CERRADO ("puedo modificarle todo").
            'puede_editar' => ($esAdministrador || $esAuditor || $esSuperUsuario) && $estatus !== 'CERRADO',
            'puede_eliminar' => $esSuperUsuario,
            'puede_regresar' => true,
            'rol' => $user->tipo_usuario_texto ?? 'Sin rol',
            'es_super_usuario' => $esSuperUsuario,
            'es_administrador' => $esAdministrador,
            'es_auditor' => $esAuditor,
            'es_capturista' => $esCapturista,
            'es_lector' => $esLector,
        ];

        $movimientoData = [
            'id' => $movimiento->id,
            'id_movimiento' => $movimiento->id,
            'id_poliza' => $movimiento->id_poliza,
            'folio' => $movimiento->poliza->folio,
            'tipo_poliza' => $tipoPoliza,
            'fecha_poliza' => $movimiento->poliza->fecha_poliza,
            'fecha_vencimiento' => $movimiento->poliza->fecha_vencimiento,
            'categoria' => $movimiento->poliza->categoria,
            'estatus' => $estatus,
            'estatus_texto' => $this->getEstatusTexto($estatus),
            'es_por_pagar' => $esPorPagar,
            'referencia' => $movimiento->poliza->referencia,
            'nota' => $movimiento->poliza->nota,
            'es_ingreso_egreso' => $esIngresoEgreso,
            'es_traspaso' => $esTraspaso,
            'puede_editar_esta' => $this->puedeEditarPoliza($movimiento->poliza),
            'es_fiscal' => $esFiscal,
            'tiene_doble_iva' => $tieneDobleIva,
            'persona' => $movimiento->poliza->persona ? $movimiento->poliza->persona->nombre_completo : null,
            'persona_id' => $movimiento->poliza->id_persona,
            'cuenta' => $movimiento->cuenta && $movimiento->cuenta->en_uso ? $movimiento->cuenta->nombre_cuenta : null,
            'cuenta_id' => $movimiento->cuenta && $movimiento->cuenta->en_uso ? $movimiento->id_cuenta : null,
            'cuenta_fondeadora' => $movimiento->cuentaFondeadora && $movimiento->cuentaFondeadora->en_uso ? $movimiento->cuentaFondeadora->nombre_cuenta : null,
            'cuenta_fondeadora_id' => $movimiento->cuentaFondeadora && $movimiento->cuentaFondeadora->en_uso ? $movimiento->id_caja_fondo : null,
            'cuenta_origen' => $cuentaOrigen,
            'cuenta_origen_id' => $cuentaOrigenId,
            'cuenta_destino' => $cuentaDestino,
            'cuenta_destino_id' => $cuentaDestinoId,
            'monto_traspaso' => $montoTraspaso,
            'monto' => $montoMostrar,
            'monto_abs' => abs($montoMostrar),
            'monto_base' => $movimiento->monto_base,
            'monto_iva' => $movimiento->monto_iva,
            'monto_iva_cero' => $montoIvaCero,
            'monto_iva_dieciseis' => $montoIvaDieciseis,
            'iva_dieciseis' => $ivaDieciseis,
            'nombre_pdf' => $movimiento->poliza->nombre_pdf,
            'nombre_xml' => $movimiento->poliza->nombre_xml,
            'abonos' => $abonos->map(function($abono) {
                return [
                    'id' => $abono->id,
                    'monto_abonado' => $abono->monto_abonado,
                    'fecha_abono' => $abono->fecha_abono,
                    'referencia' => $abono->referencia,
                    'metodo_pago' => $abono->metodo_pago,
                    'nota' => $abono->nota,
                    'usuario' => $abono->usuario?->nombre_usuario ?? 'Sistema'
                ];
            }),
            'total_abonado' => $totalAbonado,
            'saldo_pendiente' => $saldoPendiente,
            'marcador' => $movimiento->poliza->marcador ? $movimiento->poliza->marcador->nombre_marcador : null,
            'marcador_id' => $movimiento->poliza->id_marcador,
            'usuario' => $movimiento->poliza->usuarioCreador ? $movimiento->poliza->usuarioCreador->nombre_usuario : null,
            'usuario_nombre' => $movimiento->poliza->usuarioCreador ? $movimiento->poliza->usuarioCreador->nombre_completo : null,
            'usuario_id' => $movimiento->poliza->id_usuario_creador,
            'usuario_revisor' => $movimiento->poliza->usuarioRevisor ? $movimiento->poliza->usuarioRevisor->nombre_usuario : null,
            'usuario_revisor_id' => $movimiento->poliza->id_usuario_revisor,
            'usuario_autorizador' => $movimiento->poliza->usuarioAutorizador ? $movimiento->poliza->usuarioAutorizador->nombre_usuario : null,
            'usuario_autorizador_id' => $movimiento->poliza->id_usuario_autorizador,
            'fecha_revision' => $movimiento->poliza->fecha_revision,
            'fecha_autorizacion' => $movimiento->poliza->fecha_autorizacion,
            'fecha_factura' => $movimiento->poliza->fecha_factura,
            'numero_factura' => $movimiento->poliza->numero_factura,
            'fecha_creacion' => $movimiento->created_at,
            'fecha_actualizacion' => $movimiento->updated_at,
            'pdf_url' => $pdfUrl,
            'xml_url' => $xmlUrl,
            'tiene_pdf_fiscal' => !empty($movimiento->poliza->ruta_pdf),
            'tiene_xml_fiscal' => !empty($movimiento->poliza->ruta_xml),
            'uuid_factura' => $movimiento->poliza->uuid_factura,
            'serie_factura' => $movimiento->poliza->serie_factura,
            'folio_factura' => $movimiento->poliza->folio_factura,
            'comentario_revision' => $movimiento->poliza->comentario_revision,
            'comentario_autorizacion' => $movimiento->poliza->comentario_autorizacion,
            'motivo_rechazo' => $movimiento->poliza->motivo_rechazo,
            // ============================================
            // 🔥 CAMPOS DE RECURSO - ¡AGREGADOS!
            // ============================================
            'tiene_recurso' => $tieneRecurso,
            'recurso_url' => $recursoUrl,
            'recurso_tipo' => $recursoTipo,
            'recurso_id' => $recursoId,
            'recurso_nombre' => $recursoNombre,
            'archivos_adjuntos' => $movimiento->poliza->archivos->map(function($archivo) {
                return [
                    'id' => $archivo->id,
                    'nombre_original' => $archivo->nombre_original,
                    'tipo_archivo' => $archivo->tipo_archivo,
                    'tamano' => $archivo->tamano,
                    'mime_type' => $archivo->mime_type,
                    'created_at' => $archivo->created_at,
                    'url' => route('movimientos.archivos.ver', $archivo->id)
                ];
            }),
        ];

        return Inertia::render('Movimientos/Show', [
            'movimiento' => $movimientoData,
            'permisos' => $permisos,
        ]);
    }

public function showAbono(string $id)
{
    if (!Gate::allows('ver-movimientos')) {
        return redirect()->route('movimientos.index', ['vista' => 'diferidas'])
            ->with('error', 'No tienes permiso para ver movimientos');
    }

    $movimiento = MovimientoPoliza::with([
        'poliza.persona',
        'poliza.usuarioCreador',
        'poliza.usuarioRevisor',
        'poliza.usuarioAutorizador',
        'poliza.marcador',
        'cuenta' => function($q) {
            $q->where('en_uso', true);
        },
        'cuentaFondeadora' => function($q) {
            $q->where('en_uso', true);
        },
        'poliza.abonos.usuario'
    ])->find($id);

    if (!$movimiento) {
        return redirect()->route('movimientos.index', ['vista' => 'diferidas'])
            ->with('error', 'Movimiento no encontrado');
    }

    if (!$movimiento->poliza->es_por_pagar) {
        return redirect()->route('movimientos.index', ['vista' => 'diferidas'])
            ->with('error', 'Esta póliza no es de tipo "Por pagar"');
    }

    $abonos = $movimiento->poliza->abonos ?? collect();
    $totalAbonado = $abonos->sum('monto_abonado');
    $saldoPendiente = abs($movimiento->monto) - $totalAbonado;

    if ($saldoPendiente <= 0.01) {
        return redirect()->route('movimientos.index', ['vista' => 'diferidas'])
            ->with('info', 'Esta póliza ya está liquidada');
    }

    $cuentaFondeadoraId = $movimiento->id_caja_fondo;
    $cuentaFondeadoraNombre = $movimiento->cuentaFondeadora && $movimiento->cuentaFondeadora->en_uso 
        ? $movimiento->cuentaFondeadora->nombre_cuenta 
        : null;

    // ============================================
    // 🔥 VALIDAR SALDO DE LA CUENTA FONDEADORA
    // ============================================
    $saldoFondeadora = 0;
    $saldoSuficiente = true;
    $tipoPoliza = $movimiento->poliza->tipo_poliza;

    if ($tipoPoliza === 'EGRESO' && $cuentaFondeadoraId) {
        // 🔥 Obtener el saldo REAL de la cuenta fondeadora
        $cuentaFondeadora = Cuenta::find($cuentaFondeadoraId);
        if ($cuentaFondeadora) {
            // Recalcular saldo desde los movimientos
            $saldoFondeadora = $this->actualizarSaldoCuenta($cuentaFondeadoraId);
            
            // Verificar si el saldo es suficiente para cubrir el saldo pendiente
            if ($saldoFondeadora < $saldoPendiente) {
                $saldoSuficiente = false;
            }
        }
    }

    // ============================================
    // 🔥 IVAS PARA EL ABONO - MOSTRAR LOS MONTOS DISPONIBLES
    // ============================================
    $ivasParaAbono = [];

    // 🔥 Verificar si la póliza tiene IVA 0% (exento)
    $montoIvaCero = abs((float) ($movimiento->monto_iva_cero ?? 0));
    $montoIvaDieciseis = abs((float) ($movimiento->monto_iva_dieciseis ?? 0));
    $ivaDieciseisCalc = abs((float) ($movimiento->iva_dieciseis ?? 0));

    // Obtener los tipos de IVA
    $ivaCero = TipoIva::where('porcentaje', 0)->where('activo', true)->first();
    $ivaDieciseisModel = TipoIva::where('porcentaje', 16)->where('activo', true)->first();

    // 🔥 IVA 0% - mostrar el monto disponible
    if ($montoIvaCero > 0 && $ivaCero) {
        $ivasParaAbono[] = [
            'id' => $ivaCero->id,
            'monto' => $montoIvaCero,
            'porcentaje' => 0,
        ];
    }

    // 🔥 IVA 16% - mostrar el monto disponible
    if ($montoIvaDieciseis > 0 && $ivaDieciseisModel) {
        $ivasParaAbono[] = [
            'id' => $ivaDieciseisModel->id,
            'monto' => $montoIvaDieciseis,
            'porcentaje' => 16,
        ];
    }

    // 🔥 Si hay un IVA específico asignado a este movimiento
    if (empty($ivasParaAbono) && isset($movimiento->id_tipo_iva) && $movimiento->id_tipo_iva) {
        $iva = TipoIva::find($movimiento->id_tipo_iva);
        if ($iva) {
            $montoIvaOriginal = abs($movimiento->monto_iva ?? 0);
            $ivasParaAbono[] = [
                'id' => $iva->id,
                'monto' => $montoIvaOriginal > 0 ? $montoIvaOriginal : abs($movimiento->monto),
                'porcentaje' => $iva->porcentaje,
            ];
        }
    }

    // 🔥 Si aún no hay IVAs, mostrar IVA por defecto
    if (empty($ivasParaAbono)) {
        $ivaDefecto = TipoIva::where('porcentaje', 16)->where('activo', true)->first();
        if ($ivaDefecto) {
            $ivasParaAbono[] = [
                'id' => $ivaDefecto->id,
                'monto' => abs($movimiento->monto),
                'porcentaje' => 16,
            ];
        }
    }

    // ============================================
    // 🔥 OBTENER TIPOS DE IVA (TODOS LOS ACTIVOS)
    // ============================================
    $tiposIva = TipoIva::where('activo', true)
        ->orderBy('porcentaje')
        ->get()
        ->map(function($iva) {
            return [
                'id' => $iva->id,
                'nombre' => $iva->nombre,
                'porcentaje' => $iva->porcentaje,
                'porcentaje_formateado' => $iva->porcentaje . '%'
            ];
        });

    return Inertia::render('Movimientos/Abono', [
        'movimiento' => [
            'id_movimiento' => $movimiento->id,
            'id_poliza' => $movimiento->id_poliza,
            'referencia' => $movimiento->poliza->folio ?? 'S/N',
            'tipo_poliza' => $tipoPoliza,
            'fecha_poliza' => $movimiento->poliza->fecha_poliza?->format('Y-m-d'),
            'fecha_vencimiento' => $movimiento->poliza->fecha_vencimiento?->format('Y-m-d'),
            'persona' => $movimiento->poliza->persona ? $movimiento->poliza->persona->nombre_completo : 'N/A',
            'cuenta' => $movimiento->cuenta && $movimiento->cuenta->en_uso ? $movimiento->cuenta->nombre_cuenta : 'N/A',
            'cuenta_fondeadora' => $cuentaFondeadoraNombre,
            'cuenta_fondeadora_id' => $cuentaFondeadoraId,
            'saldo_fondeadora' => $saldoFondeadora,
            'saldo_suficiente' => $saldoSuficiente,
            'marcador' => $movimiento->poliza->marcador ? $movimiento->poliza->marcador->nombre_marcador : 'Sin marcador',
            'nota' => $movimiento->poliza->nota ?? 'Sin nota',
            'estatus' => $movimiento->poliza->estatus,
            'estatus_texto' => $this->getEstatusTexto($movimiento->poliza->estatus),
            'monto_abs' => abs($movimiento->monto),
            'monto_base' => abs($movimiento->monto_base ?? 0),
            'monto_iva' => abs($movimiento->monto_iva ?? 0),
            'total_abonado' => $totalAbonado,
            'saldo_pendiente' => $saldoPendiente,
            'abonos' => $abonos->map(function($abono) {
                return [
                    'id' => $abono->id,
                    'monto_abonado' => $abono->monto_abonado,
                    // Ya formateada en hora de México (sin conversiones en el navegador).
                    'fecha_abono' => $abono->fecha_abono?->format('d/m/Y'),
                    'registrado' => $abono->created_at?->format('d/m/Y h:i A'),
                    'usuario' => $abono->usuario
                        ? ($abono->usuario->nombre_usuario ?: $abono->usuario->nombre_completo)
                        : null,
                    'referencia' => $abono->referencia,
                    'metodo_pago' => $abono->metodo_pago,
                    'nota' => $abono->nota
                ];
            })->values(),
            'numero_abonos' => $abonos->count(),
            'es_por_pagar' => $movimiento->poliza->es_por_pagar,
            'ivas_heredados' => $ivasParaAbono,
            'desglose_iva' => [
                'monto_iva_cero_original' => $montoIvaCero,
                'monto_iva_dieciseis_original' => $montoIvaDieciseis,
                'iva_dieciseis_calc_original' => $ivaDieciseisCalc,
                'proporcion_abono' => 1,
                'monto_base_abono' => abs($movimiento->monto_base ?? 0),
                'monto_iva_abono' => abs($movimiento->monto_iva ?? 0),
                'monto_total_abono' => abs($movimiento->monto),
            ]
        ],
        'tipos_iva' => $tiposIva,
        'saldo_fondeadora' => $saldoFondeadora,
        'saldo_suficiente' => $saldoSuficiente,
        // Sin fondeadoras asignadas en la empresa no se puede abonar (se avisa en pantalla).
        'sin_fondeadoras' => $this->sinFondeadorasEnEmpresa($movimiento->poliza->id_empresa),
    ]);
}

   public function edit(string $id)
    {
        \Log::info('=== INICIO EDIT ===');
        \Log::info('ID recibido:', ['id' => $id]);

        if (!Gate::allows('editar-poliza')) {
            \Log::warning('❌ Permiso denegado para editar movimientos');
            return redirect()->route('movimientos.index')
                ->with('error', 'No tienes permiso para editar movimientos');
        }

        // OBTENER EMPRESA DE SESIÓN
        $empresaId = session('empresa_movimientos') ?? session('empresa_seleccionada');

        if (!$empresaId) {
            $empresa = auth()->user()->empresas()->first();
            $empresaId = $empresa ? $empresa->id : null;
        }

        if (!$empresaId) {
            \Log::error('❌ No se encontró empresa seleccionada');
            return redirect()->route('movimientos.index')
                ->with('error', 'No tienes una empresa seleccionada.');
        }

        session(['empresa_movimientos' => $empresaId]);
        session(['empresa_seleccionada' => $empresaId]);

        // 🔥 OBTENER EL MOVIMIENTO CON TODAS LAS RELACIONES
        $movimiento = MovimientoPoliza::with([
            'poliza.persona',
            'poliza.usuarioCreador',
            'poliza.usuarioRevisor',
            'poliza.usuarioAutorizador',
            'poliza.marcador',
            'cuenta' => function($q) use ($empresaId) {
                $q->where('id_empresa', $empresaId)->where('en_uso', true);
            },
            'cuentaFondeadora' => function($q) use ($empresaId) {
                $q->where('id_empresa', $empresaId)->where('en_uso', true);
            }
        ])->find($id);

        if (!$movimiento) {
            \Log::error('❌ Movimiento no encontrado:', ['id' => $id]);
            return redirect()->route('movimientos.index')
                ->with('error', 'Movimiento no encontrado');
        }

        \Log::info('✅ Movimiento encontrado:', [
            'id' => $movimiento->id,
            'poliza_id' => $movimiento->id_poliza,
            'estatus' => $movimiento->poliza->estatus,
            'tipo_poliza' => $movimiento->poliza->tipo_poliza,
            'es_por_pagar' => $movimiento->poliza->es_por_pagar,
        ]);

        if (!$this->puedeEditarPoliza($movimiento->poliza)) {
            return redirect()->route('movimientos.index')
                ->with('error', 'No puedes editar esta póliza (sólo las que capturaste y que aún no han sido revisadas)');
        }

        // 🔥 PERMITIR EDICIÓN EN TODOS LOS ESTADOS EXCEPTO CERRADO
        $estatusNoEditables = ['CERRADO'];
        $estatusActual = $movimiento->poliza->estatus;
        
        \Log::info('Validando estado:', [
            'estatus_actual' => $estatusActual,
            'estatus_no_editables' => $estatusNoEditables,
            'es_no_editable' => in_array($estatusActual, $estatusNoEditables)
        ]);

        if (in_array($estatusActual, $estatusNoEditables)) {
            \Log::warning('❌ Intento de editar póliza en estado CERRADO:', [
                'estatus' => $estatusActual
            ]);
            return redirect()->route('movimientos.index')
                ->with('error', 'No se puede editar una póliza que está CERRADA.');
        }
        \Log::info('✅ Estado permitido para edición');

        // ============================================
        // 🔥 RECUPERAR IVAS DE LA PÓLIZA
        // ============================================
        $ivasAsociados = [];
        $montoIvaCero = abs((float) ($movimiento->monto_iva_cero ?? 0));
        $montoIvaDieciseis = abs((float) ($movimiento->monto_iva_dieciseis ?? 0));
        $montoIva = abs((float) ($movimiento->monto_iva ?? 0));

        $ivaCero = TipoIva::where('porcentaje', 0)->where('activo', true)->first();
        $ivaDieciseisModel = TipoIva::where('porcentaje', 16)->where('activo', true)->first();

        if ($montoIvaCero > 0 && $ivaCero) {
            $ivasAsociados[] = [
                'id' => $ivaCero->id,
                'monto' => $montoIvaCero
            ];
            \Log::info('IVA 0% encontrado:', ['monto' => $montoIvaCero]);
        }

        if ($montoIvaDieciseis > 0 && $ivaDieciseisModel) {
            $ivasAsociados[] = [
                'id' => $ivaDieciseisModel->id,
                'monto' => $montoIvaDieciseis
            ];
            \Log::info('IVA 16% encontrado:', ['monto' => $montoIvaDieciseis]);
        }

        if (empty($ivasAsociados) && isset($movimiento->id_tipo_iva) && $movimiento->id_tipo_iva && $montoIva > 0) {
            $iva = TipoIva::find($movimiento->id_tipo_iva);
            if ($iva) {
                $ivasAsociados[] = [
                    'id' => $iva->id,
                    'monto' => $montoIva
                ];
                \Log::info('IVA por id_tipo_iva encontrado:', [
                    'id' => $iva->id,
                    'monto' => $montoIva
                ]);
            }
        }

        $esTraspaso = $movimiento->poliza->tipo_poliza === 'TRASPASO';
        \Log::info('¿Es traspaso?', ['esTraspaso' => $esTraspaso]);

        // DATOS DE TRASPASO
        $cuentaOrigenId = null;
        $cuentaDestinoId = null;
        if ($esTraspaso) {
            $movimientosTraspaso = MovimientoPoliza::where('id_poliza', $movimiento->id_poliza)->get();
            $movimientoOrigen = $movimientosTraspaso->firstWhere('monto', '<', 0);
            $movimientoDestino = $movimientosTraspaso->firstWhere('monto', '>', 0);
            $cuentaOrigenId = $movimientoOrigen ? $movimientoOrigen->id_cuenta : null;
            $cuentaDestinoId = $movimientoDestino ? $movimientoDestino->id_cuenta : null;
            \Log::info('Datos de traspaso:', [
                'cuenta_origen_id' => $cuentaOrigenId,
                'cuenta_destino_id' => $cuentaDestinoId,
            ]);
        }

        // CONSTRUIR OBJETO MOVIMIENTO
        $movimientoData = [
            'id' => $movimiento->id,
            'id_movimiento' => $movimiento->id,
            'id_poliza' => $movimiento->id_poliza,
            'tipo_poliza' => $movimiento->poliza->tipo_poliza,
            'fecha_poliza' => $movimiento->poliza->fecha_poliza?->format('Y-m-d'),
            'id_persona' => $movimiento->poliza->id_persona,
            'persona_nombre' => $movimiento->poliza->persona ? $movimiento->poliza->persona->nombre_completo : null,
            'id_cuenta' => $movimiento->id_cuenta,
            'cuenta_nombre' => $movimiento->cuenta ? $movimiento->cuenta->nombre_cuenta : null,
            'id_cuenta_fondeadora' => $movimiento->id_caja_fondo,
            'cuenta_fondeadora_nombre' => $movimiento->cuentaFondeadora ? $movimiento->cuentaFondeadora->nombre_cuenta : null,
            'es_por_pagar' => (bool) $movimiento->poliza->es_por_pagar,
            'fecha_vencimiento' => $movimiento->poliza->fecha_vencimiento?->format('Y-m-d'),
            'es_fiscal' => $movimiento->poliza->categoria === 'FISCAL',
            'id_marcador' => $movimiento->poliza->id_marcador,
            'marcador_nombre' => $movimiento->poliza->marcador ? $movimiento->poliza->marcador->nombre_marcador : null,
            'total_factura' => abs($movimiento->monto),
            'monto_abs' => abs($movimiento->monto),
            'id_tipo_iva' => $movimiento->id_tipo_iva,
            'monto_base' => abs($movimiento->monto_base ?? 0),
            'monto_iva' => abs($movimiento->monto_iva ?? 0),
            'fecha_factura' => $movimiento->poliza->fecha_factura?->format('Y-m-d'),
            'numero_factura' => $movimiento->poliza->numero_factura,
            'serie_factura' => $movimiento->poliza->serie_factura,
            'folio_factura' => $movimiento->poliza->folio_factura,
            'uuid_factura' => $movimiento->poliza->uuid_factura,
            'pdf_existente' => !empty($movimiento->poliza->ruta_pdf),
            'xml_existente' => !empty($movimiento->poliza->ruta_xml),
            'nombre_pdf' => $movimiento->poliza->nombre_pdf,
            'nombre_xml' => $movimiento->poliza->nombre_xml,
            'ivas' => $ivasAsociados,
            'id_cuenta_origen' => $cuentaOrigenId,
            'id_cuenta_destino' => $cuentaDestinoId,
            'cuenta_origen_nombre' => $cuentaOrigenId ? Cuenta::find($cuentaOrigenId)?->nombre_cuenta : null,
            'cuenta_destino_nombre' => $cuentaDestinoId ? Cuenta::find($cuentaDestinoId)?->nombre_cuenta : null,
            'nota' => $movimiento->poliza->nota,
            'referencia' => $movimiento->poliza->referencia,
            'folio' => $movimiento->poliza->folio,
            'estatus' => $movimiento->poliza->estatus,
            'estatus_texto' => $this->getEstatusTexto($movimiento->poliza->estatus),
            'monto_traspaso' => $movimiento->monto_traspaso ?? 0,
            'monto_iva_cero' => $montoIvaCero,
            'monto_iva_dieciseis' => $montoIvaDieciseis,
            'iva_dieciseis' => abs((float) ($movimiento->iva_dieciseis ?? 0)),
        ];

        // ============================================
        // 🔥 CUENTAS FONDEADORAS
        // ============================================
        $cuentasFondeadoras = Cuenta::where('id_empresa', $empresaId)
            ->fondeadorasAsignadas($empresaId)
            ->where('en_uso', true)
            ->where('fondeo_c', 1)
            ->orderBy('nombre_cuenta')
            ->get()
            ->map(function($cuenta) {
                return [
                    'id_cuenta' => $cuenta->id_cuenta,
                    'nombre_cuenta' => $cuenta->nombre_cuenta,
                    'codigo_cuenta' => $cuenta->codigo_cuenta,
                    'fondeo_c' => (int) ($cuenta->fondeo_c ?? 0),
                    'saldo' => (float) ($cuenta->saldo_inicial ?? 0),
                    'Naturaleza' => $cuenta->Naturaleza,
                    'tipo' => 'fondeadora'
                ];
            });

        // ============================================
        // 🔥 CUENTAS PARA EGRESOS
        // ============================================
        $cuentasEgreso = Cuenta::where('id_empresa', $empresaId)
            ->where('en_uso', true)
            ->where(function($query) {
                $query->where('fondeo_c', '!=', 1)
                    ->orWhereNull('fondeo_c');
            })
            ->where(function($query) {
                $query->where('es_cuenta_resultados', true)
                    ->orWhereNull('Naturaleza')
                    ->orWhere('Naturaleza', 'DEUDORA');
            })
            ->orderBy('nombre_cuenta')
            ->get()
            ->map(function($cuenta) {
                return [
                    'id_cuenta' => $cuenta->id_cuenta,
                    'nombre_cuenta' => $cuenta->nombre_cuenta,
                    'codigo_cuenta' => $cuenta->codigo_cuenta,
                    'Naturaleza' => $cuenta->Naturaleza ?? 'SIN NATURALEZA',
                    'es_cuenta_resultados' => (bool) $cuenta->es_cuenta_resultados,
                    'fondeo_c' => (int) ($cuenta->fondeo_c ?? 0),
                    'saldo_inicial' => (float) ($cuenta->saldo_inicial ?? 0),
                    'tipo_movimiento' => 'egreso'
                ];
            });

        // ============================================
        // 🔥 CUENTAS PARA INGRESOS
        // ============================================
        $cuentasIngreso = Cuenta::where('id_empresa', $empresaId)
            ->where('en_uso', true)
            ->where(function($query) {
                $query->where('fondeo_c', '!=', 1)
                    ->orWhereNull('fondeo_c');
            })
            ->where(function($query) {
                $query->where('es_cuenta_resultados', true)
                    ->orWhereNull('Naturaleza')
                    ->orWhere('Naturaleza', 'ACREEDORA');
            })
            ->orderBy('nombre_cuenta')
            ->get()
            ->map(function($cuenta) {
                return [
                    'id_cuenta' => $cuenta->id_cuenta,
                    'nombre_cuenta' => $cuenta->nombre_cuenta,
                    'codigo_cuenta' => $cuenta->codigo_cuenta,
                    'Naturaleza' => $cuenta->Naturaleza ?? 'SIN NATURALEZA',
                    'es_cuenta_resultados' => (bool) $cuenta->es_cuenta_resultados,
                    'fondeo_c' => (int) ($cuenta->fondeo_c ?? 0),
                    'saldo_inicial' => (float) ($cuenta->saldo_inicial ?? 0),
                    'tipo_movimiento' => 'ingreso'
                ];
            });

        // ============================================
        // 🔥 PERSONAS
        // ============================================
        $personas = \App\Models\Persona::where('activo', true)
            ->orderBy('Nombre')
            ->get(['id_persona', 'Nombre', 'Paterno', 'Materno'])
            ->map(function($persona) {
                $nombreCompleto = $persona->Nombre;
                if ($persona->Paterno) {
                    $nombreCompleto .= ' ' . $persona->Paterno;
                }
                if ($persona->Materno) {
                    $nombreCompleto .= ' ' . $persona->Materno;
                }
                return [
                    'id_persona' => $persona->id_persona,
                    'nombre_completo' => $nombreCompleto,
                    'nombre' => $persona->Nombre,
                    'paterno' => $persona->Paterno,
                    'materno' => $persona->Materno
                ];
            });

        // ============================================
        // 🔥 MARCADORES
        // ============================================
        $marcadores = Marcador::where('activo', true)
            ->orderBy('nombre_marcador')
            ->get()
            ->map(function($marcador) {
                return [
                    'id' => $marcador->id,
                    'nombre_marcador' => $marcador->nombre_marcador,
                    'descripcion' => $marcador->descripcion
                ];
            });

        // ============================================
        // 🔥 TIPOS DE IVA
        // ============================================
        $tiposIva = TipoIva::where('activo', true)
            ->orderBy('porcentaje')
            ->get()
            ->map(function($iva) {
                return [
                    'id' => $iva->id,
                    'nombre' => $iva->nombre,
                    'porcentaje' => $iva->porcentaje,
                    'porcentaje_formateado' => $iva->porcentaje . '%'
                ];
            });

        // ============================================
        // 🔥 GARANTIZAR QUE LA CUENTA / FONDEADORA ACTUAL SIEMPRE ESTÉ EN SU LISTA.
        // Los filtros por naturaleza pueden excluir la cuenta que la póliza ya usa
        // (p. ej. un INGRESO histórico sobre una cuenta DEUDORA). Si no está en el
        // <select>, el usuario la ve vacía y puede perderla al guardar.
        // ============================================
        $incluirCuentaActual = function ($lista, $idCuenta, array $extra = []) {
            if (!$idCuenta || $lista->contains('id_cuenta', $idCuenta)) {
                return $lista;
            }
            $c = Cuenta::find($idCuenta);
            if (!$c) {
                return $lista;
            }
            return $lista->prepend(array_merge([
                'id_cuenta' => $c->id_cuenta,
                'nombre_cuenta' => $c->nombre_cuenta,
                'codigo_cuenta' => $c->codigo_cuenta,
                'Naturaleza' => $c->Naturaleza ?? 'SIN NATURALEZA',
                'es_cuenta_resultados' => (bool) $c->es_cuenta_resultados,
                'fondeo_c' => (int) ($c->fondeo_c ?? 0),
                'saldo_inicial' => (float) ($c->saldo_inicial ?? 0),
                'saldo' => (float) ($c->saldo_inicial ?? 0),
            ], $extra));
        };

        if (!$esTraspaso) {
            if ($movimiento->poliza->tipo_poliza === 'EGRESO') {
                $cuentasEgreso = $incluirCuentaActual($cuentasEgreso, $movimiento->id_cuenta, ['tipo_movimiento' => 'egreso']);
            } else {
                $cuentasIngreso = $incluirCuentaActual($cuentasIngreso, $movimiento->id_cuenta, ['tipo_movimiento' => 'ingreso']);
            }
            $cuentasFondeadoras = $incluirCuentaActual($cuentasFondeadoras, $movimiento->id_caja_fondo, ['tipo' => 'fondeadora']);
        } else {
            $cuentasFondeadoras = $incluirCuentaActual($cuentasFondeadoras, $cuentaOrigenId, ['tipo' => 'fondeadora']);
            $cuentasFondeadoras = $incluirCuentaActual($cuentasFondeadoras, $cuentaDestinoId, ['tipo' => 'fondeadora']);
        }

        \Log::info('✅ === EDIT COMPLETADO EXITOSAMENTE ===');
        \Log::info('Renderizando Edit con movimiento ID:', ['id' => $movimientoData['id']]);

        return Inertia::render('Movimientos/Edit', [
            'movimiento' => $movimientoData,
            'empresa_id' => (int) $empresaId,
            'cuentas_egreso' => $cuentasEgreso,
            'cuentas_ingreso' => $cuentasIngreso,
            'cuentas_fondeadoras' => $cuentasFondeadoras,
            'tipos_iva' => $tiposIva,
            'marcadores' => $marcadores,
            'personas' => $personas,
            'es_traspaso' => $esTraspaso
        ]);
    }
   
    public function update(Request $request, string $id)
    {
        \Log::info('=== INICIO UPDATE ===');
        \Log::info('Datos recibidos:', $request->all());

        // El frontend (Movimientos/Edit.vue) envía este update por axios con
        // header X-Requested-With. Si respondemos con redirect (302), axios lo
        // sigue, recibe 200 y cree que todo salió bien aunque haya fallado.
        // Por eso, ante un error devolvemos JSON con status de error.
        $esAjax = $request->ajax() || $request->wantsJson();
        $errorResponse = function (string $mensaje, int $status = 422) use ($esAjax) {
            if ($esAjax) {
                return response()->json(['success' => false, 'message' => $mensaje], $status);
            }
            return back()->with('error', $mensaje);
        };

        if (!Gate::allows('editar-poliza')) {
            return $esAjax
                ? response()->json(['success' => false, 'message' => 'No tienes permiso para editar movimientos'], 403)
                : redirect()->route('movimientos.index')->with('error', 'No tienes permiso para editar movimientos');
        }

        $movimiento = MovimientoPoliza::find($id);

        if (!$movimiento) {
            \Log::error('Movimiento no encontrado:', ['id' => $id]);
            return $errorResponse('Movimiento no encontrado', 404);
        }

        $poliza = Poliza::find($movimiento->id_poliza);

        if (!$poliza) {
            \Log::error('Póliza no encontrada:', ['id_poliza' => $movimiento->id_poliza]);
            return $errorResponse('Póliza no encontrada', 404);
        }

        if (!$this->puedeEditarPoliza($poliza)) {
            return $errorResponse('No puedes editar esta póliza (sólo las que capturaste y que aún no han sido revisadas)', 403);
        }

        \Log::info('Póliza encontrada:', [
            'id' => $poliza->id,
            'folio' => $poliza->folio,
            'tipo_poliza' => $poliza->tipo_poliza,
            'estatus' => $poliza->estatus
        ]);

        // Sólo una póliza CERRADA es inmutable. Antes update() bloqueaba también
        // AUTORIZADO/ABONADO/LIQUIDADO aunque edit() sí abría el formulario, así
        // que el usuario podía llenar el form y al guardar le rebotaba.
        if (in_array($poliza->estatus, ['CERRADO'])) {
            return $errorResponse('No se puede editar una póliza CERRADA.', 409);
        }

        $rules = [
            'tipo_poliza' => 'nullable|in:INGRESO,EGRESO,TRASPASO',
            'id_cuenta' => 'nullable|exists:cuentas,id_cuenta',
            'id_cuenta_fondeadora' => 'nullable|exists:cuentas,id_cuenta',
            'nota' => 'nullable|string',
            'referencia' => 'nullable|string|max:100',
            'id_persona' => 'nullable|exists:personas,id_persona',
            'id_marcador' => 'nullable|exists:marcadores,id',
            'fecha_vencimiento' => 'nullable|date',
            'fecha_factura' => 'nullable|date',
            'numero_factura' => 'nullable|string|max:50',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
            'uuid_factura' => 'nullable|string|max:50',
            'serie_factura' => 'nullable|string|max:20',
            'folio_factura' => 'nullable|string|max:20',
            'modo_iva' => 'nullable|in:CON_IVA,SIN_IVA',
            'monto_directo' => 'nullable|numeric|min:0',
            'ivas' => 'nullable|array',
            'ivas.*.id' => 'nullable|exists:tipos_iva,id',
            'ivas.*.monto' => 'numeric|min:0',
            'id_cuenta_origen' => 'nullable|exists:cuentas,id_cuenta',
            'id_cuenta_destino' => 'nullable|exists:cuentas,id_cuenta',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            \Log::error('Validación update fallida:', $validator->errors()->toArray());
            if ($esAjax) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors()->toArray(),
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $esFiscal = $this->convertirBooleano($request->input('es_fiscal', false));
            $esPorPagar = $this->convertirBooleano($request->input('es_por_pagar', false));
            $eliminarPdf = $this->convertirBooleano($request->input('eliminar_pdf', false));
            
            $montoDirecto = (float) $request->input('monto_directo', 0);
            $modoIva = $request->input('modo_iva', 'SIN_IVA');
            $ivas = $request->input('ivas', []);
            $esTraspaso = $poliza->tipo_poliza === 'TRASPASO';

            // El tipo de póliza (INGRESO/EGRESO) es editable en el formulario.
            // Un TRASPASO se edita por otro flujo, así que no se permite convertir
            // desde/hacia TRASPASO por aquí.
            $tipoPolizaNuevo = $poliza->tipo_poliza;
            if (!$esTraspaso) {
                $solicitado = strtoupper((string) $request->input('tipo_poliza'));
                if (in_array($solicitado, ['INGRESO', 'EGRESO'], true)) {
                    $tipoPolizaNuevo = $solicitado;
                }
            }

            // 🔥 GUARDAR VALORES ANTERIORES
            $tipoPolizaAnterior = $poliza->tipo_poliza;
            $cuentaAnterior = $movimiento->id_cuenta;
            $cajaFondoAnterior = $movimiento->id_caja_fondo;
            $montoAnterior = (float) $movimiento->monto;
            $montoBaseAnterior = (float) $movimiento->monto_base;
            $montoIvaAnterior = (float) $movimiento->monto_iva;

            $nuevaCuentaFondeadora = $request->input('id_cuenta_fondeadora');
            $nuevaCuenta = $request->input('id_cuenta');
            $cambioCuentaFondeadora = $nuevaCuentaFondeadora && $nuevaCuentaFondeadora != $cajaFondoAnterior;
            $cambioCuenta = $nuevaCuenta && $nuevaCuenta != $cuentaAnterior;

            if ($cambioCuentaFondeadora && !$this->fondeadoraPermitida($nuevaCuentaFondeadora)) {
                DB::rollBack();
                return $errorResponse($this->mensajeSinFondeadoras(), 403);
            }

            \Log::info('Datos anteriores para saldos:', [
                'cuenta_anterior' => $cuentaAnterior,
                'caja_fondo_anterior' => $cajaFondoAnterior,
                'monto_anterior' => $montoAnterior,
                'nueva_cuenta_fondeadora' => $nuevaCuentaFondeadora,
                'cambio_cuenta_fondeadora' => $cambioCuentaFondeadora,
                'cambio_cuenta' => $cambioCuenta
            ]);

            // 🔥 ACTUALIZAR PÓLIZA
            $polizaData = [
                'tipo_poliza' => $tipoPolizaNuevo,
                'nota' => $request->nota,
                'referencia' => $request->referencia,
                'id_persona' => $request->id_persona,
                'id_marcador' => $request->id_marcador,
                'es_por_pagar' => $esPorPagar,
                // La fecha de póliza es editable en el formulario pero no se estaba guardando.
                'fecha_poliza' => $request->filled('fecha_poliza') ? $request->fecha_poliza : $poliza->fecha_poliza,
                'fecha_vencimiento' => $esPorPagar ? $request->fecha_vencimiento : null,
                'fecha_factura' => $request->fecha_factura,
                'numero_factura' => $request->numero_factura,
                'categoria' => $esFiscal ? 'FISCAL' : 'NO_FISCAL',
                'uuid_factura' => $request->uuid_factura,
                'serie_factura' => $request->serie_factura,
                'folio_factura' => $request->folio_factura,
            ];

            // Manejo de PDF
            if ($request->hasFile('pdf_file')) {
                if ($poliza->ruta_pdf && Storage::disk('public')->exists($poliza->ruta_pdf)) {
                    Storage::disk('public')->delete($poliza->ruta_pdf);
                }
                $pdfFile = $request->file('pdf_file');
                if ($pdfFile->isValid()) {
                    $polizaData['nombre_pdf'] = $pdfFile->getClientOriginalName();
                    $polizaData['ruta_pdf'] = $pdfFile->store('documentos_fiscales/pdfs', 'public');
                }
            }

            if ($eliminarPdf || !$esFiscal) {
                if ($poliza->ruta_pdf && Storage::disk('public')->exists($poliza->ruta_pdf)) {
                    Storage::disk('public')->delete($poliza->ruta_pdf);
                }
                $polizaData['ruta_pdf'] = null;
                $polizaData['nombre_pdf'] = null;
            }

            $poliza->update($polizaData);
            \Log::info('✅ Póliza actualizada');

            // ============================================
            // ACTUALIZAR SEGÚN TIPO DE PÓLIZA
            // ============================================
            if ($esTraspaso) {
                // ============================================
                // ACTUALIZAR TRASPASO (2 movimientos: origen - / destino +)
                // Antes esta rama estaba vacía: al editar un traspaso sólo se
                // guardaba la cabecera de la póliza y los montos/cuentas se
                // ignoraban en silencio.
                // ============================================
                $movs = MovimientoPoliza::where('id_poliza', $poliza->id)->get();
                $movOrigen  = $movs->firstWhere('monto', '<', 0) ?? $movs->first();
                $movDestino = $movs->firstWhere('monto', '>', 0) ?? $movs->last();

                $cuentasAfectadas = collect([
                    $movOrigen?->id_cuenta,
                    $movDestino?->id_cuenta,
                ]);

                $nuevaOrigen  = $request->input('id_cuenta_origen')  ?: ($movOrigen?->id_cuenta);
                $nuevaDestino = $request->input('id_cuenta_destino') ?: ($movDestino?->id_cuenta);

                if ($nuevaOrigen && $nuevaDestino && $nuevaOrigen == $nuevaDestino) {
                    throw new \Exception('La cuenta de origen y destino no pueden ser la misma.');
                }

                // Monto / desglose de IVA (mismo criterio que storeTraspaso)
                $monto = $montoDirecto > 0 ? round($montoDirecto, 2) : abs((float) ($movOrigen->monto_traspaso ?? $movOrigen->monto ?? 0));
                $totalBase = $monto;
                $totalIva = 0;
                $montoIvaCero = 0;
                $montoIvaDieciseis = 0;
                $ivaDieciseisCalc = 0;
                $ivaIds = [];

                if ($modoIva === 'CON_IVA' && !empty($ivas)) {
                    $totalBase = 0;
                    foreach ($ivas as $ivaData) {
                        $iid = isset($ivaData['id']) ? (int) $ivaData['id'] : null;
                        $imonto = isset($ivaData['monto']) ? (float) $ivaData['monto'] : 0;
                        if ($iid && $imonto > 0 && ($tipoIva = TipoIva::find($iid))) {
                            $base = round($imonto, 2);
                            $ivaCalc = round($base * ($tipoIva->porcentaje / 100), 2);
                            $totalBase += $base;
                            $totalIva += $ivaCalc;
                            $ivaIds[] = $iid;
                            if ($tipoIva->porcentaje == 0) {
                                $montoIvaCero += $base;
                            } elseif ($tipoIva->porcentaje == 16) {
                                $montoIvaDieciseis += $base;
                                $ivaDieciseisCalc += $ivaCalc;
                            }
                        }
                    }
                    if ($totalBase == 0) {
                        $totalBase = $monto;
                    }
                }

                $primerIvaId = !empty($ivaIds) ? $ivaIds[0] : null;

                if ($movOrigen) {
                    $movOrigen->update([
                        'id_cuenta'           => $nuevaOrigen,
                        'id_tipo_iva'         => $primerIvaId,
                        'monto'               => -$monto,
                        'monto_base'          => -$totalBase,
                        'monto_iva'           => -$totalIva,
                        'monto_traspaso'      => $monto,
                        'monto_iva_cero'      => -$montoIvaCero,
                        'monto_iva_dieciseis' => -$montoIvaDieciseis,
                        'iva_dieciseis'       => -$ivaDieciseisCalc,
                    ]);
                }

                if ($movDestino) {
                    $movDestino->update([
                        'id_cuenta'           => $nuevaDestino,
                        'id_tipo_iva'         => $primerIvaId,
                        'monto'               => $monto,
                        'monto_base'          => $totalBase,
                        'monto_iva'           => $totalIva,
                        'monto_traspaso'      => $monto,
                        'monto_iva_cero'      => $montoIvaCero,
                        'monto_iva_dieciseis' => $montoIvaDieciseis,
                        'iva_dieciseis'       => $ivaDieciseisCalc,
                    ]);
                }

                // Recalcular saldos de todas las cuentas involucradas (antiguas y nuevas)
                $cuentasAfectadas->push($nuevaOrigen)->push($nuevaDestino);
                $cuentasAfectadas->filter()->unique()->each(function ($idCuenta) {
                    $this->actualizarSaldoCuenta($idCuenta);
                });

                \Log::info('Traspaso actualizado:', [
                    'poliza' => $poliza->id,
                    'origen' => $nuevaOrigen,
                    'destino' => $nuevaDestino,
                    'monto' => $monto,
                ]);
            } else {
                // ============================================
                // ACTUALIZAR INGRESO/EGRESO
                // ============================================
                $movimientoData = [];
                $montoCambio = false;

                // Si sólo cambió el TIPO (INGRESO<->EGRESO) pero no el monto, hay
                // que reescribir igualmente el movimiento con el signo correcto.
                $cambioTipo = ($tipoPolizaNuevo !== $tipoPolizaAnterior)
                    || ($tipoPolizaNuevo === 'EGRESO' && $montoAnterior > 0)
                    || ($tipoPolizaNuevo === 'INGRESO' && $montoAnterior < 0);

                if ($montoDirecto <= 0 && $cambioTipo) {
                    // Reusar el monto actual, sólo corrige el signo.
                    $montoDirecto = abs($montoAnterior);
                }

                if ($montoDirecto > 0) {
                    $signo = $tipoPolizaNuevo === 'EGRESO' ? -1 : 1;

                    if ($modoIva === 'SIN_IVA') {
                        $totalBase = $montoDirecto;
                        $totalIva = 0;
                        $montoTotal = $montoDirecto;
                        $montoIvaCero = 0;
                        $montoIvaDieciseis = 0;
                        $ivaDieciseisCalc = 0;
                        $ivaIds = [];
                    } else {
                        $totalBase = 0;
                        $totalIva = 0;
                        $montoIvaCero = 0;
                        $montoIvaDieciseis = 0;
                        $ivaDieciseisCalc = 0;
                        $ivaIds = [];

                        foreach ($ivas as $ivaData) {
                            $id = isset($ivaData['id']) ? (int) $ivaData['id'] : null;
                            $montoIva = isset($ivaData['monto']) ? (float) $ivaData['monto'] : 0;
                            
                            if ($id && $montoIva > 0) {
                                $tipoIva = TipoIva::find($id);
                                if ($tipoIva) {
                                    $montoBase = round($montoIva, 2);
                                    $ivaCalculado = round($montoBase * ($tipoIva->porcentaje / 100), 2);
                                    
                                    $totalBase += $montoBase;
                                    $totalIva += $ivaCalculado;
                                    $ivaIds[] = $id;

                                    if ($tipoIva->porcentaje == 0) {
                                        $montoIvaCero += $montoBase;
                                    } elseif ($tipoIva->porcentaje == 16) {
                                        $montoIvaDieciseis += $montoBase;
                                        $ivaDieciseisCalc += $ivaCalculado;
                                    }
                                }
                            }
                        }
                        $montoTotal = $totalBase;
                    }

                    $movimientoData['monto'] = round($montoTotal * $signo, 2);
                    $movimientoData['monto_base'] = round($totalBase * $signo, 2);
                    $movimientoData['monto_iva'] = round($totalIva * $signo, 2);
                    $movimientoData['monto_iva_cero'] = round($montoIvaCero * $signo, 2);
                    $movimientoData['monto_iva_dieciseis'] = round($montoIvaDieciseis * $signo, 2);
                    $movimientoData['iva_dieciseis'] = round($ivaDieciseisCalc * $signo, 2);
                    $movimientoData['id_tipo_iva'] = !empty($ivaIds) ? $ivaIds[0] : null;
                    $montoCambio = true;
                }

                if ($request->has('id_cuenta') && $request->id_cuenta) {
                    $movimientoData['id_cuenta'] = $request->id_cuenta;
                }
                if ($request->has('id_cuenta_fondeadora') && $request->id_cuenta_fondeadora) {
                    $movimientoData['id_caja_fondo'] = $request->id_cuenta_fondeadora;
                }

                // 🔥 ACTUALIZAR EL MOVIMIENTO
                if (!empty($movimientoData)) {
                    $movimiento->update($movimientoData);
                    \Log::info('Movimiento actualizado:', $movimientoData);
                }

                // ============================================================
                // 🔥 RECALCULAR SALDOS desde cero (igual que store() y traspasos).
                // Antes se hacía ajuste incremental con guardas que fallaban al
                // cambiar sólo el signo (INGRESO<->EGRESO) o al no mover la cuenta.
                // ============================================================
                collect([
                    $cuentaAnterior,
                    $cajaFondoAnterior,
                    $movimiento->id_cuenta,
                    $movimiento->id_caja_fondo,
                ])->filter()->unique()->each(function ($idCuenta) {
                    $this->actualizarSaldoCuenta($idCuenta);
                });
            }

            $this->actualizarEstatusPoliza($poliza);

            DB::commit();
            \Log::info('=== UPDATE COMPLETADO EXITOSAMENTE ===');

            if ($esAjax) {
                return response()->json(['success' => true, 'message' => 'Movimiento actualizado exitosamente']);
            }
            return redirect()->route('movimientos.index')
                ->with('success', 'Movimiento actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('=== ERROR EN UPDATE ===');
            \Log::error('Mensaje:', ['message' => $e->getMessage()]);
            \Log::error('Trace:', ['trace' => $e->getTraceAsString()]);
            return $errorResponse('Error al actualizar el movimiento: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Revertir saldo de una cuenta (restar el monto anterior)
     */
    private function revertirSaldoCuenta($cuentaId, $monto)
    {
        if (!$cuentaId) return;
        
        $cuenta = Cuenta::find($cuentaId);
        if (!$cuenta) {
            \Log::warning('Cuenta no encontrada para revertir saldo:', ['id' => $cuentaId]);
            return;
        }

        $montoAbs = abs($monto);
        $saldoActual = (float) ($cuenta->saldo_inicial ?? 0);
        
        // Si el monto era positivo (ingreso), restarlo del saldo
        // Si el monto era negativo (egreso), sumarlo al saldo
        if ($monto > 0) {
            $nuevoSaldo = $saldoActual - $montoAbs;
        } else {
            $nuevoSaldo = $saldoActual + $montoAbs;
        }
        
        $cuenta->saldo_inicial = max(0, $nuevoSaldo);
        $cuenta->save();
        
        \Log::info('Saldo revertido:', [
            'cuenta_id' => $cuentaId,
            'saldo_anterior' => $saldoActual,
            'monto' => $monto,
            'nuevo_saldo' => $cuenta->saldo_inicial
        ]);
    }

    /**
     * Aplicar saldo a una cuenta (sumar el nuevo monto)
     */
    private function aplicarSaldoCuenta($cuentaId, $monto)
    {
        if (!$cuentaId) return;
        
        $cuenta = Cuenta::find($cuentaId);
        if (!$cuenta) {
            \Log::warning('Cuenta no encontrada para aplicar saldo:', ['id' => $cuentaId]);
            return;
        }

        $montoAbs = abs($monto);
        $saldoActual = (float) ($cuenta->saldo_inicial ?? 0);
        
        // Si el monto es positivo (ingreso), sumarlo al saldo
        // Si el monto es negativo (egreso), restarlo del saldo
        if ($monto > 0) {
            $nuevoSaldo = $saldoActual + $montoAbs;
        } else {
            $nuevoSaldo = $saldoActual - $montoAbs;
        }
        
        $cuenta->saldo_inicial = max(0, $nuevoSaldo);
        $cuenta->save();
        
        \Log::info('Saldo aplicado:', [
            'cuenta_id' => $cuentaId,
            'saldo_anterior' => $saldoActual,
            'monto' => $monto,
            'nuevo_saldo' => $cuenta->saldo_inicial
        ]);
    }

    /*
     * Convertir a booleano (mantener compatibilidad)
     */
    private function convertirBooleano($valor)
    {
        if (is_bool($valor)) return $valor;
        if (is_string($valor)) {
            return in_array(strtolower($valor), ['true', '1', 'on', 'yes']);
        }
        if (is_numeric($valor)) {
            return (int) $valor === 1;
        }
        return (bool) $valor;
    }

    // ============================================
    // 📄 DESTROY - ELIMINAR MOVIMIENTO
    // ============================================
    public function destroy(string $id)
    {
        if (!Gate::allows('eliminar-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar movimientos'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $movimiento = MovimientoPoliza::with('poliza')->find($id);

            if (!$movimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Movimiento no encontrado'
                ], 404);
            }

            $poliza = $movimiento->poliza;
            $esTraspaso = $poliza->tipo_poliza === 'TRASPASO';
            $movimientosPoliza = MovimientoPoliza::where('id_poliza', $poliza->id)->get();
            
            if (in_array($poliza->estatus, ['AUTORIZADO', 'ABONADO', 'LIQUIDADO'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden eliminar movimientos de una póliza en estado ' . $this->getEstatusTexto($poliza->estatus)
                ], 422);
            }

            $cuentasAActualizar = [];
            
            foreach ($movimientosPoliza as $mov) {
                if ($mov->id_cuenta) {
                    $cuentasAActualizar[] = $mov->id_cuenta;
                }
                if ($mov->id_caja_fondo) {
                    $cuentasAActualizar[] = $mov->id_caja_fondo;
                }
            }
            
            AbonoPoliza::where('id_poliza', $poliza->id)->delete();

            if ($poliza->ruta_pdf && Storage::disk('public')->exists($poliza->ruta_pdf)) {
                Storage::disk('public')->delete($poliza->ruta_pdf);
            }
            if ($poliza->ruta_xml && Storage::disk('public')->exists($poliza->ruta_xml)) {
                Storage::disk('public')->delete($poliza->ruta_xml);
            }

            foreach ($movimientosPoliza as $mov) {
                $mov->delete();
            }

            $poliza->delete();

            if (!$poliza->es_por_pagar) {
                foreach (array_unique($cuentasAActualizar) as $idCuenta) {
                    if ($idCuenta) {
                        $this->actualizarSaldoCuenta($idCuenta);
                    }
                }
            }

            DB::commit();

            $mensaje = 'Movimiento eliminado exitosamente';
            if ($esTraspaso) {
                $mensaje = 'Traspaso eliminado exitosamente. Los saldos de las cuentas han sido restaurados.';
            }
            if ($poliza->es_por_pagar) {
                $mensaje .= ' (Póliza diferida - no se afectaron saldos)';
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el movimiento: ' . $e->getMessage()
            ], 500);
        }
    }


    // ============================================
    // 📄 STORE ABONO - CON RESPUESTA JSON PARA ALERTA
    // ============================================
    public function storeAbono(Request $request)
    {
        \Log::info('=== INICIO storeAbono ===');
        \Log::info('Datos recibidos:', $request->all());

        // El formulario de abono es de Inertia: los errores regresan a la misma
        // pantalla en el bag de errores (un JSON 403 no se mostraba y parecía
        // que el botón "no hacía nada").
        if (!Gate::allows('crear-movimientos')) {
            return back()->withErrors(['abono' => 'No tienes permiso para crear abonos']);
        }

        $polizaAbono = Poliza::find($request->id_poliza);
        if ($polizaAbono && $this->sinFondeadorasEnEmpresa($polizaAbono->id_empresa)) {
            return back()->withErrors(['abono' => $this->mensajeSinFondeadoras()]);
        }

        $validator = Validator::make($request->all(), [
            'id_poliza' => 'required|exists:polizas,id',
            'monto_abonado' => 'required|numeric|min:0.01',
            'id_cuenta_fondeadora' => 'nullable|exists:cuentas,id_cuenta',
            'referencia' => 'nullable|string|max:100',
            'nota' => 'nullable|string',
            'fecha_abono' => 'nullable|date',
            'ivas' => 'nullable|array',
            'ivas.*.id' => 'nullable|exists:tipos_iva,id',
            'ivas.*.monto' => 'numeric|min:0',
            'total_base' => 'numeric|min:0',
            'total_iva' => 'numeric|min:0',
            'total_con_iva' => 'numeric|min:0',
        ]);

        if ($validator->fails()) {
            \Log::error('Validación fallida:', $validator->errors()->toArray());
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $polizaDiferida = Poliza::find($request->id_poliza);
            if (!$polizaDiferida) {
                throw new \Exception('Póliza diferida no encontrada');
            }

            // 🔥 VALIDAR QUE LA PÓLIZA SEA DIFERIDA (es_por_pagar = true)
            if (!$polizaDiferida->es_por_pagar) {
                throw new \Exception('Esta póliza no es de tipo "Por pagar". No se pueden registrar abonos.');
            }

            // 🔥 ESTADOS PERMITIDOS PARA ABONOS
            $estadosPermitidos = ['CAPTURADO', 'PENDIENTE', 'REVISADO', 'AUTORIZADO', 'ABONADO'];
            $estadosNoPermitidos = ['CERRADO', 'LIQUIDADO'];

            if (in_array($polizaDiferida->estatus, $estadosNoPermitidos)) {
                throw new \Exception("No se pueden registrar abonos en una póliza con estado {$polizaDiferida->estatus}. Solo se permiten en: " . implode(', ', $estadosPermitidos));
            }

            if (!in_array($polizaDiferida->estatus, $estadosPermitidos)) {
                throw new \Exception("La póliza está en estado '{$polizaDiferida->estatus}' y no se pueden registrar abonos. Estados permitidos: " . implode(', ', $estadosPermitidos));
            }

            // 🔥 OBTENER EL MOVIMIENTO ORIGINAL DE LA PÓLIZA DIFERIDA
            $movimientoOriginal = MovimientoPoliza::where('id_poliza', $polizaDiferida->id)->first();
            if (!$movimientoOriginal) {
                throw new \Exception('No se encontró el movimiento original de la póliza diferida');
            }

            // 🔥 DETERMINAR EL TIPO DE PÓLIZA DEL ABONO
            $tipoPolizaAbono = $polizaDiferida->tipo_poliza;

            \Log::info('📌 Tipo de póliza para el abono:', [
                'tipo_poliza_original' => $polizaDiferida->tipo_poliza,
                'tipo_poliza_abono' => $tipoPolizaAbono,
                'es_por_pagar' => $polizaDiferida->es_por_pagar
            ]);

            // 🔥 GUARDAR LA CATEGORÍA ORIGINAL
            $categoriaOriginal = $polizaDiferida->categoria;
            
            \Log::info('📌 Categoría original de la póliza diferida:', [
                'categoria' => $categoriaOriginal,
                'id_poliza' => $polizaDiferida->id,
                'folio' => $polizaDiferida->folio,
                'estatus' => $polizaDiferida->estatus
            ]);

            $montoAbonado = (float) $request->monto_abonado;
            $totalBase = (float) $request->input('total_base', 0);
            $totalIva = (float) $request->input('total_iva', 0);
            
            // 🔥 PROCESAR IVAS CORRECTAMENTE
            $ivas = $request->input('ivas', []);
            $ivaIds = [];
            
            if (!empty($ivas) && is_array($ivas)) {
                foreach ($ivas as $key => $ivaData) {
                    if (isset($ivaData['id']) && isset($ivaData['monto']) && $ivaData['monto'] > 0) {
                        $ivaIds[] = [
                            'id' => (int) $ivaData['id'],
                            'monto' => (float) $ivaData['monto']
                        ];
                    } elseif (isset($ivaData['monto']) && $ivaData['monto'] > 0) {
                        $ivaIds[] = [
                            'id' => (int) $key,
                            'monto' => (float) $ivaData['monto']
                        ];
                    }
                }
            }

            \Log::info('📊 IVAs procesados:', ['ivas' => $ivaIds]);

            // 🔥 OBTENER LA CUENTA FONDEADORA
            $idCuentaFondeadora = $request->id_cuenta_fondeadora ?? $movimientoOriginal->id_caja_fondo;

            if (!$idCuentaFondeadora) {
                throw new \Exception('No se encontró una cuenta fondeadora asociada a la póliza diferida');
            }

            // 🔥 CREAR LA NUEVA PÓLIZA DE ABONO
            $empresaId = $polizaDiferida->id_empresa;
            
            // ============================================================
            // 🔥 SOLUCIÓN: FECHA CORRECTA PARA MÉXICO
            // ============================================================
            // Si el usuario envía fecha_abono, usar esa; si no, usar la fecha actual de México
            if ($request->fecha_abono) {
                $fechaAbono = Carbon::parse($request->fecha_abono);
            } else {
                // Usar la zona horaria de México (Ciudad de México)
                $fechaAbono = Carbon::now('America/Mexico_City');
            }
            
            // Log para depuración
            \Log::info('📅 Fecha del abono:', [
                'fecha_abono' => $fechaAbono->toDateTimeString(),
                'timezone' => $fechaAbono->getTimezone()->getName()
            ]);
            
            $referenciaAbono = $request->referencia ?? 'ABO-' . $polizaDiferida->folio . '-' . $fechaAbono->format('YmdHis');

            $estatusInicial = ($tipoPolizaAbono === 'INGRESO') ? 'AUTORIZADO' : 'CAPTURADO';

            $nuevaPoliza = Poliza::create([
                'id_empresa' => $empresaId,
                'tipo_poliza' => $tipoPolizaAbono,
                'fecha_poliza' => $fechaAbono->toDateString(),
                'fecha_vencimiento' => null,
                'categoria' => $categoriaOriginal,
                'estatus' => $estatusInicial,
                'es_por_pagar' => false,
                'referencia' => $referenciaAbono,
                'nota' => $request->nota ?? 'Abono a póliza diferida ' . $polizaDiferida->folio,
                'id_persona' => $polizaDiferida->id_persona,
                'id_usuario_creador' => auth()->id(),
                'id_usuario_autorizador' => ($tipoPolizaAbono === 'INGRESO') ? auth()->id() : null,
                'fecha_creacion' => now(),
                'fecha_autorizacion' => ($tipoPolizaAbono === 'INGRESO') ? now() : null,
                'id_marcador' => $polizaDiferida->id_marcador,
                'fecha_factura' => $polizaDiferida->fecha_factura,
                'numero_factura' => $polizaDiferida->numero_factura,
                'ruta_pdf' => $polizaDiferida->ruta_pdf,
                'nombre_pdf' => $polizaDiferida->nombre_pdf,
                'nombre_xml' => $polizaDiferida->nombre_xml,
                'uuid_factura' => $polizaDiferida->uuid_factura,
                'serie_factura' => $polizaDiferida->serie_factura,
                'folio_factura' => $polizaDiferida->folio_factura,
            ]);

            $nuevaPoliza->refresh();
            \Log::info('📄 Nueva póliza de abono creada:', [
                'id' => $nuevaPoliza->id,
                'folio' => $nuevaPoliza->folio,
                'tipo' => $tipoPolizaAbono,
                'estatus' => $nuevaPoliza->estatus,
                'fecha_poliza' => $nuevaPoliza->fecha_poliza
            ]);

            // 🔥 CREAR EL MOVIMIENTO DE LA NUEVA PÓLIZA
            $primerIvaId = !empty($ivaIds) ? $ivaIds[0]['id'] : null;
            $signo = ($tipoPolizaAbono === 'INGRESO') ? 1 : -1;

            $movimientoAbono = MovimientoPoliza::create([
                'id_poliza' => $nuevaPoliza->id,
                'id_cuenta' => $movimientoOriginal->id_cuenta,
                'id_caja_fondo' => $idCuentaFondeadora,
                'id_tipo_iva' => $primerIvaId,
                'monto' => $montoAbonado * $signo,
                'monto_base' => $totalBase * $signo,
                'monto_iva' => $totalIva * $signo,
                'monto_traspaso' => 0,
                'monto_iva_cero' => 0,
                'monto_iva_dieciseis' => 0,
                'iva_dieciseis' => 0,
            ]);

            \Log::info('📊 Movimiento de abono creado:', [
                'id' => $movimientoAbono->id,
                'monto' => $montoAbonado * $signo,
                'tipo' => $tipoPolizaAbono,
                'signo' => $signo
            ]);

            // 🔥 CREAR REGISTRO DE ABONO EN LA TABLA ABONOS
            $abono = AbonoPoliza::create([
                'id_poliza' => $polizaDiferida->id,
                'id_movimiento' => $movimientoAbono->id,
                'monto_abonado' => $montoAbonado,
                'monto_base' => $totalBase,
                'monto_iva' => $totalIva,
                'fecha_abono' => $fechaAbono->toDateString(),
                'referencia' => $referenciaAbono,
                'nota' => $request->nota,
                'id_usuario' => auth()->id(),
                'estatus' => 'REGISTRADO',
                'id_poliza_egreso' => $nuevaPoliza->id,
            ]);

            \Log::info('✅ Abono registrado:', [
                'id' => $abono->id,
                'monto' => $montoAbonado,
                'fecha_abono' => $fechaAbono->toDateString(),
                'id_poliza_diferida' => $polizaDiferida->id,
                'id_poliza_abono' => $nuevaPoliza->id
            ]);

            // 🔥 ACTUALIZAR SALDOS CORRECTAMENTE
            if ($tipoPolizaAbono === 'INGRESO') {
                $this->actualizarSaldoCuenta($movimientoOriginal->id_cuenta);
                $this->actualizarSaldoCuenta($idCuentaFondeadora);
                \Log::info('💰 Saldo actualizado: INGRESO - Se suma a fondeadora y cuenta');
            } else {
                $this->actualizarSaldoCuenta($movimientoOriginal->id_cuenta);
                $this->actualizarSaldoCuenta($idCuentaFondeadora);
                \Log::info('💰 Saldo actualizado: EGRESO - Se resta de fondeadora y cuenta');
            }

            // 🔥 ACTUALIZAR ESTATUS DE LA PÓLIZA DIFERIDA
            $totalAbonado = AbonoPoliza::where('id_poliza', $polizaDiferida->id)->sum('monto_abonado');
            $totalOriginal = abs($movimientoOriginal->monto);
            $saldoPendiente = $totalOriginal - $totalAbonado;

            if ($saldoPendiente <= 0.01) {
                $polizaDiferida->estatus = 'LIQUIDADO';
                $polizaDiferida->fecha_liquidacion = now();
                \Log::info('🎉 Póliza diferida liquidada por abono completo');
            } else {
                $polizaDiferida->estatus = 'ABONADO';
                $polizaDiferida->fecha_abono = now();
                \Log::info('📌 Póliza diferida en estado ABONADO, saldo pendiente: ' . $saldoPendiente);
            }

            $polizaDiferida->save();

            // 🔥 VERIFICAR QUE SE GUARDÓ CORRECTAMENTE
            $polizaDiferida->refresh();
            \Log::info('📌 Póliza diferida actualizada:', [
                'estatus' => $polizaDiferida->estatus,
                'saldo_pendiente' => $saldoPendiente,
            ]);

            DB::commit();

            // 🔥 MENSAJE DE ÉXITO
            $tipoTexto = $tipoPolizaAbono === 'INGRESO' ? 'cobro' : 'pago';
            $mensaje = "✅ Abono registrado exitosamente. {$tipoTexto} de $" . number_format($montoAbonado, 2);
            $mensaje .= " | Nuevo saldo pendiente: $" . number_format(max(0, $saldoPendiente), 2);
            $mensaje .= " | Póliza de {$tipoTexto}: " . $nuevaPoliza->folio;
            $mensaje .= " | Fecha: " . $fechaAbono->format('d/m/Y');

            if ($saldoPendiente <= 0.01) {
                $mensaje .= " | 🎉 ¡Póliza diferida liquidada!";
            }

            if ($categoriaOriginal === 'FISCAL') {
                $mensaje .= " | Categoría: FISCAL";
            } else {
                $mensaje .= " | Categoría: NO FISCAL";
            }

            return redirect()->route('movimientos.index', ['vista' => 'diferidas'])
                ->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('=== ERROR EN storeAbono ===');
            \Log::error('Mensaje:', ['message' => $e->getMessage()]);
            \Log::error('Trace:', ['trace' => $e->getTraceAsString()]);
            return back()->withErrors(['abono' => 'Error al registrar el abono: ' . $e->getMessage()])->withInput();
        }
    }

    // ============================================
    // 📄 DESTROY ABONO
    // ============================================
    public function destroyAbono(string $id)
    {
        if (!Gate::allows('editar-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar abonos'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $abono = AbonoPoliza::find($id);
            
            if (!$abono) {
                return response()->json([
                    'success' => false,
                    'message' => 'Abono no encontrado'
                ], 404);
            }

            $poliza = Poliza::find($abono->id_poliza);
            if (!$poliza) {
                return response()->json([
                    'success' => false,
                    'message' => 'Póliza no encontrada'
                ], 404);
            }

            if ($poliza->estatus === 'LIQUIDADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden eliminar abonos de una póliza liquidada'
                ], 422);
            }

            if ($abono->id_poliza_egreso) {
                $polizaEgreso = Poliza::find($abono->id_poliza_egreso);
                if ($polizaEgreso) {
                    $movimientoEgreso = MovimientoPoliza::where('id_poliza', $polizaEgreso->id)->first();
                    if ($movimientoEgreso) {
                        $idCuentaFondeadora = $movimientoEgreso->id_caja_fondo;
                        if ($idCuentaFondeadora) {
                            $this->actualizarSaldoCuenta($idCuentaFondeadora);
                        }
                        if ($movimientoEgreso->id_cuenta && $movimientoEgreso->id_cuenta != $idCuentaFondeadora) {
                            $this->actualizarSaldoCuenta($movimientoEgreso->id_cuenta);
                        }
                        $movimientoEgreso->delete();
                    }
                    $polizaEgreso->delete();
                }
            }

            $abono->delete();
            $this->actualizarEstatusPoliza($poliza);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Abono y póliza de egreso eliminados exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el abono: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 💰 LIQUIDAR PÓLIZA
    // ============================================
    public function liquidarPoliza(Request $request)
    {
        if (!Gate::allows('editar-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para liquidar pólizas'
            ], 403);
        }

        $movGuard = MovimientoPoliza::with('poliza')->find($request->id_movimiento);
        if ($movGuard && $movGuard->poliza && $this->sinFondeadorasEnEmpresa($movGuard->poliza->id_empresa)) {
            return response()->json(['success' => false, 'message' => $this->mensajeSinFondeadoras()], 403);
        }

        try {
            DB::beginTransaction();

            $movimiento = MovimientoPoliza::with('poliza')->find($request->id_movimiento);
            if (!$movimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Movimiento no encontrado'
                ], 404);
            }

            $poliza = $movimiento->poliza;
            if ($poliza->estatus === 'LIQUIDADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta póliza ya está liquidada'
                ], 422);
            }

            if ($poliza->estatus !== 'ABONADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'La póliza debe estar en estado ABONADO para liquidar'
                ], 422);
            }

            $cuentaFondeadora = Cuenta::find($request->id_cuenta_fondeadora);
            if (!$cuentaFondeadora) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuenta fondeadora no encontrada'
                ], 404);
            }

            $saldoActual = $cuentaFondeadora->saldo_inicial ?? 0;
            $montoLiquidar = abs($movimiento->monto);
            $totalAbonado = AbonoPoliza::where('id_poliza', $poliza->id)->sum('monto_abonado');
            $saldoPendiente = $montoLiquidar - $totalAbonado;

            if ($saldoPendiente <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta póliza ya está completamente pagada'
                ], 422);
            }

            if ($saldoActual < $saldoPendiente) {
                return response()->json([
                    'success' => false,
                    'message' => "Saldo insuficiente en la cuenta fondeadora. Disponible: $" . number_format($saldoActual, 2) . ", Necesario: $" . number_format($saldoPendiente, 2)
                ], 422);
            }

            $empresaId = $poliza->id_empresa;
            
            $nuevaPoliza = Poliza::create([
                'id_empresa' => $empresaId,
                'tipo_poliza' => 'EGRESO',
                'fecha_poliza' => now()->toDateString(),
                'fecha_vencimiento' => null,
                'categoria' => 'NO_FISCAL',
                'estatus' => 'CAPTURADO',
                'es_por_pagar' => false,
                'referencia' => 'LIQ-' . $poliza->folio . '-' . date('YmdHis'),
                'nota' => 'Liquidación de póliza ' . $poliza->folio . ' - Cuenta fondeadora: ' . $cuentaFondeadora->nombre_cuenta,
                'id_persona' => $poliza->id_persona,
                'id_usuario_creador' => auth()->id(),
                'id_usuario_autorizador' => null,
                'fecha_creacion' => now(),
                'fecha_autorizacion' => null,
                'id_marcador' => $poliza->id_marcador,
                'fecha_factura' => null,
                'numero_factura' => null,
                'ruta_pdf' => null,
                'nombre_pdf' => null,
                'nombre_xml' => null,
                'uuid_factura' => null,
                'serie_factura' => null,
                'folio_factura' => null,
            ]);

            $nuevaPoliza->refresh();

            MovimientoPoliza::create([
                'id_poliza' => $nuevaPoliza->id,
                'id_cuenta' => $movimiento->id_cuenta,
                'id_caja_fondo' => $request->id_cuenta_fondeadora,
                'id_tipo_iva' => null,
                'monto' => -$saldoPendiente,
                'monto_base' => -$saldoPendiente,
                'monto_iva' => 0,
                'monto_traspaso' => null,
                'monto_iva_cero' => 0,
                'monto_iva_dieciseis' => 0,
                'iva_dieciseis' => 0,
            ]);

            $cuentaFondeadora->saldo_inicial = $saldoActual - $saldoPendiente;
            $cuentaFondeadora->save();

            $abono = AbonoPoliza::create([
                'id_poliza' => $poliza->id,
                'monto_abonado' => $saldoPendiente,
                'fecha_abono' => now()->toDateString(),
                'referencia' => 'LIQUIDACION AUTOMATICA - ' . ($request->referencia ?? ''),
                'metodo_pago' => 'TRANSFERENCIA',
                'nota' => 'Liquidación total de póliza. Póliza de egreso: ' . $nuevaPoliza->folio,
                'id_usuario' => auth()->id(),
                'id_poliza_egreso' => $nuevaPoliza->id,
            ]);

            $poliza->estatus = 'LIQUIDADO';
            $poliza->fecha_liquidacion = now();
            $poliza->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Póliza liquidada exitosamente. Se creó la póliza de egreso: ' . $nuevaPoliza->folio
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al liquidar la póliza: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 💰 LIQUIDAR INGRESO
    // ============================================
    public function liquidarIngreso(Request $request)
    {
        if (!Gate::allows('editar-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para liquidar ingresos'
            ], 403);
        }

        $movGuard = MovimientoPoliza::with('poliza')->find($request->id_movimiento);
        if ($movGuard && $movGuard->poliza && $this->sinFondeadorasEnEmpresa($movGuard->poliza->id_empresa)) {
            return response()->json(['success' => false, 'message' => $this->mensajeSinFondeadoras()], 403);
        }

        try {
            DB::beginTransaction();

            $movimiento = MovimientoPoliza::with('poliza')->find($request->id_movimiento);
            if (!$movimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Movimiento no encontrado'
                ], 404);
            }

            $poliza = $movimiento->poliza;
            if ($poliza->estatus === 'LIQUIDADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta póliza ya está liquidada'
                ], 422);
            }

            if ($poliza->tipo_poliza !== 'INGRESO') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta función solo aplica para pólizas de INGRESO'
                ], 422);
            }

            if ($poliza->estatus !== 'AUTORIZADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'La póliza debe estar en estado AUTORIZADO para liquidar'
                ], 422);
            }

            $montoLiquidar = abs($movimiento->monto);
            $totalAbonado = AbonoPoliza::where('id_poliza', $poliza->id)->sum('monto_abonado');
            $saldoPendiente = $montoLiquidar - $totalAbonado;

            if ($saldoPendiente <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta póliza ya está completamente pagada'
                ], 422);
            }

            $abono = AbonoPoliza::create([
                'id_poliza' => $poliza->id,
                'monto_abonado' => $saldoPendiente,
                'fecha_abono' => now()->toDateString(),
                'referencia' => 'LIQUIDACION INGRESO - ' . ($request->referencia ?? ''),
                'metodo_pago' => 'TRANSFERENCIA',
                'nota' => 'Liquidación total de póliza de ingreso desde el módulo de movimientos.',
                'id_usuario' => auth()->id(),
                'id_poliza_egreso' => null,
            ]);

            $poliza->estatus = 'ABONADO';
            $poliza->fecha_abono = now();
            $poliza->save();

            $poliza->estatus = 'LIQUIDADO';
            $poliza->fecha_liquidacion = now();
            $poliza->save();

            if ($movimiento->id_cuenta) {
                $this->actualizarSaldoCuenta($movimiento->id_cuenta);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Póliza de ingreso liquidada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al liquidar la póliza: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 📄 CREATE NÓMINA
    // ============================================
    public function createNomina()
    {
        if (!Gate::allows('crear-movimientos')) {
            return redirect()->route('movimientos.index')
                ->with('error', 'No tienes permiso para crear movimientos');
        }

        // 🔥 Antes solo miraba `empresa_movimientos` (que solo se guarda al pasar
        // por /movimientos/create). Si el usuario entraba a Nómina sin haber
        // visitado esa pantalla, esto caía siempre a "su primera empresa" en
        // vez de la empresa que tenía seleccionada en el índice de Movimientos
        // (`empresa_seleccionada`) — generando pólizas para la empresa
        // equivocada o mostrando el formulario sin cajas fondo si la primera
        // empresa no tenía ninguna configurada.
        $empresaId = session('empresa_movimientos') ?? session('empresa_seleccionada');

        if (!$empresaId) {
            $empresa = auth()->user()->empresas()->first();
            $empresaId = $empresa ? $empresa->id : null;
        }

        if (!$empresaId) {
            return redirect()->route('movimientos.index')
                ->with('error', 'No tienes una empresa seleccionada.');
        }

        // Sin ninguna cuenta fondeadora asignada no se puede generar ninguna póliza.
        if ($this->sinFondeadorasEnEmpresa($empresaId)) {
            return redirect()->route('movimientos.index')->with('error', $this->mensajeSinFondeadoras());
        }

        // Sincronizar ambas claves para que storeNomina() (y cualquier otra
        // pantalla) use exactamente esta misma empresa.
        session(['empresa_movimientos' => $empresaId, 'empresa_seleccionada' => $empresaId]);

        $empleados = Persona::where('activo', true)
            ->where('empleado', 1)
            ->orderBy('Nombre')
            ->orderBy('Paterno')
            ->orderBy('Materno')
            ->get()
            ->map(function($persona) {
                return [
                    'id_persona' => $persona->id_persona,
                    'nombre_completo' => $persona->nombre_completo,
                    'rfc' => $persona->rfc,
                    'empleado' => $persona->empleado,
                ];
            });

        $cuentasFondeadoras = Cuenta::where('id_empresa', $empresaId)
            ->fondeadorasAsignadas($empresaId)
            ->where('en_uso', true)
            ->where('fondeo_c', 1)
            ->orderBy('nombre_cuenta')
            ->get()
            ->map(function($cuenta) {
                return [
                    'id_cuenta' => $cuenta->id_cuenta,
                    'nombre_cuenta' => $cuenta->nombre_cuenta,
                    'saldo' => (float) ($cuenta->saldo_inicial ?? 0)
                ];
            });

        $cuentasNomina = Cuenta::where('id_empresa', $empresaId)
            ->where('en_uso', true)
            ->orderBy('nombre_cuenta')
            ->get()
            ->map(function($cuenta) {
                return [
                    'id_cuenta' => $cuenta->id_cuenta,
                    'nombre_cuenta' => $cuenta->nombre_cuenta,
                    'saldo_inicial' => (float) ($cuenta->saldo_inicial ?? 0)
                ];
            });

        $marcadores = Marcador::where('activo', true)
            ->orderBy('nombre_marcador')
            ->get()
            ->map(function($marcador) {
                return [
                    'id' => $marcador->id,
                    'nombre_marcador' => $marcador->nombre_marcador,
                    'descripcion' => $marcador->descripcion
                ];
            });

        // Antes: si no había empleados se redirigía al índice. Ahora se muestra
        // siempre el formulario y la vista indica que no hay empleados.
        return Inertia::render('Movimientos/NominaCreate', [
            'empresa_id' => (int) $empresaId,
            'empleados' => $empleados,
            'cuentas_fondeadoras' => $cuentasFondeadoras,
            'cuentas_nomina' => $cuentasNomina,
            'marcadores' => $marcadores,
            'sin_empleados' => $empleados->isEmpty(),
        ]);
    }

    // ============================================
    // 📄 STORE NÓMINA
    // ============================================
    public function storeNomina(Request $request)
    {
        // 🔥 Este endpoint SOLO se llama vía axios (ver NominaCreate.vue), nunca
        // por navegación normal. Todas las respuestas deben ser JSON: un
        // redirect() aquí lo sigue el navegador de forma transparente y el
        // frontend termina mostrando "éxito" aunque nada se haya guardado
        // (fue exactamente el bug reportado: "no registra nada, no hay logs").
        if (!Gate::allows('crear-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para crear movimientos'
            ], 403);
        }

        if ($request->filled('id_cuenta_fondeadora') && !$this->fondeadoraPermitida($request->id_cuenta_fondeadora)) {
            return response()->json(['success' => false, 'message' => $this->mensajeSinFondeadoras()], 403);
        }

        $validator = Validator::make($request->all(), [
            'fecha_pago' => 'required|date',
            'id_cuenta_fondeadora' => 'required|exists:cuentas,id_cuenta',
            'tipo_poliza' => 'required|in:EGRESO',
            'id_marcador' => 'nullable|exists:marcadores,id',
            // El formulario envía "observacion_general" (antes se validaba
            // "descripcion", un campo que la vista nunca llegó a enviar, por
            // lo que la validación fallaba SIEMPRE).
            'observacion_general' => 'nullable|string|max:255',
            'id_cuenta' => 'required|exists:cuentas,id_cuenta',
            'empleados' => 'required|array|min:1',
            'empleados.*.id_persona' => 'required|exists:personas,id_persona',
            'empleados.*.monto' => 'required|numeric|min:0.01',
            'empleados.*.id_cuenta_fondeadora' => 'required|exists:cuentas,id_cuenta',
            'empleados.*.observacion' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $empresaId = session('empresa_movimientos');

            if (!$empresaId) {
                $empresa = auth()->user()->empresas()->first();
                $empresaId = $empresa ? $empresa->id : null;
            }

            if (!$empresaId) {
                throw new \Exception('No se pudo determinar la empresa para la póliza');
            }

            $fechaPago = $request->fecha_pago;
            $descripcion = $request->observacion_general ?? 'Pago de nómina quincenal';
            $idMarcador = $request->id_marcador;
            $idCuenta = $request->id_cuenta;

            $totalNomina = 0;
            $totalEmpleados = 0;

            foreach ($request->empleados as $empleadoData) {
                $persona = Persona::find($empleadoData['id_persona']);
                if (!$persona || !$persona->empleado) {
                    throw new \Exception('La persona ' . ($persona ? $persona->nombre_completo : 'desconocida') . ' no es un empleado');
                }

                $monto = round($empleadoData['monto'], 2);
                $totalNomina += $monto;
                $totalEmpleados++;

                $folio = 'NOM-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

                // Evitar la nota duplicada "X - X" cuando la observación del
                // empleado es igual a la observación general (caso normal,
                // ya que se replica automáticamente en el formulario).
                $observacionEmpleado = trim($empleadoData['observacion'] ?? '');
                if ($observacionEmpleado === '' || $observacionEmpleado === trim($descripcion)) {
                    $nota = $descripcion;
                } else {
                    $nota = $descripcion . ' - ' . $observacionEmpleado;
                }

                $poliza = Poliza::create([
                    'id_empresa' => $empresaId,
                    'tipo_poliza' => 'EGRESO',
                    'fecha_poliza' => $fechaPago,
                    'fecha_vencimiento' => null,
                    'categoria' => 'NO_FISCAL',
                    'estatus' => 'CAPTURADO',
                    'es_por_pagar' => false,
                    'referencia' => $folio,
                    'nota' => $nota,
                    'id_persona' => $empleadoData['id_persona'],
                    'id_usuario_creador' => auth()->id(),
                    'id_usuario_autorizador' => null,
                    'id_usuario_revisor' => null,
                    'fecha_autorizacion' => null,
                    'fecha_revision' => null,
                    'id_marcador' => $idMarcador,
                    'fecha_creacion' => now(),
                    'ruta_pdf' => null,
                    'nombre_pdf' => null,
                    'nombre_xml' => null,
                ]);

                MovimientoPoliza::create([
                    'id_poliza' => $poliza->id,
                    'id_cuenta' => $idCuenta,
                    'id_caja_fondo' => $empleadoData['id_cuenta_fondeadora'],
                    'id_tipo_iva' => null,
                    'monto' => -$monto,
                    'monto_base' => -$monto,
                    'monto_iva' => 0,
                    'monto_traspaso' => null,
                    'monto_iva_cero' => 0,
                    'monto_iva_dieciseis' => 0,
                    'iva_dieciseis' => 0,
                ]);

                $this->actualizarSaldoCuenta($empleadoData['id_cuenta_fondeadora']);
                $this->actualizarSaldoCuenta($idCuenta);
            }

            DB::commit();

            \Log::info('✅ Nómina generada:', [
                'empresa_id' => $empresaId,
                'total_empleados' => $totalEmpleados,
                'total_nomina' => $totalNomina,
                'fecha_pago' => $fechaPago,
                'usuario' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Nómina generada exitosamente. {$totalEmpleados} pólizas creadas por un total de $" . number_format($totalNomina, 2),
                'data' => [
                    'total_empleados' => $totalEmpleados,
                    'total_nomina' => $totalNomina,
                    'fecha_pago' => \Carbon\Carbon::parse($fechaPago)->toDateString(),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('❌ Error al generar pólizas de nómina:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar las pólizas de nómina: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // ✅ REVISAR PÓLIZA
    // ============================================
    public function revisarPoliza(Request $request, $id){
        if (!Gate::allows('revisar-polizas')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para revisar pólizas'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $poliza = Poliza::findOrFail($id);
            
            if ($poliza->estatus !== 'CAPTURADO' && $poliza->estatus !== 'PENDIENTE') {
                return response()->json([
                    'success' => false,
                    'message' => 'La póliza debe estar en estado CAPTURADO o PENDIENTE para ser revisada'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'comentario' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $poliza->estatus = 'REVISADO';
            $poliza->id_usuario_revisor = auth()->id();
            $poliza->fecha_revision = now();
            $poliza->comentario_revision = $request->comentario;
            $poliza->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Póliza revisada exitosamente',
                'data' => [
                    'estatus' => $poliza->estatus,
                    'estatus_texto' => $this->getEstatusTexto($poliza->estatus),
                    'fecha_revision' => $poliza->fecha_revision?->format('d/m/Y H:i')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al revisar la póliza: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // ✅ AUTORIZAR PÓLIZA
    // ============================================
    public function autorizarPoliza(Request $request, $id){
        if (!Gate::allows('autorizar-polizas')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para autorizar pólizas'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $poliza = Poliza::findOrFail($id);
            
            if ($poliza->estatus !== 'REVISADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'La póliza debe estar en estado REVISADO para ser autorizada'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'comentario' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $poliza->estatus = 'AUTORIZADO';
            $poliza->id_usuario_autorizador = auth()->id();
            $poliza->fecha_autorizacion = now();
            $poliza->comentario_autorizacion = $request->comentario;
            $poliza->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Póliza autorizada exitosamente',
                'data' => [
                    'estatus' => $poliza->estatus,
                    'estatus_texto' => $this->getEstatusTexto($poliza->estatus),
                    'fecha_autorizacion' => $poliza->fecha_autorizacion?->format('d/m/Y H:i')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al autorizar la póliza: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // ❌ RECHAZAR PÓLIZA
    // ============================================
    public function rechazarPoliza(Request $request, $id)
    {
        if (!Gate::allows('editar-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para rechazar pólizas'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $poliza = Poliza::findOrFail($id);
            
            if (!in_array($poliza->estatus, ['REVISADO', 'AUTORIZADO'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'La póliza debe estar en estado REVISADO o AUTORIZADO para ser rechazada'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'motivo' => 'required|string|min:10|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $nuevoEstatus = $poliza->es_por_pagar ? 'PENDIENTE' : 'CAPTURADO';
            
            $poliza->estatus = $nuevoEstatus;
            $poliza->id_usuario_revisor = null;
            $poliza->fecha_revision = null;
            $poliza->id_usuario_autorizador = null;
            $poliza->fecha_autorizacion = null;
            $poliza->comentario_revision = null;
            $poliza->comentario_autorizacion = null;
            $poliza->motivo_rechazo = $request->motivo;
            $poliza->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Póliza rechazada. Regresa a estado ' . $this->getEstatusTexto($nuevoEstatus),
                'data' => [
                    'estatus' => $poliza->estatus,
                    'estatus_texto' => $this->getEstatusTexto($poliza->estatus),
                    'motivo_rechazo' => $poliza->motivo_rechazo
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar la póliza: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 🔄 REVERTIR REVISIÓN
    // ============================================
    public function revertirRevision(Request $request, $id)
    {
        if (!Gate::allows('revisar-polizas')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para revertir revisiones'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $poliza = Poliza::findOrFail($id);
            
            if ($poliza->estatus !== 'REVISADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'La póliza debe estar en estado REVISADO para revertir'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'motivo' => 'required|string|min:10|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $nuevoEstatus = $poliza->es_por_pagar ? 'PENDIENTE' : 'CAPTURADO';
            
            $poliza->estatus = $nuevoEstatus;
            $poliza->id_usuario_revisor = null;
            $poliza->fecha_revision = null;
            $poliza->comentario_revision = null;
            $poliza->motivo_rechazo = $request->motivo;
            $poliza->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Revisión revertida. La póliza regresa a estado ' . $this->getEstatusTexto($nuevoEstatus),
                'data' => [
                    'estatus' => $poliza->estatus,
                    'estatus_texto' => $this->getEstatusTexto($poliza->estatus),
                    'motivo_rechazo' => $poliza->motivo_rechazo
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al revertir la revisión: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 📄 OBTENER DOCUMENTO FISCAL
    // ============================================
    public function getDocumentoFiscal($id, $tipo)
    {
        if (!Gate::allows('ver-movimientos')) {
            abort(403, 'No tienes permiso para ver documentos fiscales');
        }

        $poliza = Poliza::find($id);
        
        if (!$poliza) {
            abort(404, 'Póliza no encontrada');
        }

        if ($poliza->categoria !== 'FISCAL') {
            abort(404, 'Esta póliza no es fiscal');
        }

        $ruta = $tipo === 'pdf' ? $poliza->ruta_pdf : $poliza->ruta_xml;
        $nombre = $tipo === 'pdf' ? $poliza->nombre_pdf : $poliza->nombre_xml;

        if (empty($ruta) || !Storage::disk('public')->exists($ruta)) {
            abort(404, 'Documento no encontrado');
        }

        return Storage::disk('public')->response($ruta, $nombre ?? ($tipo === 'pdf' ? 'factura.pdf' : 'factura.xml'));
    }

    // ============================================
    // 🔍 BUSCAR CUENTAS (API)
    // ============================================
    public function buscarCuentas(Request $request)
    {
        if (!Gate::allows('ver-movimientos')) {
            return response()->json([], 403);
        }

        $search = $request->get('q', '');
        $empresaId = session('empresa_movimientos');

        $cuentas = Cuenta::where(function($query) use ($search) {
                $query->where('nombre_cuenta', 'LIKE', "%{$search}%")
                      ->orWhere('codigo_cuenta', 'LIKE', "%{$search}%");
            })
            ->where('id_empresa', $empresaId)
            ->where('en_uso', true)
            ->limit(10)
            ->get()
            ->map(function($cuenta) {
                return [
                    'id_cuenta' => $cuenta->id_cuenta,
                    'nombre_cuenta' => $cuenta->nombre_cuenta,
                    'saldo_inicial' => (float) ($cuenta->saldo_inicial ?? 0)
                ];
            });

        return response()->json($cuentas);
    }

    // ============================================
    // 📝 OBTENER EMPLEADOS PARA NÓMINA (API)
    // ============================================
    public function getEmpleadosNomina(Request $request)
    {
        if (!Gate::allows('ver-movimientos')) {
            return response()->json([], 403);
        }

        $search = $request->get('q', '');

        $empleados = Persona::where('activo', true)
            ->where('empleado', 1)
            ->where(function($query) use ($search) {
                if (!empty($search)) {
                    $query->where('Nombre', 'LIKE', "%{$search}%")
                          ->orWhere('Paterno', 'LIKE', "%{$search}%")
                          ->orWhere('Materno', 'LIKE', "%{$search}%")
                          ->orWhereRaw("CONCAT(Nombre, ' ', Paterno, ' ', Materno) LIKE ?", ["%{$search}%"]);
                }
            })
            ->orderBy('Nombre')
            ->orderBy('Paterno')
            ->orderBy('Materno')
            ->limit(20)
            ->get()
            ->map(function($persona) {
                return [
                    'id_persona' => $persona->id_persona,
                    'nombre_completo' => $persona->nombre_completo,
                    'rfc' => $persona->rfc,
                    'empleado' => $persona->empleado,
                ];
            });

        return response()->json($empleados);
    }

    // ============================================
    // 📝 CALCULAR FECHA DE PAGO POR QUINCENA (API)
    // ============================================
    public function calcularFechaPago(Request $request)
    {
        if (!Gate::allows('ver-movimientos')) {
            return response()->json(['error' => 'No tienes permiso'], 403);
        }

        $quincena = $request->get('quincena');
        $fechaReferencia = $request->get('fecha', now()->toDateString());

        if (!$quincena) {
            return response()->json(['fecha_pago' => null]);
        }

        $fecha = new \DateTime($fechaReferencia);
        $year = (int) $fecha->format('Y');
        $month = (int) $fecha->format('m');
        $day = (int) $fecha->format('d');

        $fechaPago = null;

        if ($quincena == '1') {
            $fechaPago = new \DateTime("{$year}-{$month}-15");
            if ($day > 15) {
                $fechaPago->modify('+1 month');
            }
        } else {
            $ultimoDia = (int) (new \DateTime("{$year}-{$month}-01"))->format('t');
            $fechaPago = new \DateTime("{$year}-{$month}-{$ultimoDia}");
            if ($day > $ultimoDia) {
                $fechaPago->modify('+1 month');
                $ultimoDia = (int) (new \DateTime($fechaPago->format('Y-m-01')))->format('t');
                $fechaPago = new \DateTime($fechaPago->format('Y-m-') . $ultimoDia);
            }
        }

        return response()->json([
            'fecha_pago' => $fechaPago->format('Y-m-d'),
            'quincena' => $quincena
        ]);
    }

    // ============================================
    // 📊 EXPORTAR EXCEL
    // ============================================
    public function exportExcel(Request $request)
    {
        if (!Gate::allows('ver-movimientos')) {
            return redirect()->back()->with('error', 'No tienes permiso para exportar movimientos');
        }

        try {
            $empresaId = $request->get('empresa_id', session('empresa_movimientos'));
            $vista = $request->get('vista', 'normal');

            if (!$empresaId) {
                return redirect()->back()->with('error', 'No se ha seleccionado una empresa');
            }

            $query = MovimientoPoliza::with([
                'poliza.persona',
                'poliza.usuarioCreador',
                'poliza.usuarioRevisor',
                'poliza.usuarioAutorizador',
                'cuenta' => function($q) {
                    $q->where('en_uso', true);
                },
                'cuentaFondeadora' => function($q) {
                    $q->where('en_uso', true);
                },
                'poliza.marcador',
                'poliza.abonos'
            ]);

            $query->whereHas('poliza', function($q) use ($empresaId) {
                $q->where('id_empresa', $empresaId);
            });

            if ($vista === 'diferidas') {
                // Igual que en pantalla: pendientes por defecto; "pagadas" = liquidadas.
                $verPagadas = $request->get('estado_deuda') === 'pagadas';
                $query->whereHas('poliza', function($q) use ($verPagadas) {
                    $q->where('es_por_pagar', true);
                    $verPagadas
                        ? $q->where('estatus', 'LIQUIDADO')
                        : $q->where('estatus', '!=', 'LIQUIDADO');
                });
            } elseif ($vista === 'pendientes') {
                $query->whereHas('poliza', function($q) {
                    $q->whereIn('estatus', ['CAPTURADO', 'REVISADO']);
                });
            } elseif ($vista === 'autorizadas') {
                $query->whereHas('poliza', function($q) {
                    $q->where('estatus', 'AUTORIZADO');
                });
            } else {
                $query->whereHas('poliza', function($q) {
                    $q->where('es_por_pagar', false);
                });
            }

            if ($request->boolean('solo_fiscales')) {
                $query->whereHas('poliza', function($q) {
                    $q->where('categoria', 'FISCAL');
                });
            }

            if ($request->filled('fecha_desde')) {
                $query->whereHas('poliza', function($q) use ($request) {
                    $q->whereDate('fecha_poliza', '>=', $request->fecha_desde);
                });
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereHas('poliza', function($q) use ($request) {
                    $q->whereDate('fecha_poliza', '<=', $request->fecha_hasta);
                });
            }

            $query->whereHas('poliza', function($q) use ($request) {
                $this->aplicarFiltrosRegistroYFactura($q, $request);
            });

            if ($request->filled('referencia')) {
                $query->whereHas('poliza', function($q) use ($request) {
                    $q->where('folio', 'LIKE', '%' . $request->referencia . '%')
                      ->orWhere('referencia', 'LIKE', '%' . $request->referencia . '%');
                });
            }

            $movimientos = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            if ($vista === 'diferidas') {
                $headers = ['Vencimiento', 'Persona', 'Nota', 'Monto', 'Abonado', 'Saldo Pendiente', 'Usuario', 'Estatus'];
            } else {
                $headers = ['Referencia', 'Fecha Póliza', 'Tipo', 'Estatus', 'Persona', 'Cuenta', 'Cta. Fondeo', 'Nota', 'Monto', 'Usuario', 'PDF Fiscal'];
            }

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ];

            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $col++;
            }

            $lastCol = chr(64 + count($headers));
            $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($movimientos as $movimiento) {
                $abonos = $movimiento->poliza->abonos ?? collect();
                $totalAbonado = $abonos->sum('monto_abonado');
                $saldoPendiente = abs($movimiento->monto) - $totalAbonado;

                if ($vista === 'diferidas') {
                    $data = [
                        $this->formatFechaExcel($movimiento->poliza->fecha_vencimiento),
                        $movimiento->poliza->persona ? $movimiento->poliza->persona->nombre_completo : 'N/A',
                        $movimiento->poliza->nota ?? '—',
                        $movimiento->monto,
                        $totalAbonado,
                        $saldoPendiente,
                        $movimiento->poliza->usuarioCreador ? $movimiento->poliza->usuarioCreador->nombre_usuario : 'Sistema',
                        $this->getEstatusTexto($movimiento->poliza->estatus),
                    ];
                } else {
                    $tienePdf = ($movimiento->poliza->categoria === 'FISCAL' && !empty($movimiento->poliza->ruta_pdf)) ? 'Sí' : 'No';
                    $data = [
                        $movimiento->poliza->folio ?? '—',
                        $this->formatFechaExcel($movimiento->poliza->fecha_poliza),
                        $movimiento->poliza->tipo_poliza ?? '—',
                        $this->getEstatusTexto($movimiento->poliza->estatus),
                        $movimiento->poliza->persona ? $movimiento->poliza->persona->nombre_completo : 'N/A',
                        $movimiento->cuenta && $movimiento->cuenta->en_uso ? $movimiento->cuenta->nombre_cuenta : 'N/A',
                        $movimiento->cuentaFondeadora && $movimiento->cuentaFondeadora->en_uso ? $movimiento->cuentaFondeadora->nombre_cuenta : 'N/A',
                        $movimiento->poliza->nota ?? '—',
                        $movimiento->monto,
                        $movimiento->poliza->usuarioCreador ? $movimiento->poliza->usuarioCreador->nombre_usuario : 'Sistema',
                        $tienePdf
                    ];
                }

                $col = 'A';
                foreach ($data as $value) {
                    $sheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }

            $lastRow = $row - 1;
            $sheet->getStyle('A1:' . $lastCol . $lastRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);

            if ($vista === 'diferidas') {
                $moneyColumns = ['D', 'E', 'F'];
            } else {
                $moneyColumns = ['I'];
            }

            foreach ($moneyColumns as $col) {
                $sheet->getStyle($col . '2:' . $col . $lastRow)
                    ->getNumberFormat()
                    ->setFormatCode('$#,##0.00');
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'movimientos_' . date('Y-m-d_H-i-s') . '.xlsx';

            $tempPath = storage_path('app/temp/' . $filename);
            if (!is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0777, true);
            }
            $writer->save($tempPath);

            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al exportar Excel: ' . $e->getMessage());
        }
    }

    // ============================================
    // 📄 EXPORTAR PDF
    // ============================================
    public function exportPdf(Request $request)
    {
        if (!Gate::allows('ver-movimientos')) {
            return redirect()->back()->with('error', 'No tienes permiso para exportar movimientos');
        }

        try {
            $empresaId = $request->get('empresa_id', session('empresa_movimientos'));
            $vista = $request->get('vista', 'normal');

            if (!$empresaId) {
                return redirect()->back()->with('error', 'No se ha seleccionado una empresa');
            }

            $query = MovimientoPoliza::with([
                'poliza.persona',
                'poliza.usuarioCreador',
                'poliza.usuarioRevisor',
                'poliza.usuarioAutorizador',
                'cuenta' => function($q) {
                    $q->where('en_uso', true);
                },
                'cuentaFondeadora' => function($q) {
                    $q->where('en_uso', true);
                },
                'poliza.marcador',
                'poliza.abonos'
            ]);

            $query->whereHas('poliza', function($q) use ($empresaId) {
                $q->where('id_empresa', $empresaId);
            });

            if ($vista === 'diferidas') {
                // Igual que en pantalla: pendientes por defecto; "pagadas" = liquidadas.
                $verPagadas = $request->get('estado_deuda') === 'pagadas';
                $query->whereHas('poliza', function($q) use ($verPagadas) {
                    $q->where('es_por_pagar', true);
                    $verPagadas
                        ? $q->where('estatus', 'LIQUIDADO')
                        : $q->where('estatus', '!=', 'LIQUIDADO');
                });
            } elseif ($vista === 'pendientes') {
                $query->whereHas('poliza', function($q) {
                    $q->whereIn('estatus', ['CAPTURADO', 'REVISADO']);
                });
            } elseif ($vista === 'autorizadas') {
                $query->whereHas('poliza', function($q) {
                    $q->where('estatus', 'AUTORIZADO');
                });
            } else {
                $query->whereHas('poliza', function($q) {
                    $q->where('es_por_pagar', false);
                });
            }

            if ($request->boolean('solo_fiscales')) {
                $query->whereHas('poliza', function($q) {
                    $q->where('categoria', 'FISCAL');
                });
            }

            if ($request->filled('fecha_desde')) {
                $query->whereHas('poliza', function($q) use ($request) {
                    $q->whereDate('fecha_poliza', '>=', $request->fecha_desde);
                });
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereHas('poliza', function($q) use ($request) {
                    $q->whereDate('fecha_poliza', '<=', $request->fecha_hasta);
                });
            }

            $query->whereHas('poliza', function($q) use ($request) {
                $this->aplicarFiltrosRegistroYFactura($q, $request);
            });

            if ($request->filled('referencia')) {
                $query->whereHas('poliza', function($q) use ($request) {
                    $q->where('folio', 'LIKE', '%' . $request->referencia . '%')
                      ->orWhere('referencia', 'LIKE', '%' . $request->referencia . '%');
                });
            }

            $movimientos = $query->get();

            $data = [];
            foreach ($movimientos as $movimiento) {
                $abonos = $movimiento->poliza->abonos ?? collect();
                $totalAbonado = $abonos->sum('monto_abonado');
                $saldoPendiente = abs($movimiento->monto) - $totalAbonado;
                $tienePdf = ($movimiento->poliza->categoria === 'FISCAL' && !empty($movimiento->poliza->ruta_pdf)) ? 'Sí' : 'No';

                if ($vista === 'diferidas') {
                    $data[] = [
                        'vencimiento' => $this->formatFechaExcel($movimiento->poliza->fecha_vencimiento),
                        'persona' => $movimiento->poliza->persona ? $movimiento->poliza->persona->nombre_completo : 'N/A',
                        'nota' => $movimiento->poliza->nota ?? '—',
                        'monto' => $this->formatMonto($movimiento->monto),
                        'abonado' => $this->formatMonto($totalAbonado),
                        'saldo_pendiente' => $this->formatMonto($saldoPendiente),
                        'usuario' => $movimiento->poliza->usuarioCreador ? $movimiento->poliza->usuarioCreador->nombre_usuario : 'Sistema',
                        'estatus' => $this->getEstatusTexto($movimiento->poliza->estatus),
                    ];
                } else {
                    $data[] = [
                        'referencia' => $movimiento->poliza->folio ?? '—',
                        'fecha_poliza' => $this->formatFechaExcel($movimiento->poliza->fecha_poliza),
                        'tipo' => $movimiento->poliza->tipo_poliza ?? '—',
                        'estatus' => $this->getEstatusTexto($movimiento->poliza->estatus),
                        'persona' => $movimiento->poliza->persona ? $movimiento->poliza->persona->nombre_completo : 'N/A',
                        'cuenta' => $movimiento->cuenta && $movimiento->cuenta->en_uso ? $movimiento->cuenta->nombre_cuenta : 'N/A',
                        'cuenta_fondeadora' => $movimiento->cuentaFondeadora && $movimiento->cuentaFondeadora->en_uso ? $movimiento->cuentaFondeadora->nombre_cuenta : 'N/A',
                        'nota' => $movimiento->poliza->nota ?? '—',
                        'monto' => $this->formatMonto($movimiento->monto),
                        'usuario' => $movimiento->poliza->usuarioCreador ? $movimiento->poliza->usuarioCreador->nombre_usuario : 'Sistema',
                        'pdf_fiscal' => $tienePdf
                    ];
                }
            }

            $empresa = \App\Models\Empresa::find($empresaId);
            $nombreEmpresa = $empresa ? $empresa->nombre_empresa : 'Sin empresa';

            $pdf = Pdf::loadView('exports.movimientos_pdf', [
                'data' => $data,
                'vista' => $vista,
                'empresa' => $nombreEmpresa,
                'fecha_exportacion' => now()->format('d/m/Y H:i:s'),
                'filtros' => $request->all(),
                'es_diferidas' => $vista === 'diferidas'
            ]);

            $pdf->setPaper('A4', 'landscape');

            return $pdf->download('movimientos_' . date('Y-m-d_H-i-s') . '.pdf');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al exportar PDF: ' . $e->getMessage());
        }
    }

    // ============================================
    // 🔍 BUSCAR PERSONAS (API)
    // ============================================
    public function buscarPersonas(Request $request)
    {
        if (!Gate::allows('ver-movimientos')) {
            return response()->json([], 403);
        }

        $search = $request->get('q', '');
        $empresaId = session('empresa_movimientos');

        $personas = Persona::where('activo', true)
            ->where(function($query) use ($search) {
                if (!empty($search)) {
                    $query->where('Nombre', 'LIKE', "%{$search}%")
                        ->orWhere('Paterno', 'LIKE', "%{$search}%")
                        ->orWhere('Materno', 'LIKE', "%{$search}%")
                        ->orWhereRaw("CONCAT(Nombre, ' ', Paterno, ' ', Materno) LIKE ?", ["%{$search}%"]);
                }
            })
            ->orderBy('Nombre')
            ->orderBy('Paterno')
            ->orderBy('Materno')
            ->limit(20)
            ->get()
            ->map(function($persona) {
                return [
                    'id_persona' => $persona->id_persona,
                    'nombre_completo' => $persona->nombre_completo,
                    'rfc' => $persona->rfc,
                    'empleado' => $persona->empleado,
                ];
            });

        return response()->json($personas);
    }

    // ============================================
    // 💰 OBTENER SALDO DE CUENTA (API)
    // ============================================
    public function saldoCuenta(Request $request)
    {
        if (!Gate::allows('ver-movimientos')) {
            return response()->json(['error' => 'No tienes permiso'], 403);
        }

        $idCuenta = $request->get('id');
        if (!$idCuenta) {
            return response()->json(['error' => 'ID de cuenta requerido'], 400);
        }

        $cuenta = Cuenta::find($idCuenta);
        if (!$cuenta) {
            return response()->json(['error' => 'Cuenta no encontrada'], 404);
        }

        $saldo = $this->actualizarSaldoCuenta($idCuenta);

        return response()->json([
            'id_cuenta' => $cuenta->id_cuenta,
            'nombre_cuenta' => $cuenta->nombre_cuenta,
            'saldo' => (float) $saldo,
            'saldo_inicial' => (float) ($cuenta->saldo_inicial ?? 0)
        ]);
    }

    // ============================================
    // 📄 OBTENER ÚLTIMA PÓLIZA DE UNA PERSONA (API) - CORREGIDO
    // ============================================
    public function obtenerUltimaPoliza(Request $request)
    {
        if (!Gate::allows('ver-movimientos')) {
            return response()->json(['error' => 'No tienes permiso'], 403);
        }

        $idPersona = $request->get('id');
        
        // 🔥 OBTENER EMPRESA DE SESIÓN - PRIORIDAD CORRECTA
        $empresaId = session('empresa_movimientos') ?? session('empresa_seleccionada');
        
        if (!$empresaId) {
            // Si no hay empresa en sesión, obtener la primera empresa del usuario
            $empresa = auth()->user()->empresas()->first();
            $empresaId = $empresa ? $empresa->id : null;
        }
        
        if (!$idPersona) {
            return response()->json(['error' => 'ID de persona requerido'], 400);
        }

        if (!$empresaId) {
            return response()->json(['error' => 'No hay empresa seleccionada'], 400);
        }

        $persona = Persona::find($idPersona);
        if (!$persona) {
            return response()->json(['error' => 'Persona no encontrada'], 404);
        }

        // 🔥 IMPORTANTE: Filtrar por empresa y por persona
        $ultimaPoliza = Poliza::where('id_persona', $idPersona)
            ->where('id_empresa', $empresaId)  // 🔥 SOLO DE LA EMPRESA ACTUAL
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$ultimaPoliza) {
            return response()->json([
                'existe' => false,
                'persona_nombre' => $persona->nombre_completo,
                'mensaje' => 'No hay pólizas registradas para esta persona en esta empresa'
            ]);
        }

        $movimiento = MovimientoPoliza::where('id_poliza', $ultimaPoliza->id)->first();

        if (!$movimiento) {
            return response()->json([
                'existe' => false,
                'persona_nombre' => $persona->nombre_completo,
                'mensaje' => 'La póliza no tiene movimiento asociado'
            ]);
        }

        // 🔥 VERIFICAR QUE LA CUENTA FONDEADORA EXISTA Y ESTÉ ACTIVA EN LA EMPRESA ACTUAL
        $cuentaFondeadoraId = $movimiento->id_caja_fondo;
        $cuentaFondeadoraValida = false;
        
        if ($cuentaFondeadoraId) {
            $cuentaFondeadoraValida = Cuenta::where('id_cuenta', $cuentaFondeadoraId)
                ->where('id_empresa', $empresaId)
                ->where('en_uso', true)
                ->fondeadorasAsignadas($empresaId)
                ->exists();
        }

        // 🔥 VERIFICAR QUE LA CUENTA EXISTA Y ESTÉ ACTIVA EN LA EMPRESA ACTUAL
        $cuentaId = $movimiento->id_cuenta;
        $cuentaValida = false;
        
        if ($cuentaId) {
            $cuentaValida = Cuenta::where('id_cuenta', $cuentaId)
                ->where('id_empresa', $empresaId)
                ->where('en_uso', true)
                ->exists();
        }

        return response()->json([
            'existe' => true,
            'persona_nombre' => $persona->nombre_completo,
            'id_cuenta' => $cuentaValida ? $movimiento->id_cuenta : null,
            'es_por_pagar' => (bool) $ultimaPoliza->es_por_pagar,
            'fecha_vencimiento' => $ultimaPoliza->fecha_vencimiento ? $ultimaPoliza->fecha_vencimiento->format('Y-m-d') : null,
            'es_fiscal' => $ultimaPoliza->categoria === 'FISCAL',
            'id_marcador' => $ultimaPoliza->id_marcador,
            'id_tipo_iva' => $movimiento->id_tipo_iva,
            'concepto' => $ultimaPoliza->nota ?? 'Servicio general',
            'fecha_factura' => $ultimaPoliza->fecha_factura ? $ultimaPoliza->fecha_factura->format('Y-m-d') : null,
            'numero_factura' => $ultimaPoliza->numero_factura,
            'nota' => $ultimaPoliza->nota,
            'referencia' => $ultimaPoliza->referencia,
            // 🔥 DEVOLVER SOLO SI LA CUENTA FONDEADORA EXISTE EN LA EMPRESA ACTUAL
            'id_cuenta_fondeadora' => $cuentaFondeadoraValida ? $movimiento->id_caja_fondo : null,
            'cuenta_fondeadora_valida' => $cuentaFondeadoraValida,
            'cuenta_valida' => $cuentaValida,
        ]);
    }

    // ============================================
    // 📄 EXPORTAR PÓLIZA A PDF
    // ============================================
    public function exportPdfPoliza($id)
    {
        if (!Gate::allows('ver-movimientos')) {
            abort(403, 'No tienes permiso para ver esta póliza');
        }

        $movimiento = MovimientoPoliza::with([
            'poliza.persona',
            'poliza.usuarioCreador',
            'poliza.usuarioRevisor',
            'poliza.usuarioAutorizador',
            'poliza.marcador',
            'cuenta' => function($q) {
                $q->where('en_uso', true);
            },
            'cuentaFondeadora' => function($q) {
                $q->where('en_uso', true);
            },
            'poliza.abonos'
        ])->find($id);

        if (!$movimiento) {
            abort(404, 'Movimiento no encontrado');
        }

        $esTraspaso = $movimiento->poliza->tipo_poliza === 'TRASPASO';
        $abonos = $movimiento->poliza->abonos ?? collect();
        $totalAbonado = $abonos->sum('monto_abonado');

        // Para traspasos, `$movimiento` es sólo UNA de las dos patas (origen o
        // destino) — antes se usaba su propio `cuenta`/`cuentaFondeadora`, que
        // sólo tiene sentido para INGRESO/EGRESO y dejaba "Cuenta Origen" o
        // "Cuenta Destino" vacía según qué pata se hubiera cargado. Aquí se
        // buscan ambas patas de la póliza para resolver origen (monto < 0) y
        // destino (monto > 0) sin importar cuál se abrió.
        $cuentaOrigenNombre = null;
        $cuentaDestinoNombre = null;

        if ($esTraspaso) {
            $movimientosTraspaso = MovimientoPoliza::with(['cuenta' => function ($q) {
                $q->where('en_uso', true);
            }])->where('id_poliza', $movimiento->id_poliza)->get();

            $movOrigen = $movimientosTraspaso->firstWhere('monto', '<', 0);
            $movDestino = $movimientosTraspaso->firstWhere('monto', '>', 0);

            $cuentaOrigenNombre = $movOrigen?->cuenta?->nombre_cuenta;
            $cuentaDestinoNombre = $movDestino?->cuenta?->nombre_cuenta;

            $montoTraspaso = $movOrigen?->monto_traspaso ?? abs($movOrigen?->monto ?? $movimiento->monto);
            $saldoPendiente = $montoTraspaso - $totalAbonado;
            $montoMostrar = $montoTraspaso;
        } else {
            $saldoPendiente = abs($movimiento->monto) - $totalAbonado;
            $montoMostrar = $movimiento->monto;
        }

        $data = [
            'movimiento' => $movimiento,
            'esTraspaso' => $esTraspaso,
            'cuentaOrigenNombre' => $cuentaOrigenNombre,
            'cuentaDestinoNombre' => $cuentaDestinoNombre,
            'montoMostrar' => $montoMostrar,
            // 'abonos' faltaba aquí: la plantilla siempre hizo `$abonos->count()`
            // y `@foreach($abonos as $abono)`, así que descargar el PDF de
            // cualquier póliza diferida (por pagar) con abonos tronaba con
            // "Call to a member function count() on null".
            'abonos' => $abonos,
            'totalAbonado' => $totalAbonado,
            'saldoPendiente' => $saldoPendiente,
            'empresa' => $movimiento->poliza->empresa,
            'fecha_exportacion' => now()->format('d/m/Y H:i:s')
        ];

        $pdf = Pdf::loadView('exports.poliza_pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('poliza_' . ($movimiento->poliza->folio ?? 'sin_folio') . '.pdf');
    }

    // ============================================
    // 🔄 CERRAR PÓLIZA - CON REDIRECCIÓN AL INDEX
    // ============================================
    public function cerrarPoliza(Request $request, $id)
    {
        if (!Gate::allows('editar-movimientos')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para cerrar pólizas'
                ], 403);
            }
            return redirect()->route('movimientos.index')
                ->with('error', 'No tienes permiso para cerrar pólizas');
        }

        try {
            DB::beginTransaction();

            $poliza = Poliza::findOrFail($id);
            
            // Verificar que la póliza pueda ser cerrada
            if (in_array($poliza->estatus, ['LIQUIDADO', 'AUTORIZADO'])) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede cerrar una póliza en estado ' . $this->getEstatusTexto($poliza->estatus)
                    ], 422);
                }
                return redirect()->route('movimientos.index')
                    ->with('error', 'No se puede cerrar una póliza en estado ' . $this->getEstatusTexto($poliza->estatus));
            }

            // Validar motivo de cierre
            $validator = Validator::make($request->all(), [
                'motivo' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }
                return redirect()->back()
                    ->with('error', 'Error de validación: ' . $validator->errors()->first())
                    ->withInput();
            }

            // ============================================================
            // 🔥 MODIFICACIÓN: NO CAMBIAMOS EL ESTATUS, SOLO REGISTRAMOS
            // ============================================================
            // Guardamos el estatus actual antes de cualquier cambio
            $estatusAnterior = $poliza->estatus;
            
            // Solo registramos que se intentó cerrar, pero NO cambiamos el estatus
            $poliza->motivo_cierre = $request->motivo ?? 'Cierre manual de póliza';
            $poliza->id_usuario_cierre = auth()->id();
            $poliza->fecha_cierre = now();
            // ⚠️ NO cambiamos $poliza->estatus
            
            $poliza->save();

            DB::commit();

            // Mensaje de éxito
            $mensaje = '✅ Póliza ' . ($poliza->folio ?? 'sin folio') . ' marcada para cierre exitosamente.';
            $mensaje .= ' Estatus actual: ' . $this->getEstatusTexto($estatusAnterior);
            if ($request->motivo) {
                $mensaje .= ' Motivo: ' . $request->motivo;
            }

            // 🔥 REDIRIGIR AL INDEX CON MENSAJE DE ÉXITO
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'data' => [
                        'estatus' => $poliza->estatus,
                        'estatus_texto' => $this->getEstatusTexto($poliza->estatus),
                        'fecha_cierre' => $poliza->fecha_cierre?->format('d/m/Y H:i')
                    ]
                ]);
            }

            // 🔥 REDIRECCIÓN AL INDEX CON LA VISTA ACTUAL
            $vista = $request->input('vista', 'normal');
            $empresaId = $request->input('empresa_id', session('empresa_movimientos'));
            
            return redirect()->route('movimientos.index', [
                'vista' => $vista,
                'empresa_id' => $empresaId
            ])->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al cerrar la póliza:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al cerrar la póliza: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Error al cerrar la póliza: ' . $e->getMessage());
        }
    }

    // ============================================
    // 📄 REABRIR PÓLIZA - CON REDIRECCIÓN AL INDEX
    // ============================================
    public function reabrirPoliza(Request $request, $id){
        if (!Gate::allows('editar-movimientos')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para reabrir pólizas'
                ], 403);
            }
            return redirect()->route('movimientos.index')
                ->with('error', 'No tienes permiso para reabrir pólizas');
        }

        try {
            DB::beginTransaction();

            $poliza = Poliza::findOrFail($id);
            
            // Verificar que la póliza esté cerrada
            if ($poliza->estatus !== 'CERRADO') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo se pueden reabrir pólizas en estado CERRADO'
                    ], 422);
                }
                return redirect()->route('movimientos.index')
                    ->with('error', 'Solo se pueden reabrir pólizas en estado CERRADO');
            }

            // Validar motivo de reapertura
            $validator = Validator::make($request->all(), [
                'motivo' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }
                return redirect()->back()
                    ->with('error', 'Error de validación: ' . $validator->errors()->first())
                    ->withInput();
            }

            // Reabrir la póliza
            $nuevoEstatus = $poliza->es_por_pagar ? 'PENDIENTE' : 'CAPTURADO';
            
            $poliza->estatus = $nuevoEstatus;
            $poliza->fecha_cierre = null;
            $poliza->motivo_cierre = null;
            $poliza->id_usuario_cierre = null;
            $poliza->motivo_rechazo = $request->motivo ?? 'Póliza reabierta';
            $poliza->save();

            DB::commit();

            $mensaje = '✅ Póliza ' . ($poliza->folio ?? 'sin folio') . ' reabierta exitosamente. Regresa a estado ' . $this->getEstatusTexto($nuevoEstatus);
            if ($request->motivo) {
                $mensaje .= ' Motivo: ' . $request->motivo;
            }

            // 🔥 REDIRIGIR AL INDEX CON MENSAJE DE ÉXITO
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'data' => [
                        'estatus' => $poliza->estatus,
                        'estatus_texto' => $this->getEstatusTexto($poliza->estatus)
                    ]
                ]);
            }

            $vista = $request->input('vista', 'normal');
            $empresaId = $request->input('empresa_id', session('empresa_movimientos'));
            
            return redirect()->route('movimientos.index', [
                'vista' => $vista,
                'empresa_id' => $empresaId
            ])->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al reabrir la póliza:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al reabrir la póliza: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Error al reabrir la póliza: ' . $e->getMessage());
        }
    }

    // ============================================
    // 📄 OBTENER DETALLE DE PÓLIZA PARA SHOW (API)
    // ============================================
    public function getDetallePoliza($id)
    {
        if (!Gate::allows('ver-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para ver esta póliza'
            ], 403);
        }

        $movimiento = MovimientoPoliza::with([
            'poliza.persona',
            'poliza.usuarioCreador',
            'poliza.usuarioRevisor',
            'poliza.usuarioAutorizador',
            'poliza.marcador',
            'cuenta' => function($q) {
                $q->where('en_uso', true);
            },
            'cuentaFondeadora' => function($q) {
                $q->where('en_uso', true);
            },
            'poliza.abonos'
        ])->find($id);

        if (!$movimiento) {
            return response()->json([
                'success' => false,
                'message' => 'Movimiento no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $movimiento
        ]);
    }

    // ============================================
    // 📊 OBTENER ESTADÍSTICAS DE LA PÓLIZA
    // ============================================
    public function getEstadisticasPoliza($id)
    {
        if (!Gate::allows('ver-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso'
            ], 403);
        }

        $movimiento = MovimientoPoliza::with(['poliza.abonos'])->find($id);

        if (!$movimiento) {
            return response()->json([
                'success' => false,
                'message' => 'Movimiento no encontrado'
            ], 404);
        }

        $abonos = $movimiento->poliza->abonos ?? collect();
        $totalAbonado = $abonos->sum('monto_abonado');
        $montoTotal = abs($movimiento->monto);
        $saldoPendiente = $montoTotal - $totalAbonado;
        $porcentajeAbonado = $montoTotal > 0 ? ($totalAbonado / $montoTotal) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'monto_total' => $montoTotal,
                'total_abonado' => $totalAbonado,
                'saldo_pendiente' => $saldoPendiente,
                'porcentaje_abonado' => round($porcentajeAbonado, 2),
                'numero_abonos' => $abonos->count(),
                'ultimo_abono' => $abonos->last(),
                'estatus' => $movimiento->poliza->estatus
            ]
        ]);
    }

    // ============================================
    // 📝 OBTENER HISTORIAL DE CAMBIOS DE LA PÓLIZA
    // ============================================
    public function getHistorialPoliza($id)
    {
        if (!Gate::allows('ver-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso'
            ], 403);
        }

        $poliza = Poliza::with([
            'usuarioCreador',
            'usuarioRevisor',
            'usuarioAutorizador'
        ])->find($id);

        if (!$poliza) {
            return response()->json([
                'success' => false,
                'message' => 'Póliza no encontrada'
            ], 404);
        }

        $historial = [];

        $historial[] = [
            'fecha' => $poliza->created_at?->format('d/m/Y H:i'),
            'evento' => 'CREACIÓN',
            'descripcion' => 'Póliza creada por ' . ($poliza->usuarioCreador?->nombre_completo ?? 'Sistema'),
            'usuario' => $poliza->usuarioCreador?->nombre_completo ?? 'Sistema',
            'estatus' => $poliza->es_por_pagar ? 'PENDIENTE' : 'CAPTURADO'
        ];

        if ($poliza->fecha_revision && $poliza->usuarioRevisor) {
            $historial[] = [
                'fecha' => $poliza->fecha_revision?->format('d/m/Y H:i'),
                'evento' => 'REVISIÓN',
                'descripcion' => 'Póliza revisada por ' . ($poliza->usuarioRevisor?->nombre_completo ?? 'Sistema'),
                'usuario' => $poliza->usuarioRevisor?->nombre_completo ?? 'Sistema',
                'estatus' => 'REVISADO',
                'comentario' => $poliza->comentario_revision
            ];
        }

        if ($poliza->fecha_autorizacion && $poliza->usuarioAutorizador) {
            $historial[] = [
                'fecha' => $poliza->fecha_autorizacion?->format('d/m/Y H:i'),
                'evento' => 'AUTORIZACIÓN',
                'descripcion' => 'Póliza autorizada por ' . ($poliza->usuarioAutorizador?->nombre_completo ?? 'Sistema'),
                'usuario' => $poliza->usuarioAutorizador?->nombre_completo ?? 'Sistema',
                'estatus' => 'AUTORIZADO',
                'comentario' => $poliza->comentario_autorizacion
            ];
        }

        if ($poliza->fecha_cierre) {
            $historial[] = [
                'fecha' => $poliza->fecha_cierre?->format('d/m/Y H:i'),
                'evento' => 'CIERRE',
                'descripcion' => 'Póliza cerrada',
                'usuario' => $poliza->usuarioCierre?->nombre_completo ?? 'Sistema',
                'estatus' => 'CERRADO',
                'motivo' => $poliza->motivo_cierre
            ];
        }

        if ($poliza->motivo_rechazo) {
            $historial[] = [
                'fecha' => $poliza->updated_at?->format('d/m/Y H:i'),
                'evento' => 'RECHAZO',
                'descripcion' => 'Póliza rechazada',
                'usuario' => auth()->user()?->nombre_completo ?? 'Sistema',
                'estatus' => 'RECHAZADO',
                'motivo' => $poliza->motivo_rechazo
            ];
        }

        usort($historial, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });

        return response()->json([
            'success' => true,
            'data' => $historial
        ]);
    }

    // ============================================
    // 💰 STORE LIQUIDACIÓN - LIQUIDAR PÓLIZA DIFERIDA COMPLETAMENTE
    // ============================================
    public function storeLiquidacion(Request $request)
    {
        \Log::info('=== INICIO storeLiquidacion ===');
        \Log::info('Datos recibidos:', $request->all());

        if (!Gate::allows('editar-movimientos')) {
            \Log::warning('Permiso denegado para liquidar pólizas');
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para liquidar pólizas'
            ], 403);
        }

        $movGuard = MovimientoPoliza::with('poliza')->find($request->id_movimiento);
        if ($movGuard && $movGuard->poliza && $this->sinFondeadorasEnEmpresa($movGuard->poliza->id_empresa)) {
            return response()->json(['success' => false, 'message' => $this->mensajeSinFondeadoras()], 403);
        }

        $validator = Validator::make($request->all(), [
            'id_movimiento' => 'required|exists:movimientos_poliza,id',
        ]);

        if ($validator->fails()) {
            \Log::error('Validación fallida:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos: ' . $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();
            \Log::info('Transacción iniciada');

            $movimientoOriginal = MovimientoPoliza::with(['poliza', 'cuenta', 'cuentaFondeadora'])
                ->find($request->id_movimiento);
            
            if (!$movimientoOriginal) {
                \Log::error('Movimiento original no encontrado');
                return response()->json([
                    'success' => false,
                    'message' => 'Movimiento original no encontrado'
                ], 404);
            }

            $polizaOriginal = $movimientoOriginal->poliza;
            
            if (!$polizaOriginal) {
                \Log::error('Póliza original no encontrada');
                return response()->json([
                    'success' => false,
                    'message' => 'Póliza original no encontrada'
                ], 404);
            }

            if (in_array($polizaOriginal->estatus, ['LIQUIDADO'])) {
                \Log::warning('Póliza ya liquidada:', ['estatus' => $polizaOriginal->estatus]);
                return response()->json([
                    'success' => false,
                    'message' => 'Esta póliza ya está liquidada'
                ], 400);
            }

            $totalAbonado = AbonoPoliza::where('id_poliza', $polizaOriginal->id)->sum('monto_abonado');
            
            $esTraspaso = $polizaOriginal->tipo_poliza === 'TRASPASO';
            $montoTotal = $esTraspaso 
                ? abs($movimientoOriginal->monto_traspaso ?? $movimientoOriginal->monto)
                : abs($movimientoOriginal->monto);
            
            $saldoPendiente = round($montoTotal - $totalAbonado, 2);
            
            if ($saldoPendiente <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta póliza ya está completamente pagada/cobrada'
                ], 400);
            }

            $esIngreso = $movimientoOriginal->monto > 0;
            $esEgreso = $movimientoOriginal->monto < 0;

            $cuentaFondeadoraId = null;
            $cuentaFondeadora = null;

            if ($esEgreso) {
                $cuentaFondeadoraId = $movimientoOriginal->id_caja_fondo;
                
                if (!$cuentaFondeadoraId) {
                    $cuentaFondeadoraId = $this->obtenerCuentaFondeadoraDefecto($polizaOriginal->id_empresa);
                }

                if (!$cuentaFondeadoraId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró una cuenta fondeadora para esta póliza de egreso.'
                    ], 400);
                }

                $cuentaFondeadora = Cuenta::find($cuentaFondeadoraId);
                if (!$cuentaFondeadora) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cuenta fondeadora no encontrada'
                    ], 404);
                }

                $saldoActual = (float) ($cuentaFondeadora->saldo_inicial ?? 0);
                if ($saldoActual < $saldoPendiente) {
                    return response()->json([
                        'success' => false,
                        'message' => "Saldo insuficiente en la cuenta fondeadora. Disponible: $" . number_format($saldoActual, 2) . ", Necesario: $" . number_format($saldoPendiente, 2)
                    ], 400);
                }
            }

            $empresaId = $polizaOriginal->id_empresa;
            $fechaLiquidacion = now()->toDateString();
            
            $tipoPolizaLiquidacion = $esIngreso ? 'INGRESO' : 'EGRESO';
            
            $nuevaPoliza = Poliza::create([
                'id_empresa' => $empresaId,
                'tipo_poliza' => $tipoPolizaLiquidacion,
                'fecha_poliza' => $fechaLiquidacion,
                'fecha_vencimiento' => null,
                'categoria' => 'NO_FISCAL',
                'estatus' => 'CAPTURADO',
                'es_por_pagar' => false,
                'referencia' => ($esIngreso ? 'COB-' : 'PAG-') . $polizaOriginal->folio,
                'nota' => ($esIngreso ? 'COBRO' : 'PAGO') . ' TOTAL de póliza ' . $polizaOriginal->folio . ' - Liquidación completa',
                'id_persona' => $polizaOriginal->id_persona,
                'id_usuario_creador' => auth()->id(),
                'id_usuario_autorizador' => null,
                'fecha_creacion' => now(),
                'fecha_autorizacion' => null,
                'id_marcador' => $polizaOriginal->id_marcador,
                'fecha_factura' => null,
                'numero_factura' => null,
                'ruta_pdf' => null,
                'nombre_pdf' => null,
                'nombre_xml' => null,
                'uuid_factura' => null,
                'serie_factura' => null,
                'folio_factura' => null,
            ]);

            $nuevaPoliza->refresh();

            $montoLiquidacion = $esIngreso ? $saldoPendiente : -$saldoPendiente;
            
            MovimientoPoliza::create([
                'id_poliza' => $nuevaPoliza->id,
                'id_cuenta' => $movimientoOriginal->id_cuenta,
                'id_caja_fondo' => $esEgreso ? $cuentaFondeadoraId : null,
                'id_tipo_iva' => null,
                'monto' => $montoLiquidacion,
                'monto_base' => $montoLiquidacion,
                'monto_iva' => 0,
                'monto_traspaso' => null,
                'monto_iva_cero' => 0,
                'monto_iva_dieciseis' => 0,
                'iva_dieciseis' => 0,
            ]);

            if ($esEgreso && $cuentaFondeadoraId) {
                $this->actualizarSaldoCuenta($cuentaFondeadoraId);
            }

            if ($esIngreso) {
                $this->actualizarSaldoCuenta($movimientoOriginal->id_cuenta);
            }

            if ($esEgreso && $movimientoOriginal->id_cuenta && $movimientoOriginal->id_cuenta != $cuentaFondeadoraId) {
                $this->actualizarSaldoCuenta($movimientoOriginal->id_cuenta);
            }

            AbonoPoliza::create([
                'id_poliza' => $polizaOriginal->id,
                'monto_abonado' => $saldoPendiente,
                'fecha_abono' => $fechaLiquidacion,
                'referencia' => ($esIngreso ? 'COBRO-' : 'PAGO-') . $nuevaPoliza->folio,
                'metodo_pago' => 'TRANSFERENCIA',
                'nota' => ($esIngreso ? 'COBRO' : 'PAGO') . ' TOTAL - Póliza liquidada completamente | Póliza de ' . ($esIngreso ? 'cobro' : 'pago') . ': ' . $nuevaPoliza->folio,
                'id_usuario' => auth()->id(),
                'id_poliza_egreso' => $nuevaPoliza->id,
            ]);

            $polizaOriginal->estatus = 'LIQUIDADO';
            $polizaOriginal->fecha_liquidacion = now();
            $polizaOriginal->save();

            DB::commit();

            $tipoAccion = $esIngreso ? 'cobrada' : 'liquidada';
            $mensaje = '✅ Póliza ' . $tipoAccion . ' exitosamente. ' .
                    'Se creó la póliza de ' . ($esIngreso ? 'cobro' : 'pago') . ': ' . $nuevaPoliza->folio . 
                    ' por $' . number_format($saldoPendiente, 2) . 
                    '. La póliza ' . $polizaOriginal->folio . ' ha sido ' . $tipoAccion . ' completamente.';

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'data' => [
                    'tipo' => $esIngreso ? 'INGRESO' : 'EGRESO',
                    'poliza_liquidacion' => $nuevaPoliza->folio,
                    'monto_liquidado' => $saldoPendiente,
                    'cuenta_fondeadora' => $esEgreso ? ($cuentaFondeadora->nombre_cuenta ?? 'N/A') : 'N/A (Ingreso)',
                    'poliza_original' => $polizaOriginal->folio
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error en storeLiquidacion:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al liquidar la póliza: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener la cuenta fondeadora por defecto de la empresa
     */
    private function obtenerCuentaFondeadoraDefecto($idEmpresa)
    {
        $cuenta = Cuenta::where('id_empresa', $idEmpresa)
            ->where('en_uso', true)
            ->where('tipo_cuenta', 'FONDEADORA')
            ->first();

        if (!$cuenta) {
            $cuenta = Cuenta::where('id_empresa', $idEmpresa)
                ->where('en_uso', true)
                ->where('tipo_cuenta', 'CAJA')
                ->first();
        }

        if (!$cuenta) {
            $cuenta = Cuenta::where('id_empresa', $idEmpresa)
                ->where('en_uso', true)
                ->first();
        }

        return $cuenta ? $cuenta->id_cuenta : null;
    }

   public function imprimirPoliza($id)
    {
        if (!Gate::allows('ver-movimientos')) {
            abort(403, 'No tienes permiso para ver esta póliza');
        }

        $movimiento = MovimientoPoliza::with([
            'poliza.persona',
            'poliza.usuarioCreador',
            'poliza.usuarioRevisor',
            'poliza.usuarioAutorizador',
            'poliza.marcador',
            'poliza.empresa',
            'cuenta' => function($q) {
                $q->where('en_uso', true);
            },
            'cuentaFondeadora' => function($q) {
                $q->where('en_uso', true);
            },
            'poliza.abonos'
        ])->find($id);

        if (!$movimiento) {
            abort(404, 'Movimiento no encontrado');
        }

        $esTraspaso = $movimiento->poliza->tipo_poliza === 'TRASPASO';
        $abonos = $movimiento->poliza->abonos ?? collect();
        $totalAbonado = $abonos->sum('monto_abonado');
        
        // ============================================
        // 🔥 VALORES DIRECTOS DE LA BASE DE DATOS
        // ============================================
        $montoTotal = abs($movimiento->monto ?? 0);  // 🔥 ESTE ES EL TOTAL FINAL
        $montoBase = abs($movimiento->monto_base ?? 0);
        $montoIva = abs($movimiento->monto_iva ?? 0);
        $montoIvaCero = abs($movimiento->monto_iva_cero ?? 0);
        $montoIvaDieciseis = abs($movimiento->monto_iva_dieciseis ?? 0);
        $ivaDieciseis = abs($movimiento->iva_dieciseis ?? 0);
        
        // 🔥 TOTAL FACTURA = SUMA DE LAS BASES (monto_iva_cero + monto_iva_dieciseis)
        $totalFactura = $montoIvaCero + $montoIvaDieciseis;
        
        // 🔥 Si no hay desglose, el total es el monto base
        if ($totalFactura == 0) {
            $totalFactura = $montoBase;
        }
        
        // 🔥 CALCULAR PORCENTAJE DE IVA
        $porcentajeIva = 0;
        if ($montoIvaDieciseis > 0 && $ivaDieciseis > 0) {
            $porcentajeIva = round(($ivaDieciseis / $montoIvaDieciseis) * 100);
        } elseif ($montoBase > 0 && $montoIva > 0) {
            $porcentajeIva = round(($montoIva / $montoBase) * 100);
        }
        
        // 🔥 DETERMINAR SI TIENE DESGLOSE FISCAL
        $tieneDesgloseFiscal = ($montoIvaCero > 0 || $montoIvaDieciseis > 0 || $ivaDieciseis > 0);
        $esFiscal = $movimiento->poliza->categoria === 'FISCAL';
        $tieneIva = ($montoIva > 0 || $ivaDieciseis > 0);

        // 🔥 Para traspasos, el monto total puede venir de monto_traspaso
        if ($esTraspaso && $movimiento->monto_traspaso) {
            $montoTotal = abs($movimiento->monto_traspaso);
            $saldoPendiente = $montoTotal - $totalAbonado;
        } else {
            $saldoPendiente = $montoTotal - $totalAbonado;
        }

        $empresa = $movimiento->poliza->empresa;

        $data = [
            'movimiento' => $movimiento,
            'esTraspaso' => $esTraspaso,
            'montoMostrar' => $montoTotal,  // 🔥 EL TOTAL FINAL (monto)
            'totalAbonado' => $totalAbonado,
            'saldoPendiente' => $saldoPendiente,
            'empresa' => $empresa,
            'abonos' => $abonos,
            'fecha_exportacion' => now()->format('d/m/Y H:i:s'),
            
            // 🔥 DATOS PARA DESGLOSE FISCAL
            'montoBase' => $montoBase,
            'montoIvaTotal' => $montoIva,
            'montoIvaCero' => $montoIvaCero,
            'montoIvaDieciseis' => $montoIvaDieciseis,
            'ivaDieciseis' => $ivaDieciseis,
            'totalFactura' => $totalFactura,
            'porcentajeIva' => $porcentajeIva,
            'tieneDesgloseFiscal' => $tieneDesgloseFiscal,
            'esFiscal' => $esFiscal,
            'tieneIva' => $tieneIva,
        ];

        $tipo = request()->get('tipo', 'ticket');

        if ($tipo === 'reimision') {
            return view('exports.poliza_reimision', $data);
        }

        return view('exports.poliza_ticket', $data);
    }
    /**
     * ¿El usuario actual puede editar esta póliza?
     *  - ADMINISTRADOR / AUDITOR / SUPERUSUARIO: siempre (permiso 'editar-movimientos').
     *  - CAPTURISTA: sólo las pólizas que él capturó y mientras estén PENDIENTE /
     *    CAPTURADO (una vez revisadas o autorizadas ya no).
     */
    private function puedeEditarPoliza($poliza): bool
    {
        if (!$poliza) {
            return false;
        }
        if (Gate::allows('editar-movimientos')) {
            return true;
        }
        if (!Gate::allows('editar-poliza')) {
            return false;
        }
        return (int) $poliza->id_usuario_creador === (int) auth()->id()
            && in_array($poliza->estatus, ['PENDIENTE', 'CAPTURADO'], true);
    }

    /**
     * Respuesta de ERROR para store(). La pantalla de captura envía la póliza
     * por axios: si aquí se respondiera con `redirect()->back()`, axios seguiría
     * la redirección, recibiría un 200 y la pantalla diría "La póliza se ha
     * registrado correctamente" SIN haberse guardado nada. Por eso, en peticiones
     * AJAX/JSON se responde con 422/500 y el mensaje real.
     */
    private function respuestaErrorStore(Request $request, string $mensaje, array $errores = [], int $status = 422)
    {
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            return response()->json(array_filter([
                'success' => false,
                'message' => $mensaje,
                'errors' => $errores ?: null,
            ], fn ($v) => $v !== null), $status);
        }

        return redirect()->back()
            ->withErrors($errores)
            ->withInput()
            ->with('error', $mensaje);
    }

    /**
     * ¿Puede el usuario actual usar esta cuenta como fondeadora? Respeta las
     * fondeadoras que el SUPERUSUARIO le asignó (sin asignación = todas).
     */
    private function fondeadoraPermitida($idCuenta): bool
    {
        $cuenta = Cuenta::find($idCuenta);
        if (!$cuenta) {
            return false;
        }
        $ids = auth()->user()->idsFondeadorasAsignadas((int) $cuenta->id_empresa);

        return in_array((int) $idCuenta, $ids, true);
    }

    /** Mensaje cuando el usuario no tiene ninguna fondeadora asignada. */
    private function mensajeSinFondeadoras(): string
    {
        return 'No tienes cuentas fondeadoras asignadas en esta empresa, por lo que no puedes generar pólizas. '
            . 'Pídele a un superusuario que te las asigne.';
    }

    /** ¿El usuario actual NO tiene ninguna fondeadora asignada en la empresa? */
    private function sinFondeadorasEnEmpresa($empresaId): bool
    {
        return !$empresaId || empty(auth()->user()->idsFondeadorasAsignadas((int) $empresaId));
    }

    /**
     * Filtros por FECHA DE REGISTRO (created_at: cuándo se capturó en el sistema)
     * y por FECHA DE FACTURA (sólo pólizas fiscales). Son independientes de las
     * fechas Desde/Hasta de siempre (fecha_poliza = fecha del gasto).
     * $q es un builder de Poliza (o el closure de whereHas('poliza')).
     */
    private function aplicarFiltrosRegistroYFactura($q, Request $request): void
    {
        if ($request->filled('registro_desde')) {
            $q->whereDate('polizas.created_at', '>=', $request->registro_desde);
        }
        if ($request->filled('registro_hasta')) {
            $q->whereDate('polizas.created_at', '<=', $request->registro_hasta);
        }

        if ($request->filled('factura_desde') || $request->filled('factura_hasta')) {
            $q->where('polizas.categoria', 'FISCAL');
            if ($request->filled('factura_desde')) {
                $q->whereDate('polizas.fecha_factura', '>=', $request->factura_desde);
            }
            if ($request->filled('factura_hasta')) {
                $q->whereDate('polizas.fecha_factura', '<=', $request->factura_hasta);
            }
        }
    }

    /**
     * Datos de "quién y cuándo subió" el recurso (comprobante) de una póliza y
     * si se subió tarde: más de 20 minutos después de que se registró la póliza.
     * Se calcula aquí (hora del servidor) para no depender de la zona horaria
     * del navegador.
     */
    private function datosSubidaRecurso($recurso, $poliza): array
    {
        $vacio = [
            'recurso_subido_por' => null,
            'recurso_subido_en' => null,
            'recurso_retraso_texto' => null,
            'recurso_tarde' => false,
        ];

        if (!$recurso) {
            return $vacio;
        }

        // updated_at = última carga (subida original o reemplazo), que va de
        // la mano con id_usuario_subio (el reemplazo actualiza a ambos).
        $subidoEn = $recurso->updated_at ?? $recurso->created_at;
        $registrada = $poliza->created_at ?? $poliza->fecha_creacion;

        $segundos = ($subidoEn && $registrada)
            ? max(0, \Carbon\Carbon::parse($subidoEn)->getTimestamp() - \Carbon\Carbon::parse($registrada)->getTimestamp())
            : null;

        $texto = null;
        if ($segundos !== null) {
            $minutos = intdiv($segundos, 60);
            if ($minutos < 1) {
                $texto = 'menos de 1 minuto';
            } elseif ($minutos < 60) {
                $texto = $minutos . ' min';
            } elseif ($minutos < 1440) {
                $texto = intdiv($minutos, 60) . ' h ' . ($minutos % 60) . ' min';
            } else {
                $texto = intdiv($minutos, 1440) . ' d ' . intdiv($minutos % 1440, 60) . ' h';
            }
        }

        return [
            'recurso_subido_por' => $recurso->usuario
                ? ($recurso->usuario->nombre_usuario ?: $recurso->usuario->nombre_completo)
                : null,
            'recurso_subido_en' => $subidoEn
                ? \Carbon\Carbon::parse($subidoEn)->timezone(config('app.timezone'))->format('d/m/Y h:i A')
                : null,
            'recurso_retraso_texto' => $texto,
            'recurso_tarde' => $segundos !== null && $segundos > 20 * 60,
        ];
    }

    // ============================================
    // 📎 SUBIR ARCHIVO ADJUNTO A PÓLIZA
    // ============================================
    public function subirArchivo(Request $request, $idPoliza)
    {
        if (!Gate::allows('subir-recursos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para subir archivos'
            ], 403);
        }

        $polizaDestino = Poliza::find($idPoliza);

        // El capturista sólo puede subir recursos a las pólizas que él capturó.
        if ($polizaDestino && auth()->user()->tipo_usuario === 'CAPTURISTA'
            && (int) $polizaDestino->id_usuario_creador !== (int) auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Solo puedes subir recursos a las pólizas que tú capturaste'
            ], 403);
        }

        // Un solo recurso por póliza (para cambiarlo se usa "Reemplazar", que sí
        // requiere permiso de edición).
        if ($polizaDestino && $polizaDestino->archivos()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta póliza ya tiene un recurso. Usa "Reemplazar" para cambiarlo.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'archivo' => 'required|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $poliza = Poliza::find($idPoliza);
            if (!$poliza) {
                throw new \Exception('Póliza no encontrada');
            }

            $archivo = $request->file('archivo');
            $nombreOriginal = $archivo->getClientOriginalName();
            $mimeType = $archivo->getMimeType();
            $tamano = $archivo->getSize();
            
            // Determinar tipo de archivo
            $tipoArchivo = 'other';
            if (str_contains($mimeType, 'pdf') || $archivo->getClientOriginalExtension() === 'pdf') {
                $tipoArchivo = 'pdf';
            } elseif (str_contains($mimeType, 'image')) {
                $tipoArchivo = 'image';
            }

            // Generar nombre único
            $nombreGuardado = 'poliza_' . $idPoliza . '_' . time() . '_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
            
            // Guardar archivo
            $ruta = $archivo->storeAs('poliza_archivos', $nombreGuardado, 'public');

            // Crear registro en base de datos
            $archivoDB = PolizaArchivo::create([
                'id_poliza' => $idPoliza,
                'nombre_original' => $nombreOriginal,
                'nombre_guardado' => $nombreGuardado,
                'ruta' => $ruta,
                'tipo_archivo' => $tipoArchivo,
                'mime_type' => $mimeType,
                'tamano' => $tamano,
                'id_usuario_subio' => auth()->id(),
                'descripcion' => $request->descripcion ?? null
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Archivo subido exitosamente',
                'data' => [
                    'id' => $archivoDB->id,
                    'nombre_original' => $archivoDB->nombre_original,
                    'url' => $archivoDB->url
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al subir archivo:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al subir el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 📎 ELIMINAR ARCHIVO ADJUNTO
    // ============================================
    public function eliminarArchivo($idArchivo)
    {
        if (!Gate::allows('editar-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar archivos'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $archivo = PolizaArchivo::find($idArchivo);
            if (!$archivo) {
                throw new \Exception('Archivo no encontrado');
            }

            // Eliminar archivo físico
            if (Storage::disk('public')->exists($archivo->ruta)) {
                Storage::disk('public')->delete($archivo->ruta);
            }

            // Eliminar registro
            $archivo->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Archivo eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al eliminar archivo:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 📎 REEMPLAZAR / MODIFICAR ARCHIVO ADJUNTO
    // ============================================
    public function reemplazarArchivo(Request $request, $idArchivo)
    {
        if (!Gate::allows('editar-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para modificar archivos'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'archivo' => 'required|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $archivoDB = PolizaArchivo::find($idArchivo);
            if (!$archivoDB) {
                throw new \Exception('Archivo no encontrado');
            }

            $rutaAnterior = $archivoDB->ruta;

            $archivo = $request->file('archivo');
            $nombreOriginal = $archivo->getClientOriginalName();
            $mimeType = $archivo->getMimeType();
            $tamano = $archivo->getSize();

            // Determinar tipo de archivo
            $tipoArchivo = 'other';
            if (str_contains($mimeType, 'pdf') || $archivo->getClientOriginalExtension() === 'pdf') {
                $tipoArchivo = 'pdf';
            } elseif (str_contains($mimeType, 'image')) {
                $tipoArchivo = 'image';
            }

            // Generar nombre único para el nuevo archivo
            $nombreGuardado = 'poliza_' . $archivoDB->id_poliza . '_' . time() . '_' . uniqid() . '.' . $archivo->getClientOriginalExtension();

            // Guardar el nuevo archivo
            $ruta = $archivo->storeAs('poliza_archivos', $nombreGuardado, 'public');

            // Actualizar el registro
            $archivoDB->update([
                'nombre_original' => $nombreOriginal,
                'nombre_guardado' => $nombreGuardado,
                'ruta' => $ruta,
                'tipo_archivo' => $tipoArchivo,
                'mime_type' => $mimeType,
                'tamano' => $tamano,
                'id_usuario_subio' => auth()->id(),
            ]);

            // Eliminar el archivo físico anterior
            if ($rutaAnterior && Storage::disk('public')->exists($rutaAnterior)) {
                Storage::disk('public')->delete($rutaAnterior);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Archivo reemplazado exitosamente',
                'data' => [
                    'id' => $archivoDB->id,
                    'nombre_original' => $archivoDB->nombre_original,
                    'url' => $archivoDB->url
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al reemplazar archivo:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al reemplazar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // 📎 VER ARCHIVO ADJUNTO
    // ============================================
    public function verArchivo($idArchivo)
    {
        if (!Gate::allows('ver-movimientos')) {
            abort(403, 'No tienes permiso para ver archivos');
        }

        $archivo = PolizaArchivo::find($idArchivo);
        if (!$archivo) {
            abort(404, 'Archivo no encontrado');
        }

        if (!Storage::disk('public')->exists($archivo->ruta)) {
            abort(404, 'Archivo no encontrado en el servidor');
        }

        return Storage::disk('public')->response($archivo->ruta, $archivo->nombre_original);
    }


    // ============================================
    // 📌 STORE MARCADOR - CREAR NUEVO MARCADOR
    // ============================================
    public function storeMarcador(Request $request)
    {
        if (!Gate::allows('crear-movimientos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para crear marcadores'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nombre_marcador' => 'required|string|max:100|unique:marcadores,nombre_marcador',
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $marcador = Marcador::create([
                'nombre_marcador' => $request->nombre_marcador,
                'descripcion' => $request->descripcion,
                'activo' => $request->input('activo', true)
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Marcador creado exitosamente',
                'data' => [
                    'id' => $marcador->id,
                    'nombre_marcador' => $marcador->nombre_marcador,
                    'descripcion' => $marcador->descripcion,
                    'activo' => $marcador->activo
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al crear marcador:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el marcador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista todos los marcadores activos (para el gestor / modal).
     */
    public function indexMarcadores()
    {
        $marcadores = Marcador::where('activo', true)
            ->orderBy('nombre_marcador')
            ->get(['id', 'nombre_marcador', 'descripcion', 'activo']);

        return response()->json(['success' => true, 'data' => $marcadores]);
    }

    /**
     * Actualiza el nombre / descripción de un marcador.
     */
    public function updateMarcador(Request $request, $id)
    {
        if (!Gate::allows('crear-movimientos')) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para editar marcadores'], 403);
        }

        $marcador = Marcador::find($id);
        if (!$marcador) {
            return response()->json(['success' => false, 'message' => 'El marcador no existe'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre_marcador' => 'required|string|max:100|unique:marcadores,nombre_marcador,' . $id,
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $marcador->update([
            'nombre_marcador' => $request->nombre_marcador,
            'descripcion' => $request->descripcion,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Marcador actualizado',
            'data' => [
                'id' => $marcador->id,
                'nombre_marcador' => $marcador->nombre_marcador,
                'descripcion' => $marcador->descripcion,
                'activo' => $marcador->activo,
            ],
        ]);
    }

    /**
     * Elimina (desactiva) un marcador. No se puede si está en uso por movimientos.
     */
    public function destroyMarcador($id)
    {
        if (!Gate::allows('crear-movimientos')) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para eliminar marcadores'], 403);
        }

        $marcador = Marcador::find($id);
        if (!$marcador) {
            return response()->json(['success' => false, 'message' => 'El marcador no existe'], 404);
        }

        $enUso = DB::table('polizas')->where('id_marcador', $id)->exists()
            || DB::table('empresas_usuarios_marcadores')->where('id_marcador', $id)->exists();

        if ($enUso) {
            // En uso: sólo se desactiva para no romper referencias históricas.
            $marcador->update(['activo' => false]);

            return response()->json([
                'success' => true,
                'message' => 'El marcador está en uso, se archivó en lugar de eliminarlo.',
                'archived' => true,
            ]);
        }

        $marcador->delete();

        return response()->json(['success' => true, 'message' => 'Marcador eliminado']);
    }

} // FIN DEL CONTROLADOR