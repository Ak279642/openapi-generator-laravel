<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

class OpenApiWriter
{
    public function __construct(private readonly SchemaRegistry $registry, private readonly array $config = [])
    {
    }

    public function write(array $routes, array $controllers, array $requests, array $resources): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => $this->getInfo(),
            'servers' => $this->getServers(),
            'paths' => $this->buildPaths($routes, $controllers, $requests),
            'components' => $this->buildComponents(),
            'security' => $this->getSecurity(),
        ];
    }

    private function getInfo(): array
    {
        return $this->config['openapi']['info'] ?? [
            'title' => 'Laravel API',
            'version' => '1.0.0',
            'description' => 'Auto-generated API documentation.',
        ];
    }

    private function getServers(): array
    {
        return $this->config['openapi']['servers'] ?? [];
    }

    private function buildPaths(array $routes, array $controllers, array $requests): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $path = '/' . $route['uri'];
            $controllerClass = $route['controller'] ?? null;

            if (!$controllerClass || !isset($controllers[$controllerClass])) {
                continue;
            }

            $controller = $controllers[$controllerClass];
            $methodName = $route['method'] ?? null;

            if (!$methodName || !isset($controller['methods'][$methodName])) {
                continue;
            }

            $methodInfo = $controller['methods'][$methodName];
            $requestInfo = $this->getRequestInfo($methodInfo['requestClass'] ?? null, $requests);

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            foreach ($route['methods'] as $method) {
                $methodLower = strtolower($method);
                $paths[$path][$methodLower] = $this->buildOperation($route, $methodInfo, $requestInfo);
            }
        }

        return $paths;
    }

    private function buildOperation(
        array $route,
        array $methodInfo,
        ?array $requestInfo
    ): array {
        $httpMethod = strtolower($route['_http_method'] ?? ($route['methods'][0] ?? 'get'));

        $operation = [
            'tags' => $this->generateTags($route),
            'summary' => $this->generateSummary($methodInfo, $route),
            'operationId' => $this->generateOperationId($route),
            'responses' => $this->buildResponses(
                $methodInfo,
                $requestInfo
            ),
        ];
        $parameters = [];

        if (in_array($httpMethod, ['get', 'delete'], true)) {
            $parameters = $this->buildParameters(
                $route,
                $requestInfo
            );
        } else {
            foreach ($route['parameters'] ?? [] as $parameter) {
                $parameters[] = [
                    'name' => $parameter,
                    'in' => 'path',
                    'required' => true,
                    'schema' => $this->getPathParameterSchema($parameter),
                ];
            }
        }

        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        if (in_array($httpMethod, ['post', 'put', 'patch'], true)) {
            $requestBody = $this->buildRequestBody($requestInfo);

            if ($requestBody !== null) {
                $operation['requestBody'] = $requestBody;
            }
        }

        if ($this->hasAuth($methodInfo)) {
            $operation['security'] = [
                [
                    ($this->config['security']['scheme_name'] ?? 'bearerAuth') => [],
                ],
            ];
        }

        if (!empty($methodInfo['deprecated'])) {
            $operation['deprecated'] = true;
        }

        return $operation;
    }
    private function buildParameters(
        array $route,
        ?array $requestInfo
    ): array {
        $parameters = [];


        foreach ($route['parameters'] ?? [] as $parameter) {
            $parameters[] = [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                ],
            ];
        }


        if ($requestInfo !== null && !empty($requestInfo['queryParameters'])) {
            foreach ($requestInfo['queryParameters'] as $parameter) {
                $parameters[] = $parameter;
            }
        }


        $unique = [];

        foreach ($parameters as $parameter) {
            $key = ($parameter['in'] ?? '') . ':' . ($parameter['name'] ?? '');

            $unique[$key] = $parameter;
        }

        return array_values($unique);
    }

    private function getPathParameterSchema(string $name): array
    {
        if ($name === 'id' || str_ends_with($name, '_id')) {
            return ['type' => 'integer'];
        }

        if (str_contains($name, 'uuid')) {
            return [
                'type' => 'string',
                'format' => 'uuid',
            ];
        }

        return ['type' => 'string'];
    }

    private function buildResponses(
        array $methodInfo,
        ?array $requestInfo,
    ): array {
        $responses = [];

        $resourceClass = $methodInfo['resourceClass'] ?? null;
        $isCollection = $methodInfo['isCollection'] ?? false;

        if (
            !empty($methodInfo['found']) &&
            is_string($resourceClass) &&
            $this->registry->has($resourceClass)
        ) {
            $schemaName = $this->registry->getSchemaName($resourceClass);

            $dataSchema = $isCollection
                ? [
                    'type' => 'array',
                    'items' => [
                        '$ref' => "#/components/schemas/{$schemaName}",
                    ],
                ]
                : [
                    '$ref' => "#/components/schemas/{$schemaName}",
                ];

            $schema = [
                'type' => 'object',
                'properties' => [
                    'success' => [
                        'type' => 'boolean',
                        'example' => true,
                    ],
                    'message' => [
                        'type' => 'string',
                        'example' => 'Success',
                    ],
                    'data' => $dataSchema,
                    'meta' => [
                        '$ref' => $isCollection
                            ? '#/components/schemas/PaginatedResponseMeta'
                            : '#/components/schemas/ResponseMeta',
                    ],
                ],
                'required' => [
                    'success',
                    'message',
                    'data',
                    'meta',
                ],
            ];

            $responses['200'] = [
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => $schema,
                    ],
                ],
            ];
        } else {
            $schema = $this->getDefaultSuccessSchema();

            $schema['properties']['meta'] = [
                '$ref' => $isCollection
                    ? '#/components/schemas/PaginatedResponseMeta'
                    : '#/components/schemas/ResponseMeta',
            ];

            $schema['required'] = [
                'success',
                'message',
                'data',
                'meta',
            ];

            $responses['200'] = [
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => $schema,
                    ],
                ],
            ];
        }

        if ($requestInfo !== null) {
            $responses['422'] = $this->getValidationErrorResponse();
        }

        if ($this->hasAuth($methodInfo)) {
            $responses['401'] = $this->getErrorResponse(
                'Unauthorized',
                'UNAUTHORIZED'
            );

            $responses['403'] = $this->getErrorResponse(
                'Forbidden',
                'FORBIDDEN'
            );
        }

        $responses['404'] = $this->getErrorResponse(
            'Not Found',
            'NOT_FOUND'
        );

        $responses['500'] = $this->getServerErrorResponse();

        return $responses;
    }
    private function getDefaultSuccessSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'success' => [
                    'type' => 'boolean',
                    'example' => true,
                ],
                'message' => [
                    'type' => 'string',
                    'example' => 'Success',
                ],
                'data' => [
                    'oneOf' => [
                        [
                            'type' => 'object',
                        ],
                        [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                            ],
                        ],
                        [
                            'type' => 'null',
                        ],
                    ],
                ],
                'meta' => [
                    '$ref' => '#/components/schemas/ResponseMeta',
                ],
            ],
            'required' => [
                'success',
                'message',
                'data',
                'meta',
            ],
        ];
    }

    private function getErrorResponse(
        string $message,
        string $code
    ): array {
        return [
            'description' => $message,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean',
                                'example' => false,
                            ],
                            'message' => [
                                'type' => 'string',
                                'example' => $message,
                            ],
                            'error_code' => [
                                'type' => 'string',
                                'example' => $code,
                            ],
                        ],
                        'required' => [
                            'success',
                            'message',
                            'error_code',
                        ],
                    ],
                ],
            ],
        ];
    }
    private function getValidationErrorResponse(): array
    {
        return [
            'description' => 'Validation Error',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean',
                                'example' => false,
                            ],
                            'message' => [
                                'type' => 'string',
                                'example' => 'Validation failed.',
                            ],
                            'error_code' => [
                                'type' => 'string',
                                'example' => 'VALIDATION_ERROR',
                            ],
                            'errors' => [
                                'type' => 'object',
                                'additionalProperties' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                        ],
                        'required' => [
                            'success',
                            'message',
                            'error_code',
                            'errors',
                        ],
                    ],
                ],
            ],
        ];
    }
    private function getServerErrorResponse(): array
    {
        return [
            'description' => 'Internal Server Error',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean',
                                'example' => false,
                            ],
                            'message' => [
                                'type' => 'string',
                                'example' => 'Internal Server Error',
                            ],
                            'data' => [
                                'oneOf' => [
                                    [
                                        'type' => 'object',
                                    ],
                                    [
                                        'type' => 'null',
                                    ],
                                ],
                                'example' => null,
                            ],
                            'error_code' => [
                                'type' => 'string',
                                'example' => 'SERVER_ERROR',
                            ],
                        ],
                        'required' => [
                            'success',
                            'message',
                            'data',
                            'error_code',
                        ],
                    ],
                ],
            ],
        ];
    }
    private function buildRequestBody(?array $requestInfo): ?array
    {
        if ($requestInfo === null) {
            return null;
        }

        $schema = $requestInfo['requestBody'] ?? null;

        if ($schema === null) {
            return null;
        }

        if (isset($schema['_contentType'])) {
            unset($schema['_contentType']);
        }

        $contentType = $requestInfo['contentType'] ?? 'application/json';

        $content = [
            $contentType => [
                'schema' => $schema,
            ],
        ];

        if ($contentType === 'multipart/form-data') {
            $content['multipart/form-data']['encoding'] = $this->buildMultipartEncoding(
                $schema['properties'] ?? []
            );
        }

        return [
            'required' => true,
            'content' => $content,
        ];
    }

    private function buildMultipartEncoding(array $properties): array
    {
        $encoding = [];

        foreach ($properties as $name => $property) {
            if (($property['type'] ?? '') === 'object') {
                foreach ($this->flattenMultipartObject($property, $name) as $key => $value) {
                    $encoding[$key] = $value;
                }

                continue;
            }

            if (($property['type'] ?? '') === 'array') {
                $encoding[$name] = [
                    'style' => 'form',
                    'explode' => true,
                ];

                continue;
            }

            $encoding[$name] = [
                'style' => 'form',
            ];
        }

        return $encoding;
    }

    private function flattenMultipartObject(array $property, string $prefix): array
    {
        $encoding = [];

        foreach ($property['properties'] ?? [] as $name => $child) {
            $field = "{$prefix}[{$name}]";

            if (($child['type'] ?? '') === 'object') {
                $encoding += $this->flattenMultipartObject($child, $field);
                continue;
            }

            if (($child['type'] ?? '') === 'array') {
                $encoding[$field] = [
                    'style' => 'form',
                    'explode' => true,
                ];

                continue;
            }

            $encoding[$field] = [
                'style' => 'form',
            ];
        }

        return $encoding;
    }
    private function getRequestInfo(
        ?string $requestClass,
        array $requests
    ): ?array {
        if (
            $requestClass === null ||
            !isset($requests[$requestClass])
        ) {
            return null;
        }

        return $requests[$requestClass];
    }

    private function generateSummary(array $methodInfo, array $route): string
    {
        $docComment = $methodInfo['docComment'] ?? null;

        if ($docComment) {
            if (preg_match('/@summary\s+(.+)/', $docComment, $matches)) {
                return trim($matches[1]);
            }

            if (preg_match('/@description\s+(.+)/', $docComment, $matches)) {
                return trim($matches[1]);
            }

            $lines = explode("\n", $docComment);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, '*') && !str_starts_with($line, '* @') && !str_starts_with($line, '*/')) {
                    $summary = trim(substr($line, 1));
                    if (!empty($summary) && !str_starts_with($summary, '/')) {
                        return $summary;
                    }
                }
            }
        }

        $method = implode(', ', $route['methods']);
        $controller = class_basename($route['controller'] ?? '');
        $action = $route['method'] ?? 'index';

        return "{$method} {$controller}::{$action}";
    }

    private function generateOperationId(array $route): string
    {
        $controller = class_basename($route['controller'] ?? 'Controller');
        $controller = str_replace('Controller', '', $controller);

        return sprintf(
            '%s_%s',
            lcfirst($controller),
            $route['method'] ?? 'index'
        );
    }
    private function generateTags(array $route): array
    {
        $controller = $route['controller'] ?? null;

        if ($controller === null) {
            return ['General'];
        }

        try {
            $reflection = new \ReflectionClass($controller);

            $parts = explode('\\', $reflection->getNamespaceName());

            $apiIndex = array_search('Api', $parts, true);

            if ($apiIndex !== false) {

                $namespaces = array_slice($parts, $apiIndex + 2);

                $namespaces = array_values(array_filter($namespaces, static function ($part) {
                    return !in_array($part, [
                        'Controllers',
                        'Controller',
                    ], true);
                }));

                if (!empty($namespaces)) {
                    return [
                        end($namespaces),
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return [
            str_replace(
                'Controller',
                '',
                class_basename($controller)
            ),
        ];
    }
    private function buildComponents(): array
    {
        $schemas = [];

        foreach ($this->registry->getAll() as $name => $schema) {

            if (isset($schema['_contentType'])) {
                unset($schema['_contentType']);
            }

            $schemas[$name] = $schema;
        }
        $schemas['ResponseMeta'] = [
            'type' => 'object',
            'properties' => [
                'request_id' => [
                    'type' => 'string',
                    'example' => '5d57a3db-30e6-48ba-bd2c-1d5ddc35d985',
                ],
                'response_time' => [
                    'type' => 'string',
                    'example' => '23.45ms',
                ],
                'timestamp' => [
                    'type' => 'string',
                    'format' => 'date-time',
                ],
                'timestamp_unix' => [
                    'type' => 'integer',
                ],
                'api_version' => [
                    'type' => 'string',
                ],
                'environment' => [
                    'type' => 'string',
                ],
                'environment_display' => [
                    'type' => 'string',
                ],
                'applied_filters' => [
                    'type' => 'object',
                    'nullable' => true,
                    'additionalProperties' => true,
                ],
            ],
        ];

        $schemas['PaginatedResponseMeta'] = [
            'allOf' => [
                [
                    '$ref' => '#/components/schemas/ResponseMeta',
                ],
                [
                    'type' => 'object',
                    'properties' => [
                        'pagination' => [
                            'type' => 'object',
                            'properties' => [
                                'total' => [
                                    'type' => 'integer',
                                ],
                                'per_page' => [
                                    'type' => 'integer',
                                ],
                                'current_page' => [
                                    'type' => 'integer',
                                ],
                                'last_page' => [
                                    'type' => 'integer',
                                ],
                                'from' => [
                                    'type' => 'integer',
                                    'nullable' => true,
                                ],
                                'to' => [
                                    'type' => 'integer',
                                    'nullable' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];


        $schemas['ValidationError'] = [
            'type' => 'object',
            'properties' => [
                'success' => [
                    'type' => 'boolean',
                    'example' => false,
                ],
                'message' => [
                    'type' => 'string',
                    'example' => 'Validation failed',
                ],
                'error_code' => [
                    'type' => 'string',
                    'example' => 'VALIDATION_ERROR',
                ],
                'errors' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'meta' => [
                    '$ref' => '#/components/schemas/ResponseMeta',
                ],
            ],
            'required' => [
                'success',
                'message',
                'error_code',
                'errors',
                'meta',
            ],
        ];

        $schemas['ErrorResponse'] = [
            'type' => 'object',
            'properties' => [
                'success' => [
                    'type' => 'boolean',
                    'example' => false,
                ],
                'message' => [
                    'type' => 'string',
                ],
                'error_code' => [
                    'type' => 'string',
                ],
                'meta' => [
                    '$ref' => '#/components/schemas/ResponseMeta',
                ],
            ],
            'required' => [
                'success',
                'message',
                'error_code',
                'meta',
            ],
        ];

        return [
            'schemas' => $schemas,
            'securitySchemes' => $this->getSecuritySchemes(),
            'responses' => $this->getCommonResponses(),
        ];
    }

    private function getSecuritySchemes(): array
    {
        $name = $this->config['security']['scheme_name'] ?? 'bearerAuth';
        return [
            $name => [
                'type' => $this->config['security']['type'] ?? 'http',
                'scheme' => $this->config['security']['scheme'] ?? 'bearer',
                'bearerFormat' => $this->config['security']['bearer_format'] ?? 'JWT',
            ],
        ];
    }

    private function getSecurity(): array
    {
        if (!($this->config['security']['enabled'] ?? false)) {
            return $this->config['openapi']['security'] ?? [];
        }
        $name = $this->config['security']['scheme_name'] ?? 'bearerAuth';
        return [[$name => []]];
    }

    private function hasAuth(array $methodInfo): bool
    {
        $middleware = $methodInfo['middleware'] ?? [];

        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        foreach ($middleware as $item) {

            if (!is_string($item)) {
                continue;
            }

            foreach (($this->config['security']['middleware'] ?? []) as $pattern) {
                if (str_starts_with($item, (string) $pattern) || str_contains($item, (string) $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }
    private function getCommonResponses(): array
    {
        return [
            'Unauthorized' => [
                'description' => 'Unauthorized',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorResponse',
                        ],
                    ],
                ],
            ],

            'Forbidden' => [
                'description' => 'Forbidden',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorResponse',
                        ],
                    ],
                ],
            ],

            'NotFound' => [
                'description' => 'Not Found',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorResponse',
                        ],
                    ],
                ],
            ],

            'ValidationError' => [
                'description' => 'Validation Error',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ValidationError',
                        ],
                    ],
                ],
            ],

            'ServerError' => [
                'description' => 'Internal Server Error',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/ErrorResponse',
                        ],
                    ],
                ],
            ],
        ];
    }
}
