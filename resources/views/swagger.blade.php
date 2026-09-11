<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $config['ui']['brand'] ?? 'API' }} · API Reference</title>

    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f6f8fc;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .api-header {
            background: linear-gradient(135deg, #0b1a33 0%, #1a2f4f 100%);
            color: #fff;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            border-bottom: 2px solid #2d4b74;
            box-shadow: 0 4px 20px rgba(0, 20, 40, 0.2);
            position: relative;
            z-index: 10;
            min-height: 52px;
        }

        .api-header .brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-shrink: 0;
        }

        .api-header .brand .logo-img {
            height: 32px;
            width: auto;
            filter: brightness(0) invert(1) drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
            transition: filter 0.2s;
            display: block;
        }

        .api-header .brand .logo-img:hover {
            filter: brightness(0) invert(0.9) drop-shadow(0 2px 8px rgba(123, 179, 255, 0.3));
        }

        .api-header .brand .brand-text {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            line-height: 1;
        }

        .api-header .brand h1 {
            font-weight: 600;
            font-size: 1.2rem;
            letter-spacing: -0.02em;
            margin: 0;
            background: linear-gradient(to right, #f0f7ff, #b6d4ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .api-header .brand .subtitle {
            font-weight: 400;
            font-size: 0.6rem;
            color: #a0c0e8;
            background: rgba(255, 255, 255, 0.06);
            padding: 0.05rem 0.6rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            -webkit-text-fill-color: #cbdffa;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .api-header .badge-group {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        .api-header .badge-group .version-badge {
            background: rgba(255, 255, 255, 0.08);
            padding: 0.15rem 0.7rem;
            border-radius: 30px;
            font-size: 0.6rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .api-header .badge-group .version-badge i {
            font-size: 0.6rem;
            color: #7bb3ff;
        }

        .api-header .badge-group .env-tag {
            background: #1f3a5f;
            padding: 0.15rem 0.7rem;
            border-radius: 30px;
            font-size: 0.6rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #b6d4ff;
            border: 1px solid #2d4b74;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .api-header .badge-group .env-tag i {
            font-size: 0.55rem;
            color: #6bf0a0;
        }

        .api-header .header-actions {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-shrink: 0;
            margin-left: auto;
        }

        .api-header .header-actions .postman-btn {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            white-space: nowrap;
        }

        .api-header .header-actions .postman-btn i {
            color: #ff6c37;
            font-size: 0.75rem;
        }

        .api-header .header-actions .postman-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .api-header .header-actions .scalar-link {
            color: #a0c0e8;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            transition: color 0.2s;
            white-space: nowrap;
        }

        .api-header .header-actions .scalar-link:hover {
            color: #fff;
        }

        .api-header .header-actions .support-link {
            color: #a0c0e8;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            transition: color 0.2s;
            white-space: nowrap;
        }

        .api-header .header-actions .support-link:hover {
            color: #fff;
        }

        .manual-description {
            max-width: 1400px;
            margin: 1rem auto 0;
            padding: 0 2rem;
        }

        .manual-description .desc-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03), 0 1px 4px rgba(0, 20, 40, 0.04);
            border: 1px solid #e2ecf9;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .manual-description .desc-card:hover {
            border-color: #c0d6ed;
            box-shadow: 0 6px 20px rgba(11, 26, 51, 0.06);
        }

        .manual-description .desc-card .desc-body {
            color: #2d4b74;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .manual-description .desc-card .desc-body p {
            margin-bottom: 0.5rem;
        }

        .manual-description .desc-card .desc-body .inline-code {
            background: #f1f7fd;
            padding: 0.05rem 0.4rem;
            border-radius: 4px;
            font-family: 'Inter', monospace;
            font-size: 0.8rem;
            color: #0b1a33;
            border: 1px solid #e2ecf9;
        }

        .manual-description .desc-card .desc-body .link-scalar {
            color: #1f3a5f;
            font-weight: 600;
            text-decoration: none;
            border-bottom: 1.5px dotted #7bb3ff;
            transition: border-color 0.2s;
        }

        .manual-description .desc-card .desc-body .link-scalar:hover {
            border-bottom-color: #0b1a33;
        }

        .dev-note {
            margin-top: 0.5rem;
            background: #f0f7ff;
            border-left: 4px solid #1a2f4f;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #1a2f4f;
        }

        .dev-note strong {
            color: #0b1a33;
        }

        .dev-note i {
            margin-right: 0.3rem;
            color: #1f3a5f;
        }

        #swagger-ui {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem 2rem;
            background: transparent;
        }

        .swagger-ui {
            font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
        }

        .swagger-ui .info .title {
            font-weight: 600 !important;
            font-size: 2rem !important;
            letter-spacing: -0.02em;
            color: #0b1a33 !important;
        }

        .swagger-ui .info .title small {
            font-size: 0.75rem !important;
            background: #e9eff6 !important;
            color: #1a2f4f !important;
            padding: 0.15rem 0.7rem !important;
            border-radius: 30px !important;
            font-weight: 500 !important;
            border: 1px solid #d0dfee !important;
        }

        .swagger-ui .info .description {
            font-size: 0.95rem !important;
            color: #334e77 !important;
        }

        .swagger-ui .topbar {
            display: none !important;
        }

        .swagger-ui .btn {
            font-family: 'Inter', sans-serif !important;
            font-weight: 500 !important;
            border-radius: 8px !important;
            transition: all 0.15s ease !important;
            border: 1px solid transparent !important;
        }

        .swagger-ui .btn.authorize {
            border-color: #2d4b74 !important;
            color: #0b1a33 !important;
            background: white !important;
            box-shadow: 0 2px 6px rgba(0, 20, 40, 0.04) !important;
        }

        .swagger-ui .btn.authorize:hover {
            background: #f0f7ff !important;
            border-color: #1f3a5f !important;
            box-shadow: 0 4px 12px rgba(11, 26, 51, 0.08) !important;
        }

        .swagger-ui .btn.execute {
            background: #0b1a33 !important;
            color: white !important;
            border-radius: 8px !important;
            padding: 0.4rem 1.2rem !important;
            font-size: 0.85rem !important;
        }

        .swagger-ui .btn.execute:hover {
            background: #1a2f4f !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(11, 26, 51, 0.15) !important;
        }

        .swagger-ui .opblock {
            border-radius: 12px !important;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03), 0 1px 4px rgba(0, 20, 40, 0.04) !important;
            border: 1px solid #e2ecf9 !important;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
            margin-bottom: 1rem !important;
        }

        .swagger-ui .opblock:hover {
            border-color: #c0d6ed !important;
            box-shadow: 0 6px 20px rgba(11, 26, 51, 0.06) !important;
        }

        .swagger-ui .opblock .opblock-summary {
            padding: 0.6rem 1rem !important;
        }

        .swagger-ui .opblock .opblock-summary-method {
            border-radius: 6px !important;
            font-weight: 600 !important;
            font-size: 0.65rem !important;
            padding: 0.15rem 0.6rem !important;
            letter-spacing: 0.3px;
        }

        .swagger-ui .opblock-get .opblock-summary-method {
            background: #d4e6ff !important;
            color: #0055b3 !important;
        }

        .swagger-ui .opblock-post .opblock-summary-method {
            background: #d4f0d4 !important;
            color: #006b3f !important;
        }

        .swagger-ui .opblock-put .opblock-summary-method {
            background: #fff0d4 !important;
            color: #b45b0a !important;
        }

        .swagger-ui .opblock-patch .opblock-summary-method {
            background: #f0e6d4 !important;
            color: #8a6e2b !important;
        }

        .swagger-ui .opblock-delete .opblock-summary-method {
            background: #fce4e4 !important;
            color: #b33a3a !important;
        }

        .swagger-ui .response-col_status {
            font-weight: 500 !important;
            color: #0b1a33 !important;
        }

        .swagger-ui .tab-header {
            font-weight: 500 !important;
            color: #1e293b !important;
        }

        .swagger-ui .tab li {
            font-family: 'Inter', sans-serif !important;
            font-weight: 500 !important;
        }

        .swagger-ui .tab li.selected {
            border-bottom-color: #0b1a33 !important;
            color: #0b1a33 !important;
        }

        .swagger-ui .model-title {
            font-weight: 600 !important;
            color: #0b1a33 !important;
        }

        .swagger-ui .prop-type {
            color: #1f3a5f !important;
        }

        .swagger-ui .parameters-col_description {
            font-size: 0.85rem !important;
        }

        .swagger-ui .response-control-media-type {
            font-weight: 500 !important;
        }

        .swagger-ui .scheme-container {
            background: transparent !important;
            box-shadow: none !important;
            padding: 0.3rem 0 0.8rem !important;
            border-bottom: 1px solid #e2ecf9 !important;
        }

        .swagger-ui .info {
            margin: 0 0 1.5rem 0 !important;
            padding: 0.8rem 0 0.3rem 0 !important;
        }

        .swagger-ui .info .main .title {
            font-size: 1.8rem !important;
        }

        .swagger-ui .btn-cancel {
            background: #f1f5f9 !important;
            color: #1e293b !important;
            border-radius: 8px !important;
            border: 1px solid #d0dfee !important;
        }

        .swagger-ui .btn-cancel:hover {
            background: #e6edf6 !important;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #eef3fa;
        }

        ::-webkit-scrollbar-thumb {
            background: #b6cfe5;
            border-radius: 12px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94b3d1;
        }

        @media (max-width: 768px) {
            .api-header {
                padding: 0.4rem 1rem;
                gap: 0.5rem;
            }

            .api-header .brand .logo-img {
                height: 26px;
            }

            .api-header .brand h1 {
                font-size: 1rem;
            }

            .api-header .brand .subtitle {
                font-size: 0.5rem;
                padding: 0.05rem 0.4rem;
            }

            .api-header .badge-group .version-badge,
            .api-header .badge-group .env-tag {
                font-size: 0.5rem;
                padding: 0.1rem 0.5rem;
            }

            .api-header .header-actions {
                margin-left: 0;
            }

            .api-header .header-actions .postman-btn {
                font-size: 0.55rem;
                padding: 0.15rem 0.6rem;
            }

            .api-header .header-actions .scalar-link,
            .api-header .header-actions .support-link {
                font-size: 0.6rem;
            }

            .manual-description {
                padding: 0 1rem;
            }

            .manual-description .desc-card {
                padding: 0.8rem 1rem;
            }

            #swagger-ui {
                padding: 0.8rem 0.75rem 1.5rem;
            }
        }

        @media (max-width: 640px) {
            .api-header {
                flex-wrap: wrap;
                padding: 0.3rem 0.8rem;
                gap: 0.3rem;
            }

            .api-header .brand {
                gap: 0.4rem;
            }

            .api-header .brand .logo-img {
                height: 22px;
            }

            .api-header .brand h1 {
                font-size: 0.9rem;
            }

            .api-header .badge-group {
                gap: 0.3rem;
            }

            .api-header .header-actions {
                gap: 0.4rem;
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .manual-description .desc-card .desc-body {
                font-size: 0.8rem;
            }

            .dev-note {
                font-size: 0.75rem;
                padding: 0.4rem 0.7rem;
            }

            #swagger-ui {
                padding: 0.5rem 0.5rem 1rem;
            }
        }

        .swagger-ui .auth-wrapper {
            margin-left: 0.3rem !important;
        }

        .swagger-ui .info .main {
            border-bottom: none !important;
        }

        .swagger-ui .opblock-description-wrapper p {
            color: #2d4b74 !important;
            font-size: 0.85rem !important;
        }

        .swagger-ui .highlight-code {
            background: #f1f7fd !important;
            border-radius: 8px !important;
        }
    </style>
</head>

<body>
    <header class="api-header">
        <div class="brand">
            @if(!empty($config['ui']['logo_url']))<img src="{{ $config['ui']['logo_url'] }}" alt="{{ $config['ui']['brand'] ?? 'API' }} logo" class="logo-img" />@endif
            <div class="brand-text">
                <h1>{{ $config['ui']['brand'] ?? 'API' }}</h1>
                <span class="subtitle">Developer Reference</span>
            </div>
        </div>
        <div class="badge-group">
            <span class="version-badge">
                <i class="fas fa-tag"></i> {{ $config['ui']['version'] ?? '1.0.0' }}
            </span>
            <span class="env-tag">
                <i class="fas fa-check-circle"></i> API
            </span>
            <span class="env-tag" style="background:#1a2f4f; border-color:#3a5f8a;">
                <i class="fas fa-lock" style="color:#7bb3ff;"></i> OAuth 2.0
            </span>
        </div>
        <div class="header-actions">
            <a href="{{ $specUrl }}" download="openapi.json" class="postman-btn">
                <i class="fas fa-download"></i> OpenAPI JSON
            </a>
            <a href="{{ $scalarUrl }}" target="_blank" class="scalar-link">
                <i class="fas fa-cube"></i> Scalar
            </a>
            <a href="{{ $config['ui']['support_url'] ?? '#' }}" target="_blank" class="support-link">
                <i class="fas fa-life-ring"></i> Support
            </a>
        </div>
    </header>
    <div class="manual-description">
        <div class="desc-card">
            <div class="desc-body">
                <p>
                    <i class="fas fa-key" style="color:#1a2f4f; margin-right:6px;"></i>
                    <strong>Authentication:</strong> All requests must include a valid OAuth 2.0 bearer token.
                    Click the <span class="inline-code">Authorize</span> button to set your credentials.
                </p>
                <div class="dev-note">
                    <i class="fas fa-pencil-alt"></i>
                    <strong>Developer help:</strong> This API documentation is auto-generated.
                    To keep it in sync, use <strong>Controller</strong>, <strong>Form Request</strong> and
                    <strong>Json Resource</strong> based development.
                    For conditional request validation, define your rules inside the
                    <span class="inline-code">openApiDocs()</span> method in your Form Request classes.
                    The documentation reflects the latest schema from your code.
                </div>
                <p style="margin-bottom:0; margin-top:0.5rem; font-size:0.85rem; color:#5a7a9a;">
                    <i class="fas fa-arrow-right" style="margin-right:6px;"></i>
                    Need help? Check the <a href="{{ $config['ui']['support_url'] ?? '#' }}" target="_blank"
                        style="color:#1f3a5f; font-weight:500; text-decoration:underline dotted;">support portal</a>
                    or browse our <a href="{{ $config['ui']['support_url'] ?? '#' }}" target="_blank"
                        style="color:#1f3a5f; font-weight:500; text-decoration:underline dotted;">changelog</a>.
                    &nbsp;&nbsp;|&nbsp;&nbsp; <i class="fas fa-cube"></i>
                    <a href="{{ $scalarUrl }}" target="_blank" class="link-scalar">Scalar UI</a>
                </p>
            </div>
        </div>
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "{{ $specUrl }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                persistAuthorization: true,
                displayRequestDuration: true,
                displayOperationId: false,
                tryItOutEnabled: true,
                filter: true,
                showExtensions: true,
                showCommonExtensions: true,
                supportedSubmitMethods: [
                    "get", "post", "put", "patch", "delete"
                ],
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout",
                defaultModelExpandDepth: 1,
                defaultModelsExpandDepth: 1,
                docExpansion: 'list',
            });
            window.ui = ui;
        };
    </script>
</body>

</html>
