<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Módulos heredados de RIC que no se usan en RIC Condominios
    |--------------------------------------------------------------------------
    | Se ocultan del menú y sus rutas quedan bloqueadas (middleware `modulo`),
    | pero el código sigue en el proyecto: para reactivar uno, quítalo de aquí.
    |
    |  personas            Catálogo de proveedores / empleados / clientes
    |                      (en las pólizas el proveedor queda opcional).
    |  nomina              Pólizas de nómina dentro de Movimientos.
    |  fondeadoras_usuario Asignación de cuentas de banco por usuario: sin ella,
    |                      cada usuario usa todas las fondeadoras de su condominio.
    |  dashboard_contable  Tablero de estadísticas de RIC.
    */
    'modulos_ocultos' => ['personas', 'nomina', 'fondeadoras_usuario', 'dashboard_contable'],

    /*
    | Botones de "Accesos de demostración" en el login (una cuenta por rol).
    | null (sin definir): sólo con APP_ENV=local y entrando por localhost.
    | true: siempre (servidor de demostración) · false: nunca.
    */
    'accesos_demo' => env('ACCESOS_DEMO') === null ? null : filter_var(env('ACCESOS_DEMO'), FILTER_VALIDATE_BOOL),
];
