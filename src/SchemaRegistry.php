<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use Illuminate\Support\Str;

class SchemaRegistry
{
    private array $schemas = [];
    private array $schemaNames = [];
    private array $reservedNames = [];

    public function register(string $class, array $schema): void
    {
        // Skip empty schemas
        if (empty($schema) || (isset($schema['properties']) && empty($schema['properties']))) {
            return;
        }

        $name = $this->generateSchemaName($class);

        // Check if this schema already exists
        if (isset($this->schemas[$class])) {
            $this->schemas[$class] = $this->mergeSchemas($this->schemas[$class], $schema);
            return;
        }

        $this->schemas[$class] = $schema;
        $this->schemaNames[$class] = $name;
        $this->reservedNames[$name] = true;
    }

    public function has(string $class): bool
    {
        return isset($this->schemas[$class]);
    }

    public function get(string $class): array
    {
        return $this->schemas[$class] ?? [];
    }

    public function getSchemaName(string $class): string
    {
        if (isset($this->schemaNames[$class])) {
            return $this->schemaNames[$class];
        }

        return $this->generateSchemaName($class);
    }

    public function getAll(): array
    {
        $result = [];

        foreach ($this->schemas as $class => $schema) {
            $name = $this->getSchemaName($class);
            $result[$name] = $schema;
        }

        return $result;
    }

    public function getSchemaForClass(string $class): ?array
    {
        if ($this->has($class)) {
            return $this->get($class);
        }

        $schemaName = $this->getSchemaName($class);
        foreach ($this->schemas as $registeredClass => $schema) {
            if ($this->getSchemaName($registeredClass) === $schemaName) {
                return $schema;
            }
        }

        return null;
    }

    public function exists(string $name): bool
    {
        return isset($this->reservedNames[$name]);
    }

    private function mergeSchemas(array $existing, array $new): array
    {
        $result = $existing;

        if (isset($new['properties']) && isset($existing['properties'])) {
            $result['properties'] = array_merge($existing['properties'], $new['properties']);
        }

        if (isset($new['required']) && isset($existing['required'])) {
            $result['required'] = array_unique(array_merge($existing['required'], $new['required']));
        }

        foreach ($new as $key => $value) {
            if (!isset($existing[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function generateSchemaName(string $class): string
    {
        if (isset($this->schemaNames[$class])) {
            return $this->schemaNames[$class];
        }

        $basename = class_basename($class);

        $basename = Str::replace(
            ['Resource', 'Request', 'Collection', 'Controller'],
            '',
            $basename
        );

        $basename = Str::studly(Str::singular($basename));

        if (!isset($this->reservedNames[$basename])) {
            return $basename;
        }

        $parts = explode('\\', trim($class, '\\'));

        $context = '';

        foreach (array_reverse($parts) as $part) {

            if (in_array($part, [
                'Requests',
                'Resources',
                'Http',
                'Api',
                'V1',
                'V2',
                'Controllers',
            ], true)) {
                continue;
            }

            if ($part === class_basename($class)) {
                continue;
            }

            $context = Str::studly($part);
            break;
        }

        if ($context !== '') {
            $candidate = $context . $basename;

            if (!isset($this->reservedNames[$candidate])) {
                return $candidate;
            }
        }

        $i = 1;

        while (isset($this->reservedNames[$basename . $i])) {
            $i++;
        }

        return $basename . $i;
    }

    public function reserveName(string $name): void
    {
        $this->reservedNames[$name] = true;
    }

    public function clearCache(): void
    {
        $this->schemas = [];
        $this->schemaNames = [];
        $this->reservedNames = [];
    }
}
