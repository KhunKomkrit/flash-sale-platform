<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Flash Sale Platform') }} API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body {
            margin: 0;
            background: #f7f7f7;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        function getLoginToken(response) {
            if (! response || response.status !== 200 || ! response.url.endsWith('/api/login')) {
                return null;
            }

            if (response.obj && response.obj.access_token) {
                return response.obj.access_token;
            }

            try {
                return JSON.parse(response.text || response.data || response.body || '{}').access_token || null;
            } catch (error) {
                return null;
            }
        }

        window.ui = SwaggerUIBundle({
            url: @json(url('/docs/openapi.json')),
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: true,
            responseInterceptor: function (response) {
                const token = getLoginToken(response);

                if (token) {
                    window.ui.authActions.authorize({
                        bearerAuth: {
                            name: 'bearerAuth',
                            schema: {
                                type: 'http',
                                scheme: 'bearer',
                                bearerFormat: 'Sanctum',
                            },
                            value: token,
                        },
                    });
                }

                return response;
            },
            presets: [
                SwaggerUIBundle.presets.apis,
            ],
        });
    </script>
</body>
</html>
