<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Llaves VAPID de las notificaciones push. Se generan una sola vez por servidor:
// si se cambian, todos los celulares tienen que volver a activar los avisos.
Artisan::command('push:llaves {--forzar : Reemplaza las llaves existentes}', function () {
    $env = base_path('.env');
    $contenido = file_get_contents($env);

    if (preg_match('/^WEBPUSH_PUBLIC_KEY=.+$/m', $contenido) && !$this->option('forzar')) {
        $this->info('Ya hay llaves de notificaciones en .env (usa --forzar para reemplazarlas).');

        return 0;
    }

    $llaves = \Minishlink\WebPush\VAPID::createVapidKeys();
    foreach (['WEBPUSH_PUBLIC_KEY' => $llaves['publicKey'], 'WEBPUSH_PRIVATE_KEY' => $llaves['privateKey']] as $clave => $valor) {
        $contenido = preg_match("/^{$clave}=.*$/m", $contenido)
            ? preg_replace("/^{$clave}=.*$/m", "{$clave}={$valor}", $contenido)
            : rtrim($contenido) . PHP_EOL . "{$clave}={$valor}" . PHP_EOL;
    }
    file_put_contents($env, $contenido);
    $this->call('config:clear');
    $this->info('Llaves de notificaciones push generadas y guardadas en .env.');

    return 0;
})->purpose('Genera las llaves VAPID para las notificaciones push');
