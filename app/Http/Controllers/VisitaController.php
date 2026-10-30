<?php

namespace App\Http\Controllers;

use App\Services\NotificacionesPush;
use App\Models\Casa;
use App\Models\Empresa;
use App\Models\Visita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Control de accesos.
 *
 *  Caseta (vigilancia): registra la llegada → la casa autoriza o rechaza →
 *  vigilancia marca la entrada y después la salida.
 *  Casa (condómino): autoriza / rechaza, pre-autoriza visitas esperadas y ve
 *  el historial de visitas de su casa.
 */
class VisitaController extends Controller
{
    // ======================================================
    // 🛡️ CASETA
    // ======================================================

    public function caseta()
    {
        if (!Gate::allows('operar-caseta')) {
            return redirect()->route('dashboard')->with('error', 'No tienes acceso a la caseta');
        }

        $ids = auth()->user()->idsCondominios();

        return Inertia::render('Seguridad/Caseta', [
            'condominios' => Empresa::whereIn('id', $ids)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'casas' => Casa::whereIn('id_empresa', $ids)->where('activo', true)
                ->with('responsable')->orderBy('identificador')->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'id_empresa' => $c->id_empresa,
                    'etiqueta' => $c->identificador . ($c->responsable ? ' · ' . $c->responsable->nombre_completo : '') . ($c->calle ? ' · ' . $c->calle : ''),
                ]),
            'tipos' => Visita::TIPOS,
            'estatus' => Visita::ESTATUS,
        ]);
    }

    public function registrarLlegada(Request $request)
    {
        abort_unless(Gate::allows('operar-caseta'), 403);

        $data = $request->validate([
            'id_casa' => ['required', 'integer', 'exists:casas,id'],
            'tipo_visita' => ['required', Rule::in(array_keys(Visita::TIPOS))],
            'nombre_visitante' => ['required', 'string', 'max:150'],
            'identificacion' => ['nullable', 'string', 'max:100'],
            'placas' => ['nullable', 'string', 'max:20'],
            'vehiculo' => ['nullable', 'string', 'max:100'],
            'num_personas' => ['required', 'integer', 'min:1', 'max:50'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'foto' => ['nullable', 'image', 'max:8192'],
            'sin_autorizacion' => ['boolean'],
        ], [
            'id_casa.required' => '¿A qué casa va?',
            'nombre_visitante.required' => 'Captura el nombre de quien llega.',
        ]);

        $casa = Casa::findOrFail($data['id_casa']);
        abort_unless(auth()->user()->puedeVerCondominio($casa->id_empresa), 403);

        // Paquetería o accesos que la casa no necesita aprobar entran autorizados.
        $directo = $request->boolean('sin_autorizacion');

        // array_merge (no `+`): las placas normalizadas deben reemplazar a las capturadas.
        $visita = Visita::create(array_merge(collect($data)->except(['foto', 'sin_autorizacion'])->all(), [
            'id_empresa' => $casa->id_empresa,
            'placas' => isset($data['placas']) ? strtoupper(preg_replace('/[\s-]+/', '', $data['placas'])) : null,
            'estatus' => $directo ? 'AUTORIZADA' : 'ESPERANDO',
            'id_usuario_registro' => auth()->id(),
            'fecha_llegada' => now(),
            'id_usuario_respuesta' => $directo ? auth()->id() : null,
            'fecha_respuesta' => $directo ? now() : null,
            'comentario_respuesta' => $directo ? 'Sin autorización de la casa (registrado en caseta)' : null,
            'foto' => $request->hasFile('foto') ? $request->file('foto')->store("visitas/{$casa->id_empresa}", 'local') : null,
        ]));

        // La casa recibe el aviso en su celular aunque tenga la app cerrada.
        if ($visita->estatus === 'ESPERANDO') {
            app(NotificacionesPush::class)->visitaEnCaseta($visita);
        }

        return response()->json(['visita' => $visita->fresh(['casa'])->aArreglo()]);
    }

    /** Entrada: autorizada o pre-autorizada → dentro. */
    public function entrada(Visita $visita)
    {
        $this->autorizarCaseta($visita);
        abort_unless(in_array($visita->estatus, ['AUTORIZADA', 'PREAUTORIZADA'], true), 422, 'Sólo entra una visita autorizada o pre-autorizada.');

        $visita->update([
            'estatus' => 'DENTRO',
            'fecha_llegada' => $visita->fecha_llegada ?? now(),
            'id_usuario_registro' => $visita->id_usuario_registro ?? auth()->id(),
            'id_usuario_entrada' => auth()->id(),
            'fecha_entrada' => now(),
        ]);

        return response()->json(['visita' => $visita->aArreglo()]);
    }

    public function salida(Visita $visita)
    {
        $this->autorizarCaseta($visita);
        abort_unless($visita->estatus === 'DENTRO', 422, 'La visita no está dentro.');

        $visita->update(['estatus' => 'SALIO', 'id_usuario_salida' => auth()->id(), 'fecha_salida' => now()]);

        return response()->json(['visita' => $visita->aArreglo()]);
    }

    // ======================================================
    // 🏠 CASA (condómino)
    // ======================================================

    public function misVisitas()
    {
        $casa = $this->miCasa();
        if (!$casa) {
            return redirect()->route('casas.mi');
        }

        return Inertia::render('Seguridad/MisVisitas', [
            'casa' => $casa->only(['id', 'identificador']),
            'tipos' => Visita::TIPOS,
            'estatus' => Visita::ESTATUS,
        ]);
    }

    /** La casa autoriza o rechaza a quien está en caseta. */
    public function responder(Request $request, Visita $visita)
    {
        $casa = $this->miCasa();
        abort_unless($casa && $casa->id === $visita->id_casa, 403, 'Sólo la casa destino puede responder.');
        abort_unless($visita->estatus === 'ESPERANDO', 422, 'Esta visita ya fue respondida.');

        // Se acepta 'autorizar' o 'AUTORIZAR'.
        $request->merge(['accion' => strtolower((string) $request->input('accion'))]);
        $data = $request->validate([
            'accion' => ['required', Rule::in(['autorizar', 'rechazar'])],
            'comentario' => ['nullable', 'string', 'max:255'],
        ]);

        $visita->update([
            'estatus' => $data['accion'] === 'autorizar' ? 'AUTORIZADA' : 'RECHAZADA',
            'id_usuario_respuesta' => auth()->id(),
            'fecha_respuesta' => now(),
            'comentario_respuesta' => $data['comentario'] ?? null,
        ]);
        app(NotificacionesPush::class)->visitaRespondida($visita->load('casa'));

        return response()->json(['visita' => $visita->aArreglo()]);
    }

    public function preautorizar(Request $request)
    {
        $casa = $this->miCasa();
        abort_unless($casa, 403, 'Tu usuario no está ligado a una casa.');

        $data = $request->validate([
            'tipo_visita' => ['required', Rule::in(array_keys(Visita::TIPOS))],
            'nombre_visitante' => ['required', 'string', 'max:150'],
            'placas' => ['nullable', 'string', 'max:20'],
            'vehiculo' => ['nullable', 'string', 'max:100'],
            'num_personas' => ['required', 'integer', 'min:1', 'max:50'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'fecha_esperada' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'nombre_visitante.required' => 'Captura el nombre de tu visita.',
            'fecha_esperada.after_or_equal' => 'La fecha no puede ser pasada.',
        ]);

        $visita = Visita::create(array_merge($data, [
            'id_empresa' => $casa->id_empresa,
            'id_casa' => $casa->id,
            'placas' => isset($data['placas']) ? strtoupper(preg_replace('/[\s-]+/', '', $data['placas'])) : null,
            'estatus' => 'PREAUTORIZADA',
            'id_usuario_respuesta' => auth()->id(),
            'fecha_respuesta' => now(),
        ]));

        return response()->json(['visita' => $visita->fresh(['casa'])->aArreglo()]);
    }

    /** Cancela: la caseta cualquier visita sin entrar; la casa, sus pre-autorizadas. */
    public function cancelar(Visita $visita)
    {
        $casa = $this->miCasa();
        $esCasa = $casa && $casa->id === $visita->id_casa && $visita->estatus === 'PREAUTORIZADA';

        if (!$esCasa) {
            $this->autorizarCaseta($visita);
            abort_unless(in_array($visita->estatus, ['ESPERANDO', 'AUTORIZADA', 'PREAUTORIZADA', 'RECHAZADA'], true), 422, 'Esta visita ya no se puede cancelar.');
        }

        $visita->update(['estatus' => 'CANCELADA']);

        return response()->json(['visita' => $visita->aArreglo()]);
    }

    // ======================================================
    // 📋 LISTADOS (JSON) Y PULSO
    // ======================================================

    /**
     * Visitas para la caseta (sus condominios) o para la casa del condómino.
     * Sin fecha: las activas (pre-autorizadas vigentes, esperando, autorizadas,
     * dentro) más todo lo del día. Con fecha: la bitácora de ese día.
     */
    /**
     * Placa leída en caseta: ¿ya había venido? Regresa su última visita para
     * llenar el vehículo y sugerir el nombre y la casa.
     */
    public function porPlaca(string $placa)
    {
        abort_unless(Gate::allows('operar-caseta'), 403);

        $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));
        if (strlen($placa) < 4) {
            return response()->json(['visita' => null]);
        }

        $visita = Visita::with('casa:id,identificador')
            ->whereIn('id_empresa', auth()->user()->idsCondominios())
            ->where('placas', $placa)
            ->latest('id')
            ->first();

        return response()->json(['visita' => $visita ? [
            'nombre_visitante' => $visita->nombre_visitante,
            'vehiculo' => $visita->vehiculo,
            'id_casa' => $visita->id_casa,
            'casa' => $visita->casa?->identificador,
            'tipo_visita' => $visita->tipo_visita,
            'fecha' => $visita->created_at?->toIso8601String(),
            'visitas' => Visita::whereIn('id_empresa', auth()->user()->idsCondominios())->where('placas', $placa)->count(),
        ] : null]);
    }

    public function lista(Request $request)
    {
        $usuario = auth()->user();
        $query = Visita::with([
            'casa:id,identificador',
            'registro:id_usuario,nombre_usuario', 'respuesta:id_usuario,nombre_usuario',
            'entrada:id_usuario,nombre_usuario', 'salida:id_usuario,nombre_usuario',
        ]);

        if (Gate::allows('operar-caseta')) {
            $query->whereIn('id_empresa', $usuario->idsCondominios());
            if ($request->filled('id_empresa')) {
                $query->where('id_empresa', $request->id_empresa);
            }
        } else {
            $casa = $this->miCasa();
            abort_unless($casa, 403);
            $query->where('id_casa', $casa->id);
        }

        if ($request->filled('fecha')) {
            $fecha = $request->fecha;
            $query->where(fn ($q) => $q->whereDate('fecha_llegada', $fecha)
                ->orWhere(fn ($p) => $p->whereNull('fecha_llegada')->whereDate('fecha_esperada', $fecha)));
        } else {
            $hoy = today()->toDateString();
            $query->where(fn ($q) => $q->whereIn('estatus', ['ESPERANDO', 'AUTORIZADA', 'DENTRO'])
                ->orWhere(fn ($p) => $p->where('estatus', 'PREAUTORIZADA')->whereDate('fecha_esperada', '>=', $hoy))
                ->orWhereDate('fecha_llegada', $hoy));
        }
        if ($request->filled('estatus')) {
            $query->where('estatus', $request->estatus);
        }
        if ($request->filled('id_casa')) {
            $query->where('id_casa', $request->id_casa);
        }
        if ($request->filled('buscar')) {
            $buscar = '%' . $request->buscar . '%';
            $placas = '%' . strtoupper(preg_replace('/[\s-]+/', '', $request->buscar)) . '%';
            $query->where(fn ($q) => $q->where('nombre_visitante', 'LIKE', $buscar)
                ->orWhere('placas', 'LIKE', $placas)
                ->orWhere('vehiculo', 'LIKE', $buscar));
        }

        $visitas = $query
            // Primero lo que requiere acción: esperando > autorizada > dentro > pre-autorizada.
            ->orderByRaw("FIELD(estatus, 'PREAUTORIZADA', 'DENTRO', 'AUTORIZADA', 'ESPERANDO') DESC")
            ->orderByDesc('updated_at')
            ->limit(300)
            ->get();

        return response()->json(['visitas' => $visitas->map(fn ($v) => $v->aArreglo())]);
    }

    public function foto(Visita $visita)
    {
        $casa = $this->miCasa();
        if (!($casa && $casa->id === $visita->id_casa)) {
            $this->autorizarCaseta($visita);
        }
        abort_unless($visita->foto && Storage::disk('local')->exists($visita->foto), 404);

        return Storage::disk('local')->response($visita->foto);
    }

    // ======================================================
    // 🔒 HELPERS
    // ======================================================

    private function miCasa(): ?Casa
    {
        $usuario = auth()->user();
        $condomino = $usuario->esCondomino() ? $usuario->condomino : null;

        return ($condomino && $condomino->activo) ? $condomino->casa : null;
    }

    private function autorizarCaseta(Visita $visita): void
    {
        abort_unless(Gate::allows('operar-caseta') && auth()->user()->puedeVerCondominio($visita->id_empresa), 403);
    }
}
