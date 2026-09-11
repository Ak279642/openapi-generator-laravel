<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $config['ui']['brand'] ?? 'API' }} · API Documentation</title>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0b1a33;
        }

        .api-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: linear-gradient(135deg, #0b1a33 0%, #1a2f4f 100%);
            color: #fff;
            padding: 0.3rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            border-bottom: 2px solid #2d4b74;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            min-height: 48px;
        }

        .api-header .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .api-header .brand .logo-img {
            height: 28px;
            width: auto;
            filter: brightness(0) invert(1) drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
            transition: filter 0.2s;
            display: block;
        }

        .api-header .brand .logo-img:hover {
            filter: brightness(0) invert(0.9) drop-shadow(0 2px 8px rgba(123, 179, 255, 0.3));
        }

        .api-header .brand h1 {
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: -0.02em;
            margin: 0;
            background: linear-gradient(to right, #f0f7ff, #b6d4ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .api-header .brand .version-tag {
            font-size: 0.55rem;
            font-weight: 500;
            color: #a0c0e8;
            background: rgba(255, 255, 255, 0.06);
            padding: 0.1rem 0.5rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            -webkit-text-fill-color: #cbdffa;
            white-space: nowrap;
        }

        .api-header .badge-group {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-shrink: 0;
        }

        .api-header .badge-group .badge-tag {
            background: rgba(255, 255, 255, 0.06);
            padding: 0.1rem 0.5rem;
            border-radius: 30px;
            font-size: 0.5rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 0.2rem;
            color: #b6d4ff;
            white-space: nowrap;
        }

        .api-header .badge-group .badge-tag i {
            font-size: 0.45rem;
            color: #6bf0a0;
        }

        .api-header .badge-group .badge-tag .lock-icon {
            color: #7bb3ff;
        }

        .api-header .header-links {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-shrink: 0;
            margin-left: auto;
        }

        .api-header .header-links a {
            color: #a0c0e8;
            text-decoration: none;
            font-size: 0.65rem;
            font-weight: 500;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
        }

        .api-header .header-links a:hover {
            color: #fff;
        }

        .api-header .header-links .postman-link {
            background: rgba(255, 255, 255, 0.06);
            padding: 0.15rem 0.7rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.6rem;
        }

        .api-header .header-links .postman-link i {
            color: #ff6c37;
        }

        .api-header .header-links .postman-link:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .api-header .header-links .dev-help-btn {
            background: rgba(255, 255, 255, 0.06);
            padding: 0.15rem 0.7rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.6rem;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            color: #a0c0e8;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .api-header .header-links .dev-help-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        #app {
            width: 100%;
            height: 100%;
            padding-top: 48px;
            background: #f6f8fc;
        }

        @media (prefers-color-scheme: dark) {
            #app {
                background: #0b1a33;
            }
        }

        .scalar-api-reference {
            --theme-background: #f6f8fc !important;
        }

        @media (prefers-color-scheme: dark) {
            .scalar-api-reference {
                --theme-background: #0b1a33 !important;
            }
        }

        @media (max-width: 768px) {
            .api-header {
                padding: 0.3rem 1rem;
                gap: 0.4rem;
                min-height: 44px;
            }

            .api-header .brand .logo-img {
                height: 22px;
            }

            .api-header .brand h1 {
                font-size: 0.95rem;
            }

            .api-header .badge-group .badge-tag {
                font-size: 0.4rem;
                padding: 0.05rem 0.4rem;
            }

            .api-header .header-links a {
                font-size: 0.55rem;
            }

            .api-header .header-links .postman-link,
            .api-header .header-links .dev-help-btn {
                font-size: 0.5rem;
                padding: 0.1rem 0.5rem;
            }

            #app {
                padding-top: 44px;
            }
        }

        @media (max-width: 480px) {
            .api-header {
                flex-wrap: wrap;
                padding: 0.2rem 0.6rem;
                gap: 0.2rem;
                min-height: 40px;
            }

            .api-header .brand {
                gap: 0.3rem;
            }

            .api-header .brand .logo-img {
                height: 18px;
            }

            .api-header .brand h1 {
                font-size: 0.8rem;
            }

            .api-header .brand .version-tag {
                font-size: 0.45rem;
                padding: 0.05rem 0.35rem;
            }

            .api-header .badge-group {
                gap: 0.2rem;
            }

            .api-header .badge-group .badge-tag {
                font-size: 0.35rem;
                padding: 0.05rem 0.3rem;
            }

            .api-header .header-links {
                gap: 0.3rem;
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .api-header .header-links a {
                font-size: 0.5rem;
            }

            .api-header .header-links .postman-link,
            .api-header .header-links .dev-help-btn {
                font-size: 0.45rem;
                padding: 0.05rem 0.4rem;
            }

            #app {
                padding-top: 40px;
            }
        }
    </style>
</head>
<body>

    <header class="api-header">
        <div class="brand">
            @if(!empty($config['ui']['logo_url']))<img src="{{ $config['ui']['logo_url'] }}" alt="{{ $config['ui']['brand'] ?? 'API' }} logo" class="logo-img" />@endif
            <h1>{{ $config['ui']['brand'] ?? 'API' }}</h1>
            <span class="version-tag">{{ $config['ui']['version'] ?? '1.0.0' }}</span>
        </div>
        <div class="badge-group">
            <span class="badge-tag">
                <i class="fas fa-check-circle"></i> API
            </span>
            <span class="badge-tag">
                <i class="fas fa-lock lock-icon"></i> OAuth 2.0
            </span>
        </div>
        <div class="header-links">
            <a href="{{ $specUrl }}" download="openapi.json" class="postman-link" title="Download OpenAPI JSON">
                <i class="fas fa-download"></i> OpenAPI JSON
            </a>
            <a href="{{ request()->url() }}" title="Scalar UI">
                <i class="fas fa-cube"></i> Scalar
            </a>
            <a href="{{ $config['ui']['support_url'] ?? '#' }}" target="_blank" title="Support">
                <i class="fas fa-life-ring"></i> Support
            </a>
            <a href="{{ $config['ui']['support_url'] ?? '#' }}" target="_blank" title="Changelog">
                <i class="fas fa-history"></i> Changelog
            </a>
            <button class="dev-help-btn" onclick="toggleDevNote()">
                <i class="fas fa-pencil-alt"></i> Dev Help
            </button>
        </div>
    </header>

    <div id="app"></div>

    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference/dist/browser/standalone.js">
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Scalar.createApiReference('#app', {
                url: @json($specUrl),
                layout: "classic",
                defaultOpenFirstTag: true,
                showSidebar: false,
                hideClientButton: false,
                showDeveloperTools: "localhost",
                showToolbar: "localhost",
                operationTitleSource: "summary",
                theme: "kepler",
                persistAuth: false,
                telemetry: @json($config['ui']['telemetry'] ?? false),
                externalUrls: {},
                default: false,
                isEditable: false,
                hideModels: false,
                documentDownloadType: "both",
                hideTestRequestButton: false,
                hideSearch: false,
                showOperationId: false,
                hideDarkModeToggle: false,
                withDefaultFonts: true,
                defaultOpenAllTags: false,
                expandAllModelSections: false,
                expandAllResponses: false,
                expandAllSchemaProperties: false,
                orderSchemaPropertiesBy: "alpha",
                orderRequiredPropertiesFirst: true,
                authentication: {
                    preferredSecurityScheme: "bearerAuth"
                },
                searchHotKey: "k",
                hideDownloadButton: false,
                darkMode: true,
                metaData: {
                    title: "{{ $config['ui']['brand'] ?? 'API' }} API Reference",
                    description: "Auto-generated API documentation for {{ $config['ui']['brand'] ?? 'API' }} platform"
                },
                modelsSectionLabel: "Models",
                slug: "api-1",
                title: "API #1"
            });
        });

        function toggleDevNote() {
            const existingNote = document.getElementById('devNotePopup');

            if (existingNote) {
                existingNote.remove();
                return;
            }

            const popup = document.createElement('div');
            popup.id = 'devNotePopup';
            popup.style.cssText = `
                position: fixed;
                top: 60px;
                right: 1.5rem;
                z-index: 1000;
                background: #ffffff;
                border-radius: 12px;
                padding: 1rem 1.2rem;
                max-width: 380px;
                box-shadow: 0 8px 30px rgba(0,0,0,0.12);
                border: 1px solid #e2ecf9;
                font-size: 0.8rem;
                color: #1a2f4f;
                line-height: 1.6;
                font-family: 'Inter', sans-serif;
            `;

            popup.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:0.4rem;">
                    <strong style="font-size:0.85rem;"><i class="fas fa-pencil-alt" style="color:#1a2f4f;margin-right:0.3rem;"></i>Developer Guidance</strong>
                    <button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;color:#5a7a9a;cursor:pointer;font-size:1.1rem;">&times;</button>
                </div>
                <p style="margin-bottom:0.4rem;">
                    This API documentation is <strong>auto-generated</strong>.
                    Use <strong>Controller</strong>, <strong>Form Request</strong> and
                    <strong>Json Resource</strong> based development to keep it in sync.
                </p>
                <p style="margin-bottom:0;">
                    Define conditional validation rules in
                    <span style="background:#f1f7fd;padding:0.05rem 0.35rem;border-radius:4px;font-family:monospace;font-size:0.75rem;border:1px solid #e2ecf9;">openApiDocs()</span>
                    method inside your Form Request classes.
                </p>
                <div style="margin-top:0.5rem;padding-top:0.4rem;border-top:1px solid #e2ecf9;font-size:0.7rem;color:#5a7a9a;">
                    <i class="fas fa-download"></i>
                    <a href="{{ $specUrl }}" style="color:#1f3a5f;text-decoration:none;font-weight:500;">Download OpenAPI JSON</a>
                </div>
            `;

            document.body.appendChild(popup);

            setTimeout(() => {
                document.addEventListener('click', function closePopup(e) {
                    if (!popup.contains(e.target) && !e.target.closest('.dev-help-btn')) {
                        popup.remove();
                        document.removeEventListener('click', closePopup);
                    }
                });
            }, 100);
        }
    </script>

</body>
</html>
