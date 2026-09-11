<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Carbon\Carbon;
use DateTime;

class TypeResolver
{
    private array $typeMap = [
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'number',
        'double' => 'number',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'string' => 'string',
        'array' => 'array',
        'object' => 'object',
        'null' => 'null',
        'mixed' => 'string',
    ];

    private array $dateFormats = [
        'toISOString' => 'date-time',
        'toIso8601String' => 'date-time',
        'toDateTimeString' => 'date-time',
        'toDateString' => 'date',
    ];

    public function resolveValidation(array $validation): array
    {
        $type = $validation['type'] ?? 'string';

        $schema = [
            'type' => $type,
        ];

        if ($validation['nullable'] ?? false) {
            $schema['type'] = [
                $type,
                'null',
            ];
        }

        if (isset($validation['enum']) && !empty($validation['enum'])) {
            $schema['enum'] = $validation['enum'];
        }

        if (isset($validation['minimum'])) {
            $schema['minimum'] = $validation['minimum'];
        }

        if (isset($validation['maximum'])) {
            $schema['maximum'] = $validation['maximum'];
        }

        if (isset($validation['minLength'])) {
            $schema['minLength'] = $validation['minLength'];
        }

        if (isset($validation['maxLength'])) {
            $schema['maxLength'] = $validation['maxLength'];
        }

        if (isset($validation['pattern'])) {
            $schema['pattern'] = $validation['pattern'];
        }

        if (isset($validation['format'])) {
            $schema['format'] = $validation['format'];
        }

        $schema['example'] = $this->generateExample($validation);

        return $schema;
    }

    public function resolveType(string $type): array
    {
        if (isset($this->typeMap[$type])) {
            return [
                'type' => $this->typeMap[$type],
            ];
        }

        if (enum_exists($type)) {
            return $this->resolveEnum($type);
        }

        if (is_subclass_of($type, Carbon::class) || $type === DateTime::class) {
            return [
                'type' => 'string',
                'format' => 'date-time',
            ];
        }

        if (is_subclass_of($type, JsonResource::class)) {
            return [
                '$ref' => '#/components/schemas/' . str_replace(
                    'Resource',
                    '',
                    class_basename($type)
                ),
            ];
        }

        if (is_subclass_of($type, ResourceCollection::class)) {
            return [
                'type' => 'array',
            ];
        }

        return [
            'type' => 'object',
        ];
    }

    private function resolveEnum(string $enumClass): array
    {
        try {
            $values = [];

            foreach ($enumClass::cases() as $case) {
                if (is_subclass_of($enumClass, \BackedEnum::class)) {
                    $values[] = $case->value;
                } else {
                    $values[] = $case->name;
                }
            }

            return [
                'type' => 'string',
                'enum' => $values,
            ];
        } catch (\Throwable $e) {
            return ['type' => 'string'];
        }
    }

    private function generateExample(array $validation): mixed
    {
        $type = $validation['type'] ?? 'string';

        return match ($type) {
            'string' => 'string',
            'integer' => 0,
            'number' => 0.0,
            'boolean' => true,
            'email' => 'user@example.com',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'date' => '2024-01-01',
            'datetime' => '2024-01-01T00:00:00Z',
            'array' => [],
            'object' => (object) [],
            default => null,
        };
    }

    public function isResourceClass(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        return is_subclass_of($class, JsonResource::class);
    }

    public function isCollectionClass(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        return is_subclass_of($class, ResourceCollection::class);
    }

    public function detectDateFormat(string $methodName): ?string
    {
        return $this->dateFormats[$methodName] ?? null;
    }
}
