<?php

namespace App\Http\Controllers;

use App\Models\Casa;
use App\Models\CasaFoto;
use App\Models\Condomino;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Casas / propiedades de los condominios y sus condóminos.
 *
 * El condominio es la empresa de RIC: un usuario sólo ve las casas de los
 * condominios que tiene asignados (empresas_usuarios); el SUPERUSUARIO ve todo.
 */
class CasaController extends Controller
{
    // ======================================================
    // 🏠 CASAS
    // ======================================================

    public function index(Request $request)
    {
        if (!Gate::allows('ver-casas')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para ver las casas');
        }

        $usuario = auth()->user();
        $query = Casa::visiblesPara($usuario)
            ->with(['condominio:id,nombre_empresa', 'responsable', 'registro:id_usuario,nombre_usuario', 'modifico:id_usuario,nombre_usuario'])
            ->withCount(['condominos' => fn ($q) => $q->where('activo', true), 'fotos']);

        if ($request->filled('id_empresa')) {
            $query->where('id_empresa', $request->id_empresa);
        }
        if ($request->filled('search')) {
            $query->where('identificador', 'LIKE', '%' . $request->search . '%');
        }
        if ($request->filled('direccion')) {
            $query->search($request->direccion);
        }
        // Un solo filtro para la columna agrupada "Responsable / contacto": nombre, teléfono o correo.
        if ($request->filled('responsable')) {
            $buscar = '%' . $request->responsable . '%';
            $query->whereHas('condominos', function ($q) use ($buscar) {
                $q->where('activo', true)->where(fn ($w) => $w
                    ->where(DB::raw("CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno)"), 'LIKE', $buscar)
                    ->orWhere('telefono', 'LIKE', $buscar)
                    ->orWhere('correo', 'LIKE', $buscar));
            });
        }
        if ($request->filled('contacto')) {
            $query->whereHas('condominos', function ($q) use ($request) {
                $q->where('telefono', 'LIKE', '%' . $request->contacto . '%')
                  ->orWhere('correo', 'LIKE', '%' . $request->contacto . '%');
            });
        }
        if ($request->filled('estatus_ocupacion')) {
            $query->where('estatus_ocupacion', $request->estatus_ocupacion);
        }
        if ($request->filled('gps')) {
            $request->gps === 'con'
                ? $query->whereNotNull('latitud')->whereNotNull('longitud')
                : $query->where(fn ($q) => $q->whereNull('latitud')->orWhereNull('longitud'));
        }
        if ($request->filled('activo')) {
            $query->where('activo', $request->activo === 'true');
        }

        $casas = $query->orderBy('id_empresa')
            ->orderBy('identificador')
            ->paginate(15)
            ->withQueryString();

        $base = Casa::visiblesPara($usuario);
        $stats = [
            'total' => (clone $base)->count(),
            'habitadas' => (clone $base)->where('estatus_ocupacion', 'HABITADA')->count(),
            'con_gps' => (clone $base)->whereNotNull('latitud')->whereNotNull('longitud')->count(),
            'sin_responsable' => (clone $base)->where('activo', true)->whereDoesntHave('responsable')->count(),
        ];

        return Inertia::render('Casas/Index', [
            'casas' => $casas,
            'stats' => $stats,
            'condominios' => $this->condominiosVisibles(),
            'estatusOcupacion' => Casa::ESTATUS_OCUPACION,
            'filtros' => $request->only(['id_empresa', 'search', 'direccion', 'responsable', 'contacto', 'estatus_ocupacion', 'gps', 'activo']),
        ]);
    }

    public function create()
    {
        if (!Gate::allows('gestionar-casas')) {
            return redirect()->route('casas.index')->with('error', 'No tienes permiso para registrar casas');
        }

        return Inertia::render('Casas/Form', [
            'casa' => null,
            'condominios' => $this->condominiosVisibles(),
            'centros' => $this->centrosCondominio(),
            'tiposPropiedad' => Casa::TIPOS_PROPIEDAD,
            'estatusOcupacion' => Casa::ESTATUS_OCUPACION,
        ]);
    }

    public function store(Request $request)
    {
        if (!Gate::allows('gestionar-casas')) {
            abort(403);
        }

        $data = $this->validarCasa($request);
        $this->autorizarCondominio($data['id_empresa']);

        $data['id_usuario_registro'] = auth()->id();
        $data['id_usuario_modifico'] = auth()->id();
        $casa = Casa::create($data);

        return redirect()->route('casas.show', $casa->id)
            ->with('success', "Casa {$casa->identificador} registrada. Ahora agrega a sus condóminos.");
    }

    public function show(Casa $casa)
    {
        $this->autorizarVerCasa($casa);

        $casa->load([
            'condominio',
            'registro:id_usuario,nombre_usuario,nombre_completo',
            'modifico:id_usuario,nombre_usuario,nombre_completo',
            'fotos.subio:id_usuario,nombre_usuario',
            'condominos' => fn ($q) => $q->orderByDesc('activo')->orderByDesc('es_responsable')->orderBy('nombre'),
            'condominos.usuario:id_usuario,nombre_usuario,activo',
            'condominos.registro:id_usuario,nombre_usuario',
            'condominos.modifico:id_usuario,nombre_usuario',
        ]);

        // Contratos / convenios de la casa: privados (la casa y la administración).
        $verDocumentos = $this->esMiCasa($casa) || Gate::allows('atender-servicios');

        return Inertia::render('Casas/Show', [
            'casa' => $casa,
            'documentos' => $verDocumentos
                ? \App\Models\DocumentoGenerado::where('id_casa', $casa->id)->latest()->get(['id', 'titulo', 'created_at'])
                : null,
            'puedeEditar' => Gate::allows('gestionar-casas'),
            'puedeActualizarCasa' => $this->puedeActualizarCasa($casa),
            'esMiCasa' => $this->esMiCasa($casa),
            'tiposPropiedad' => Casa::TIPOS_PROPIEDAD,
            'estatusOcupacion' => Casa::ESTATUS_OCUPACION,
            'tiposCondomino' => Condomino::TIPOS,
            'tiposFoto' => CasaFoto::TIPOS,
        ]);
    }

    public function edit(Casa $casa)
    {
        if (!Gate::allows('gestionar-casas')) {
            return redirect()->route('casas.show', $casa->id)->with('error', 'No tienes permiso para editar casas');
        }
        $this->autorizarVerCasa($casa);
        $casa->load(['registro:id_usuario,nombre_usuario', 'modifico:id_usuario,nombre_usuario']);

        return Inertia::render('Casas/Form', [
            'casa' => $casa,
            'condominios' => $this->condominiosVisibles(),
            'centros' => $this->centrosCondominio(),
            'tiposPropiedad' => Casa::TIPOS_PROPIEDAD,
            'estatusOcupacion' => Casa::ESTATUS_OCUPACION,
        ]);
    }

    public function update(Request $request, Casa $casa)
    {
        if (!Gate::allows('gestionar-casas')) {
            abort(403);
        }
        $this->autorizarVerCasa($casa);

        $data = $this->validarCasa($request, $casa);
        $this->autorizarCondominio($data['id_empresa']);

        $data['id_usuario_modifico'] = auth()->id();
        $casa->update($data);

        return redirect()->route('casas.show', $casa->id)->with('success', "Casa {$casa->identificador} actualizada.");
    }

    public function toggleActivo(Casa $casa)
    {
        if (!Gate::allows('gestionar-casas')) {
            abort(403);
        }
        $this->autorizarVerCasa($casa);

        $casa->update(['activo' => !$casa->activo, 'id_usuario_modifico' => auth()->id()]);

        return back()->with('success', "Casa {$casa->identificador} " . ($casa->activo ? 'activada' : 'desactivada') . '.');
    }

    /**
     * Fija la ubicación GPS. También lo puede hacer el condómino de la casa
     * (por ejemplo, con "usar mi ubicación" desde su celular).
     */
    public function actualizarUbicacion(Request $request, Casa $casa)
    {
        if (!$this->puedeActualizarCasa($casa)) {
            abort(403);
        }

        $data = $request->validate([
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
        ]);

        $casa->update($data + ['id_usuario_modifico' => auth()->id()]);

        return back()->with('success', 'Ubicación de la casa actualizada.');
    }

    /**
     * "Mi casa": el condómino entra directo a la ficha de su casa.
     */
    public function miCasa()
    {
        $condomino = auth()->user()->condomino;

        if (!$condomino || !$condomino->activo) {
            return Inertia::render('Casas/SinCasa');
        }

        return redirect()->route('casas.show', $condomino->id_casa);
    }

    // ======================================================
    // 📷 FOTOS
    // ======================================================

    public function subirFoto(Request $request, Casa $casa)
    {
        if (!$this->puedeActualizarCasa($casa)) {
            abort(403);
        }

        $data = $request->validate([
            'foto' => ['required', 'image', 'max:8192'],
            'tipo' => ['required', Rule::in(array_keys(CasaFoto::TIPOS))],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'foto.image' => 'El archivo debe ser una imagen (JPG, PNG, WEBP).',
            'foto.max' => 'La imagen no puede pesar más de 8 MB.',
        ]);

        $archivo = $request->file('foto');
        $casa->fotos()->create([
            'tipo' => $data['tipo'],
            'descripcion' => $data['descripcion'] ?? null,
            'ruta' => $archivo->store("casas/{$casa->id}", 'public'),
            'nombre_original' => $archivo->getClientOriginalName(),
            'id_usuario_subio' => auth()->id(),
        ]);

        return back()->with('success', 'Foto agregada.');
    }

    public function eliminarFoto(CasaFoto $foto)
    {
        $casa = $foto->casa;
        $esSuya = $foto->id_usuario_subio === auth()->id() && $this->puedeActualizarCasa($casa);

        if (!Gate::allows('gestionar-casas') && !$esSuya) {
            abort(403);
        }
        $this->autorizarVerCasa($casa);

        Storage::disk('public')->delete($foto->ruta);
        $foto->delete();

        return back()->with('success', 'Foto eliminada.');
    }

    // ======================================================
    // 👥 CONDÓMINOS
    // ======================================================

    public function storeCondomino(Request $request, Casa $casa)
    {
        if (!Gate::allows('gestionar-casas')) {
            abort(403);
        }
        $this->autorizarVerCasa($casa);

        $data = $this->validarCondomino($request);

        DB::transaction(function () use ($request, $casa, $data) {
            $condomino = new Condomino($this->datosCondomino($data));
            $condomino->id_casa = $casa->id;
            $condomino->id_usuario_registro = auth()->id();
            $condomino->id_usuario_modifico = auth()->id();

            if ($request->hasFile('foto_archivo')) {
                $condomino->foto = $request->file('foto_archivo')->store("casas/{$casa->id}/condominos", 'public');
            }
            $condomino->save();

            $this->sincronizarAcceso($condomino, $data);
            $this->unicoResponsable($condomino);
        });

        return back()->with('success', 'Condómino agregado.');
    }

    public function updateCondomino(Request $request, Condomino $condomino)
    {
        if (!Gate::allows('gestionar-casas')) {
            abort(403);
        }
        $this->autorizarVerCasa($condomino->casa);

        $data = $this->validarCondomino($request, $condomino);

        DB::transaction(function () use ($request, $condomino, $data) {
            $condomino->fill($this->datosCondomino($data));
            $condomino->id_usuario_modifico = auth()->id();

            if ($request->hasFile('foto_archivo')) {
                if ($condomino->foto) {
                    Storage::disk('public')->delete($condomino->foto);
                }
                $condomino->foto = $request->file('foto_archivo')->store("casas/{$condomino->id_casa}/condominos", 'public');
            }
            $condomino->save();

            $this->sincronizarAcceso($condomino, $data);
            $this->unicoResponsable($condomino);
        });

        return back()->with('success', 'Datos del condómino actualizados.');
    }

    /**
     * Activa / da de baja al condómino. Si tiene acceso al sistema, su usuario
     * sigue el mismo estado (un condómino dado de baja ya no entra a la app).
     */
    public function toggleCondomino(Condomino $condomino)
    {
        if (!Gate::allows('gestionar-casas')) {
            abort(403);
        }
        $this->autorizarVerCasa($condomino->casa);

        DB::transaction(function () use ($condomino) {
            $condomino->activo = !$condomino->activo;
            if (!$condomino->activo) {
                $condomino->es_responsable = false;
            }
            $condomino->id_usuario_modifico = auth()->id();
            $condomino->save();

            $condomino->usuario?->update(['activo' => $condomino->activo]);
        });

        return back()->with('success', 'Condómino ' . ($condomino->activo ? 'reactivado' : 'dado de baja') . '.');
    }

    // ======================================================
    // 🔒 HELPERS
    // ======================================================

    private function condominiosVisibles()
    {
        $usuario = auth()->user();
        $query = $usuario->esSuperUsuario() ? Empresa::query() : $usuario->empresas();

        return $query->where('empresas.activo', true)
            ->orderBy('nombre_empresa')
            ->get(['empresas.id', 'empresas.nombre_empresa']);
    }

    /**
     * Punto medio de las casas con GPS de cada condominio, para que el mapa de
     * una casa nueva abra ya sobre el condominio y no sobre todo México.
     */
    private function centrosCondominio(): array
    {
        return Casa::centros(auth()->user()->idsCondominios());
    }

    private function autorizarCondominio($empresaId): void
    {
        if (!$this->condominiosVisibles()->contains('id', (int) $empresaId)) {
            abort(403, 'No tienes asignado ese condominio.');
        }
    }

    private function esMiCasa(Casa $casa): bool
    {
        $condomino = auth()->user()->condomino;

        return auth()->user()->esCondomino()
            && $condomino
            && $condomino->activo
            && $condomino->id_casa === $casa->id;
    }

    private function autorizarVerCasa(Casa $casa): void
    {
        if ($this->esMiCasa($casa)) {
            return;
        }

        if (!Gate::allows('ver-casas') || !Casa::visiblesPara(auth()->user())->whereKey($casa->id)->exists()) {
            abort(403, 'No tienes acceso a esta casa.');
        }
    }

    /** Fotos y GPS: administración o el condómino de la propia casa. */
    private function puedeActualizarCasa(Casa $casa): bool
    {
        if ($this->esMiCasa($casa)) {
            return true;
        }

        return Gate::allows('gestionar-casas')
            && Casa::visiblesPara(auth()->user())->whereKey($casa->id)->exists();
    }

    private function validarCasa(Request $request, ?Casa $casa = null): array
    {
        return $request->validate([
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'identificador' => [
                'required', 'string', 'max:30',
                Rule::unique('casas', 'identificador')
                    ->where('id_empresa', $request->input('id_empresa'))
                    ->ignore($casa?->id),
            ],
            'tipo_propiedad' => ['required', Rule::in(array_keys(Casa::TIPOS_PROPIEDAD))],
            'manzana' => ['nullable', 'string', 'max:20'],
            'lote' => ['nullable', 'string', 'max:20'],
            'calle' => ['nullable', 'string', 'max:100'],
            'numero_exterior' => ['nullable', 'string', 'max:20'],
            'numero_interior' => ['nullable', 'string', 'max:20'],
            'referencias' => ['nullable', 'string', 'max:1000'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
            'estatus_ocupacion' => ['required', Rule::in(array_keys(Casa::ESTATUS_OCUPACION))],
            'cuota_mantenimiento' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ], [
            'id_empresa.required' => 'Selecciona el condominio.',
            'identificador.required' => 'Captura el identificador de la casa.',
            'identificador.unique' => 'Ya existe una casa con ese identificador en el condominio.',
        ]);
    }

    private function validarCondomino(Request $request, ?Condomino $condomino = null): array
    {
        $idUsuario = $condomino?->id_usuario;
        $creaAcceso = $request->boolean('crear_acceso') && !$idUsuario;

        return $request->validate([
            'tipo' => ['required', Rule::in(array_keys(Condomino::TIPOS))],
            'es_responsable' => ['boolean'],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['nullable', 'string', 'max:60'],
            'apellido_materno' => ['nullable', 'string', 'max:60'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'telefono_alterno' => ['nullable', 'string', 'max:20'],
            'correo' => array_filter([
                $creaAcceso ? 'required' : 'nullable', 'email', 'max:100',
                // El correo es el de su usuario: no puede repetirse en `usuarios`.
                ($creaAcceso || $idUsuario) ? Rule::unique('usuarios', 'email')->ignore($idUsuario, 'id_usuario') : null,
            ]),
            'emergencia_nombre' => ['nullable', 'string', 'max:150'],
            'emergencia_telefono' => ['nullable', 'string', 'max:20'],
            'emergencia_parentesco' => ['nullable', 'string', 'max:50'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'foto_archivo' => ['nullable', 'image', 'max:5120'],
            'crear_acceso' => ['boolean'],
            'nombre_usuario' => [
                $creaAcceso ? 'required' : 'nullable', 'string', 'max:50',
                Rule::unique('usuarios', 'nombre_usuario')->ignore($idUsuario, 'id_usuario'),
            ],
            'password' => [$creaAcceso ? 'required' : 'nullable', 'string', 'min:6'],
        ], [
            'nombre.required' => 'Captura el nombre del condómino.',
            'correo.required' => 'Para darle acceso al sistema se necesita su correo.',
            'correo.unique' => 'Ese correo ya pertenece a otro usuario del sistema.',
            'nombre_usuario.required' => 'Captura el nombre de usuario para su acceso.',
            'nombre_usuario.unique' => 'Ese nombre de usuario ya existe.',
            'password.required' => 'Captura una contraseña inicial.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'foto_archivo.image' => 'La foto debe ser una imagen.',
        ]);
    }

    private function datosCondomino(array $data): array
    {
        return collect($data)->only([
            'tipo', 'es_responsable', 'nombre', 'apellido_paterno', 'apellido_materno',
            'telefono', 'telefono_alterno', 'correo',
            'emergencia_nombre', 'emergencia_telefono', 'emergencia_parentesco', 'notas',
        ])->all();
    }

    /**
     * Crea el usuario CONDOMINO (si se pidió) o mantiene sincronizados sus
     * datos con los del condómino. El usuario queda asignado al condominio
     * para que más adelante vea sus pólizas y estado de resultados.
     */
    private function sincronizarAcceso(Condomino $condomino, array $data): void
    {
        $usuario = $condomino->usuario;

        if (!$usuario && !empty($data['crear_acceso'])) {
            $usuario = Usuario::create([
                'nombre_completo' => $condomino->nombre_completo,
                'nombre_usuario' => $data['nombre_usuario'],
                'email' => $condomino->correo,
                'password_hash' => Hash::make($data['password']),
                'telefono' => $condomino->telefono,
                'tipo_usuario' => 'CONDOMINO',
                'activo' => $condomino->activo,
            ]);
            $condomino->update(['id_usuario' => $usuario->id_usuario]);
        } elseif ($usuario) {
            $cambios = [
                'nombre_completo' => $condomino->nombre_completo,
                'telefono' => $condomino->telefono,
            ];
            if (!empty($condomino->correo)) {
                $cambios['email'] = $condomino->correo;
            }
            if (!empty($data['nombre_usuario'])) {
                $cambios['nombre_usuario'] = $data['nombre_usuario'];
            }
            if (!empty($data['password'])) {
                $cambios['password_hash'] = Hash::make($data['password']);
            }
            $usuario->update($cambios);
        }

        if ($usuario) {
            $usuario->empresas()->syncWithoutDetaching([$condomino->casa->id_empresa]);
        }
    }

    /** Sólo un responsable activo por casa. */
    private function unicoResponsable(Condomino $condomino): void
    {
        if ($condomino->es_responsable) {
            Condomino::where('id_casa', $condomino->id_casa)
                ->where('id', '!=', $condomino->id)
                ->update(['es_responsable' => false]);
        }
    }
}
