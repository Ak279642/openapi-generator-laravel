<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class ControllerScanner
{
    private array $controllerCache = [];
    private ControllerResponseAnalyzer $responseAnalyzer;

    public function __construct()
    {
        $this->responseAnalyzer = new ControllerResponseAnalyzer();
    }

    public function scan(array $routes): array
    {
        $controllers = [];

        foreach ($routes as $route) {
            $controllerClass = $route['controller'] ?? null;

            if (!$controllerClass) {
                continue;
            }

            if (isset($this->controllerCache[$controllerClass])) {
                $controllers[$controllerClass] = $this->controllerCache[$controllerClass];
                continue;
            }

            if (!class_exists($controllerClass)) {
                continue;
            }

            $reflection = new ReflectionClass($controllerClass);
            $methods = $this->scanMethods($reflection);

            $controllers[$controllerClass] = [
                'class' => $controllerClass,
                'methods' => $methods,
                'routes' => $this->findRoutesForController($controllerClass, $routes),
            ];

            $this->controllerCache[$controllerClass] = $controllers[$controllerClass];
        }

        return $controllers;
    }

    private function scanMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {

            if ($method->class !== $reflection->getName()) {
                continue;
            }

            if (in_array($method->getName(), [
                '__construct',
                '__destruct',
                '__call',
                '__callStatic',
            ], true)) {
                continue;
            }

            $parameters = $method->getParameters();

            $requestClass = $this->findRequestClass($parameters);

            $returnType = null;
            $returnTypeString = null;

            $reflectionReturnType = $method->getReturnType();

            if ($reflectionReturnType instanceof ReflectionNamedType) {
                $returnType = $reflectionReturnType;
                $returnTypeString = $reflectionReturnType->getName();
            }

            $responseInfo = $this->responseAnalyzer->analyze($method);

            $methods[$method->getName()] = [
                'methodName'       => $method->getName(),
                'parameters'       => $parameters,
                'requestClass'     => $requestClass,
                'returnType'       => $returnType,
                'returnTypeString' => $returnTypeString,
                'docComment'       => $method->getDocComment() ?: '',
                'middleware'       => $this->getMethodMiddleware($method),

                'found'            => $responseInfo['found'] ?? false,
                'resourceClass'    => $responseInfo['resourceClass'] ?? null,
                'isCollection'     => $responseInfo['isCollection'] ?? false,
                'responseStatus'   => $responseInfo['status'] ?? 200,
                'responseHeaders'  => $responseInfo['headers'] ?? [],
                'responseType'     => $responseInfo['responseType'] ?? 'resource',

                'httpMethods'      => [],
                'queryParameters'  => [],
                'pathParameters'   => [],
                'bodyParameters'   => [],
                'validationErrors' => $requestClass !== null,
            ];
        }

        return $methods;
    }

    private function findRequestClass(array $parameters): ?string
    {
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if (!$type || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();
            if (class_exists($className) && is_subclass_of($className, FormRequest::class)) {
                return $className;
            }
        }

        return null;
    }

    private function findRoutesForController(string $controllerClass, array $routes): array
    {
        return array_filter($routes, function ($route) use ($controllerClass) {
            return ($route['controller'] ?? null) === $controllerClass;
        });
    }

    private function getMethodMiddleware(ReflectionMethod $method): array
    {
        $middleware = [];

        try {
            $attributes = $method->getAttributes(\Illuminate\Routing\Middleware::class);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                if (method_exists($instance, 'getMiddleware')) {
                    $middleware = array_merge($middleware, (array) $instance->getMiddleware());
                }
            }
        } catch (\Throwable $e) {
            // Skip if middleware cannot be read
        }

        return $middleware;
    }

    public function clearCache(): void
    {
        $this->controllerCache = [];
    }
}
