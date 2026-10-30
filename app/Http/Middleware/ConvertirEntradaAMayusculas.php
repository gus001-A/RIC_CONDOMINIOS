<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convierte a MAYÚSCULAS el texto que el usuario captura en formularios
 * (body de POST/PUT/PATCH), excepto contraseñas y correos electrónicos.
 *
 * - Contraseñas: nunca se tocan (login, cambio de contraseña, creación de
 *   usuario, etc.) porque son sensibles a mayúsculas/minúsculas.
 * - Correos: se dejan tal cual el usuario los escribió; convertirlos a
 *   mayúsculas no aporta nada y puede causar confusión visual o problemas
 *   con integraciones externas que sí distinguen mayúsculas/minúsculas.
 * - Rutas de autenticación (login, registro, recuperación de contraseña):
 *   se excluyen por completo, no hay beneficio en tocarlas y evita
 *   cualquier sorpresa en un flujo crítico.
 *
 * 🔥 IMPORTANTE: la query string (GET, `?vista=diferidas`, `?sort_by=...`,
 * filtros de búsqueda, paginación, etc.) NUNCA se toca. Esos valores son de
 * CONTROL de la aplicación, no texto capturado por el usuario, y el código
 * los compara en minúsculas (`if ($vista === 'diferidas')`). Convertirlos a
 * mayúsculas rompía el cambio de vista (Diferidas/Traspasos/Pendientes) en
 * todo el módulo de Movimientos — ya pasó una vez, por eso este aviso.
 */
class ConvertirEntradaAMayusculas
{
    /**
     * Patrones (sobre el NOMBRE del campo) que nunca se convierten.
     */
    protected array $patronesExcluidos = [
        '/password/i',
        '/contrasena/i',
        '/contraseña/i',
        '/^email$/i',
        '/correo/i',
        // Texto de contratos / convenios (plantillas y documentos generados):
        // un documento legal completo en mayúsculas es ilegible.
        '/^contenido/i',
        // Campos internos de Laravel: NUNCA tocar. `_token` es el CSRF token,
        // sensible a mayúsculas/minúsculas — convertirlo tumbaría todos los
        // formularios con "Page Expired" / 419.
        '/^_token$/i',
        '/^_method$/i',
    ];

    /**
     * Rutas (por URI) donde no se aplica ninguna conversión.
     */
    protected array $rutasExcluidas = [
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'reset-password/*',
        'confirm-password',
        'password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is($this->rutasExcluidas)) {
            return $next($request);
        }

        // Solo se toca el BODY. GET nunca trae "body" real en esta app (la
        // query string es para filtros/orden/vista), así que no hay nada
        // que convertir y, sobre todo, nada que romper.
        if (!$request->isMethod('post') && !$request->isMethod('put') && !$request->isMethod('patch')) {
            return $next($request);
        }

        // Bag correcto según el tipo de petición: `json` para los POST por
        // axios con Content-Type: application/json, `request` para
        // formularios normales/multipart. La query string (`$request->query`)
        // queda completamente fuera, a propósito.
        $fuente = $request->isJson() ? $request->json() : $request->request;
        $datos = $fuente->all();

        if (!empty($datos)) {
            $fuente->replace($this->convertirRecursivo($datos));
        }

        return $next($request);
    }

    private function convertirRecursivo(array $datos, ?string $claveContenedora = null): array
    {
        foreach ($datos as $clave => $valor) {
            if (is_array($valor)) {
                // La clave del arreglo (p.ej. "empleados") no debe excluir a
                // sus hijos salvo que el propio hijo coincida con un patrón.
                $datos[$clave] = $this->convertirRecursivo($valor, is_string($clave) ? $clave : $claveContenedora);
                continue;
            }

            if (!is_string($valor) || $valor === '') {
                continue;
            }

            if ($this->campoExcluido((string) $clave)) {
                continue;
            }

            // Valores de CONTROL (banderas de checkbox/FormData), no texto capturado:
            // "false" -> "FALSE" rompía reglas como `in:true,false,1,0,on,off` y no
            // dejaba registrar pólizas (es_por_pagar / es_fiscal "inválidos").
            if (in_array($valor, ['true', 'false', 'on', 'off', 'null', 'undefined'], true)) {
                continue;
            }

            $datos[$clave] = mb_strtoupper($valor, 'UTF-8');
        }

        return $datos;
    }

    private function campoExcluido(string $clave): bool
    {
        foreach ($this->patronesExcluidos as $patron) {
            if (preg_match($patron, $clave)) {
                return true;
            }
        }

        return false;
    }
}
