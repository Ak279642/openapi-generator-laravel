<?php

declare(strict_types=1);

return [
    'enabled' => env('OPENAPI_GENERATOR_ENABLED', true),

    'route_prefix' => 'docs',
    'swagger_path' => 'docs/swagger',
    'scalar_path' => 'docs/scalar',
    'json_path' => 'docs/openapi.json',
    'yaml_path' => 'docs/openapi.yaml',
    'middleware' => [],

    'scan' => [
        'route_prefixes' => ['api/'],
        'include_named_routes' => [],
        'exclude_paths' => [
            'telescope/*', 'docs/*', 'sanctum/*', 'up', 'ignition/*',
            'horizon/*', 'nova/*', 'pulse/*', '_debugbar/*', 'debugbar/*',
        ],
        'exclude_names' => ['telescope.*', 'horizon.*', 'nova.*', 'pulse.*', 'ignition.*'],
        'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    ],

    'output' => [
        'directory' => storage_path('app/openapi'),
        'json' => 'openapi.json',
        'yaml' => 'openapi.yaml',
    ],

    'openapi' => [
        'version' => '3.1.0',
        'info' => [
            'title' => env('OPENAPI_TITLE', env('APP_NAME', 'Laravel API')),
            'version' => env('OPENAPI_VERSION', '1.0.0'),
            'description' => env('OPENAPI_DESCRIPTION', 'Auto-generated API documentation.'),
        ],
        'servers' => [],
        'security' => [],
    ],

    'security' => [
        'enabled' => env('OPENAPI_SECURITY_ENABLED', false),
        'scheme_name' => 'bearerAuth',
        'type' => 'http',
        'scheme' => 'bearer',
        'bearer_format' => 'JWT',
        'middleware' => ['auth', 'auth:sanctum', 'auth:api', 'passport', 'jwt'],
    ],

    'ui' => [
        'title' => env('OPENAPI_UI_TITLE', 'API Documentation'),
        'brand' => env('OPENAPI_UI_BRAND', env('APP_NAME', 'Laravel API')),
        'version' => env('OPENAPI_VERSION', '1.0.0'),
        'logo_url' => env('OPENAPI_LOGO_URL'),
        'support_url' => env('OPENAPI_SUPPORT_URL'),
        'changelog_url' => env('OPENAPI_CHANGELOG_URL'),
        'telemetry' => false,
    ],

    'schemas' => [
        'max_depth' => 5,
    ],

    'cache' => [
        'enabled' => env('OPENAPI_GENERATOR_CACHE', false),
    ],
];
