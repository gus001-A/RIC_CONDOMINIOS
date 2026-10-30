<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Cuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class EmpresaController extends Controller
{
    /** El superusuario ve todos los condominios; el administrador, sólo los que tiene asignados. */
    private function esSuper(): bool
    {
        return Auth::user()?->tipo_usuario === 'SUPERUSUARIO';
    }

    private function visibles()
    {
        return Empresa::query()->when(!$this->esSuper(), fn ($q) => $q->whereHas('usuarios', fn ($u) => $u->where('usuarios.id_usuario', Auth::id())));
    }

    private function puedeVer(Empresa $empresa): bool
    {
        return $this->esSuper() || $empresa->usuarios()->where('usuarios.id_usuario', Auth::id())->exists();
    }

    public function index(Request $request)
    {
        // ✅ Verificar permiso para ver empresas
        if (!Gate::allows('ver-empresas')) {
            return redirect()->route('dashboard')
                ->with('error', 'No tienes permiso para ver condominios');
        }

        $query = $this->visibles();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('rfc')) {
            $query->where('rfc', 'LIKE', '%' . $request->rfc . '%');
        }

        if ($request->filled('tipo_persona')) {
            $query->where('tipo_persona', $request->tipo_persona);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('contacto')) {
            $query->where(function($q) use ($request) {
                $q->where('correo', 'LIKE', '%' . $request->contacto . '%')
                ->orWhere('telefono_personal', 'LIKE', '%' . $request->contacto . '%')
                ->orWhere('telefono_trabajo', 'LIKE', '%' . $request->contacto . '%');
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->activo === 'true');
        }

        // 🟢 SOLO CARGAR CONTADOR DE USUARIOS (las pólizas no tienen relación directa)
        $empresas = $query->withCount(['usuarios', 'casas'])
                        ->orderBy('id', 'desc')  // 🔄 Cambio: ordenar por ID (más reciente primero)
                        ->paginate(10)
                        ->withQueryString();

        $stats = [
            'total' => $this->visibles()->count(),
            'casas' => \App\Models\Casa::whereIn('id_empresa', $this->visibles()->pluck('id'))->where('activo', true)->count(),
            'activas' => $this->visibles()->where('activo', true)->count(),
            'inactivas' => $this->visibles()->where('activo', false)->count(),
            'fisicas' => $this->visibles()->where('tipo_persona', 'FISICA')->count(),
            'morales' => $this->visibles()->where('tipo_persona', 'MORAL')->count(),
            'estados' => $this->visibles()->distinct('estado')->whereNotNull('estado')->count(),
            'ciudades' => $this->visibles()->distinct('ciudad')->whereNotNull('ciudad')->count(),
        ];

        $estados = $this->visibles()->whereNotNull('estado')
            ->distinct()
            ->orderBy('estado')
            ->pluck('estado')
            ->toArray();

        // ✅ RECOPILAR FLASH MESSAGES
        $flash = [];
        $flashTypes = ['success', 'error', 'info', 'warning', 'updated', 'created', 'deleted'];
        foreach ($flashTypes as $type) {
            if (session()->has($type)) {
                $flash[$type] = session($type);
            }
        }

        return Inertia::render('Empresas/Index', [
            'empresas' => $empresas,
            'stats' => $stats,
            'estados' => $estados,
            'filtros' => $request->all(),
            'flash' => $flash // ✅ PASAR FLASH EXPLÍCITAMENTE
        ]);
    }

    public function create()
    {
        // ✅ Verificar permiso para crear empresas
        if (!Gate::allows('crear-empresas')) {
            return redirect()->route('empresas.index')
                ->with('error', 'No tienes permiso para crear condominios');
        }

        $estadosMexico = [
            'Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche',
            'Chiapas', 'Chihuahua', 'Coahuila', 'Colima', 'Ciudad de México',
            'Durango', 'Estado de México', 'Guanajuato', 'Guerrero', 'Hidalgo',
            'Jalisco', 'Michoacán', 'Morelos', 'Nayarit', 'Nuevo León',
            'Oaxaca', 'Puebla', 'Querétaro', 'Quintana Roo', 'San Luis Potosí',
            'Sinaloa', 'Sonora', 'Tabasco', 'Tamaulipas', 'Tlaxcala',
            'Veracruz', 'Yucatán', 'Zacatecas'
        ];

        // ✅ RECOPILAR FLASH MESSAGES
        $flash = [];
        $flashTypes = ['success', 'error', 'info', 'warning', 'updated', 'created', 'deleted'];
        foreach ($flashTypes as $type) {
            if (session()->has($type)) {
                $flash[$type] = session($type);
            }
        }

        // Mismo formulario para alta y edición (estilo de Casas).
        return Inertia::render('Empresas/Form', ['empresa' => null]);
    }

    public function store(Request $request)
    {
        // ✅ Verificar permiso para crear empresas
        if (!Gate::allows('crear-empresas')) {
            return redirect()->route('empresas.index')
                ->with('error', 'No tienes permiso para crear condominios');
        }

        try {
            $rules = [
                'nombre_empresa' => 'required|string|max:150',
                'rfc' => 'required|string|max:13|unique:empresas,rfc',
                'regimen' => 'nullable|string|max:50',
                'tipo_persona' => 'required|in:FISICA,MORAL',
                'clave' => 'nullable|string|max:20|unique:empresas,clave',
                'calle' => 'nullable|string|max:100',
                'numero_exterior' => 'nullable|string|max:20',
                'numero_interior' => 'nullable|string|max:20',
                'colonia' => 'nullable|string|max:100',
                'ciudad' => 'nullable|string|max:100',
                'municipio' => 'nullable|string|max:100',
                'estado' => 'nullable|string|max:100',
                'codigo_postal' => 'nullable|string|max:10',
                'correo' => 'nullable|email|max:100',
                'telefono_personal' => 'nullable|string|max:20',
                'telefono_trabajo' => 'nullable|string|max:20',
                'extension' => 'nullable|string|max:10',
            ];

            if ($request->tipo_persona === 'MORAL') {
                $rules['representante_nombre'] = 'required|string|max:100';
                $rules['representante_apellido_paterno'] = 'required|string|max:50';
                $rules['representante_apellido_materno'] = 'nullable|string|max:50';
                $rules['representante_rfc'] = 'required|string|max:13';
                $rules['representante_curp'] = 'nullable|string|max:18';
            } else {
                $rules['representante_nombre'] = 'nullable|string|max:100';
                $rules['representante_apellido_paterno'] = 'nullable|string|max:50';
                $rules['representante_apellido_materno'] = 'nullable|string|max:50';
                $rules['representante_rfc'] = 'nullable|string|max:13';
                $rules['representante_curp'] = 'nullable|string|max:18';
            }

            $validated = $request->validate($rules);

            $empresa = Empresa::create([
                'nombre_empresa' => $validated['nombre_empresa'],
                'rfc' => $validated['rfc'],
                'regimen' => $validated['regimen'] ?? null,
                'tipo_persona' => $validated['tipo_persona'],
                'clave' => $validated['clave'] ?? null,
                'calle' => $validated['calle'] ?? null,
                'numero_exterior' => $validated['numero_exterior'] ?? null,
                'numero_interior' => $validated['numero_interior'] ?? null,
                'colonia' => $validated['colonia'] ?? null,
                // La "ciudad" ya no se captura por separado: es igual al municipio.
                'ciudad' => $validated['municipio'] ?? ($validated['ciudad'] ?? null),
                'municipio' => $validated['municipio'] ?? null,
                'estado' => $validated['estado'] ?? null,
                'codigo_postal' => $validated['codigo_postal'] ?? null,
                'correo' => $validated['correo'] ?? null,
                'telefono_personal' => $validated['telefono_personal'] ?? null,
                'telefono_trabajo' => $validated['telefono_trabajo'] ?? null,
                'extension' => $validated['extension'] ?? null,
                'representante_nombre' => $validated['representante_nombre'] ?? null,
                'representante_apellido_paterno' => $validated['representante_apellido_paterno'] ?? null,
                'representante_apellido_materno' => $validated['representante_apellido_materno'] ?? null,
                'representante_rfc' => $validated['representante_rfc'] ?? null,
                'representante_curp' => $validated['representante_curp'] ?? null,
                'activo' => true,
            ]);

            // Quien lo da de alta (si no es superusuario) queda asignado para poder operarlo.
            if (!$this->esSuper()) {
                $empresa->usuarios()->attach(Auth::id(), ['fecha_asignacion' => now()]);
            }

            try {
                DB::transaction(function () use ($empresa) {
                    $this->crearCuentasContables($empresa);
                });
            } catch (\Exception $e) {
                $empresa->delete();
                return redirect()->route('empresas.index')
                    ->with('error', 'Error al crear las cuentas contables: ' . $e->getMessage());
            }

            return redirect()->route('empresas.index')
                ->with('success', 'Condominio "' . $empresa->nombre_empresa . '" creado exitosamente con sus cuentas contables');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->with('error', 'Error de validación: ' . implode(', ', \Illuminate\Support\Arr::flatten($e->errors())))
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error al crear el condominio: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Crear todas las cuentas contables para una empresa
     * ⚠️ MODIFICADO: Naturaleza = null, en_uso = false para todas las cuentas
     */
    private function crearCuentasContables($empresa)
    {
        $idEmpresa = $empresa->id;
        $nombreEmpresa = $empresa->nombre_empresa;
        $claveEmpresa = $empresa->clave ?? 'E' . str_pad($idEmpresa, 3, '0', STR_PAD_LEFT);
        $numeroEmpresa = str_pad($idEmpresa, 3, '0', STR_PAD_LEFT);
        $usuarioId = Auth::id() ?? 1;
        $now = now();

        // ============================================
        // 1. CUENTA PRINCIPAL (NIVEL 0)
        // ============================================
        $cuentaPrincipal = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $claveEmpresa,
            'nombre_cuenta' => $nombreEmpresa,
            'descripcion' => 'DESCRIPCION DE LA CUENTA PRINCIPAL PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'id_cuenta_madre' => null,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => false,
            'saldo_inicial' => 0,
            'nivel' => 0,
            'indice_c' => (string)$idEmpresa,
            'clave_c' => $claveEmpresa,
            'empresa_c' => null,
            'fondeo_c' => 0,
            'cuenta_resultados' => 0,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        $cuentaPrincipal->update([
            'id_cuenta_madre' => $cuentaPrincipal->id_cuenta,
            'empresa_c' => $cuentaPrincipal->id_cuenta,
        ]);

        $cuentaPrincipalId = $cuentaPrincipal->id_cuenta;

        // ============================================
        // FUNCIÓN PARA CALCULAR EL INDICE_C DINÁMICAMENTE
        // ============================================
        $calcularIndice = function($indiceMadre, $madreId) use ($idEmpresa) {
            $hijos = Cuenta::where('id_empresa', $idEmpresa)
                ->where('madre_c', $madreId)
                ->count();
            
            $nuevoIndice = $indiceMadre . '-' . str_pad($hijos + 1, 3, '0', STR_PAD_LEFT);
            return $nuevoIndice;
        };

        // ============================================
        // 2. ACTIVOS (NIVEL 1)
        // ============================================
        $indiceActivos = $idEmpresa . '-001';
        $codigoActivos = 'AC' . $numeroEmpresa;
        
        $activos = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $codigoActivos,
            'nombre_cuenta' => 'ACTIVOS',
            'descripcion' => 'CUENTA DE ACTIVOS PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'tipo_cuenta' => 'FONDEADORA',
            'id_cuenta_madre' => $cuentaPrincipalId,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => false,
            'saldo_inicial' => 0,
            'nivel' => 1,
            'indice_c' => $indiceActivos,
            'clave_c' => $codigoActivos,
            'madre_c' => $cuentaPrincipalId,
            'empresa_c' => $cuentaPrincipalId,
            'fondeo_c' => 0,
            'cuenta_resultados' => 0,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        // ============================================
        // 3. ACTIVO CIRCULANTE (NIVEL 2)
        // ============================================
        $indiceCirculante = $calcularIndice($idEmpresa . '-001', $activos->id_cuenta);
        $codigoCirculante = 'CI' . $numeroEmpresa;
        
        $activoCirculante = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $codigoCirculante,
            'nombre_cuenta' => 'ACTIVO CIRCULANTE',
            'descripcion' => 'CUENTA DE ACTIVO CIRCULANTE PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'tipo_cuenta' => 'FONDEADORA',
            'id_cuenta_madre' => $activos->id_cuenta,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => false,
            'saldo_inicial' => 0,
            'nivel' => 2,
            'indice_c' => $indiceCirculante,
            'clave_c' => $codigoCirculante,
            'madre_c' => $activos->id_cuenta,
            'empresa_c' => $cuentaPrincipalId,
            'fondeo_c' => 0,
            'cuenta_resultados' => 0,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        // ============================================
        // 4. CUENTAS DE ACTIVO CIRCULANTE (NIVEL 3)
        // ============================================
        $cuentasCirculante = [
            ['codigo' => 'CJ', 'nombre' => 'FONDO FIJO DE CAJA'],
            ['codigo' => 'BC', 'nombre' => 'BANCOS'],
            ['codigo' => 'CL', 'nombre' => 'CLIENTES'],
            ['codigo' => 'DC', 'nombre' => 'DOCUMENTOS POR COBRAR'],
            ['codigo' => 'DD', 'nombre' => 'DEUDORES DIVERSOS'],
            ['codigo' => 'FE', 'nombre' => 'FUNCIONARIOS Y EMPLEADOS'],
            ['codigo' => 'IV', 'nombre' => 'IVA ACREDITABLE'],
            ['codigo' => 'AL', 'nombre' => 'ALMACEN'],
            ['codigo' => 'DE', 'nombre' => 'DEPOSITOS EN GARANTIA'],
        ];

        foreach ($cuentasCirculante as $cuenta) {
            $indice = $calcularIndice($indiceCirculante, $activoCirculante->id_cuenta);
            $codigo = $cuenta['codigo'] . $numeroEmpresa;
            
            Cuenta::create([
                'id_empresa' => $idEmpresa,
                'codigo_cuenta' => $codigo,
                'nombre_cuenta' => $cuenta['nombre'],
                'descripcion' => 'CUENTA DE ' . $cuenta['nombre'] . ' PARA LA EMPRESA ' . $nombreEmpresa,
                'Naturaleza' => null, // ❌ SIN NATURALEZA
                'tipo_cuenta' => 'FONDEADORA',
                'id_cuenta_madre' => $activoCirculante->id_cuenta,
                'en_uso' => false, // ❌ INACTIVA POR DEFECTO
                'es_cuenta_resultados' => false,
                'saldo_inicial' => 0,
                'nivel' => 3,
                'indice_c' => $indice,
                'clave_c' => $codigo,
                'madre_c' => $activoCirculante->id_cuenta,
                'origen_c' => $activoCirculante->id_cuenta,
                'empresa_c' => $cuentaPrincipalId,
                'fondeo_c' => 0,
                'cuenta_resultados' => 0,
                'usuario_c' => $usuarioId,
                'fecha_c' => $now,
                'id_empresa_c' => $idEmpresa,
            ]);
        }

        // ============================================
        // 5. ACTIVO FIJO (NIVEL 2)
        // ============================================
        $indiceFijo = $calcularIndice($idEmpresa . '-001', $activos->id_cuenta);
        $codigoFijo = 'FI' . $numeroEmpresa;
        
        $activoFijo = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $codigoFijo,
            'nombre_cuenta' => 'ACTIVO FIJO',
            'descripcion' => 'CUENTA DE ACTIVO FIJO PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'tipo_cuenta' => 'FONDEADORA',
            'id_cuenta_madre' => $activos->id_cuenta,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => false,
            'saldo_inicial' => 0,
            'nivel' => 2,
            'indice_c' => $indiceFijo,
            'clave_c' => $codigoFijo,
            'madre_c' => $activos->id_cuenta,
            'empresa_c' => $cuentaPrincipalId,
            'fondeo_c' => 0,
            'cuenta_resultados' => 0,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        // ============================================
        // 6. CUENTAS DE ACTIVO FIJO (NIVEL 3)
        // ============================================
        $cuentasFijo = [
            ['codigo' => 'TR', 'nombre' => 'TERRENOS'],
            ['codigo' => 'ED', 'nombre' => 'EDIFICIOS Y CONSTRUCCIONES'],
            ['codigo' => 'EC', 'nombre' => 'DEPRECIACION DE EDIFICIOS Y CONSTRUCCIONES'],
            ['codigo' => 'ME', 'nombre' => 'MAQUINARIA Y EQUIPO'],
            ['codigo' => 'DY', 'nombre' => 'DEPRECIACION DE MAQUINARIA Y EQUIPO'],
            ['codigo' => 'EO', 'nombre' => 'EQUIPO DE OFICINA'],
            ['codigo' => 'DO', 'nombre' => 'DEPRECIACION DE EQUIPO DE OFICINA'],
            ['codigo' => 'ER', 'nombre' => 'EQUIPO DE TRANSPORTE'],
            ['codigo' => 'DT', 'nombre' => 'DEPRECIACION DE EQUIPO DE TRANSPORTE'],
        ];

        foreach ($cuentasFijo as $cuenta) {
            $indice = $calcularIndice($indiceFijo, $activoFijo->id_cuenta);
            $codigo = $cuenta['codigo'] . $numeroEmpresa;
            
            Cuenta::create([
                'id_empresa' => $idEmpresa,
                'codigo_cuenta' => $codigo,
                'nombre_cuenta' => $cuenta['nombre'],
                'descripcion' => 'CUENTA DE ' . $cuenta['nombre'] . ' PARA LA EMPRESA ' . $nombreEmpresa,
                'Naturaleza' => null, // ❌ SIN NATURALEZA
                'tipo_cuenta' => 'FONDEADORA',
                'id_cuenta_madre' => $activoFijo->id_cuenta,
                'en_uso' => false, // ❌ INACTIVA POR DEFECTO
                'es_cuenta_resultados' => false,
                'saldo_inicial' => 0,
                'nivel' => 3,
                'indice_c' => $indice,
                'clave_c' => $codigo,
                'madre_c' => $activoFijo->id_cuenta,
                'origen_c' => $activoFijo->id_cuenta,
                'empresa_c' => $cuentaPrincipalId,
                'fondeo_c' => 0,
                'cuenta_resultados' => 0,
                'usuario_c' => $usuarioId,
                'fecha_c' => $now,
                'id_empresa_c' => $idEmpresa,
            ]);
        }

        // ============================================
        // 7. ACTIVO DIFERIDO (NIVEL 2)
        // ============================================
        $indiceDiferido = $calcularIndice($idEmpresa . '-001', $activos->id_cuenta);
        $codigoDiferido = 'CD' . $numeroEmpresa;
        
        $activoDiferido = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $codigoDiferido,
            'nombre_cuenta' => 'ACTIVO DIFERIDO',
            'descripcion' => 'CUENTA DE ACTIVO DIFERIDO PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'tipo_cuenta' => 'FONDEADORA',
            'id_cuenta_madre' => $activos->id_cuenta,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => false,
            'saldo_inicial' => 0,
            'nivel' => 2,
            'indice_c' => $indiceDiferido,
            'clave_c' => $codigoDiferido,
            'madre_c' => $activos->id_cuenta,
            'empresa_c' => $cuentaPrincipalId,
            'fondeo_c' => 0,
            'cuenta_resultados' => 0,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        // ============================================
        // 8. CUENTAS DE ACTIVO DIFERIDO (NIVEL 3)
        // ============================================
        $cuentasDiferido = [
            ['codigo' => 'GO', 'nombre' => 'GASTOS DE ORGANIZACION'],
            ['codigo' => 'AG', 'nombre' => 'AMORTIZACION DE GASTOS DE ORGANIZACION'],
            ['codigo' => 'AN', 'nombre' => 'PAGOS POR ANTICIPADO'],
            ['codigo' => 'IN', 'nombre' => 'INVERSIONES EN ACCIONES'],
        ];

        foreach ($cuentasDiferido as $cuenta) {
            $indice = $calcularIndice($indiceDiferido, $activoDiferido->id_cuenta);
            $codigo = $cuenta['codigo'] . $numeroEmpresa;
            
            Cuenta::create([
                'id_empresa' => $idEmpresa,
                'codigo_cuenta' => $codigo,
                'nombre_cuenta' => $cuenta['nombre'],
                'descripcion' => 'CUENTA DE ' . $cuenta['nombre'] . ' PARA LA EMPRESA ' . $nombreEmpresa,
                'Naturaleza' => null, // ❌ SIN NATURALEZA
                'tipo_cuenta' => 'FONDEADORA',
                'id_cuenta_madre' => $activoDiferido->id_cuenta,
                'en_uso' => false, // ❌ INACTIVA POR DEFECTO
                'es_cuenta_resultados' => false,
                'saldo_inicial' => 0,
                'nivel' => 3,
                'indice_c' => $indice,
                'clave_c' => $codigo,
                'madre_c' => $activoDiferido->id_cuenta,
                'origen_c' => $activoDiferido->id_cuenta,
                'empresa_c' => $cuentaPrincipalId,
                'fondeo_c' => 0,
                'cuenta_resultados' => 0,
                'usuario_c' => $usuarioId,
                'fecha_c' => $now,
                'id_empresa_c' => $idEmpresa,
            ]);
        }

        // ============================================
        // 9. PASIVOS (NIVEL 1)
        // ============================================
        $indicePasivos = $idEmpresa . '-002';
        $codigoPasivos = 'PA' . $numeroEmpresa;
        
        $pasivos = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $codigoPasivos,
            'nombre_cuenta' => 'PASIVOS',
            'descripcion' => 'CUENTA DE PASIVOS PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'tipo_cuenta' => 'FONDEADORA',
            'id_cuenta_madre' => $cuentaPrincipalId,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => false,
            'saldo_inicial' => 0,
            'nivel' => 1,
            'indice_c' => $indicePasivos,
            'clave_c' => $codigoPasivos,
            'madre_c' => $cuentaPrincipalId,
            'empresa_c' => $cuentaPrincipalId,
            'fondeo_c' => 0,
            'cuenta_resultados' => 0,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        // ============================================
        // 10. PASIVO FIJO (NIVEL 2)
        // ============================================
        $indicePasivoFijo = $calcularIndice($indicePasivos, $pasivos->id_cuenta);
        $codigoPasivoFijo = 'PF' . $numeroEmpresa;
        
        $pasivoFijo = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $codigoPasivoFijo,
            'nombre_cuenta' => 'PASIVO FIJO',
            'descripcion' => 'CUENTA DE PASIVO FIJO PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'tipo_cuenta' => 'FONDEADORA',
            'id_cuenta_madre' => $pasivos->id_cuenta,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => false,
            'saldo_inicial' => 0,
            'nivel' => 2,
            'indice_c' => $indicePasivoFijo,
            'clave_c' => $codigoPasivoFijo,
            'madre_c' => $pasivos->id_cuenta,
            'empresa_c' => $cuentaPrincipalId,
            'fondeo_c' => 0,
            'cuenta_resultados' => 0,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        // ============================================
        // 11. CUENTAS DE PASIVO FIJO (NIVEL 3)
        // ============================================
        $cuentasPasivoFijo = [
            ['codigo' => 'PB', 'nombre' => 'PRESTAMOS BANCARIOS A LARGO PLAZO'],
            ['codigo' => 'DX', 'nombre' => 'DOCUMENTOS POR PAGAR LARGO PLAZO'],
        ];

        foreach ($cuentasPasivoFijo as $cuenta) {
            $indice = $calcularIndice($indicePasivoFijo, $pasivoFijo->id_cuenta);
            $codigo = $cuenta['codigo'] . $numeroEmpresa;
            
            Cuenta::create([
                'id_empresa' => $idEmpresa,
                'codigo_cuenta' => $codigo,
                'nombre_cuenta' => $cuenta['nombre'],
                'descripcion' => 'CUENTA DE ' . $cuenta['nombre'] . ' PARA LA EMPRESA ' . $nombreEmpresa,
                'Naturaleza' => null, // ❌ SIN NATURALEZA
                'tipo_cuenta' => 'FONDEADORA',
                'id_cuenta_madre' => $pasivoFijo->id_cuenta,
                'en_uso' => false, // ❌ INACTIVA POR DEFECTO
                'es_cuenta_resultados' => false,
                'saldo_inicial' => 0,
                'nivel' => 3,
                'indice_c' => $indice,
                'clave_c' => $codigo,
                'madre_c' => $pasivoFijo->id_cuenta,
                'origen_c' => $pasivoFijo->id_cuenta,
                'empresa_c' => $cuentaPrincipalId,
                'fondeo_c' => 0,
                'cuenta_resultados' => 0,
                'usuario_c' => $usuarioId,
                'fecha_c' => $now,
                'id_empresa_c' => $idEmpresa,
            ]);
        }

        // ============================================
        // 12. PASIVO DIFERIDO (NIVEL 2)
        // ============================================
        $indicePasivoDiferido = $calcularIndice($indicePasivos, $pasivos->id_cuenta);
        $codigoPasivoDiferido = 'PD' . $numeroEmpresa;
        
        $pasivoDiferido = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $codigoPasivoDiferido,
            'nombre_cuenta' => 'PASIVO DIFERIDO',
            'descripcion' => 'CUENTA DE PASIVO DIFERIDO PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'tipo_cuenta' => 'FONDEADORA',
            'id_cuenta_madre' => $pasivos->id_cuenta,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => false,
            'saldo_inicial' => 0,
            'nivel' => 2,
            'indice_c' => $indicePasivoDiferido,
            'clave_c' => $codigoPasivoDiferido,
            'madre_c' => $pasivos->id_cuenta,
            'empresa_c' => $cuentaPrincipalId,
            'fondeo_c' => 0,
            'cuenta_resultados' => 0,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        // ============================================
        // 13. CUENTAS DE PASIVO DIFERIDO (NIVEL 3)
        // ============================================
        $cuentasPasivoDiferido = [
            ['codigo' => 'RA', 'nombre' => 'RENTAS COBRADAS ANTICIPADAMENTE'],
            ['codigo' => 'IC', 'nombre' => 'INTERESES COBRADOS ANTICIPADAMENTE'],
        ];

        foreach ($cuentasPasivoDiferido as $cuenta) {
            $indice = $calcularIndice($indicePasivoDiferido, $pasivoDiferido->id_cuenta);
            $codigo = $cuenta['codigo'] . $numeroEmpresa;
            
            Cuenta::create([
                'id_empresa' => $idEmpresa,
                'codigo_cuenta' => $codigo,
                'nombre_cuenta' => $cuenta['nombre'],
                'descripcion' => 'CUENTA DE ' . $cuenta['nombre'] . ' PARA LA EMPRESA ' . $nombreEmpresa,
                'Naturaleza' => null, // ❌ SIN NATURALEZA
                'tipo_cuenta' => 'FONDEADORA',
                'id_cuenta_madre' => $pasivoDiferido->id_cuenta,
                'en_uso' => false, // ❌ INACTIVA POR DEFECTO
                'es_cuenta_resultados' => false,
                'saldo_inicial' => 0,
                'nivel' => 3,
                'indice_c' => $indice,
                'clave_c' => $codigo,
                'madre_c' => $pasivoDiferido->id_cuenta,
                'origen_c' => $pasivoDiferido->id_cuenta,
                'empresa_c' => $cuentaPrincipalId,
                'fondeo_c' => 0,
                'cuenta_resultados' => 0,
                'usuario_c' => $usuarioId,
                'fecha_c' => $now,
                'id_empresa_c' => $idEmpresa,
            ]);
        }

        // ============================================
        // 14. CAPITAL (NIVEL 1)
        // ============================================
        $indiceCapital = $idEmpresa . '-003';
        $codigoCapital = 'CA' . $numeroEmpresa;
        
        $capital = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $codigoCapital,
            'nombre_cuenta' => 'CAPITAL',
            'descripcion' => 'CUENTA DE CAPITAL PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'tipo_cuenta' => 'FONDEADORA',
            'id_cuenta_madre' => $cuentaPrincipalId,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => false,
            'saldo_inicial' => 0,
            'nivel' => 1,
            'indice_c' => $indiceCapital,
            'clave_c' => $codigoCapital,
            'madre_c' => $cuentaPrincipalId,
            'empresa_c' => $cuentaPrincipalId,
            'fondeo_c' => 0,
            'cuenta_resultados' => 0,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        // ============================================
        // 15. CUENTAS DE CAPITAL (NIVEL 2)
        // ============================================
        $cuentasCapital = [
            ['codigo' => 'CS', 'nombre' => 'CAPITAL SOCIAL'],
            ['codigo' => 'FJ', 'nombre' => 'CAPITAL FIJO'],
            ['codigo' => 'CV', 'nombre' => 'CAPITAL VARIABLE'],
            ['codigo' => 'RL', 'nombre' => 'RESERVA LEGAL'],
            ['codigo' => 'EA', 'nombre' => 'RESULTADO DE EJERCICIOS ANTERIORES'],
            ['codigo' => 'SV', 'nombre' => 'SUPERAVIT'],
        ];

        foreach ($cuentasCapital as $cuenta) {
            $indice = $calcularIndice($indiceCapital, $capital->id_cuenta);
            $codigo = $cuenta['codigo'] . $numeroEmpresa;
            
            Cuenta::create([
                'id_empresa' => $idEmpresa,
                'codigo_cuenta' => $codigo,
                'nombre_cuenta' => $cuenta['nombre'],
                'descripcion' => 'CUENTA DE ' . $cuenta['nombre'] . ' PARA LA EMPRESA ' . $nombreEmpresa,
                'Naturaleza' => null, // ❌ SIN NATURALEZA
                'tipo_cuenta' => 'FONDEADORA',
                'id_cuenta_madre' => $capital->id_cuenta,
                'en_uso' => false, // ❌ INACTIVA POR DEFECTO
                'es_cuenta_resultados' => false,
                'saldo_inicial' => 0,
                'nivel' => 2,
                'indice_c' => $indice,
                'clave_c' => $codigo,
                'madre_c' => $capital->id_cuenta,
                'origen_c' => $capital->id_cuenta,
                'empresa_c' => $cuentaPrincipalId,
                'fondeo_c' => 0,
                'cuenta_resultados' => 0,
                'usuario_c' => $usuarioId,
                'fecha_c' => $now,
                'id_empresa_c' => $idEmpresa,
            ]);
        }

        // ============================================
        // 16. RESULTADOS (NIVEL 1)
        // ============================================
        $indiceResultados = $idEmpresa . '-004';
        $codigoResultados = 'RE' . $numeroEmpresa;
        
        $resultados = Cuenta::create([
            'id_empresa' => $idEmpresa,
            'codigo_cuenta' => $codigoResultados,
            'nombre_cuenta' => 'RESULTADOS',
            'descripcion' => 'CUENTA DE RESULTADOS PARA LA EMPRESA ' . $nombreEmpresa,
            'Naturaleza' => null, // ❌ SIN NATURALEZA
            'tipo_cuenta' => 'RESULTADO',
            'id_cuenta_madre' => $cuentaPrincipalId,
            'en_uso' => false, // ❌ INACTIVA POR DEFECTO
            'es_cuenta_resultados' => true,
            'saldo_inicial' => 0,
            'nivel' => 1,
            'indice_c' => $indiceResultados,
            'clave_c' => $codigoResultados,
            'madre_c' => $cuentaPrincipalId,
            'empresa_c' => $cuentaPrincipalId,
            'fondeo_c' => 0,
            'cuenta_resultados' => 1,
            'usuario_c' => $usuarioId,
            'fecha_c' => $now,
            'id_empresa_c' => $idEmpresa,
        ]);

        // ============================================
        // 17. CUENTAS DE RESULTADOS (NIVEL 2)
        // ============================================
        $cuentasResultados = [
            ['codigo' => 'VT', 'nombre' => 'VENTAS TOTALES'],
            ['codigo' => 'DV', 'nombre' => 'DEVOLUCIONES SOBRE VENTAS'],
            ['codigo' => 'RV', 'nombre' => 'REBAJAS SOBRE VENTAS'],
            ['codigo' => 'FN', 'nombre' => 'PRODUCTOS FINANCIEROS'],
            ['codigo' => 'OP', 'nombre' => 'OTROS PRODUCTOS'],
            ['codigo' => 'VN', 'nombre' => 'COSTO DE VENTAS'],
            ['codigo' => 'GA', 'nombre' => 'GASTOS DE ADMINISTRACION'],
            ['codigo' => 'GV', 'nombre' => 'GASTOS DE VENTAS'],
            ['codigo' => 'GF', 'nombre' => 'GASTOS FINANCIEROS'],
            ['codigo' => 'OG', 'nombre' => 'OTROS GASTOS'],
            ['codigo' => 'PR', 'nombre' => 'PERDIDA INFLACIONARIA'],
            ['codigo' => 'ID', 'nombre' => 'INTERESES DEDUCIBLES'],
            ['codigo' => 'GI', 'nombre' => 'GANANCIA INFLACIONARIA'],
            ['codigo' => 'AM', 'nombre' => 'PERDIDAS POR AMORTIZAR'],
            ['codigo' => 'VC', 'nombre' => 'INGRESOS VARIACION CAMBIARIA'],
            ['codigo' => 'GC', 'nombre' => 'GASTOS VARIACION CAMBIARIA'],
        ];

        foreach ($cuentasResultados as $cuenta) {
            $indice = $calcularIndice($indiceResultados, $resultados->id_cuenta);
            $codigo = $cuenta['codigo'] . $numeroEmpresa;
            
            Cuenta::create([
                'id_empresa' => $idEmpresa,
                'codigo_cuenta' => $codigo,
                'nombre_cuenta' => $cuenta['nombre'],
                'descripcion' => 'CUENTA DE ' . $cuenta['nombre'] . ' PARA LA EMPRESA ' . $nombreEmpresa,
                'Naturaleza' => null, // ❌ SIN NATURALEZA
                'tipo_cuenta' => 'RESULTADO',
                'id_cuenta_madre' => $resultados->id_cuenta,
                'en_uso' => false, // ❌ INACTIVA POR DEFECTO
                'es_cuenta_resultados' => true,
                'saldo_inicial' => 0,
                'nivel' => 2,
                'indice_c' => $indice,
                'clave_c' => $codigo,
                'madre_c' => $resultados->id_cuenta,
                'origen_c' => $resultados->id_cuenta,
                'empresa_c' => $cuentaPrincipalId,
                'fondeo_c' => 0,
                'cuenta_resultados' => 1,
                'usuario_c' => $usuarioId,
                'fecha_c' => $now,
                'id_empresa_c' => $idEmpresa,
            ]);
        }

        return true;
    }

    public function show(Empresa $empresa)
    {
        // ✅ Verificar permiso para ver empresas
        if (!Gate::allows('ver-empresas')) {
            return redirect()->route('empresas.index')
                ->with('error', 'No tienes permiso para ver condominios');
        }
        if (!$this->puedeVer($empresa)) {
            return redirect()->route('empresas.index')->with('error', 'Ese condominio no está asignado a tu usuario');
        }

        try {
            $empresa->load(['usuarios', 'cuentas']);

            $stats = [
                'total_usuarios' => $empresa->usuarios->count(),
                'total_cuentas' => $empresa->cuentas->count(),
                'tipo_persona' => $empresa->tipo_persona,
                'tiene_representante' => $empresa->tipo_persona === 'MORAL',
                'direccion_completa' => $empresa->direccion_completa,
            ];

            // ✅ RECOPILAR FLASH MESSAGES
            $flash = [];
            $flashTypes = ['success', 'error', 'info', 'warning', 'updated', 'created', 'deleted'];
            foreach ($flashTypes as $type) {
                if (session()->has($type)) {
                    $flash[$type] = session($type);
                }
            }

            return Inertia::render('Empresas/Show', [
                'empresa' => $empresa,
                'stats' => $stats,
                'flash' => $flash // ✅ PASAR FLASH EXPLÍCITAMENTE
            ]);

        } catch (\Exception $e) {
            return redirect()->route('empresas.index')
                ->with('error', 'Error al cargar el condominio: ' . $e->getMessage());
        }
    }

    public function edit(Empresa $empresa)
    {
        // ✅ Verificar permiso para editar empresas
        if (!Gate::allows('crear-empresas')) {
            return redirect()->route('empresas.index')
                ->with('error', 'No tienes permiso para editar condominios');
        }
        if (!$this->puedeVer($empresa)) {
            return redirect()->route('empresas.index')->with('error', 'Ese condominio no está asignado a tu usuario');
        }

        try {
            $estadosMexico = [
                'Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche',
                'Chiapas', 'Chihuahua', 'Coahuila', 'Colima', 'Ciudad de México',
                'Durango', 'Estado de México', 'Guanajuato', 'Guerrero', 'Hidalgo',
                'Jalisco', 'Michoacán', 'Morelos', 'Nayarit', 'Nuevo León',
                'Oaxaca', 'Puebla', 'Querétaro', 'Quintana Roo', 'San Luis Potosí',
                'Sinaloa', 'Sonora', 'Tabasco', 'Tamaulipas', 'Tlaxcala',
                'Veracruz', 'Yucatán', 'Zacatecas'
            ];

            // ✅ RECOPILAR FLASH MESSAGES
            $flash = [];
            $flashTypes = ['success', 'error', 'info', 'warning', 'updated', 'created', 'deleted'];
            foreach ($flashTypes as $type) {
                if (session()->has($type)) {
                    $flash[$type] = session($type);
                }
            }

            return Inertia::render('Empresas/Form', [
                'empresa' => $empresa->loadCount(['casas', 'usuarios']),
            ]);

        } catch (\Exception $e) {
            return redirect()->route('empresas.index')
                ->with('error', 'Error al cargar el condominio para editar: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Empresa $empresa)
    {
        // ✅ Verificar permiso para editar empresas
        if (!Gate::allows('crear-empresas')) {
            return redirect()->route('empresas.index')
                ->with('error', 'No tienes permiso para editar condominios');
        }
        if (!$this->puedeVer($empresa)) {
            return redirect()->route('empresas.index')->with('error', 'Ese condominio no está asignado a tu usuario');
        }

        try {
            $rules = [
                'nombre_empresa' => 'required|string|max:150',
                'rfc' => [
                    'required',
                    'string',
                    'max:13',
                    Rule::unique('empresas')->ignore($empresa->id, 'id'),
                ],
                'regimen' => 'nullable|string|max:50',
                'tipo_persona' => 'required|in:FISICA,MORAL',
                'clave' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('empresas')->ignore($empresa->id, 'id'),
                ],
                'calle' => 'nullable|string|max:100',
                'numero_exterior' => 'nullable|string|max:20',
                'numero_interior' => 'nullable|string|max:20',
                'colonia' => 'nullable|string|max:100',
                'ciudad' => 'nullable|string|max:100',
                'municipio' => 'nullable|string|max:100',
                'estado' => 'nullable|string|max:100',
                'codigo_postal' => 'nullable|string|max:10',
                'correo' => 'nullable|email|max:100',
                'telefono_personal' => 'nullable|string|max:20',
                'telefono_trabajo' => 'nullable|string|max:20',
                'extension' => 'nullable|string|max:10',
                'activo' => 'nullable|boolean',
            ];

            if ($request->tipo_persona === 'MORAL') {
                $rules['representante_nombre'] = 'required|string|max:100';
                $rules['representante_apellido_paterno'] = 'required|string|max:50';
                $rules['representante_apellido_materno'] = 'nullable|string|max:50';
                $rules['representante_rfc'] = 'required|string|max:13';
                $rules['representante_curp'] = 'nullable|string|max:18';
            } else {
                $rules['representante_nombre'] = 'nullable|string|max:100';
                $rules['representante_apellido_paterno'] = 'nullable|string|max:50';
                $rules['representante_apellido_materno'] = 'nullable|string|max:50';
                $rules['representante_rfc'] = 'nullable|string|max:13';
                $rules['representante_curp'] = 'nullable|string|max:18';
            }

            $validated = $request->validate($rules);

            $empresa->update([
                'nombre_empresa' => $validated['nombre_empresa'],
                'rfc' => $validated['rfc'],
                'regimen' => $validated['regimen'] ?? null,
                'tipo_persona' => $validated['tipo_persona'],
                'clave' => $validated['clave'] ?? null,
                'calle' => $validated['calle'] ?? null,
                'numero_exterior' => $validated['numero_exterior'] ?? null,
                'numero_interior' => $validated['numero_interior'] ?? null,
                'colonia' => $validated['colonia'] ?? null,
                // La "ciudad" ya no se captura por separado: es igual al municipio.
                'ciudad' => $validated['municipio'] ?? ($validated['ciudad'] ?? null),
                'municipio' => $validated['municipio'] ?? null,
                'estado' => $validated['estado'] ?? null,
                'codigo_postal' => $validated['codigo_postal'] ?? null,
                'correo' => $validated['correo'] ?? null,
                'telefono_personal' => $validated['telefono_personal'] ?? null,
                'telefono_trabajo' => $validated['telefono_trabajo'] ?? null,
                'extension' => $validated['extension'] ?? null,
                'representante_nombre' => $validated['representante_nombre'] ?? null,
                'representante_apellido_paterno' => $validated['representante_apellido_paterno'] ?? null,
                'representante_apellido_materno' => $validated['representante_apellido_materno'] ?? null,
                'representante_rfc' => $validated['representante_rfc'] ?? null,
                'representante_curp' => $validated['representante_curp'] ?? null,
                'activo' => $validated['activo'] ?? $empresa->activo,
            ]);

            return redirect()->route('empresas.index')
                ->with('success', 'Condominio "' . $empresa->nombre_empresa . '" actualizado exitosamente');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->with('error', 'Error de validación: ' . implode(', ', \Illuminate\Support\Arr::flatten($e->errors())))
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error al actualizar el condominio: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * 🟢 ELIMINACIÓN LÓGICA - Desactivar empresa (cambia activo a false)
     */
    public function destroy(Empresa $empresa)
    {
        // ✅ Desactivar condominios: sólo superusuario
        if (!Gate::allows('eliminar-empresas')) {
            return redirect()->route('empresas.index')
                ->with('error', 'No tienes permiso para eliminar condominios');
        }

        try {
            // Verificar si ya está inactiva
            if (!$empresa->activo) {
                return redirect()->route('empresas.index')
                    ->with('warning', 'El condominio ya está inactivo');
            }

            $nombre = $empresa->nombre_empresa;
            
            // 🟢 ELIMINACIÓN LÓGICA: Cambiar activo a false
            $empresa->update(['activo' => false]);

            return redirect()->route('empresas.index')
                ->with('success', 'Condominio "' . $nombre . '" desactivado exitosamente');

        } catch (\Exception $e) {
            return redirect()->route('empresas.index')
                ->with('error', 'Error al desactivar el condominio: ' . $e->getMessage());
        }
    }

    /**
     * 🟢 ACTIVAR/DESACTIVAR EMPRESA (Toggle)
     */
    public function toggleActive(Request $request, $id)
    {
        // ✅ Activar/desactivar condominios: sólo superusuario
        if (!Gate::allows('eliminar-empresas')) {
            return response()->json([
                'flash' => ['error' => 'No tienes permiso para cambiar el estado de la empresa']
            ], 403);
        }

        try {
            $empresa = Empresa::findOrFail($id);

            $nuevoEstado = !$empresa->activo;
            $empresa->update(['activo' => $nuevoEstado]);
            
            $estadoTexto = $nuevoEstado ? 'activada' : 'desactivada';
            
            return response()->json([
                'success' => true,
                'flash' => ['success' => 'Empresa ' . $estadoTexto . ' exitosamente'],
                'activo' => $nuevoEstado
            ]);
                
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'flash' => ['error' => 'Empresa no encontrada']
            ], 404);
                
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'flash' => ['error' => 'Error: ' . $e->getMessage()]
            ], 500);
        }
    }

    public function getEmpresasSelect(Request $request)
    {
        // ✅ Verificar permiso para ver empresas
        if (!Gate::allows('ver-empresas')) {
            return response()->json([]);
        }

        try {
            $query = $this->visibles()->where('activo', true);

            if ($request->filled('search')) {
                $query->search($request->search);
            }

            $empresas = $query->select('id as id', 'nombre_empresa', 'rfc', 'tipo_persona')
                             ->orderBy('nombre_empresa')
                             ->limit(20)
                             ->get();

            return response()->json($empresas);

        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    }
}