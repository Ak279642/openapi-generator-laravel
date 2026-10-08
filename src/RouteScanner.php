<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class RouteScanner
{
    private array $ignoredPaths = [];

    private array $supportedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private array $routeCache = [];

    private array $loadedRouteFiles = [];

    public function __construct(private readonly array $config = [])
    {
        $this->ignoredPaths = $config['exclude_paths'] ?? [];
        $this->supportedMethods = array_map('strtoupper', $config['methods'] ?? $this->supportedMethods);
    }

    public function scan(): array
    {
        if (!empty($this->routeCache)) {
            return $this->routeCache;
        }

        $this->discoverApiRouteFiles();

        $routes = [];
        $seen = [];
        $allRoutes = Route::getRoutes();

        foreach ($allRoutes as $route) {
            if ($this->shouldIgnore($route)) {
                continue;
            }

            $methods = $this->filterMethods($route->methods());

            if (empty($methods)) {
                continue;
            }

            if (!$this->isIncludedRoute($route)) {
                continue;
            }

            $data = $this->buildRouteData($route);
            $key = implode('|', $data['methods'])
                . '|' . $data['uri']
                . '|' . ($data['action'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $routes[] = $data;
        }

        $this->routeCache = $routes;

        return $routes;
    }

    /**
     * Laravel only exposes routes that have already been registered.
     *
     * Applications commonly split API routes into files such as
     * routes/api/v1.php, routes/api/v2.php, or deeper directories.
     * During documentation generation those files may not have been loaded by
     * the application's RouteServiceProvider yet, so discover and register
     * them before reading the route collection.
     */
    private function discoverApiRouteFiles(): void
    {
        if (!($this->config['discover_route_files'] ?? true)) {
            return;
        }

        try {
            if (function_exists('app') && method_exists(app(), 'routesAreCached') && app()->routesAreCached()) {
                return;
            }
        } catch (\Throwable) {
            // Fall back to normal discovery when the application state cannot be inspected.
        }

        foreach ($this->routeFileDefinitions() as $definition) {
            $path = $definition['path'];

            if (!is_file($path)) {
                continue;
            }

            $realPath = realpath($path) ?: $path;

            if ($this->wasAlreadyIncluded($realPath) || isset($this->loadedRouteFiles[$realPath])) {
                continue;
            }

            $middleware = $definition['middleware'];
            $prefix = trim((string) $definition['prefix'], '/');

            $registrar = Route::middleware($middleware);

            if ($prefix !== '') {
                $registrar = $registrar->prefix($prefix);
            }

            $registrar->group($realPath);
            $this->loadedRouteFiles[$realPath] = true;
        }
    }

    private function routeFileDefinitions(): array
    {
        $definitions = [];
        $defaultMiddleware = (array) ($this->config['route_file_middleware'] ?? ['api']);
        $defaultPrefix = trim((string) ($this->config['route_file_prefix'] ?? 'api'), '/');
        $prefixFromPath = (bool) ($this->config['route_file_prefix_from_path'] ?? false);

        $routesDirectory = function_exists('base_path')
            ? base_path('routes')
            : getcwd() . DIRECTORY_SEPARATOR . 'routes';

        $rootApiFile = $routesDirectory . DIRECTORY_SEPARATOR . 'api.php';

        if (is_file($rootApiFile)) {
            $definitions[] = [
                'path' => $rootApiFile,
                'middleware' => $defaultMiddleware,
                'prefix' => $defaultPrefix,
            ];
        }

        $apiDirectory = $routesDirectory . DIRECTORY_SEPARATOR . 'api';

        if (is_dir($apiDirectory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $apiDirectory,
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $relative = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    substr($file->getPathname(), strlen($apiDirectory) + 1)
                );

                $relativeWithoutExtension = preg_replace('/\.php$/i', '', $relative) ?: '';
                $prefix = $defaultPrefix;

                if ($prefixFromPath && $relativeWithoutExtension !== '') {
                    $prefix = trim($defaultPrefix . '/' . $relativeWithoutExtension, '/');
                }

                $definitions[] = [
                    'path' => $file->getPathname(),
                    'middleware' => $defaultMiddleware,
                    'prefix' => $prefix,
                ];
            }
        }

        foreach ((array) ($this->config['route_files'] ?? []) as $configured) {
            if (is_string($configured)) {
                $definitions[] = [
                    'path' => $this->normalizeRouteFilePath($configured),
                    'middleware' => $defaultMiddleware,
                    'prefix' => $defaultPrefix,
                ];

                continue;
            }

            if (!is_array($configured) || !isset($configured['path'])) {
                continue;
            }

            $definitions[] = [
                'path' => $this->normalizeRouteFilePath((string) $configured['path']),
                'middleware' => (array) ($configured['middleware'] ?? $defaultMiddleware),
                'prefix' => (string) ($configured['prefix'] ?? $defaultPrefix),
            ];
        }

        $unique = [];

        foreach ($definitions as $definition) {
            $key = realpath($definition['path']) ?: $definition['path'];
            $unique[$key] = $definition;
        }

        return array_values($unique);
    }

    private function normalizeRouteFilePath(string $path): string
    {
        if ($path === '') {
            return $path;
        }

        if (
            str_starts_with($path, DIRECTORY_SEPARATOR) ||
            preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
        ) {
            return $path;
        }

        return function_exists('base_path')
            ? base_path($path)
            : getcwd() . DIRECTORY_SEPARATOR . $path;
    }

    private function wasAlreadyIncluded(string $path): bool
    {
        foreach (get_included_files() as $included) {
            if ((realpath($included) ?: $included) === $path) {
                return true;
            }
        }

        return false;
    }

    private function shouldIgnore(IlluminateRoute $route): bool
    {
        $uri = $route->uri();
        $name = $route->getName();

        foreach ($this->ignoredPaths as $ignored) {
            if (fnmatch((string) $ignored, $uri)) {
                return true;
            }
        }

        if ($name !== null) {
            $ignoredNames = $this->config['exclude_names']
                ?? ['telescope.*', 'horizon.*', 'nova.*', 'pulse.*', 'ignition.*'];

            foreach ($ignoredNames as $ignored) {
                if (fnmatch((string) $ignored, $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function filterMethods(array $methods): array
    {
        return array_values(array_filter($methods, function ($method) {
            return in_array(strtoupper((string) $method), $this->supportedMethods, true);
        }));
    }

    private function isIncludedRoute(IlluminateRoute $route): bool
    {
        $uri = $route->uri();
        $prefixes = $this->config['route_prefixes'] ?? ['api/'];
        $named = $this->config['include_named_routes'] ?? [];

        foreach ($prefixes as $prefix) {
            $prefix = trim((string) $prefix, '/');

            if ($prefix === '' || $uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return true;
            }
        }

        $includeMiddleware = (array) ($this->config['include_middleware'] ?? ['api']);

        foreach ($route->middleware() as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }

            foreach ($includeMiddleware as $pattern) {
                $pattern = (string) $pattern;

                if (
                    $pattern !== '' &&
                    (
                        $middleware === $pattern ||
                        fnmatch($pattern, $middleware) ||
                        str_starts_with($middleware, $pattern . ':')
                    )
                ) {
                    return true;
                }
            }
        }

        $name = $route->getName();

        foreach ($named as $pattern) {
            if ($name !== null && fnmatch((string) $pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    private function buildRouteData(IlluminateRoute $route): array
    {
        $action = $route->getActionName();
        $controller = null;
        $method = null;

        if ($action !== 'Closure' && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
        }

        return [
            'uri' => $route->uri(),
            'methods' => $this->filterMethods($route->methods()),
            'action' => $action,
            'controller' => $controller,
            'method' => $method,
            'middleware' => $route->middleware(),
            'prefix' => $route->getPrefix() ?? '',
            'name' => $route->getName(),
            'domain' => $route->getDomain(),
            'parameters' => $route->parameterNames(),
        ];
    }

    public function clearCache(): void
    {
        $this->routeCache = [];
    }
}
