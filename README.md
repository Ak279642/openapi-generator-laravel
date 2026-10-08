# Opeapi Generator Laravel

Laravel package for automatic OpenAPI 3.1 documentation generation from application routes, controllers, Form Requests, and JSON Resources.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12

## Installation

```bash
composer require ak279642/openapi-generator-laravel
php artisan vendor:publish --tag=openapi-generator-config
php artisan openapi:generate
```

## Documentation UIs

- Swagger UI: `/docs/swagger`
- Scalar: `/docs/scalar`
- JSON specification: `/docs/openapi.json`
- YAML specification: `/docs/openapi.yaml`

The route prefix and middleware are configurable.

## Generation

```bash
php artisan openapi:generate
php artisan openapi:generate --format=json
php artisan openapi:generate --format=yaml
php artisan openapi:generate --format=both
php artisan openapi:generate --check
php artisan openapi:generate --no-cache
```

`--output` may point to a file for a single format or to a directory for `both`.

## Configuration

Publish `config/openapi-generator.php` and configure:

- OpenAPI metadata and servers
- API route prefixes and exclusions
- security detection and bearer scheme
- generated file locations
- schema depth
- Swagger/Scalar branding
- documentation middleware

## Application conventions

The generator is intentionally convention-light. It reads the Laravel route collection and uses reflection/AST inspection where possible. Form Requests can expose conditional or documentation-specific metadata through an `openApiDocs()` method when your application uses that convention.

JSON Resources are inspected to build reusable component schemas and nested resource references are protected against recursion.



## Split and versioned API route files

The generator supports APIs split across nested route files, including:

```text
routes/
├── api.php
└── api/
    ├── v1.php
    ├── v2.php
    └── admin/
        └── v1.php
```

By default it inspects Laravel's registered route collection and also discovers `routes/api.php` plus PHP files below `routes/api/`. A file that has already been loaded by Laravel or a custom `RouteServiceProvider` is not loaded a second time.

Routes are included when either their URI matches `scan.route_prefixes` or their middleware matches `scan.include_middleware`. This means a route group such as `v1/users` with the `api` middleware is documented even when it does not start with `api/`.

Example custom provider registration:

```php
Route::middleware('api')
    ->prefix('v1')
    ->group(base_path('routes/api/v1.php'));
```

For applications where the nested filename should create the URL prefix automatically, enable:

```php
'scan' => [
    'route_file_prefix_from_path' => true,
],
```

Then `routes/api/v1.php` is loaded using the `api/v1` prefix. Leave this option disabled when the route file or your service provider already defines the version prefix.

Additional route files can be registered explicitly:

```php
'scan' => [
    'route_files' => [
        [
            'path' => 'routes/internal.php',
            'prefix' => 'internal',
            'middleware' => ['api'],
        ],
    ],
],
```

Set `discover_route_files` to `false` if route registration should be controlled entirely by the application.

## Inline controller validation

Controller methods no longer need a dedicated Form Request just to appear correctly in the generated OpenAPI document. The generator statically detects common inline Laravel validation patterns such as:

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:100',
        'email' => ['required', 'email'],
        'age' => ['nullable', 'integer', 'min:18'],
        'roles' => ['array'],
        'roles.*' => ['string'],
    ]);

    // ...
}
```

It also detects `Validator::make($data, [...])`. Detected rules are converted into request-body/query schemas, required fields, formats, enums, limits, nullable fields, nested objects/arrays, and multipart file fields where possible.

Form Requests remain fully supported and take precedence when a controller method uses one.

## Array-based response structures

Literal controller response arrays are now inspected and converted into OpenAPI response schemas automatically:

```php
return response()->json([
    'success' => true,
    'message' => 'User created',
    'data' => [
        'id' => 1,
        'name' => 'Avinash',
        'active' => true,
    ],
]);
```

The generated schema preserves the object/array shape and infers scalar types for nested literal values. Direct array returns are supported as well:

```php
return [
    'success' => true,
    'data' => [],
];
```

Dynamic expressions that cannot be determined safely at generation time are left as open schemas instead of executing application code.

## Publishing views

```bash
php artisan vendor:publish --tag=openapi-generator-views
```

Published views live under `resources/views/vendor/openapi-generator`.

## License

MIT
