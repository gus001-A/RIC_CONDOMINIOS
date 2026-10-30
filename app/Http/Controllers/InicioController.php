<?php

namespace App\Http\Controllers;

use App\Models\AlertaSeguridad;
use App\Models\Casa;
use App\Models\Conversacion;
use App\Models\CuotaCargo;
use App\Models\CuotaPago;
use App\Models\Incidencia;
use App\Models\Insumo;
use App\Models\SolicitudServicio;
use App\Models\Usuario;
use App\Models\Visita;
use App\Services\EstadoCuentaCuotas;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Inicio (dashboard) de RIC Condominios: un tablero distinto por rol.
 *  - Administración: cobranza, morosidad, incidencias, alertas y servicios.
 *  - Condómino: su saldo, sus visitas, sus reportes y avisos del condominio.
 *  - Vigilancia: movimiento de la caseta del día y alertas activas.
 *  - Mantenimiento: incidencias por atender e insumos por reponer.
 */
class InicioController extends Controller
{
    private const INCIDENCIA_ABIERTA = ['PENDIENTE', 'EN PROCESO'];
    private const SERVICIO_ABIERTO = ['NUEVA', 'EN_REVISION', 'COTIZADA', 'EN_PROCESO'];

    public function index(EstadoCuentaCuotas $cuotas)
    {
        $usuario = auth()->user();
        // Roles contables de RIC (lector, capturista) no tienen tablero de condominio.
        if (!in_array($usuario->tipo_usuario, ['CONDOMINO', 'VIGILANCIA', 'MANTENIMIENTO', 'ADMINISTRADOR', 'SUPERUSUARIO', 'AUDITOR'], true)) {
            return redirect()->route('movimientos.index');
        }
        $ids = $usuario->idsCondominios();

        [$rol, $datos] = match (true) {
            $usuario->esCondomino() => ['CONDOMINO', $this->condomino($usuario, $cuotas)],
            $usuario->esVigilancia() => ['VIGILANCIA', $this->vigilancia($ids)],
            $usuario->esMantenimiento() => ['MANTENIMIENTO', $this->mantenimiento($usuario, $ids)],
            default => ['ADMINISTRACION', $this->administracion($ids, $cuotas)],
        };

        return Inertia::render('Inicio/Index', [
            'rol' => $rol,
            'datos' => $datos,
            'comunes' => [
                'alertas' => $this->alertasActivas($ids),
                'mensajes_sin_leer' => Gate::allows('usar-mensajes')
                    ? Conversacion::visiblesPara($usuario)->sinLeer($usuario->id_usuario)->count()
                    : 0,
                'hoy' => now()->format('Y-m-d'),
            ],
            'catalogos' => [
                'incidencia_estatus' => Incidencia::ESTATUS,
                'prioridades' => Incidencia::PRIORIDADES,
                'visita_estatus' => Visita::ESTATUS,
                'visita_tipos' => Visita::TIPOS,
                'servicio_estatus' => SolicitudServicio::ESTATUS,
                'servicio_tipos' => SolicitudServicio::TIPOS,
            ],
        ]);
    }

    // ======================================================
    // Administración
    // ======================================================

    private function administracion(array $ids, EstadoCuentaCuotas $cuotas): array
    {
        $casas = Casa::whereIn('id_empresa', $ids)->where('activo', true)->with('responsable')->get()->keyBy('id');

        // Estado de cuenta de cada casa (mismo cálculo FIFO que Cuotas y Transparencia).
        $resumen = collect($ids)->flatMap(fn ($id) => $cuotas->resumenCondominio($id)->all())
            ->filter(fn ($r, $idCasa) => $casas->has($idCasa));
        $deudoras = $resumen->filter(fn ($r) => $r['saldo_vencido'] > 0.009);

        $inicioMes = now()->startOfMonth();
        $cobradoMes = (float) CuotaPago::vigentes()->whereIn('id_empresa', $ids)->where('fecha_pago', '>=', $inicioMes->toDateString())->sum('monto');
        $cargadoMes = (float) CuotaCargo::vigentes()->whereIn('id_empresa', $ids)->whereBetween('periodo', [$inicioMes->toDateString(), now()->endOfMonth()->toDateString()])->sum('monto');

        $incidencias = Incidencia::whereIn('id_empresa', $ids);

        return [
            'kpis' => [
                'casas' => $casas->count(),
                'deudoras' => $deudoras->count(),
                'adeudo_vencido' => round($deudoras->sum('saldo_vencido'), 2),
                'cobrado_mes' => round($cobradoMes, 2),
                'cargado_mes' => round($cargadoMes, 2),
                'incidencias_abiertas' => (clone $incidencias)->whereIn('estatus', self::INCIDENCIA_ABIERTA)->count(),
                'por_supervisar' => (clone $incidencias)->where('estatus', 'ATENDIDA')->count(),
                'servicios_abiertos' => SolicitudServicio::whereIn('id_empresa', $ids)->whereIn('estatus', self::SERVICIO_ABIERTO)->count(),
                'visitas_hoy' => Visita::whereIn('id_empresa', $ids)->where('fecha_llegada', '>=', today())->count(),
            ],
            'cobranza' => $this->cobranzaMensual($ids),
            'deudores' => $deudoras->sortByDesc('saldo_vencido')->take(6)->map(fn ($r, $idCasa) => [
                'id' => $idCasa,
                'casa' => $casas[$idCasa]->identificador,
                'responsable' => $casas[$idCasa]->responsable?->nombre_completo,
                'saldo_vencido' => $r['saldo_vencido'],
                'cargos_vencidos' => $r['cargos_vencidos'],
                'adeudo_desde' => $r['adeudo_desde'],
            ])->values(),
            'ocupacion' => $casas->groupBy('estatus_ocupacion')->map->count(),
            'incidencias' => $this->incidenciasRecientes((clone $incidencias)->whereIn('estatus', [...self::INCIDENCIA_ABIERTA, 'ATENDIDA'])),
            'servicios' => SolicitudServicio::whereIn('id_empresa', $ids)->whereIn('estatus', self::SERVICIO_ABIERTO)
                ->with('casa:id,identificador')->latest()->take(5)->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'folio' => $s->folio, 'titulo' => $s->titulo, 'tipo' => $s->tipo,
                    'estatus' => $s->estatus, 'casa' => $s->casa?->identificador, 'fecha' => $s->created_at,
                ]),
        ];
    }

    /** Cargos contra pagos de los últimos 6 meses (para la gráfica de cobranza). */
    private function cobranzaMensual(array $ids): array
    {
        $desde = now()->startOfMonth()->subMonths(5);
        $cargos = CuotaCargo::vigentes()->whereIn('id_empresa', $ids)->where('periodo', '>=', $desde->toDateString())->get(['periodo', 'monto'])
            ->groupBy(fn ($c) => $c->periodo->format('Y-m'))->map(fn ($g) => (float) $g->sum('monto'));
        $pagos = CuotaPago::vigentes()->whereIn('id_empresa', $ids)->where('fecha_pago', '>=', $desde->toDateString())->get(['fecha_pago', 'monto'])
            ->groupBy(fn ($p) => $p->fecha_pago->format('Y-m'))->map(fn ($g) => (float) $g->sum('monto'));

        return collect(range(0, 5))->map(function ($i) use ($desde, $cargos, $pagos) {
            $mes = $desde->copy()->addMonths($i);
            $clave = $mes->format('Y-m');

            return [
                'mes' => $clave,
                'etiqueta' => ucfirst($mes->locale('es')->isoFormat('MMM')),
                'cargos' => round($cargos[$clave] ?? 0, 2),
                'pagos' => round($pagos[$clave] ?? 0, 2),
            ];
        })->all();
    }

    // ======================================================
    // Condómino
    // ======================================================

    private function condomino(Usuario $usuario, EstadoCuentaCuotas $cuotas): array
    {
        $condomino = $usuario->condomino;
        $casa = ($condomino && $condomino->activo) ? $condomino->casa : null;
        if (!$casa) {
            return ['casa' => null];
        }

        $resumen = $cuotas->detalleCasa($casa)['resumen'];
        $visitas = Visita::where('id_casa', $casa->id);

        return [
            'casa' => [
                'id' => $casa->id,
                'identificador' => $casa->identificador,
                'direccion' => trim(implode(' ', array_filter([$casa->calle, $casa->numero_exterior]))),
                'condominio' => $casa->condominio?->nombre_empresa,
                'cuota' => (float) $casa->cuota_mantenimiento,
                'es_responsable' => (bool) $condomino->es_responsable,
            ],
            'cuenta' => $resumen,
            'visitas' => [
                'esperando' => (clone $visitas)->where('estatus', 'ESPERANDO')->count(),
                'dentro' => (clone $visitas)->where('estatus', 'DENTRO')->count(),
                'preautorizadas' => (clone $visitas)->where('estatus', 'PREAUTORIZADA')->count(),
                'mes' => (clone $visitas)->where('fecha_llegada', '>=', now()->startOfMonth())->count(),
            ],
            'ultimas_visitas' => (clone $visitas)->latest('updated_at')->take(5)->get()
                ->map(fn ($v) => $this->visitaCorta($v)),
            'incidencias' => $this->incidenciasRecientes(Incidencia::where('id_usuario_reporta', $usuario->id_usuario)->whereIn('estatus', [...self::INCIDENCIA_ABIERTA, 'ATENDIDA'])),
            'servicios' => SolicitudServicio::where('id_casa', $casa->id)->whereIn('estatus', self::SERVICIO_ABIERTO)
                ->with('atiende:id_usuario,nombre_usuario')->withCount('cotizaciones')->latest()->take(4)->get()
                ->map(fn ($s) => [
                    'id' => $s->id, 'folio' => $s->folio, 'titulo' => $s->titulo, 'tipo' => $s->tipo, 'estatus' => $s->estatus,
                    'fecha' => $s->created_at, 'actualizada' => $s->updated_at, 'atiende' => $s->atiende?->nombre_usuario,
                    'cotizaciones' => $s->cotizaciones_count,
                ]),
        ];
    }

    // ======================================================
    // Vigilancia
    // ======================================================

    private function vigilancia(array $ids): array
    {
        $visitas = Visita::whereIn('id_empresa', $ids)->with('casa:id,identificador');
        $hoy = today();

        // Agenda de pre-autorizadas por la fecha en que la casa dijo que llegan.
        $pre = (clone $visitas)->where('estatus', 'PREAUTORIZADA')
            ->where(fn ($q) => $q->whereNull('fecha_esperada')->orWhere('fecha_esperada', '<=', $hoy->copy()->addDays(7)->toDateString()))
            ->orderBy('fecha_esperada')->get();
        $agenda = ['hoy' => [], 'manana' => [], 'semana' => []];
        foreach ($pre as $v) {
            $f = $v->fecha_esperada;
            $clave = (!$f || $f->lte($hoy)) ? 'hoy' : ($f->isSameDay($hoy->copy()->addDay()) ? 'manana' : 'semana');
            $agenda[$clave][] = $this->visitaCorta($v);
        }

        return [
            'kpis' => [
                'dentro' => (clone $visitas)->where('estatus', 'DENTRO')->count(),
                'por_entrar' => (clone $visitas)->whereIn('estatus', ['ESPERANDO', 'AUTORIZADA'])->count(),
                'preautorizadas_hoy' => count($agenda['hoy']),
                'llegadas_hoy' => (clone $visitas)->where('fecha_llegada', '>=', $hoy)->count(),
                'salidas_hoy' => (clone $visitas)->where('fecha_salida', '>=', $hoy)->count(),
                'rechazadas_hoy' => (clone $visitas)->where('estatus', 'RECHAZADA')->where('fecha_respuesta', '>=', $hoy)->count(),
            ],
            'agenda' => $agenda,
            // Cola: quienes ya entraron, el que lleva más tiempo dentro primero.
            'dentro' => (clone $visitas)->where('estatus', 'DENTRO')->orderBy('fecha_entrada')->take(15)->get()->map(fn ($v) => $this->visitaCorta($v)),
            // Por entrar: esperando respuesta de la casa o ya autorizadas en la pluma.
            'por_entrar' => (clone $visitas)->whereIn('estatus', ['ESPERANDO', 'AUTORIZADA'])->orderBy('fecha_llegada')->take(15)->get()->map(fn ($v) => $this->visitaCorta($v)),
            'salieron' => (clone $visitas)->where('estatus', 'SALIO')->where('fecha_salida', '>=', $hoy)->latest('fecha_salida')->take(20)->get()->map(fn ($v) => $this->visitaCorta($v)),
            'por_hora' => $this->llegadasPorHora($ids),
        ];
    }

    /** Llegadas de hoy agrupadas por hora (6:00 a 23:00) para la gráfica de la caseta. */
    private function llegadasPorHora(array $ids): array
    {
        $porHora = Visita::whereIn('id_empresa', $ids)->where('fecha_llegada', '>=', today())->get(['fecha_llegada'])
            ->groupBy(fn ($v) => (int) Carbon::parse($v->fecha_llegada)->format('G'))->map->count();

        return collect(range(6, 23))->map(fn ($h) => ['hora' => $h, 'total' => $porHora[$h] ?? 0])->all();
    }

    // ======================================================
    // Mantenimiento
    // ======================================================

    /** Días para atender según prioridad (fecha compromiso de la agenda). */
    private const DIAS_COMPROMISO = ['URGENTE' => 0, 'ALTA' => 1, 'MEDIA' => 3, 'BAJA' => 7];

    private function mantenimiento(Usuario $usuario, array $ids): array
    {
        $incidencias = Incidencia::whereIn('id_empresa', $ids);
        $bajos = Insumo::whereIn('id_empresa', $ids)->where('activo', true)->whereColumn('existencia', '<=', 'stock_minimo');
        $hoy = today();

        // Agenda: cada incidencia abierta con su fecha compromiso (reporte + días por prioridad).
        $agenda = ['hoy' => [], 'manana' => [], 'semana' => [], 'despues' => 0];
        $orden = ['URGENTE', 'ALTA', 'MEDIA', 'BAJA'];
        $abiertas = (clone $incidencias)->whereIn('estatus', self::INCIDENCIA_ABIERTA)
            ->with(['casa:id,identificador', 'asignado:id_usuario,nombre_usuario'])->get()
            ->map(function ($i) use ($hoy, $usuario, $orden) {
                $compromiso = $i->created_at->copy()->startOfDay()->addDays(self::DIAS_COMPROMISO[$i->prioridad] ?? 3);

                return $this->incidenciaCorta($i) + [
                    'compromiso' => $compromiso->toDateString(),
                    'atrasada' => $compromiso->lt($hoy),
                    'mia' => (int) $i->id_usuario_asignado === (int) $usuario->id_usuario,
                    '_orden' => $compromiso->timestamp * 10 + (int) array_search($i->prioridad, $orden, true),
                ];
            })->sortBy('_orden');
        foreach ($abiertas as $i) {
            $f = Carbon::parse($i['compromiso']);
            unset($i['_orden']);
            if ($f->lte($hoy)) {
                $agenda['hoy'][] = $i;
            } elseif ($f->isSameDay($hoy->copy()->addDay())) {
                $agenda['manana'][] = $i;
            } elseif ($f->lte($hoy->copy()->addDays(7))) {
                $agenda['semana'][] = $i;
            } else {
                $agenda['despues']++;
            }
        }

        // Insumos: primero los agotados, luego los que tienen menos existencia respecto a su mínimo.
        $insumos = (clone $bajos)->get(['id', 'nombre', 'categoria', 'unidad', 'ubicacion', 'existencia', 'stock_minimo'])
            ->map(fn ($x) => [
                'id' => $x->id,
                'nombre' => $x->nombre,
                'categoria' => Insumo::CATEGORIAS[$x->categoria] ?? $x->categoria,
                'unidad' => Insumo::UNIDADES[$x->unidad] ?? $x->unidad,
                'ubicacion' => $x->ubicacion,
                'existencia' => (float) $x->existencia,
                'minimo' => (float) $x->stock_minimo,
                'nivel' => $x->stock_minimo > 0 ? (int) max(0, min(100, round($x->existencia / $x->stock_minimo * 100))) : 0,
            ])
            ->sortBy([['nivel', 'asc'], ['existencia', 'asc']])->values()->take(15);

        return [
            'kpis' => [
                'pendientes' => (clone $incidencias)->where('estatus', 'PENDIENTE')->count(),
                'en_proceso' => (clone $incidencias)->where('estatus', 'EN PROCESO')->count(),
                'mias' => (clone $incidencias)->where('id_usuario_asignado', $usuario->id_usuario)->whereIn('estatus', self::INCIDENCIA_ABIERTA)->count(),
                'urgentes' => (clone $incidencias)->whereIn('estatus', self::INCIDENCIA_ABIERTA)->whereIn('prioridad', ['ALTA', 'URGENTE'])->count(),
                'atendidas_mes' => (clone $incidencias)->whereIn('estatus', ['ATENDIDA', 'SUPERVISADA'])->where('fecha_atencion', '>=', now()->startOfMonth())->count(),
                'insumos_bajos' => (clone $bajos)->count(),
            ],
            'agenda' => $agenda,
            'insumos' => $insumos,
        ];
    }

    // ======================================================
    // Comunes
    // ======================================================

    private function alertasActivas(array $ids): array
    {
        if (!Gate::allows('ver-alertas')) {
            return [];
        }

        return AlertaSeguridad::whereIn('id_empresa', $ids)->where('estatus', 'ACTIVA')->with('casa:id,identificador')->latest()->take(5)->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'tipo' => AlertaSeguridad::TIPOS[$a->tipo]['label'] ?? $a->tipo,
                'ubicacion' => $a->ubicacion,
                'casa' => $a->anonima ? null : $a->casa?->identificador,
                'fecha' => $a->created_at,
            ])->all();
    }

    private function incidenciasRecientes($query, int $cuantas = 5)
    {
        return $query->with(['casa:id,identificador', 'asignado:id_usuario,nombre_usuario'])->latest()->take($cuantas)->get()
            ->map(fn ($i) => $this->incidenciaCorta($i));
    }

    private function incidenciaCorta(Incidencia $i): array
    {
        return [
            'id' => $i->id,
            'folio' => $i->folio,
            'titulo' => $i->titulo,
            'estatus' => $i->estatus,
            'prioridad' => $i->prioridad,
            'categoria' => Incidencia::CATEGORIAS[$i->categoria] ?? $i->categoria,
            'ubicacion' => $i->ubicacion,
            'casa' => $i->casa?->identificador,
            'asignado' => $i->asignado?->nombre_usuario,
            'fecha' => $i->created_at,
            'actualizada' => $i->updated_at,
        ];
    }

    private function visitaCorta(Visita $v): array
    {
        return [
            'id' => $v->id,
            'nombre' => $v->nombre_visitante,
            'tipo' => $v->tipo_visita,
            'estatus' => $v->estatus,
            'casa' => $v->casa?->identificador,
            'placas' => $v->placas,
            'llegada' => $v->fecha_llegada,
            'entrada' => $v->fecha_entrada,
            'esperada' => $v->fecha_esperada?->format('Y-m-d'),
            'salida' => $v->fecha_salida,
            'motivo' => $v->motivo,
            'personas' => $v->num_personas,
            'vehiculo' => $v->vehiculo,
        ];
    }
}
