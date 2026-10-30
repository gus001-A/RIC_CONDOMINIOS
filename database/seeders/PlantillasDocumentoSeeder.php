<?php

namespace Database\Seeders;

use App\Models\PlantillaDocumento;
use Illuminate\Database\Seeder;

/**
 * Plantillas generales (para todos los condominios) de ejemplo.
 *
 *   php artisan db:seed --class=PlantillasDocumentoSeeder
 *
 * Son MODELOS DE REFERENCIA: antes de usarlas con clientes deben revisarse
 * con un abogado y ajustarse a la legislación del estado.
 */
class PlantillasDocumentoSeeder extends Seeder
{
    private const AVISO = 'Modelo de referencia. Revísalo con un abogado antes de usarlo.';

    public function run(): void
    {
        $plantillas = [
            [
                'nombre' => 'Contrato de arrendamiento de casa habitación',
                'tipo' => 'CONTRATO_ARRENDAMIENTO',
                'contenido' => <<<'TXT'
# Contrato de arrendamiento de casa habitación

En {{condominio.direccion}}, a {{fecha.hoy_letra}}, celebran el presente contrato de arrendamiento, por una parte **{{propietario.nombre}}**, en lo sucesivo "EL ARRENDADOR", y por la otra **{{campo:Nombre del arrendatario}}**, en lo sucesivo "EL ARRENDATARIO", al tenor de las siguientes:

## Cláusulas

**PRIMERA. Inmueble.** EL ARRENDADOR da en arrendamiento a EL ARRENDATARIO la casa identificada como **{{casa.identificador}}**, ubicada en {{casa.direccion_completa}}, para uso exclusivo de casa habitación.

**SEGUNDA. Renta.** EL ARRENDATARIO pagará una renta mensual de {{solicitud.precio}} {{solicitud.precio_letra}}, dentro de los primeros {{campo:Días para pagar la renta}} días de cada mes.

**TERCERA. Vigencia.** El presente contrato tendrá una vigencia de {{campo:Vigencia en meses}} meses contados a partir del {{campo:Fecha de inicio}}.

**CUARTA. Depósito.** EL ARRENDATARIO entrega en este acto la cantidad de {{campo:Depósito en garantía}} como depósito en garantía, que le será devuelto al término del contrato si el inmueble se entrega en las condiciones en que lo recibió.

**QUINTA. Cuotas de mantenimiento.** La cuota de mantenimiento del condominio, actualmente de {{casa.cuota}} {{casa.cuota_letra}} mensuales, será cubierta por {{campo:Quién paga la cuota de mantenimiento}}.

**SEXTA. Reglamento.** EL ARRENDATARIO se obliga a respetar el reglamento interno de {{condominio.nombre}} y será responsable de los daños que cause a las áreas comunes.

**SÉPTIMA. Fiador.** Se constituye como fiador y obligado solidario **{{campo:Nombre del fiador}}**.

Leído que fue el presente contrato y enteradas las partes de su contenido y alcance, lo firman de conformidad.



EL ARRENDADOR
{{propietario.nombre}}



EL ARRENDATARIO
{{campo:Nombre del arrendatario}}



FIADOR
{{campo:Nombre del fiador}}
TXT,
            ],
            [
                'nombre' => 'Convenio de pago de cuotas de mantenimiento',
                'tipo' => 'CONVENIO_PAGO',
                'contenido' => <<<'TXT'
# Convenio de pago de cuotas de mantenimiento

En {{condominio.direccion}}, a {{fecha.hoy_letra}}, la administración de **{{condominio.nombre}}**, representada por {{condominio.representante}}, y **{{propietario.nombre}}**, responsable de la casa **{{casa.identificador}}**, acuerdan lo siguiente:

**PRIMERO.** El condómino reconoce un adeudo por cuotas de mantenimiento de {{cuenta.saldo}} {{cuenta.saldo_letra}}, con corte al {{fecha.hoy}}.

**SEGUNDO.** El adeudo se cubrirá en {{campo:Número de pagos}} pagos de {{campo:Monto de cada pago}}, a partir del {{campo:Fecha del primer pago}}, sin perjuicio de seguir cubriendo puntualmente la cuota ordinaria de {{casa.cuota}} mensuales.

**TERCERO.** En caso de incumplimiento de dos pagos consecutivos, el saldo pendiente será exigible en su totalidad.

**CUARTO.** Los pagos se realizarán a las cuentas del condominio y se reflejarán en el estado de cuenta de la casa.

Firman de conformidad.



POR LA ADMINISTRACIÓN
{{condominio.representante}}



EL CONDÓMINO
{{propietario.nombre}}
TXT,
            ],
            [
                'nombre' => 'Constancia de no adeudo',
                'tipo' => 'CARTA',
                'contenido' => <<<'TXT'
# Constancia de no adeudo

{{condominio.nombre}}
{{condominio.direccion}}

A quien corresponda:

La administración de **{{condominio.nombre}}** hace constar que la casa **{{casa.identificador}}**, ubicada en {{casa.direccion}}, cuyo responsable es **{{propietario.nombre}}**, presenta al día {{fecha.hoy_letra}} un saldo de cuotas de mantenimiento de {{cuenta.saldo}}, siendo su último pago registrado el {{cuenta.ultimo_pago}}.

Se extiende la presente a petición del interesado para los fines que a él convengan.



Atentamente
{{condominio.representante}}
Administración de {{condominio.nombre}}
TXT,
            ],
        ];

        foreach ($plantillas as $p) {
            PlantillaDocumento::updateOrCreate(
                ['id_empresa' => null, 'nombre' => $p['nombre']],
                $p + ['descripcion' => self::AVISO, 'activo' => true]
            );
        }

        $this->command->info('Plantillas generales listas (modelos de referencia).');
    }
}
