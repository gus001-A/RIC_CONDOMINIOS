<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 2.2cm 2.4cm; }
        body { font-family: 'DejaVu Serif', serif; font-size: 11.5px; line-height: 1.55; color: #111827; }
        h1 { font-size: 15px; text-align: center; text-transform: uppercase; margin: 0 0 14px; letter-spacing: 0.5px; }
        h2 { font-size: 12.5px; margin: 16px 0 6px; text-transform: uppercase; }
        p { margin: 0 0 9px; text-align: justify; }
        strong { font-weight: bold; }
        /* Datos que faltaron al generar: se ven en amarillo para corregirlos. */
        mark { background: #fef08a; }
    </style>
</head>
<body>
    {{-- El HTML lo arma GeneradorDocumentos::aHtml() a partir de texto ya escapado. --}}
    {!! $html !!}
</body>
</html>
