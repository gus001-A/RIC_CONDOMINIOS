<?php

namespace App\Http\Controllers;

use App\Models\AlertaSeguridad;
use App\Models\Casa;
use App\Models\Condomino;
use App\Models\CuotaCargo;
use App\Models\CuotaPago;
use App\Models\Empresa;
use App\Models\Incidencia;
use App\Models\Insumo;
use App\Models\InsumoMovimiento;
use App\Models\SolicitudServicio;
use App\Models\Visita;
use App\Services\EstadoCuentaCuotas;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Reportes del condominio (cobranza, operación, seguridad, almacén y padrón).
 *
 * Cada reporte arma la misma estructura y de ahí salen la vista previa en
 * pantalla, el Excel y el PDF:
 *   titulo, subtitulo, columnas [{key, titulo, tipo, ancho}], filas, totales, resumen
 * tipo de columna: texto | moneda | numero | fecha | fechahora | porcentaje
 */
class ReporteCondominioController extends Controller
{
    /** Catálogo que ve la pantalla: grupo, nombre, descripción y si pide rango de fechas. */
    public const REPORTES = [
        'cobranza' => ['grupo' => 'Cuotas y cobranza', 'titulo' => 'Cobranza por casa', 'descripcion' => 'Lo cargado contra lo cobrado de cada casa en el periodo y el porcentaje de cobranza.', 'rango' => true, 'icono' => 'wallet'],
        'antiguedad' => ['grupo' => 'Cuotas y cobranza', 'titulo' => 'Antigüedad de saldos', 'descripcion' => 'Adeudo de cada casa al día de hoy, separado por días de atraso.', 'rango' => false, 'icono' => 'clock'],
        'pagos' => ['grupo' => 'Cuotas y cobranza', 'titulo' => 'Pagos recibidos', 'descripcion' => 'Cada pago de cuota registrado: fecha, casa, forma de pago, referencia y póliza.', 'rango' => true, 'icono' => 'dollar'],
        'incidencias' => ['grupo' => 'Operación', 'titulo' => 'Incidencias y mantenimiento', 'descripcion' => 'Reportes del periodo con su estatus, responsable, horas para atender y gastos.', 'rango' => true, 'icono' => 'tool'],
        'servicios' => ['grupo' => 'Operación', 'titulo' => 'Servicios y asesoría', 'descripcion' => 'Solicitudes de las casas, su estatus y la cotización aceptada.', 'rango' => true, 'icono' => 'shop'],
        'visitas' => ['grupo' => 'Seguridad', 'titulo' => 'Bitácora de visitas', 'descripcion' => 'Quién entró, a qué casa, con qué vehículo y a qué horas.', 'rango' => true, 'icono' => 'idcard'],
        'alertas' => ['grupo' => 'Seguridad', 'titulo' => 'Alertas vecinales', 'descripcion' => 'Alertas emitidas, quién las emitió (aunque sean anónimas) y cómo se cerraron.', 'rango' => true, 'icono' => 'bell'],
        'inventario' => ['grupo' => 'Almacén', 'titulo' => 'Existencias del almacén', 'descripcion' => 'Existencia, mínimo, costo promedio y valor de cada insumo.', 'rango' => false, 'icono' => 'inbox'],
        'consumos' => ['grupo' => 'Almacén', 'titulo' => 'Consumo de insumos', 'descripcion' => 'Salidas del almacén en el periodo, en qué se usaron y su costo.', 'rango' => true, 'icono' => 'swap'],
        'directorio' => ['grupo' => 'Padrón', 'titulo' => 'Directorio de casas y condóminos', 'descripcion' => 'Cada casa con sus residentes, teléfonos, correo y acceso al sistema.', 'rango' => false, 'icono' => 'team'],
    ];

    public function __construct(private EstadoCuentaCuotas $estadoCuenta)
    {
    }

    public function catalogo()
    {
        abort_unless(Gate::allows('ver-reportes'), 403);

        return response()->json(collect(self::REPORTES)->map(fn ($r, $clave) => $r + ['clave' => $clave])->values());
    }

    public function datos(Request $request, string $tipo)
    {
        return response()->json($this->armar($request, $tipo));
    }

    public function excel(Request $request, string $tipo)
    {
        $r = $this->armar($request, $tipo);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $r['titulo']), 0, 31));
        $n = count($r['columnas']);
        $ultima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);

        $hoja->setCellValue('A1', $r['titulo']);
        $hoja->setCellValue('A2', $r['subtitulo']);
        $hoja->mergeCells("A1:{$ultima}1");
        $hoja->mergeCells("A2:{$ultima}2");
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('0F2A47');
        $hoja->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('5D7085');

        $fila = 4;
        if ($r['resumen']) {
            foreach ($r['resumen'] as $i => $k) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($i % max(1, $n)) + 1);
                $hoja->setCellValue("{$col}{$fila}", $k['label']);
                $hoja->setCellValue($col . ($fila + 1), $this->valorPlano($k['valor'], $k['tipo'] ?? 'texto'));
                $hoja->getStyle("{$col}{$fila}")->getFont()->setSize(9)->getColor()->setRGB('5D7085');
                $hoja->getStyle($col . ($fila + 1))->getFont()->setBold(true)->setSize(12);
                $this->formatoNumero($hoja, $col . ($fila + 1), $k['tipo'] ?? 'texto');
            }
            $fila += 3;
        }

        // Encabezados
        foreach ($r['columnas'] as $i => $c) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue("{$col}{$fila}", $c['titulo']);
            $hoja->getColumnDimension($col)->setWidth(max(10, min(48, ($c['ancho'] ?? 16))));
        }
        $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3A5C']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $hoja->getRowDimension($fila)->setRowHeight(24);
        $inicio = $fila + 1;

        foreach ($r['filas'] as $f) {
            $fila++;
            foreach ($r['columnas'] as $i => $c) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $hoja->setCellValue("{$col}{$fila}", $this->valorPlano($f[$c['key']] ?? null, $c['tipo']));
            }
            if ($fila % 2 === 0) {
                $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F6FA');
            }
        }

        if ($r['totales']) {
            $fila++;
            $hoja->setCellValue("A{$fila}", 'TOTAL');
            foreach ($r['columnas'] as $i => $c) {
                if (array_key_exists($c['key'], $r['totales'])) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                    $hoja->setCellValue("{$col}{$fila}", $this->valorPlano($r['totales'][$c['key']], $c['tipo']));
                }
            }
            $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0FA']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1A3A5C']]],
            ]);
        }

        foreach ($r['columnas'] as $i => $c) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $this->formatoNumero($hoja, "{$col}{$inicio}:{$col}{$fila}", $c['tipo']);
        }
        if ($fila >= $inicio) {
            $hoja->getStyle("A{$inicio}:{$ultima}{$fila}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E1E8F0');
        }
        $hoja->freezePane('A' . $inicio);

        $nombre = $this->nombreArchivo($r) . '.xlsx';
        $temporal = tempnam(sys_get_temp_dir(), 'rep');
        (new Xlsx($libro))->save($temporal);

        return response()->download($temporal, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function pdf(Request $request, string $tipo)
    {
        $r = $this->armar($request, $tipo);
        $horizontal = count($r['columnas']) > 6;

        $pdf = Pdf::loadView('exports.reporte_condominio', [
            'r' => $r,
            'fmt' => fn ($v, $t) => $this->valorTexto($v, $t),
            'generado' => now()->format('d/m/Y H:i'),
            'usuario' => auth()->user()->nombre_usuario,
        ])->setPaper('letter', $horizontal ? 'landscape' : 'portrait')
            ->setOption('isFontSubsettingEnabled', true); // sólo las letras usadas: PDF mucho más ligero

        return $pdf->stream($this->nombreArchivo($r) . '.pdf');
    }

    // ============================================================
    // Armado de cada reporte
    // ============================================================

    private function armar(Request $request, string $tipo): array
    {
        abort_unless(Gate::allows('ver-reportes'), 403);
        abort_unless(isset(self::REPORTES[$tipo]), 404);

        $empresaId = (int) $request->input('empresa_id');
        abort_unless($empresaId && auth()->user()->puedeVerCondominio($empresaId), 403);
        $empresa = Empresa::findOrFail($empresaId);

        $desde = $request->filled('desde') ? Carbon::parse($request->desde)->startOfDay() : now()->startOfMonth();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->hasta)->endOfDay() : now()->endOfDay();
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }

        $r = $this->{'reporte' . ucfirst($tipo)}($empresaId, $desde, $hasta);
        $def = self::REPORTES[$tipo];

        return [
            'tipo' => $tipo,
            'titulo' => $def['titulo'],
            'subtitulo' => $empresa->nombre_empresa . ' · '
                . ($def['rango'] ? 'Del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y') : 'Al ' . now()->format('d/m/Y'))
                . (isset($r['nota']) ? ' · ' . $r['nota'] : ''),
            'columnas' => $r['columnas'],
            'filas' => array_values($r['filas']),
            'totales' => $r['totales'] ?? [],
            'resumen' => $r['resumen'] ?? [],
        ];
    }

    private function reporteCobranza(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $cargos = CuotaCargo::vigentes()->where('id_empresa', $empresaId)
            ->whereBetween('periodo', [$desde->copy()->startOfMonth()->toDateString(), $hasta->toDateString()])
            ->selectRaw('id_casa, SUM(monto) as total')->groupBy('id_casa')->pluck('total', 'id_casa');
        $pagos = CuotaPago::vigentes()->where('id_empresa', $empresaId)
            ->whereBetween('fecha_pago', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('id_casa, SUM(monto) as total, COUNT(*) as n')->groupBy('id_casa')->get()->keyBy('id_casa');

        $filas = Casa::where('id_empresa', $empresaId)->where('activo', true)->with('responsable')->orderBy('identificador')->get()
            ->map(function ($c) use ($cargos, $pagos) {
                $cargado = round((float) ($cargos[$c->id] ?? 0), 2);
                $cobrado = round((float) ($pagos[$c->id]->total ?? 0), 2);

                return [
                    'casa' => $c->identificador,
                    'responsable' => $c->responsable?->nombre_completo ?? 'Sin responsable',
                    'cuota' => $c->cuota_mantenimiento !== null ? (float) $c->cuota_mantenimiento : null,
                    'cargado' => $cargado,
                    'cobrado' => $cobrado,
                    'pagos' => (int) ($pagos[$c->id]->n ?? 0),
                    'diferencia' => round($cargado - $cobrado, 2),
                    'porcentaje' => $cargado > 0 ? round($cobrado / $cargado * 100, 1) : null,
                ];
            });

        $tCargado = round($filas->sum('cargado'), 2);
        $tCobrado = round($filas->sum('cobrado'), 2);

        return [
            'columnas' => [
                ['key' => 'casa', 'titulo' => 'Casa', 'tipo' => 'texto', 'ancho' => 12],
                ['key' => 'responsable', 'titulo' => 'Responsable', 'tipo' => 'texto', 'ancho' => 30],
                ['key' => 'cuota', 'titulo' => 'Cuota mensual', 'tipo' => 'moneda', 'ancho' => 14],
                ['key' => 'cargado', 'titulo' => 'Cargado', 'tipo' => 'moneda', 'ancho' => 14],
                ['key' => 'cobrado', 'titulo' => 'Cobrado', 'tipo' => 'moneda', 'ancho' => 14],
                ['key' => 'pagos', 'titulo' => 'Pagos', 'tipo' => 'numero', 'ancho' => 9],
                ['key' => 'diferencia', 'titulo' => 'Diferencia', 'tipo' => 'moneda', 'ancho' => 14],
                ['key' => 'porcentaje', 'titulo' => '% cobrado', 'tipo' => 'porcentaje', 'ancho' => 11],
            ],
            'filas' => $filas->all(),
            'totales' => ['cargado' => $tCargado, 'cobrado' => $tCobrado, 'pagos' => $filas->sum('pagos'), 'diferencia' => round($tCargado - $tCobrado, 2),
                'porcentaje' => $tCargado > 0 ? round($tCobrado / $tCargado * 100, 1) : null],
            'resumen' => [
                ['label' => 'Cargado en el periodo', 'valor' => $tCargado, 'tipo' => 'moneda', 'color' => '#1a5a96'],
                ['label' => 'Cobrado en el periodo', 'valor' => $tCobrado, 'tipo' => 'moneda', 'color' => '#1faf5c'],
                ['label' => 'Por cobrar', 'valor' => round($tCargado - $tCobrado, 2), 'tipo' => 'moneda', 'color' => '#d9363e'],
                ['label' => 'Cobranza', 'valor' => $tCargado > 0 ? round($tCobrado / $tCargado * 100, 1) : 0, 'tipo' => 'porcentaje', 'color' => '#6e4bc2'],
            ],
            'nota' => 'Cargos por mes de cuota; cobros por fecha de pago',
        ];
    }

    private function reporteAntiguedad(int $empresaId): array
    {
        $antiguedad = $this->estadoCuenta->antiguedadCondominio($empresaId);
        $filas = Casa::where('id_empresa', $empresaId)->with('responsable')->orderBy('identificador')->get()
            ->filter(fn ($c) => ($antiguedad[$c->id]['total'] ?? 0) > 0.009)
            ->map(fn ($c) => [
                'casa' => $c->identificador,
                'responsable' => $c->responsable?->nombre_completo ?? 'Sin responsable',
                'telefono' => $c->responsable?->telefono,
            ] + $antiguedad[$c->id]);

        $suma = fn ($k) => round($filas->sum($k), 2);
        $totales = collect(['por_vencer', 'd1_30', 'd31_60', 'd61_90', 'mas_90', 'total'])->mapWithKeys(fn ($k) => [$k => $suma($k)])->all();

        return [
            'columnas' => [
                ['key' => 'casa', 'titulo' => 'Casa', 'tipo' => 'texto', 'ancho' => 12],
                ['key' => 'responsable', 'titulo' => 'Responsable', 'tipo' => 'texto', 'ancho' => 28],
                ['key' => 'telefono', 'titulo' => 'Teléfono', 'tipo' => 'texto', 'ancho' => 14],
                ['key' => 'por_vencer', 'titulo' => 'Por vencer', 'tipo' => 'moneda', 'ancho' => 13],
                ['key' => 'd1_30', 'titulo' => '1 a 30 días', 'tipo' => 'moneda', 'ancho' => 13],
                ['key' => 'd31_60', 'titulo' => '31 a 60 días', 'tipo' => 'moneda', 'ancho' => 13],
                ['key' => 'd61_90', 'titulo' => '61 a 90 días', 'tipo' => 'moneda', 'ancho' => 13],
                ['key' => 'mas_90', 'titulo' => 'Más de 90 días', 'tipo' => 'moneda', 'ancho' => 14],
                ['key' => 'total', 'titulo' => 'Total', 'tipo' => 'moneda', 'ancho' => 14],
            ],
            'filas' => $filas->sortByDesc('total')->all(),
            'totales' => $totales,
            'resumen' => [
                ['label' => 'Casas con saldo', 'valor' => $filas->count(), 'tipo' => 'numero', 'color' => '#1a5a96'],
                ['label' => 'Vencido (todo)', 'valor' => round($totales['total'] - $totales['por_vencer'], 2), 'tipo' => 'moneda', 'color' => '#d9363e'],
                ['label' => 'Más de 90 días', 'valor' => $totales['mas_90'], 'tipo' => 'moneda', 'color' => '#a11d24'],
                ['label' => 'Por vencer', 'valor' => $totales['por_vencer'], 'tipo' => 'moneda', 'color' => '#f0a202'],
            ],
        ];
    }

    private function reportePagos(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $pagos = CuotaPago::vigentes()->where('id_empresa', $empresaId)
            ->whereBetween('fecha_pago', [$desde->toDateString(), $hasta->toDateString()])
            ->with(['casa:id,identificador', 'poliza:id,folio', 'registro:id_usuario,nombre_usuario'])
            ->orderBy('fecha_pago')->orderBy('id')->get();

        $filas = $pagos->map(fn ($p) => [
            'fecha' => $p->fecha_pago?->format('Y-m-d'),
            'casa' => $p->casa?->identificador,
            'monto' => (float) $p->monto,
            'forma' => CuotaPago::FORMAS_PAGO[$p->forma_pago] ?? $p->forma_pago,
            'referencia' => $p->referencia,
            'poliza' => $p->poliza?->folio,
            'registro' => $p->registro?->nombre_usuario,
            'registrado' => $p->created_at?->toDateTimeString(),
        ]);
        $porForma = $filas->groupBy('forma')->map(fn ($g) => round($g->sum('monto'), 2))->sortDesc();

        return [
            'columnas' => [
                ['key' => 'fecha', 'titulo' => 'Fecha de pago', 'tipo' => 'fecha', 'ancho' => 13],
                ['key' => 'casa', 'titulo' => 'Casa', 'tipo' => 'texto', 'ancho' => 11],
                ['key' => 'monto', 'titulo' => 'Monto', 'tipo' => 'moneda', 'ancho' => 13],
                ['key' => 'forma', 'titulo' => 'Forma de pago', 'tipo' => 'texto', 'ancho' => 16],
                ['key' => 'referencia', 'titulo' => 'Referencia', 'tipo' => 'texto', 'ancho' => 20],
                ['key' => 'poliza', 'titulo' => 'Póliza', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'registro', 'titulo' => 'Registró', 'tipo' => 'texto', 'ancho' => 16],
                ['key' => 'registrado', 'titulo' => 'Fecha y hora de registro', 'tipo' => 'fechahora', 'ancho' => 18],
            ],
            'filas' => $filas->all(),
            'totales' => ['monto' => round($filas->sum('monto'), 2)],
            'resumen' => array_merge(
                [['label' => 'Total cobrado', 'valor' => round($filas->sum('monto'), 2), 'tipo' => 'moneda', 'color' => '#1faf5c'],
                 ['label' => 'Pagos', 'valor' => $filas->count(), 'tipo' => 'numero', 'color' => '#1a5a96']],
                $porForma->take(2)->map(fn ($v, $k) => ['label' => $k, 'valor' => $v, 'tipo' => 'moneda', 'color' => '#1597a5'])->values()->all(),
            ),
            'nota' => 'No incluye pagos cancelados',
        ];
    }

    private function reporteIncidencias(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $incidencias = Incidencia::where('id_empresa', $empresaId)
            ->whereBetween('created_at', [$desde, $hasta])
            ->with(['reporta:id_usuario,nombre_usuario', 'asignado:id_usuario,nombre_usuario', 'casa:id,identificador'])
            ->withSum(['gastos as total_gastos' => fn ($q) => $q->where('cancelado', false)], 'monto')
            ->orderBy('created_at')->get();

        $filas = $incidencias->map(function ($i) {
            $atendida = $i->fecha_atencion ?? $i->fecha_supervision;

            return [
                'folio' => $i->folio,
                'tipo' => Incidencia::TIPOS[$i->tipo] ?? $i->tipo,
                'categoria' => Incidencia::CATEGORIAS[$i->categoria] ?? $i->categoria,
                'titulo' => $i->titulo,
                'ubicacion' => $i->ubicacion,
                'prioridad' => Incidencia::PRIORIDADES[$i->prioridad] ?? $i->prioridad,
                'estatus' => Incidencia::ESTATUS[$i->estatus] ?? $i->estatus,
                'reporto' => trim(($i->reporta?->nombre_usuario ?? '') . ($i->casa ? ' · Casa ' . $i->casa->identificador : '')),
                'asignado' => $i->asignado?->nombre_usuario ?? 'Sin asignar',
                'reportada' => $i->created_at?->toDateTimeString(),
                'atendida' => $atendida ? Carbon::parse($atendida)->toDateTimeString() : null,
                'horas' => $atendida ? round($i->created_at->diffInMinutes(Carbon::parse($atendida)) / 60, 1) : null,
                'gastos' => round((float) $i->total_gastos, 2),
            ];
        });
        $atendidas = $filas->whereNotNull('horas');

        return [
            'columnas' => [
                ['key' => 'folio', 'titulo' => 'Folio', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'tipo', 'titulo' => 'Tipo', 'tipo' => 'texto', 'ancho' => 12],
                ['key' => 'categoria', 'titulo' => 'Categoría', 'tipo' => 'texto', 'ancho' => 16],
                ['key' => 'titulo', 'titulo' => 'Reporte', 'tipo' => 'texto', 'ancho' => 30],
                ['key' => 'ubicacion', 'titulo' => 'Ubicación', 'tipo' => 'texto', 'ancho' => 20],
                ['key' => 'prioridad', 'titulo' => 'Prioridad', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'estatus', 'titulo' => 'Estatus', 'tipo' => 'texto', 'ancho' => 12],
                ['key' => 'reporto', 'titulo' => 'Reportó', 'tipo' => 'texto', 'ancho' => 20],
                ['key' => 'asignado', 'titulo' => 'Asignado', 'tipo' => 'texto', 'ancho' => 15],
                ['key' => 'reportada', 'titulo' => 'Reportada', 'tipo' => 'fechahora', 'ancho' => 16],
                ['key' => 'atendida', 'titulo' => 'Atendida', 'tipo' => 'fechahora', 'ancho' => 16],
                ['key' => 'horas', 'titulo' => 'Horas para atender', 'tipo' => 'numero', 'ancho' => 10],
                ['key' => 'gastos', 'titulo' => 'Gastos', 'tipo' => 'moneda', 'ancho' => 12],
            ],
            'filas' => $filas->all(),
            'totales' => ['gastos' => round($filas->sum('gastos'), 2)],
            'resumen' => [
                ['label' => 'Reportes', 'valor' => $filas->count(), 'tipo' => 'numero', 'color' => '#1a5a96'],
                ['label' => 'Atendidos', 'valor' => $atendidas->count(), 'tipo' => 'numero', 'color' => '#1faf5c'],
                ['label' => 'Promedio para atender (horas)', 'valor' => $atendidas->count() ? round($atendidas->avg('horas'), 1) : 0, 'tipo' => 'numero', 'color' => '#f0a202'],
                ['label' => 'Gasto total', 'valor' => round($filas->sum('gastos'), 2), 'tipo' => 'moneda', 'color' => '#d9363e'],
            ],
        ];
    }

    private function reporteServicios(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $solicitudes = SolicitudServicio::where('id_empresa', $empresaId)
            ->whereBetween('created_at', [$desde, $hasta])
            ->with(['casa:id,identificador', 'solicita:id_usuario,nombre_usuario', 'atiende:id_usuario,nombre_usuario', 'cotizaciones'])
            ->orderBy('created_at')->get();

        $filas = $solicitudes->map(function ($s) {
            $aceptada = $s->cotizaciones->firstWhere('estatus', 'ACEPTADA');

            return [
                'folio' => $s->folio,
                'casa' => $s->casa?->identificador,
                'tipo' => SolicitudServicio::TIPOS[$s->tipo] ?? $s->tipo,
                'titulo' => $s->titulo,
                'estatus' => SolicitudServicio::ESTATUS[$s->estatus] ?? $s->estatus,
                'cotizaciones' => $s->cotizaciones->count(),
                'proveedor' => $aceptada?->proveedor,
                'monto' => $aceptada ? (float) $aceptada->monto : null,
                'solicito' => $s->solicita?->nombre_usuario,
                'fecha' => $s->created_at?->toDateTimeString(),
                'atiende' => $s->atiende?->nombre_usuario ?? 'Sin asignar',
            ];
        });

        return [
            'columnas' => [
                ['key' => 'folio', 'titulo' => 'Folio', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'casa', 'titulo' => 'Casa', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'tipo', 'titulo' => 'Tipo', 'tipo' => 'texto', 'ancho' => 16],
                ['key' => 'titulo', 'titulo' => 'Solicitud', 'tipo' => 'texto', 'ancho' => 30],
                ['key' => 'estatus', 'titulo' => 'Estatus', 'tipo' => 'texto', 'ancho' => 13],
                ['key' => 'cotizaciones', 'titulo' => 'Cotizaciones', 'tipo' => 'numero', 'ancho' => 11],
                ['key' => 'proveedor', 'titulo' => 'Proveedor aceptado', 'tipo' => 'texto', 'ancho' => 20],
                ['key' => 'monto', 'titulo' => 'Monto aceptado', 'tipo' => 'moneda', 'ancho' => 14],
                ['key' => 'solicito', 'titulo' => 'Solicitó', 'tipo' => 'texto', 'ancho' => 16],
                ['key' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fechahora', 'ancho' => 16],
                ['key' => 'atiende', 'titulo' => 'Atiende', 'tipo' => 'texto', 'ancho' => 15],
            ],
            'filas' => $filas->all(),
            'totales' => ['cotizaciones' => $filas->sum('cotizaciones'), 'monto' => round($filas->sum('monto'), 2)],
            'resumen' => [
                ['label' => 'Solicitudes', 'valor' => $filas->count(), 'tipo' => 'numero', 'color' => '#1a5a96'],
                ['label' => 'Concluidas', 'valor' => $solicitudes->where('estatus', 'CONCLUIDA')->count(), 'tipo' => 'numero', 'color' => '#1faf5c'],
                ['label' => 'Con cotización aceptada', 'valor' => $filas->whereNotNull('monto')->count(), 'tipo' => 'numero', 'color' => '#6e4bc2'],
                ['label' => 'Monto aceptado', 'valor' => round($filas->sum('monto'), 2), 'tipo' => 'moneda', 'color' => '#f0a202'],
            ],
        ];
    }

    private function reporteVisitas(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $visitas = Visita::where('id_empresa', $empresaId)
            ->where(fn ($q) => $q->whereBetween('fecha_llegada', [$desde, $hasta])
                ->orWhere(fn ($p) => $p->whereNull('fecha_llegada')->whereBetween('created_at', [$desde, $hasta])))
            ->with(['casa:id,identificador', 'registro:id_usuario,nombre_usuario', 'respuesta:id_usuario,nombre_usuario'])
            ->orderByRaw('COALESCE(fecha_llegada, created_at)')->get();

        $filas = $visitas->map(fn ($v) => [
            'fecha' => ($v->fecha_llegada ?? $v->created_at)?->toDateTimeString(),
            'casa' => $v->casa?->identificador,
            'visitante' => $v->nombre_visitante,
            'tipo' => Visita::TIPOS[$v->tipo_visita] ?? $v->tipo_visita,
            'personas' => (int) $v->num_personas,
            'placas' => $v->placas,
            'vehiculo' => $v->vehiculo,
            'estatus' => Visita::ESTATUS[$v->estatus] ?? $v->estatus,
            'autorizo' => $v->respuesta?->nombre_usuario,
            'entrada' => $v->fecha_entrada?->toDateTimeString(),
            'salida' => $v->fecha_salida?->toDateTimeString(),
            'registro' => $v->registro?->nombre_usuario,
        ]);

        return [
            'columnas' => [
                ['key' => 'fecha', 'titulo' => 'Llegada', 'tipo' => 'fechahora', 'ancho' => 16],
                ['key' => 'casa', 'titulo' => 'Casa', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'visitante', 'titulo' => 'Visitante', 'tipo' => 'texto', 'ancho' => 24],
                ['key' => 'tipo', 'titulo' => 'Tipo', 'tipo' => 'texto', 'ancho' => 13],
                ['key' => 'personas', 'titulo' => 'Personas', 'tipo' => 'numero', 'ancho' => 9],
                ['key' => 'placas', 'titulo' => 'Placas', 'tipo' => 'texto', 'ancho' => 11],
                ['key' => 'vehiculo', 'titulo' => 'Vehículo', 'tipo' => 'texto', 'ancho' => 18],
                ['key' => 'estatus', 'titulo' => 'Estatus', 'tipo' => 'texto', 'ancho' => 13],
                ['key' => 'autorizo', 'titulo' => 'Autorizó', 'tipo' => 'texto', 'ancho' => 15],
                ['key' => 'entrada', 'titulo' => 'Entrada', 'tipo' => 'fechahora', 'ancho' => 16],
                ['key' => 'salida', 'titulo' => 'Salida', 'tipo' => 'fechahora', 'ancho' => 16],
                ['key' => 'registro', 'titulo' => 'Registró (caseta)', 'tipo' => 'texto', 'ancho' => 15],
            ],
            'filas' => $filas->all(),
            'totales' => ['personas' => $filas->sum('personas')],
            'resumen' => [
                ['label' => 'Visitas', 'valor' => $filas->count(), 'tipo' => 'numero', 'color' => '#1a5a96'],
                ['label' => 'Personas', 'valor' => $filas->sum('personas'), 'tipo' => 'numero', 'color' => '#1597a5'],
                ['label' => 'Con vehículo', 'valor' => $filas->filter(fn ($f) => $f['placas'] || $f['vehiculo'])->count(), 'tipo' => 'numero', 'color' => '#6e4bc2'],
                ['label' => 'Rechazadas', 'valor' => $visitas->where('estatus', 'RECHAZADA')->count(), 'tipo' => 'numero', 'color' => '#d9363e'],
            ],
        ];
    }

    private function reporteAlertas(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $alertas = AlertaSeguridad::where('id_empresa', $empresaId)
            ->whereBetween('created_at', [$desde, $hasta])
            ->with(['usuario:id_usuario,nombre_usuario', 'casa:id,identificador', 'cierre:id_usuario,nombre_usuario'])
            ->orderBy('created_at')->get();

        $filas = $alertas->map(fn ($a) => [
            'fecha' => $a->created_at?->toDateTimeString(),
            'tipo' => AlertaSeguridad::TIPOS[$a->tipo]['label'] ?? $a->tipo,
            'estatus' => AlertaSeguridad::ESTATUS[$a->estatus] ?? $a->estatus,
            'ubicacion' => $a->ubicacion,
            // La administración sí ve quién emitió una alerta anónima (trazabilidad).
            'emisor' => ($a->usuario?->nombre_usuario ?? '—') . ($a->anonima ? ' (anónima)' : ''),
            'casa' => $a->casa?->identificador,
            'descripcion' => $a->descripcion,
            'cerro' => $a->cierre?->nombre_usuario,
            'cierre' => $a->fecha_cierre ? Carbon::parse($a->fecha_cierre)->toDateTimeString() : null,
            'comentario' => $a->comentario_cierre,
        ]);

        return [
            'columnas' => [
                ['key' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fechahora', 'ancho' => 16],
                ['key' => 'tipo', 'titulo' => 'Tipo', 'tipo' => 'texto', 'ancho' => 18],
                ['key' => 'estatus', 'titulo' => 'Estatus', 'tipo' => 'texto', 'ancho' => 13],
                ['key' => 'ubicacion', 'titulo' => 'Ubicación', 'tipo' => 'texto', 'ancho' => 20],
                ['key' => 'emisor', 'titulo' => 'Emitió', 'tipo' => 'texto', 'ancho' => 20],
                ['key' => 'casa', 'titulo' => 'Casa', 'tipo' => 'texto', 'ancho' => 9],
                ['key' => 'descripcion', 'titulo' => 'Descripción', 'tipo' => 'texto', 'ancho' => 30],
                ['key' => 'cerro', 'titulo' => 'Cerró', 'tipo' => 'texto', 'ancho' => 14],
                ['key' => 'cierre', 'titulo' => 'Fecha de cierre', 'tipo' => 'fechahora', 'ancho' => 16],
                ['key' => 'comentario', 'titulo' => 'Comentario de cierre', 'tipo' => 'texto', 'ancho' => 28],
            ],
            'filas' => $filas->all(),
            'resumen' => [
                ['label' => 'Alertas', 'valor' => $filas->count(), 'tipo' => 'numero', 'color' => '#d9363e'],
                ['label' => 'Atendidas', 'valor' => $alertas->where('estatus', 'ATENDIDA')->count(), 'tipo' => 'numero', 'color' => '#1faf5c'],
                ['label' => 'Falsas alarmas', 'valor' => $alertas->where('estatus', 'FALSA_ALARMA')->count(), 'tipo' => 'numero', 'color' => '#f0a202'],
                ['label' => 'Anónimas', 'valor' => $alertas->where('anonima', true)->count(), 'tipo' => 'numero', 'color' => '#5d7085'],
            ],
        ];
    }

    private function reporteInventario(int $empresaId): array
    {
        $insumos = Insumo::where('id_empresa', $empresaId)->where('activo', true)->orderBy('categoria')->orderBy('nombre')->get();

        $filas = $insumos->map(fn ($i) => [
            'clave' => $i->clave,
            'nombre' => $i->nombre,
            'categoria' => Insumo::CATEGORIAS[$i->categoria] ?? $i->categoria,
            'ubicacion' => $i->ubicacion,
            'unidad' => Insumo::UNIDADES[$i->unidad] ?? $i->unidad,
            'existencia' => (float) $i->existencia,
            'minimo' => (float) $i->stock_minimo,
            'costo' => (float) $i->costo_promedio,
            'valor' => round(max(0, (float) $i->existencia) * (float) $i->costo_promedio, 2),
            'estado' => $i->existencia <= 0 ? 'Agotado' : ($i->bajo_minimo ? 'Bajo el mínimo' : 'Suficiente'),
        ]);

        return [
            'columnas' => [
                ['key' => 'clave', 'titulo' => 'Clave', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'nombre', 'titulo' => 'Insumo', 'tipo' => 'texto', 'ancho' => 30],
                ['key' => 'categoria', 'titulo' => 'Categoría', 'tipo' => 'texto', 'ancho' => 16],
                ['key' => 'ubicacion', 'titulo' => 'Ubicación', 'tipo' => 'texto', 'ancho' => 16],
                ['key' => 'unidad', 'titulo' => 'Unidad', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'existencia', 'titulo' => 'Existencia', 'tipo' => 'numero', 'ancho' => 11],
                ['key' => 'minimo', 'titulo' => 'Mínimo', 'tipo' => 'numero', 'ancho' => 9],
                ['key' => 'costo', 'titulo' => 'Costo promedio', 'tipo' => 'moneda', 'ancho' => 13],
                ['key' => 'valor', 'titulo' => 'Valor', 'tipo' => 'moneda', 'ancho' => 13],
                ['key' => 'estado', 'titulo' => 'Estado', 'tipo' => 'texto', 'ancho' => 14],
            ],
            'filas' => $filas->all(),
            'totales' => ['valor' => round($filas->sum('valor'), 2)],
            'resumen' => [
                ['label' => 'Insumos', 'valor' => $filas->count(), 'tipo' => 'numero', 'color' => '#1a5a96'],
                ['label' => 'Bajo el mínimo', 'valor' => $filas->where('estado', 'Bajo el mínimo')->count(), 'tipo' => 'numero', 'color' => '#f0a202'],
                ['label' => 'Agotados', 'valor' => $filas->where('estado', 'Agotado')->count(), 'tipo' => 'numero', 'color' => '#d9363e'],
                ['label' => 'Valor del almacén', 'valor' => round($filas->sum('valor'), 2), 'tipo' => 'moneda', 'color' => '#1faf5c'],
            ],
            'nota' => 'Sólo insumos activos',
        ];
    }

    private function reporteConsumos(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $salidas = InsumoMovimiento::where('id_empresa', $empresaId)->where('tipo', 'SALIDA')
            ->whereBetween('created_at', [$desde, $hasta])
            ->with(['insumo:id,nombre,unidad,clave', 'incidencia:id,folio', 'usuario:id_usuario,nombre_usuario'])
            ->orderBy('created_at')->get();

        $filas = $salidas->map(function ($m) {
            $cantidad = abs((float) $m->cantidad);
            $costo = (float) ($m->costo_unitario ?? 0);

            return [
                'fecha' => $m->created_at?->toDateTimeString(),
                'insumo' => $m->insumo?->nombre,
                'cantidad' => $cantidad,
                'unidad' => Insumo::UNIDADES[$m->insumo?->unidad] ?? $m->insumo?->unidad,
                'costo' => $costo,
                'importe' => round($cantidad * $costo, 2),
                'motivo' => $m->motivo,
                'incidencia' => $m->incidencia?->folio,
                'usuario' => $m->usuario?->nombre_usuario,
            ];
        });
        $masUsado = $filas->groupBy('insumo')->map(fn ($g) => $g->sum('importe'))->sortDesc()->keys()->first();

        return [
            'columnas' => [
                ['key' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fechahora', 'ancho' => 16],
                ['key' => 'insumo', 'titulo' => 'Insumo', 'tipo' => 'texto', 'ancho' => 28],
                ['key' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'numero', 'ancho' => 10],
                ['key' => 'unidad', 'titulo' => 'Unidad', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'costo', 'titulo' => 'Costo unitario', 'tipo' => 'moneda', 'ancho' => 13],
                ['key' => 'importe', 'titulo' => 'Importe', 'tipo' => 'moneda', 'ancho' => 13],
                ['key' => 'motivo', 'titulo' => '¿En qué se usó?', 'tipo' => 'texto', 'ancho' => 30],
                ['key' => 'incidencia', 'titulo' => 'Incidencia', 'tipo' => 'texto', 'ancho' => 11],
                ['key' => 'usuario', 'titulo' => 'Usuario', 'tipo' => 'texto', 'ancho' => 15],
            ],
            'filas' => $filas->all(),
            'totales' => ['importe' => round($filas->sum('importe'), 2)],
            'resumen' => [
                ['label' => 'Salidas', 'valor' => $filas->count(), 'tipo' => 'numero', 'color' => '#1a5a96'],
                ['label' => 'Costo consumido', 'valor' => round($filas->sum('importe'), 2), 'tipo' => 'moneda', 'color' => '#d9363e'],
                ['label' => 'Ligadas a incidencias', 'valor' => $filas->whereNotNull('incidencia')->count(), 'tipo' => 'numero', 'color' => '#6e4bc2'],
                ['label' => 'Mayor consumo', 'valor' => $masUsado ?? '—', 'tipo' => 'texto', 'color' => '#1597a5'],
            ],
        ];
    }

    private function reporteDirectorio(int $empresaId): array
    {
        $casas = Casa::where('id_empresa', $empresaId)->where('activo', true)
            ->with(['condominos' => fn ($q) => $q->where('activo', true)->with('usuario:id_usuario,nombre_usuario')->orderByDesc('es_responsable')])
            ->orderBy('identificador')->get();

        $filas = [];
        foreach ($casas as $c) {
            $base = [
                'casa' => $c->identificador,
                'direccion' => $c->direccion_corta,
                'ocupacion' => Casa::ESTATUS_OCUPACION[$c->estatus_ocupacion] ?? $c->estatus_ocupacion,
                'cuota' => $c->cuota_mantenimiento !== null ? (float) $c->cuota_mantenimiento : null,
            ];
            if ($c->condominos->isEmpty()) {
                $filas[] = $base + ['nombre' => 'Sin condóminos registrados', 'tipo' => null, 'responsable' => null, 'telefonos' => null, 'correo' => null, 'acceso' => null];
                continue;
            }
            foreach ($c->condominos as $p) {
                $filas[] = $base + [
                    'nombre' => $p->nombre_completo,
                    'tipo' => Condomino::TIPOS[$p->tipo] ?? $p->tipo,
                    'responsable' => $p->es_responsable ? 'Sí' : 'No',
                    'telefonos' => implode(' / ', array_filter([$p->telefono, $p->telefono_alterno])),
                    'correo' => $p->correo,
                    'acceso' => $p->usuario?->nombre_usuario,
                ];
            }
        }

        return [
            'columnas' => [
                ['key' => 'casa', 'titulo' => 'Casa', 'tipo' => 'texto', 'ancho' => 10],
                ['key' => 'direccion', 'titulo' => 'Dirección', 'tipo' => 'texto', 'ancho' => 26],
                ['key' => 'ocupacion', 'titulo' => 'Ocupación', 'tipo' => 'texto', 'ancho' => 13],
                ['key' => 'cuota', 'titulo' => 'Cuota', 'tipo' => 'moneda', 'ancho' => 11],
                ['key' => 'nombre', 'titulo' => 'Condómino', 'tipo' => 'texto', 'ancho' => 28],
                ['key' => 'tipo', 'titulo' => 'Tipo', 'tipo' => 'texto', 'ancho' => 13],
                ['key' => 'responsable', 'titulo' => 'Responsable', 'tipo' => 'texto', 'ancho' => 11],
                ['key' => 'telefonos', 'titulo' => 'Teléfonos', 'tipo' => 'texto', 'ancho' => 22],
                ['key' => 'correo', 'titulo' => 'Correo', 'tipo' => 'texto', 'ancho' => 26],
                ['key' => 'acceso', 'titulo' => 'Usuario del sistema', 'tipo' => 'texto', 'ancho' => 16],
            ],
            'filas' => $filas,
            'resumen' => [
                ['label' => 'Casas activas', 'valor' => $casas->count(), 'tipo' => 'numero', 'color' => '#1a5a96'],
                ['label' => 'Condóminos', 'valor' => $casas->sum(fn ($c) => $c->condominos->count()), 'tipo' => 'numero', 'color' => '#1faf5c'],
                ['label' => 'Con acceso al sistema', 'valor' => collect($filas)->whereNotNull('acceso')->count(), 'tipo' => 'numero', 'color' => '#6e4bc2'],
                ['label' => 'Casas sin condóminos', 'valor' => $casas->filter(fn ($c) => $c->condominos->isEmpty())->count(), 'tipo' => 'numero', 'color' => '#f0a202'],
            ],
        ];
    }

    // ============================================================
    // Formato
    // ============================================================

    private function valorPlano($v, string $tipo)
    {
        if ($v === null || $v === '') {
            return null;
        }

        return match ($tipo) {
            'moneda', 'numero' => is_numeric($v) ? (float) $v : $v,
            'porcentaje' => is_numeric($v) ? (float) $v / 100 : $v,
            'fecha' => Carbon::parse($v)->format('d/m/Y'),
            'fechahora' => Carbon::parse($v)->format('d/m/Y H:i'),
            default => (string) $v,
        };
    }

    private function valorTexto($v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return '—';
        }

        return match ($tipo) {
            'moneda' => is_numeric($v) ? '$' . number_format((float) $v, 2) : (string) $v,
            'numero' => is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 3), '0'), '.') : (string) $v,
            'porcentaje' => is_numeric($v) ? number_format((float) $v, 1) . '%' : (string) $v,
            'fecha' => Carbon::parse($v)->format('d/m/Y'),
            'fechahora' => Carbon::parse($v)->format('d/m/Y H:i'),
            default => (string) $v,
        };
    }

    private function formatoNumero($hoja, string $rango, string $tipo): void
    {
        $formato = match ($tipo) {
            'moneda' => '"$"#,##0.00',
            'numero' => '#,##0.###',
            'porcentaje' => '0.0%',
            default => null,
        };
        if ($formato) {
            $hoja->getStyle($rango)->getNumberFormat()->setFormatCode($formato);
            $hoja->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function nombreArchivo(array $r): string
    {
        $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $r['titulo']) ?: $r['tipo'];

        return preg_replace('/[^A-Za-z0-9]+/', '_', trim($base)) . '_' . now()->format('Ymd_His');
    }
}
