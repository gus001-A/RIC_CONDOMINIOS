<?php

namespace Database\Seeders;

use App\Models\Casa;
use App\Models\Condomino;
use App\Models\Cuenta;
use App\Models\CuotaCargo;
use App\Models\Empresa;
use App\Models\Poliza;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de prueba del módulo de condominios (SÓLO para desarrollo local).
 *
 *   php artisan db:seed --class=CondominiosDemoSeeder
 *
 * Usuarios de prueba:
 *   ADMIN.DEMO      / Demo2026!   (ADMINISTRADOR del condominio demo)
 *   CONDOMINO.DEMO  / Demo2026!   (CONDOMINO de la casa M1-L01)
 *   MANTTO.DEMO     / Demo2026!   (MANTENIMIENTO del condominio demo)
 *   CASETA.DEMO     / Demo2026!   (VIGILANCIA del condominio demo)
 */
class CondominiosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $condominio = Empresa::updateOrCreate(
            ['nombre_empresa' => 'CONDOMINIO DEMO LAS FLORES'],
            [
                'rfc' => 'CDL010101AA1',
                'tipo_persona' => 'MORAL',
                'clave' => 'DEMO',
                'calle' => 'AV. DE LAS FLORES',
                'numero_exterior' => '100',
                'colonia' => 'CENTRO',
                'ciudad' => 'CUAUTLA',
                'municipio' => 'CUAUTLA',
                'estado' => 'MORELOS',
                'codigo_postal' => '62740',
                'activo' => true,
            ]
        );

        $admin = Usuario::updateOrCreate(
            ['nombre_usuario' => 'ADMIN.DEMO'],
            [
                'nombre_completo' => 'ADMINISTRADOR DEMO',
                'email' => 'admin.demo@condominios.test',
                'password_hash' => Hash::make('Demo2026!'),
                'tipo_usuario' => 'ADMINISTRADOR',
                'activo' => true,
            ]
        );
        $admin->empresas()->syncWithoutDetaching([$condominio->id]);

        $casas = [
            ['identificador' => 'M1-L01', 'manzana' => '1', 'lote' => '01', 'calle' => 'PRIVADA ROSAS', 'numero_exterior' => '1', 'latitud' => 18.8121500, 'longitud' => -98.9544200, 'cuota_mantenimiento' => 850],
            ['identificador' => 'M1-L02', 'manzana' => '1', 'lote' => '02', 'calle' => 'PRIVADA ROSAS', 'numero_exterior' => '3', 'estatus_ocupacion' => 'EN RENTA', 'cuota_mantenimiento' => 850],
        ];

        foreach ($casas as $datos) {
            Casa::updateOrCreate(
                ['id_empresa' => $condominio->id, 'identificador' => $datos['identificador']],
                $datos + [
                    'tipo_propiedad' => 'CASA',
                    'estatus_ocupacion' => $datos['estatus_ocupacion'] ?? 'HABITADA',
                    'id_usuario_registro' => $admin->id_usuario,
                    'id_usuario_modifico' => $admin->id_usuario,
                ]
            );
        }

        $casa = Casa::where('id_empresa', $condominio->id)->where('identificador', 'M1-L01')->first();

        $usuarioCondomino = Usuario::updateOrCreate(
            ['nombre_usuario' => 'CONDOMINO.DEMO'],
            [
                'nombre_completo' => 'MARIA LOPEZ HERNANDEZ',
                'email' => 'condomino.demo@condominios.test',
                'password_hash' => Hash::make('Demo2026!'),
                'telefono' => '7350000001',
                'tipo_usuario' => 'CONDOMINO',
                'activo' => true,
            ]
        );
        $usuarioCondomino->empresas()->syncWithoutDetaching([$condominio->id]);

        Condomino::updateOrCreate(
            ['id_usuario' => $usuarioCondomino->id_usuario],
            [
                'id_casa' => $casa->id,
                'tipo' => 'PROPIETARIO',
                'es_responsable' => true,
                'nombre' => 'MARIA',
                'apellido_paterno' => 'LOPEZ',
                'apellido_materno' => 'HERNANDEZ',
                'telefono' => '7350000001',
                'correo' => 'condomino.demo@condominios.test',
                'emergencia_nombre' => 'JUAN LOPEZ',
                'emergencia_telefono' => '7350000002',
                'emergencia_parentesco' => 'HERMANO',
                'activo' => true,
                'id_usuario_registro' => $admin->id_usuario,
                'id_usuario_modifico' => $admin->id_usuario,
            ]
        );

        $mantenimiento = Usuario::updateOrCreate(
            ['nombre_usuario' => 'MANTTO.DEMO'],
            [
                'nombre_completo' => 'JOSE RAMIREZ (MANTENIMIENTO)',
                'email' => 'mantto.demo@condominios.test',
                'password_hash' => Hash::make('Demo2026!'),
                'tipo_usuario' => 'MANTENIMIENTO',
                'activo' => true,
            ]
        );
        $mantenimiento->empresas()->syncWithoutDetaching([$condominio->id]);

        $vigilancia = Usuario::updateOrCreate(
            ['nombre_usuario' => 'CASETA.DEMO'],
            [
                'nombre_completo' => 'GUARDIA DE CASETA DEMO',
                'email' => 'caseta.demo@condominios.test',
                'password_hash' => Hash::make('Demo2026!'),
                'tipo_usuario' => 'VIGILANCIA',
                'activo' => true,
            ]
        );
        $vigilancia->empresas()->syncWithoutDetaching([$condominio->id]);

        $this->contabilidadDemo($condominio, $admin);
        $this->inventarioDemo($condominio, $admin);

        $this->command->info('Condominio demo listo: ADMIN.DEMO y CONDOMINO.DEMO (ver contraseñas en este seeder).');
    }

    /**
     * Insumos con existencia inicial. Las compras pasan por el servicio de
     * Inventario para que el kárdex quede igual que si se capturaran a mano.
     */
    private function inventarioDemo(Empresa $condominio, Usuario $admin): void
    {
        if (\App\Models\Insumo::where('id_empresa', $condominio->id)->exists()) {
            return; // ya sembrado
        }
        auth()->setUser($admin);
        $inventario = app(\App\Services\Inventario::class);

        $insumos = [
            ['FOC-LED9', 'FOCO LED 9W LUZ BLANCA', 'ELECTRICO', 'PIEZA', 10, 24, 38.50, 'BODEGA'],
            ['FOT-120', 'FOTOCELDA 120V', 'ELECTRICO', 'PIEZA', 2, 3, 95.00, 'BODEGA'],
            ['CLO-GAL', 'CLORO EN GALÓN', 'LIMPIEZA', 'GALON', 4, 6, 52.00, 'CUARTO DE LIMPIEZA'],
            ['TEF-ROL', 'CINTA TEFLÓN', 'PLOMERIA', 'ROLLO', 5, 2, 12.00, 'BODEGA'],
            ['FER-BUL', 'FERTILIZANTE 25 KG', 'JARDINERIA', 'BULTO', 1, 0, 420.00, 'JARDÍN'],
        ];

        foreach ($insumos as [$clave, $nombre, $categoria, $unidad, $minimo, $cantidad, $costo, $ubicacion]) {
            $insumo = \App\Models\Insumo::create([
                'id_empresa' => $condominio->id,
                'clave' => $clave,
                'nombre' => $nombre,
                'categoria' => $categoria,
                'unidad' => $unidad,
                'stock_minimo' => $minimo,
                'ubicacion' => $ubicacion,
                'id_usuario_registro' => $admin->id_usuario,
                'id_usuario_modifico' => $admin->id_usuario,
            ]);
            if ($cantidad > 0) {
                $inventario->entrada($insumo, $cantidad, $costo, ['proveedor' => 'FERRETERÍA CENTRO', 'referencia' => 'NOTA DEMO']);
            }
        }
    }

    /**
     * Cuentas, pólizas y cuotas de ejemplo para probar Transparencia y Cuotas.
     */
    private function contabilidadDemo(Empresa $condominio, Usuario $admin): void
    {
        $cuenta = fn ($codigo, $nombre, $datos) => Cuenta::updateOrCreate(
            ['id_empresa' => $condominio->id, 'codigo_cuenta' => $codigo],
            $datos + ['nombre_cuenta' => $nombre, 'en_uso' => true, 'nivel' => '2']
        );

        $banco = $cuenta('DEMO-100', 'BANCO CONDOMINIO', ['tipo_cuenta' => 'FONDEADORA', 'fondeo_c' => 1, 'Naturaleza' => 'DEUDORA']);
        $ingresoCuotas = $cuenta('DEMO-400', 'INGRESOS POR CUOTAS', ['tipo_cuenta' => 'RESULTADO', 'es_cuenta_resultados' => 1, 'Naturaleza' => 'ACREEDORA']);
        $jardineria = $cuenta('DEMO-510', 'JARDINERÍA ÁREAS COMUNES', ['tipo_cuenta' => 'RESULTADO', 'es_cuenta_resultados' => 1, 'Naturaleza' => 'DEUDORA']);
        $vigilancia = $cuenta('DEMO-520', 'SERVICIO DE VIGILANCIA', ['tipo_cuenta' => 'RESULTADO', 'es_cuenta_resultados' => 1, 'Naturaleza' => 'DEUDORA']);
        $admin->cuentasFondeadorasAsignadas()->syncWithoutDetaching([$banco->id_cuenta]);

        if (Poliza::where('id_empresa', $condominio->id)->exists()) {
            return; // ya sembrado
        }

        $mes = now()->startOfMonth();
        $polizas = [
            ['INGRESO', $ingresoCuotas, 1700, 'COBRO CUOTAS M1-L01 Y M1-L02', 2],
            ['EGRESO', $jardineria, -1200, 'PODA Y RIEGO DE ÁREAS VERDES', 5],
            ['EGRESO', $vigilancia, -4500, 'SERVICIO DE VIGILANCIA QUINCENA 1', 15],
        ];
        foreach ($polizas as [$tipo, $cuentaResultado, $monto, $referencia, $dia]) {
            $poliza = Poliza::create([
                'id_empresa' => $condominio->id,
                'fecha_poliza' => $mes->copy()->day($dia),
                'tipo_poliza' => $tipo,
                'categoria' => 'NO_FISCAL',
                'estatus' => 'AUTORIZADO',
                'referencia' => $referencia,
                'id_usuario_creador' => $admin->id_usuario,
            ]);
            $poliza->movimientos()->create([
                'id_cuenta' => $cuentaResultado->id_cuenta,
                'id_caja_fondo' => $banco->id_cuenta,
                'monto' => $monto,
                'monto_base' => abs($monto),
            ]);
        }

        // Cuotas: tres meses de cargos; M1-L01 al corriente, M1-L02 debe dos meses.
        $casas = Casa::where('id_empresa', $condominio->id)->get();
        foreach ([2, 1, 0] as $atras) {
            $periodo = now()->startOfMonth()->subMonths($atras);
            foreach ($casas as $casa) {
                CuotaCargo::firstOrCreate(
                    ['id_casa' => $casa->id, 'periodo' => $periodo->toDateString(), 'concepto' => 'Cuota de mantenimiento'],
                    [
                        'id_empresa' => $condominio->id,
                        'monto' => $casa->cuota_mantenimiento,
                        'fecha_vencimiento' => $periodo->copy()->day(10)->toDateString(),
                        'id_usuario_registro' => $admin->id_usuario,
                        'id_usuario_modifico' => $admin->id_usuario,
                    ]
                );
            }
        }
        $pagos = ['M1-L01' => 3, 'M1-L02' => 1];
        foreach ($casas as $casa) {
            for ($i = 0; $i < ($pagos[$casa->identificador] ?? 0); $i++) {
                $casa->pagosCuota()->create([
                    'id_empresa' => $condominio->id,
                    'fecha_pago' => now()->startOfMonth()->subMonths(2 - $i)->day(5)->toDateString(),
                    'monto' => $casa->cuota_mantenimiento,
                    'forma_pago' => 'TRANSFERENCIA',
                    'id_usuario_registro' => $admin->id_usuario,
                    'id_usuario_modifico' => $admin->id_usuario,
                ]);
            }
        }
    }
}
