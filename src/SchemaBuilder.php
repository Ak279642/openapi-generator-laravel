<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;


class SchemaBuilder
{
    private SchemaRegistry $registry;
    private TypeResolver $typeResolver;
    private int $maxDepth = 3;
    private array $processingStack = [];
    private ResourceScanner $resourceScanner;

    public function __construct(
        SchemaRegistry $registry,
        TypeResolver $typeResolver,
        ResourceScanner $resourceScanner
    ) {
        $this->registry = $registry;
        $this->typeResolver = $typeResolver;
        $this->resourceScanner = $resourceScanner;
    }

    public function build(array $resources, array $requests): void
    {
        // Build resource schemas first
        foreach ($resources as $class => $data) {
            if (!$this->registry->has($class)) {

                $this->buildResourceSchema($class, $data);
                // echo "REGISTERED: {$class}\n";
            }
        }

        // Build request schemas
        foreach ($requests as $class => $data) {
            if (!$this->registry->has($class)) {
                $this->buildRequestSchema($class, $data);
                // echo "REGISTERED: {$class}\n";
            }
        }
    }

    private function buildResourceSchema(string $class, array $data): void
    {
        if ($this->isMaxDepthReached()) {
            return;
        }

        if ($this->isCircularReference($class)) {
            return;
        }

        $this->processingStack[] = $class;

        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        $properties = $data['properties'] ?? [];

        // First pass: build all properties including nested resources
        foreach ($properties as $key => $property) {
            $resolved = $this->resolveProperty($property);
            $schema['properties'][$key] = $resolved;

            if (isset($property['required']) && $property['required'] === true) {
                $schema['required'][] = $key;
            }
        }

        // Ensure schema has at least some properties
        if (empty($schema['properties'])) {
            $schema['properties']['id'] = ['type' => 'integer'];
            $schema['required'][] = 'id';
        }

        // Register the complete schema
        $this->registry->register($class, $schema);

        array_pop($this->processingStack);
    }

    private function resolveProperty(array $property): array
    {
        // Handle direct reference
        if (isset($property['$ref'])) {
            $refClass = $property['$ref'];
            if (is_string($refClass)) {
                // Ensure the referenced schema exists
                if (!$this->registry->has($refClass)) {
                    // Try to build the nested resource
                    if (class_exists($refClass) && $this->resourceScanner->isResource($refClass)) {
                        $nestedData = $this->resourceScanner->scanClass($refClass);
                        if ($nestedData !== null) {
                            $this->buildResourceSchema($refClass, $nestedData);
                        }
                    }
                }

                if ($this->registry->has($refClass)) {
                    return [
                        '$ref' => '#/components/schemas/' . $this->registry->getSchemaName($refClass),
                    ];
                }
            }

            return ['type' => 'object'];
        }

        // Handle array of resources
        if (isset($property['type']) && $property['type'] === 'array' && isset($property['items'])) {
            $items = $this->resolveProperty($property['items']);
            return [
                'type' => 'array',
                'items' => $items,
            ];
        }

        // Handle nullable
        if (isset($property['nullable']) && $property['nullable'] === true) {
            $schema = $this->resolveProperty(array_diff_key($property, ['nullable' => true]));
            $schema['nullable'] = true;
            return $schema;
        }

        // Keep primitive types as-is
        return $property;
    }

    private function buildRequestSchema(string $class, array $data): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        $fields = $data['fields'] ?? [];
        $required = $data['required'] ?? [];

        foreach ($fields as $field => $fieldSchema) {
            $schema['properties'][$field] = $this->typeResolver->resolveValidation($fieldSchema);
        }

        $schema['required'] = $required;

        if (!empty($data['contentType'])) {
            $schema['_contentType'] = $data['contentType'];
        }

        $this->registry->register($class, $schema);
    }

    private function isMaxDepthReached(): bool
    {
        return count($this->processingStack) >= $this->maxDepth;
    }

    private function isCircularReference(string $class): bool
    {
        return in_array($class, $this->processingStack);
    }

    public function clearCache(): void
    {
        $this->processingStack = [];
    }
}
