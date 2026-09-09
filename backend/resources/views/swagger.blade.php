<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Finance Tracker — API Documentation (Swagger)</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
        }

        .header-bar {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-bottom: 1px solid #334155;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            font-size: 28px;
        }

        .header-title {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: #f8fafc;
            letter-spacing: -0.02em;
        }

        .header-subtitle {
            margin: 0;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .badges {
            display: flex;
            gap: 8px;
        }

        .badge {
            background: #1e3a8a;
            color: #93c5fd;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid #2563eb;
        }

        .badge-success {
            background: #064e3b;
            color: #6ee7b7;
            border-color: #059669;
        }

        .btn-raw {
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-raw:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        #swagger-ui {
            background: #ffffff;
            min-height: calc(100vh - 72px);
        }

        /* Swagger UI Custom Tweaks */
        .swagger-ui .topbar {
            display: none !important;
        }

        .swagger-ui .info {
            margin: 30px 0 20px 0;
        }

        .swagger-ui .info .title {
            font-family: 'Inter', sans-serif;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <header class="header-bar">
        <div class="header-left">
            <span class="logo-icon">💰</span>
            <div>
                <h1 class="header-title">WhatsApp Finance Tracker API</h1>
                <p class="header-subtitle">REST Interface for Vue 3 Web Dashboard, React Native Expo Mobile, & Baileys Gateway</p>
            </div>
        </div>
        <div class="badges">
            <span class="badge">Laravel 11 Core</span>
            <span class="badge badge-success">OpenAPI 3.0</span>
            <a href="/openapi.json" target="_blank" class="btn-raw">📥 Download openapi.json</a>
        </div>
    </header>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.18.2/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            window.ui = SwaggerUIBundle({
                url: "/openapi.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "BaseLayout",
                persistAuthorization: true,
                displayRequestDuration: true,
                filter: true,
                docExpansion: "list",
                defaultModelsExpandDepth: 2
            });
        };
    </script>
</body>
</html>
