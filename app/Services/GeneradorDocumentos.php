<?php

namespace App\Services;

use App\Models\Casa;
use App\Models\SolicitudServicio;
use Carbon\Carbon;

/**
 * Llena plantillas de contratos / convenios con los datos del condominio, la
 * casa y su propietario.
 *
 *   {{casa.identificador}}           variable del sistema (no distingue mayúsculas)
 *   {{campo:Nombre del arrendatario}} dato libre que se captura al generar
 *
 * Formato del texto (sencillo, para que cualquiera lo edite):
 *   # Título centrado      ## Subtítulo      **negritas**      línea vacía = párrafo
 */
class GeneradorDocumentos
{
    /** Catálogo de variables (clave => descripción) para la ayuda del editor. */
    public const VARIABLES = [
        'condominio.nombre' => 'Nombre del condominio',
        'condominio.rfc' => 'RFC del condominio',
        'condominio.direccion' => 'Dirección del condominio',
        'condominio.representante' => 'Representante / administrador legal',
        'casa.identificador' => 'Identificador de la casa (ej. M1-L01)',
        'casa.direccion' => 'Dirección de la casa dentro del condominio',
        'casa.direccion_completa' => 'Dirección de la casa + condominio',
        'casa.cuota' => 'Cuota mensual de mantenimiento',
        'casa.cuota_letra' => 'Cuota mensual con letra',
        'propietario.nombre' => 'Nombre del responsable de la casa',
        'propietario.telefono' => 'Teléfono del responsable',
        'propietario.correo' => 'Correo del responsable',
        'cuenta.saldo' => 'Saldo de cuotas (total)',
        'cuenta.saldo_letra' => 'Saldo de cuotas con letra',
        'cuenta.saldo_vencido' => 'Saldo vencido de cuotas',
        'cuenta.ultimo_pago' => 'Fecha del último pago de cuotas',
        'solicitud.folio' => 'Folio de la solicitud de servicio',
        'solicitud.precio' => 'Precio deseado (renta / venta)',
        'solicitud.precio_letra' => 'Precio deseado con letra',
        'solicitud.fecha_disponible' => 'Fecha de disponibilidad',
        'fecha.hoy' => 'Fecha de hoy (25/10/2026)',
        'fecha.hoy_letra' => 'Fecha de hoy con letra (25 de octubre de 2026)',
    ];

    public function __construct(private EstadoCuentaCuotas $estadoCuenta)
    {
    }

    /** Valores de todas las variables para una casa (y opcionalmente una solicitud). */
    public function valores(Casa $casa, ?SolicitudServicio $solicitud = null): array
    {
        $casa->loadMissing(['condominio', 'responsable']);
        $e = $casa->condominio;
        $r = $casa->responsable;
        $cuenta = $this->estadoCuenta->detalleCasa($casa)['resumen'];

        $dirCondominio = implode(', ', array_filter([
            $e?->calle ? trim($e->calle . ' ' . $e->numero_exterior) : null,
            $e?->colonia, $e?->municipio, $e?->estado, $e?->codigo_postal ? 'C.P. ' . $e->codigo_postal : null,
        ]));

        return [
            'condominio.nombre' => $e?->nombre_empresa,
            'condominio.rfc' => $e?->rfc,
            'condominio.direccion' => $dirCondominio,
            'condominio.representante' => trim(implode(' ', array_filter([$e?->representante_nombre, $e?->representante_apellido_paterno, $e?->representante_apellido_materno]))),
            'casa.identificador' => $casa->identificador,
            'casa.direccion' => $casa->direccion_corta,
            'casa.direccion_completa' => trim($casa->direccion_corta . ', ' . ($e?->nombre_empresa ?? '') . ($dirCondominio ? ', ' . $dirCondominio : ''), ', '),
            'casa.cuota' => $this->moneda($casa->cuota_mantenimiento),
            'casa.cuota_letra' => $this->conLetra($casa->cuota_mantenimiento),
            'propietario.nombre' => $r?->nombre_completo,
            'propietario.telefono' => $r?->telefono,
            'propietario.correo' => $r?->correo,
            'cuenta.saldo' => $this->moneda($cuenta['saldo']),
            'cuenta.saldo_letra' => $this->conLetra(max(0, $cuenta['saldo'])),
            'cuenta.saldo_vencido' => $this->moneda($cuenta['saldo_vencido']),
            'cuenta.ultimo_pago' => $cuenta['ultimo_pago'] ? Carbon::parse($cuenta['ultimo_pago'])->format('d/m/Y') : 'SIN PAGOS',
            'solicitud.folio' => $solicitud?->folio,
            'solicitud.precio' => $this->moneda($solicitud?->precio_deseado),
            'solicitud.precio_letra' => $this->conLetra($solicitud?->precio_deseado),
            'solicitud.fecha_disponible' => $solicitud?->fecha_disponible?->format('d/m/Y'),
            'fecha.hoy' => now()->format('d/m/Y'),
            'fecha.hoy_letra' => $this->fechaConLetra(now()),
        ];
    }

    /** Campos libres {{campo:Etiqueta}} que trae la plantilla, sin repetir. */
    public function camposLibres(string $contenido): array
    {
        preg_match_all('/\{\{\s*campo\s*:\s*([^}]+?)\s*\}\}/iu', $contenido, $m);

        return array_values(array_unique(array_map('trim', $m[1])));
    }

    /**
     * Sustituye variables y campos libres. Lo que no tenga valor se deja
     * marcado como [[...]] para que se note al revisar el documento.
     */
    public function llenar(string $contenido, array $valores, array $campos = []): string
    {
        $campos = array_change_key_case($campos, CASE_LOWER);

        $texto = preg_replace_callback('/\{\{\s*campo\s*:\s*([^}]+?)\s*\}\}/iu', function ($m) use ($campos) {
            $valor = $campos[mb_strtolower(trim($m[1]))] ?? null;
            return ($valor !== null && $valor !== '') ? $valor : '[[' . trim($m[1]) . ']]';
        }, $contenido);

        return preg_replace_callback('/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/iu', function ($m) use ($valores) {
            $clave = mb_strtolower($m[1]);
            if (!array_key_exists($clave, $valores)) {
                return $m[0]; // variable desconocida: se deja tal cual
            }
            $valor = $valores[$clave];
            return ($valor !== null && $valor !== '') ? $valor : '[[' . $clave . ']]';
        }, $texto);
    }

    /** Texto con formato sencillo → HTML seguro (para la vista y el PDF). */
    public function aHtml(string $texto): string
    {
        $html = [];
        foreach (preg_split("/\R{2,}/", trim(str_replace("\r", '', $texto))) as $bloque) {
            $lineas = explode("\n", $bloque);
            if (count($lineas) === 1 && preg_match('/^(#{1,2})\s+(.*)$/', $lineas[0], $m)) {
                $etiqueta = strlen($m[1]) === 1 ? 'h1' : 'h2';
                $html[] = "<{$etiqueta}>" . $this->enLinea($m[2]) . "</{$etiqueta}>";
                continue;
            }
            $html[] = '<p>' . implode('<br>', array_map(fn ($l) => $this->enLinea($l), $lineas)) . '</p>';
        }

        return implode("\n", $html);
    }

    private function enLinea(string $linea): string
    {
        $seguro = e($linea);
        $seguro = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $seguro);
        // Datos faltantes resaltados para que se noten al revisar.
        return preg_replace('/\[\[(.+?)\]\]/u', '<mark>[[ $1 ]]</mark>', $seguro);
    }

    private function moneda($monto): ?string
    {
        return $monto === null ? null : '$' . number_format((float) $monto, 2);
    }

    private function fechaConLetra(Carbon $f): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return $f->day . ' de ' . $meses[$f->month - 1] . ' de ' . $f->year;
    }

    /** 1850.50 → "(MIL OCHOCIENTOS CINCUENTA PESOS 50/100 M.N.)" */
    public function conLetra($monto): ?string
    {
        if ($monto === null) {
            return null;
        }
        $monto = round((float) $monto, 2);
        $enteros = (int) floor($monto);
        $centavos = (int) round(($monto - $enteros) * 100);
        $palabras = $enteros === 0 ? 'CERO' : $this->numeroALetras($enteros);
        $moneda = $enteros === 1 ? 'PESO' : 'PESOS';
        // "UN MILLÓN DE PESOS", "DOS MILLONES DE PESOS"
        if ($enteros >= 1000000 && $enteros % 1000000 === 0) {
            $moneda = 'DE ' . $moneda;
        }

        return sprintf('(%s %s %02d/100 M.N.)', $palabras, $moneda, $centavos);
    }

    private function numeroALetras(int $n): string
    {
        $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
            'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
            'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
        $decenas = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $menorMil = function (int $x) use ($unidades, $decenas, $centenas): string {
            if ($x === 100) {
                return 'CIEN';
            }
            $c = intdiv($x, 100);
            $r = $x % 100;
            $partes = [];
            if ($c) {
                $partes[] = $centenas[$c];
            }
            if ($r < 30) {
                if ($r) {
                    $partes[] = $unidades[$r];
                }
            } else {
                $partes[] = $decenas[intdiv($r, 10)] . ($r % 10 ? ' Y ' . $unidades[$r % 10] : '');
            }
            return implode(' ', $partes);
        };

        $millones = intdiv($n, 1000000);
        $miles = intdiv($n % 1000000, 1000);
        $resto = $n % 1000;
        $partes = [];
        if ($millones) {
            $partes[] = $millones === 1 ? 'UN MILLÓN' : $menorMil($millones) . ' MILLONES';
        }
        if ($miles) {
            $partes[] = $miles === 1 ? 'MIL' : $menorMil($miles) . ' MIL';
        }
        if ($resto) {
            $partes[] = $menorMil($resto);
        }

        return implode(' ', $partes);
    }
}
