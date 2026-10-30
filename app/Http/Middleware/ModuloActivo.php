<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea las rutas de un módulo que está en config('condominios.modulos_ocultos').
 * Uso en rutas: ->middleware('modulo:personas')
 */
class ModuloActivo
{
    public static function oculto(string $modulo): bool
    {
        return in_array($modulo, config('condominios.modulos_ocultos', []), true);
    }

    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        if (!self::oculto($modulo)) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 404, 'Este módulo no está disponible.');

        return redirect()->route('dashboard')->with('error', 'Ese módulo no está disponible en RIC Condominios.');
    }
}
