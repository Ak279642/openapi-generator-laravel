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

## Publishing views

```bash
php artisan vendor:publish --tag=openapi-generator-views
```

Published views live under `resources/views/vendor/openapi-generator`.

## License

MIT
