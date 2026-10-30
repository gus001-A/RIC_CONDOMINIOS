<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Publicado en una carpeta del dominio (p. ej. https://ric.sistema-sicad.site/survicos, donde el .htaccess
// de la raíz manda cada carpeta a su public): Laravel toma esa carpeta como base de todas sus URLs.
// En local (php artisan serve) las rutas no empiezan con la carpeta y no cambia nada.
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$ruta = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (str_ends_with($script, '/public/index.php')) {
    $carpeta = substr($script, 0, -strlen('/public/index.php')); // p. ej. "/survicos"
    if (($ruta === $carpeta || str_starts_with($ruta, $carpeta.'/')) && ! str_starts_with($ruta, $carpeta.'/public')) {
        $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $carpeta.'/index.php';
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
