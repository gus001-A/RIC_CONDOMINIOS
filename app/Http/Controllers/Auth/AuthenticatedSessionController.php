<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            // Accesos rápidos por rol (cuentas *.DEMO de CondominiosDemoSeeder). En producción la lista va vacía.
            'demo' => $this->mostrarAccesosDemo() ? $this->cuentasDemo() : [],
        ]);
    }

    /**
     * Por defecto sólo en desarrollo Y entrando desde esta computadora (localhost), para
     * que un .env con APP_ENV=local subido por error no publique las contraseñas.
     * ACCESOS_DEMO=true en .env los muestra siempre (servidor de demostración);
     * ACCESOS_DEMO=false los oculta siempre.
     */
    private function mostrarAccesosDemo(): bool
    {
        $forzado = config('condominios.accesos_demo');
        if ($forzado !== null) {
            return (bool) $forzado;
        }

        return app()->isLocal() && in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1'], true);
    }

    /** Una cuenta de prueba por rol, en el orden en que se usan. */
    private function cuentasDemo(): array
    {
        $orden = ['ADMINISTRADOR', 'CONDOMINO', 'VIGILANCIA', 'MANTENIMIENTO'];

        return Usuario::where('activo', true)
            ->where('nombre_usuario', 'like', '%.DEMO')
            ->whereIn('tipo_usuario', $orden)
            ->orderBy('id_usuario')
            ->get(['nombre_usuario', 'nombre_completo', 'tipo_usuario'])
            ->unique('tipo_usuario')
            ->sortBy(fn ($u) => array_search($u->tipo_usuario, $orden, true))
            ->map(fn ($u) => [
                'usuario' => $u->nombre_usuario,
                'nombre' => $u->nombre_completo,
                'rol' => $u->tipo_usuario,
                // Contraseña de las cuentas demo del seeder (sólo se envía en local).
                'password' => 'Demo2026!',
            ])
            ->values()
            ->all();
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}