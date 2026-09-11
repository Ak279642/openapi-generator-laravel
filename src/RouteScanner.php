<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;

class RouteScanner
{
    private array $ignoredPaths = [];

    private array $supportedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly array $config = [])
    {
        $this->ignoredPaths = $config['exclude_paths'] ?? [];
        $this->supportedMethods = array_map('strtoupper', $config['methods'] ?? $this->supportedMethods);
    }

    private array $routeCache = [];

    public function scan(): array
    {
        if (!empty($this->routeCache)) {
            return $this->routeCache;
        }

        $routes = [];
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

            $routes[] = $this->buildRouteData($route);
        }

        $this->routeCache = $routes;
        return $routes;
    }

    private function shouldIgnore(IlluminateRoute $route): bool
    {
        $uri = $route->uri();
        $name = $route->getName();

        foreach ($this->ignoredPaths as $ignored) {
            if (fnmatch($ignored, $uri)) {
                return true;
            }
        }

        if ($name !== null) {
            $ignoredNames = ['telescope.*', 'horizon.*', 'nova.*', 'pulse.*', 'ignition.*'];
            foreach ($ignoredNames as $ignored) {
                if (fnmatch($ignored, $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function filterMethods(array $methods): array
    {
        return array_filter($methods, function ($method) {
            return in_array($method, $this->supportedMethods);
        });
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
            [$controller, $method] = explode('@', $action);
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
