<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Asignación de CUENTAS FONDEADORAS a los usuarios (sólo SUPERUSUARIO), igual
 * que la asignación de empresas pero filtrada por empresa.
 *
 * Regla: un usuario SIN ninguna fondeadora asignada en una empresa NO puede
 * generar ningún tipo de póliza en ella; sólo usa las que tenga asignadas.
 */
class CuentaFondeadoraController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Gate::allows('gestionar-fondeadoras-usuarios'), 403, 'No tienes permiso para gestionar cuentas fondeadoras');
    }

    private function fondeadorasDeEmpresa(int $empresaId)
    {
        return Cuenta::where('id_empresa', $empresaId)
            ->where('en_uso', true)
            // Misma definición que usan Nueva póliza / Editar / Nómina para
            // elegir la fondeadora: fondeo_c = 1 ("tipo_cuenta" FONDEADORA no
            // lo es: en muchas empresas casi todas las cuentas lo traen).
            ->where('fondeo_c', 1)
            ->orderBy('nombre_cuenta')
            ->get(['id_cuenta', 'codigo_cuenta', 'nombre_cuenta']);
    }

    public function index(Request $request)
    {
        $this->autorizar();

        $empresas = Empresa::where('activo', true)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']);

        $empresaId = (int) ($request->input('empresa_id') ?: session('empresa_fondeadoras') ?: ($empresas->first()->id ?? 0));
        if (!$empresas->contains('id', $empresaId)) {
            $empresaId = (int) ($empresas->first()->id ?? 0);
        }
        session(['empresa_fondeadoras' => $empresaId]);

        $fondeadoras = $empresaId ? $this->fondeadorasDeEmpresa($empresaId) : collect();
        $idsFondeadoras = $fondeadoras->pluck('id_cuenta');

        // usuario => [ids de fondeadoras asignadas en ESTA empresa]
        $asignaciones = DB::table('cuentas_fondeadoras_usuarios')
            ->whereIn('id_cuenta', $idsFondeadoras)
            ->get(['id_usuario', 'id_cuenta'])
            ->groupBy('id_usuario')
            ->map(fn ($filas) => $filas->pluck('id_cuenta')->map(fn ($i) => (int) $i)->values()->all());

        // Sólo los usuarios que tienen asignada esta empresa
        $usuarios = $empresaId
            ? Usuario::whereHas('empresas', fn ($q) => $q->where('empresas.id', $empresaId))
                ->where('activo', true)
                ->orderBy('nombre_completo')
                ->get(['id_usuario', 'nombre_completo', 'nombre_usuario', 'tipo_usuario'])
                ->map(fn ($u) => [
                    'id' => $u->id_usuario,
                    'nombre' => $u->nombre_completo,
                    'usuario' => $u->nombre_usuario,
                    'tipo' => $u->tipo_usuario,
                    'fondeadoras' => $asignaciones[$u->id_usuario] ?? [],
                ])
                ->values()
            : collect();

        return Inertia::render('CuentasFondeadoras/Index', [
            'empresas' => $empresas,
            'empresa_seleccionada' => $empresaId,
            'fondeadoras' => $fondeadoras->map(fn ($c) => [
                'id' => $c->id_cuenta,
                'codigo' => $c->codigo_cuenta,
                'nombre' => $c->nombre_cuenta,
            ])->values(),
            'usuarios' => $usuarios,
        ]);
    }

    /**
     * Fija las fondeadoras de UN usuario en UNA empresa.
     */
    public function actualizarUsuario(Request $request, $idUsuario)
    {
        $this->autorizar();

        $datos = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'fondeadoras' => 'present|array',
            'fondeadoras.*' => 'integer',
        ]);

        $usuario = Usuario::findOrFail($idUsuario);
        abort_unless($usuario->empresas()->where('empresas.id', $datos['empresa_id'])->exists(), 422, 'El usuario no tiene asignada esa empresa');

        $permitidas = $this->fondeadorasDeEmpresa((int) $datos['empresa_id'])->pluck('id_cuenta');
        $nuevas = $permitidas->intersect($datos['fondeadoras'])->values();

        DB::transaction(function () use ($usuario, $permitidas, $nuevas) {
            DB::table('cuentas_fondeadoras_usuarios')
                ->where('id_usuario', $usuario->id_usuario)
                ->whereIn('id_cuenta', $permitidas)
                ->delete();

            $ahora = now();
            DB::table('cuentas_fondeadoras_usuarios')->insert(
                $nuevas->map(fn ($id) => [
                    'id_usuario' => $usuario->id_usuario,
                    'id_cuenta' => $id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->all()
            );
        });

        return back()->with('success', 'Cuentas fondeadoras de ' . $usuario->nombre_completo . ' actualizadas');
    }

    /**
     * Fija los usuarios que pueden usar UNA fondeadora.
     */
    public function actualizarCuenta(Request $request, $idCuenta)
    {
        $this->autorizar();

        $datos = $request->validate([
            'usuarios' => 'present|array',
            'usuarios.*' => 'integer',
        ]);

        $cuenta = Cuenta::findOrFail($idCuenta);
        abort_unless($this->fondeadorasDeEmpresa((int) $cuenta->id_empresa)->contains('id_cuenta', $cuenta->id_cuenta), 422, 'La cuenta no es una fondeadora activa');

        // Sólo usuarios que tengan asignada la empresa de la cuenta
        $validos = Usuario::whereIn('id_usuario', $datos['usuarios'])
            ->whereHas('empresas', fn ($q) => $q->where('empresas.id', $cuenta->id_empresa))
            ->pluck('id_usuario');

        DB::transaction(function () use ($cuenta, $validos) {
            DB::table('cuentas_fondeadoras_usuarios')->where('id_cuenta', $cuenta->id_cuenta)->delete();

            $ahora = now();
            DB::table('cuentas_fondeadoras_usuarios')->insert(
                $validos->map(fn ($id) => [
                    'id_usuario' => $id,
                    'id_cuenta' => $cuenta->id_cuenta,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->all()
            );
        });

        return back()->with('success', 'Usuarios de la cuenta ' . $cuenta->nombre_cuenta . ' actualizados');
    }
}
