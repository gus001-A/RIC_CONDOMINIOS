<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de Contraseña - RIC</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .header {
            background: #0a1e2f;
            padding: 36px 40px 28px;
            text-align: center;
            border-bottom: 3px solid #d4a843;
        }
        .header img {
            max-height: 56px;
            width: auto;
            margin-bottom: 12px;
        }
        .header h1 {
            color: #ffffff;
            font-size: 26px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .header p {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            margin: 4px 0 0 0;
            font-weight: 300;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .content {
            padding: 40px 44px 32px;
        }
        .greeting {
            font-size: 20px;
            font-weight: 500;
            color: #0a1e2f;
            margin-bottom: 6px;
        }
        .greeting span {
            color: #0a1e2f;
            font-weight: 600;
        }
        .subhead {
            font-size: 15px;
            color: #475569;
            margin-bottom: 24px;
            border-bottom: 1px solid #e9edf2;
            padding-bottom: 16px;
        }
        .alert-box {
            background: #f0f4f8;
            border-left: 4px solid #0a1e2f;
            padding: 14px 20px;
            border-radius: 4px;
            margin: 8px 0 20px;
        }
        .alert-box p {
            margin: 0;
            font-size: 15px;
            color: #0a1e2f;
            font-weight: 500;
        }
        .text {
            color: #334155;
            line-height: 1.7;
            font-size: 15px;
        }
        .text strong {
            color: #0a1e2f;
            font-weight: 600;
        }
        .text a {
            color: #0a1e2f;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .button-wrapper {
            text-align: center;
            margin: 30px 0 28px;
        }
        .btn-reset {
            background: #0a1e2f;
            color: #ffffff;
            padding: 13px 44px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            display: inline-block;
            transition: background 0.2s ease;
            border: none;
            cursor: pointer;
            letter-spacing: 0.3px;
            border: 1px solid #0a1e2f;
        }
        .btn-reset:hover {
            background: #1a3650;
            border-color: #1a3650;
        }
        .divider {
            border: none;
            border-top: 1px solid #e9edf2;
            margin: 24px 0;
        }
        .url-label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 4px;
            font-weight: 500;
        }
        .url-box {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 4px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #0a1e2f;
            border: 1px solid #e2e8f0;
            margin: 4px 0 8px;
        }
        .info-grid {
            display: flex;
            gap: 20px;
            margin: 20px 0 8px;
            flex-wrap: wrap;
        }
        .info-item {
            flex: 1;
            min-width: 120px;
        }
        .info-item .label {
            font-size: 11px;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .info-item .value {
            font-size: 14px;
            color: #0a1e2f;
            font-weight: 500;
            margin-top: 2px;
        }
        .security-note {
            background: #f8fafc;
            padding: 14px 18px;
            border-radius: 4px;
            margin: 20px 0 4px;
            border: 1px solid #e9edf2;
        }
        .security-note p {
            margin: 0;
            font-size: 13px;
            color: #475569;
        }
        .security-note strong {
            color: #0a1e2f;
        }
        .footer {
            background: #fafbfc;
            padding: 24px 44px;
            text-align: center;
            border-top: 1px solid #e9edf2;
        }
        .footer .badges {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .footer .badge {
            background: #f1f4f8;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            color: #475569;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .footer .company {
            font-size: 13px;
            color: #94a3b8;
        }
        .footer .company strong {
            color: #0a1e2f;
            font-weight: 600;
        }
        .footer .copyright {
            font-size: 12px;
            color: #cbd5e1;
            margin-top: 4px;
        }
        .footer .auto-msg {
            font-size: 11px;
            color: #dce1e8;
            margin-top: 6px;
        }
        @media (max-width: 480px) {
            .content { padding: 28px 20px; }
            .header { padding: 28px 20px 22px; }
            .footer { padding: 20px; }
            .btn-reset { padding: 12px 28px; font-size: 14px; width: 100%; }
            .info-grid { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="RIC Logo">
            <h1>RIC</h1>
            <p>Red Informática Contable</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Estimado(a), <span>{{ $nombre_completo ?? 'Usuario' }}</span>
            </div>
            <div class="subhead">
                Restablecimiento de contraseña
            </div>

            <div class="alert-box">
                <p>Solicitud de restablecimiento de contraseña recibida</p>
            </div>

            <p class="text">
                Hemos recibido una solicitud para restablecer la contraseña de su cuenta en <strong>RIC</strong>.
                Si <strong>no</strong> realizó esta solicitud, puede ignorar este mensaje sin realizar ninguna acción adicional.
            </p>

            <div class="button-wrapper">
                <a href="{{ $reset_url }}" class="btn-reset">
                    Restablecer contraseña
                </a>
            </div>

            <hr class="divider">

            <p class="url-label">Enlace de restablecimiento</p>
            <div class="url-box">
                {{ $reset_url }}
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Validez</div>
                    <div class="value">60 minutos</div>
                </div>
                <div class="info-item">
                    <div class="label">Solicitado desde</div>
                    <div class="value">{{ $ip ?? 'Dirección IP registrada' }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Fecha</div>
                    <div class="value">{{ now()->format('d/m/Y H:i') }}</div>
                </div>
            </div>

            <div class="security-note">
                <p>
                    <strong>Nota de seguridad:</strong> Este enlace expirará automáticamente después de 60 minutos.
                    Si no solicitó este restablecimiento, recomendamos revisar la seguridad de su cuenta.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="badges">
                <span class="badge">Conexión segura</span>
                <span class="badge">Enlace temporal</span>
                <span class="badge">Protegido</span>
            </div>
            <p class="company">
                <strong>RIC</strong> &mdash; Red Informática Contable
            </p>
            <p class="copyright">
                &copy; {{ date('Y') }} RIC. Todos los derechos reservados.
            </p>
            <p class="auto-msg">
                Mensaje automático. Por favor, no responda a esta dirección.
            </p>
        </div>
    </div>
</body>
</html>