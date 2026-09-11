<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use OpeapiGeneratorLaravel\Ast\RuleAstExtractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\NestedRules;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\ProhibitedIf;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;
use ReflectionClass;

class FormRequestScanner
{
    private array $cache = [];
    private array $reflectionCache = [];
    private array $enumCache = [];
    private array $fileRules = ['file', 'image', 'mimes', 'mimetypes', 'extensions'];
    private array $typeMap = [
        'string' => 'string',
        'integer' => 'integer',
        'numeric' => 'number',
        'decimal' => 'number',
        'boolean' => 'boolean',
        'array' => 'array',
        'object' => 'object',
        'file' => 'file',
        'image' => 'image',
        'email' => 'string',
        'url' => 'string',
        'uuid' => 'string',
        'ulid' => 'string',
        'ip' => 'string',
        'mac_address' => 'string',
        'json' => 'object',
    ];

    public function scan(array $controllers): array
    {
        $results = [];

        foreach ($controllers as $controller) {
            foreach ($controller['methods'] as $methodName => $method) {
                $requestClass = $method['requestClass'] ?? null;

                if (!$requestClass || !class_exists($requestClass)) {
                    continue;
                }

                if (isset($this->cache[$requestClass])) {
                    $results[$requestClass] = $this->cache[$requestClass];
                    continue;
                }

                if (!is_subclass_of($requestClass, FormRequest::class)) {
                    continue;
                }

                $scanned = $this->scanRequest($requestClass);
                if ($scanned !== null) {
                    $results[(string) $requestClass] = $scanned;
                    $this->cache[(string) $requestClass] = $scanned;
                }
            }
        }

        return $results;
    }

    private function scanRequest(mixed $class): ?array
    {
        try {
            $reflection = $this->getReflection($class);
            $rules = $this->extractRules($reflection);

            if (empty($rules)) {
                return null;
            }

            $parsedRules = $this->parseRules($rules);
            $contentType = $this->detectContentType($parsedRules);

            return [
                'class' => $class,
                'fields' => $this->buildFieldSchemas($parsedRules),
                'required' => $this->extractRequiredFields($parsedRules),
                'contentType' => $contentType,
                'isMultipart' => $contentType === 'multipart/form-data',
                'queryParameters' => $this->buildQueryParameters($parsedRules),
                'requestBody' => $this->buildRequestBodySchema($parsedRules),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getReflection(string $class): ReflectionClass
    {
        if (!isset($this->reflectionCache[$class])) {
            $this->reflectionCache[$class] = new ReflectionClass($class);
        }
        return $this->reflectionCache[$class];
    }

    private function extractRules(ReflectionClass $reflection): array
    {
        return app(RuleAstExtractor::class)->extract($reflection);
    }

    private function parseRules(array $rules): array
    {
        $parsed = [];

        foreach ($rules as $field => $rule) {
            $rulesArray = $this->normalizeRules($rule);

            if (str_contains($field, '.')) {
                $parsed[$field] = $this->parseNestedField($field, $rulesArray);
            } else {
                $parsed[$field] = $this->parseRuleSet($rulesArray);
            }
        }

        return $parsed;
    }

    private function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        if (is_array($rules)) {
            return $rules;
        }

        return [];
    }

    private function parseRuleSet(array $rules): array
    {
        $schema = [
            'type' => 'string',
            'rules' => [],
            'nullable' => false,
            'required' => false,
            'array' => false,
            'nested' => false,
        ];

        foreach ($rules as $rule) {
            $this->parseSingleRule($rule, $schema);
        }

        return $schema;
    }

    private function parseNestedField(string $field, array $rulesArray): array
    {
        $schema = $this->parseRuleSet($rulesArray);
        $schema['nested'] = true;
        $schema['nestedPath'] = $field;

        if (str_contains($field, '.*.')) {
            $schema['array'] = true;
            $schema['type'] = 'array';
            $schema['items'] = $this->buildNestedArrayItems($field, $schema);
        }

        return $schema;
    }

    private function buildNestedArrayItems(string $field, array $schema): array
    {
        $parts = explode('.*.', $field);
        $lastPart = end($parts);

        $items = [
            'type' => 'object',
            'properties' => [
                $lastPart => [
                    'type' => $schema['type'] ?? 'string',
                ],
            ],
        ];

        if (isset($schema['format'])) {
            $items['properties'][$lastPart]['format'] = $schema['format'];
        }

        if (isset($schema['enum'])) {
            $items['properties'][$lastPart]['enum'] = $schema['enum'];
        }

        return $items;
    }

    private function parseSingleRule($rule, array &$schema): void
    {
        if ($rule instanceof \Closure) {
            return;
        }

        $ruleString = $this->getRuleString($rule);

        $this->handleBasicRules($rule, $ruleString, $schema);
        $this->handleTypeRules($rule, $ruleString, $schema);
        $this->handleValidationRules($rule, $ruleString, $schema);
        $this->handleStringRules($rule, $ruleString, $schema);
        $this->handleNumericRules($rule, $ruleString, $schema);
        $this->handleArrayRules($rule, $ruleString, $schema);
        $this->handleSpecialRules($rule, $ruleString, $schema);
    }

    private function getRuleString($rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }

        if (is_object($rule)) {
            return get_class($rule);
        }

        return '';
    }

    private function handleBasicRules($rule, string $ruleString, array &$schema): void
    {
        $basicRules = [
            'required' => ['required' => true],
            'nullable' => ['nullable' => true],
            'sometimes' => ['sometimes' => true],
            'filled' => ['required' => true],
            'present' => ['present' => true],
            'accepted' => ['type' => 'boolean', 'accepted' => true],
            'declined' => ['type' => 'boolean', 'declined' => true],
        ];

        foreach ($basicRules as $ruleName => $properties) {
            if ($ruleString === $ruleName || $ruleString === $ruleName . ':') {
                foreach ($properties as $key => $value) {
                    $schema[$key] = $value;
                }
                return;
            }
        }

        if (str_starts_with($ruleString, 'accepted_if:')) {
            $schema['type'] = 'boolean';
            $schema['accepted_if'] = $this->extractParameters($ruleString);
            return;
        }

        if (str_starts_with($ruleString, 'declined_if:')) {
            $schema['type'] = 'boolean';
            $schema['declined_if'] = $this->extractParameters($ruleString);
            return;
        }

        if ($ruleString === 'confirmed' || $ruleString === 'confirmed:') {
            $schema['confirmed'] = true;
            return;
        }
    }

    private function handleTypeRules($rule, string $ruleString, array &$schema): void
    {
        foreach ($this->typeMap as $ruleName => $type) {
            if ($ruleString === $ruleName || $ruleString === $ruleName . ':') {
                $schema['type'] = $type;

                if ($ruleName === 'email') {
                    $schema['format'] = 'email';
                } elseif ($ruleName === 'url') {
                    $schema['format'] = 'uri';
                } elseif ($ruleName === 'uuid') {
                    $schema['format'] = 'uuid';
                } elseif ($ruleName === 'ip') {
                    $schema['format'] = 'ip';
                } elseif ($ruleName === 'file' || $ruleName === 'image') {
                    $schema['format'] = 'binary';
                }
                return;
            }
        }

        if ($ruleString === 'date') {
            $schema['type'] = 'string';
            $schema['format'] = 'date';
            return;
        }

        if (str_starts_with($ruleString, 'date_format:')) {
            $schema['type'] = 'string';
            $schema['format'] = $this->extractParameters($ruleString)[0] ?? 'date';
            return;
        }

        if (str_starts_with($ruleString, 'before:')) {
            $schema['type'] = 'string';
            $schema['before'] = $this->extractParameters($ruleString)[0] ?? '';
            return;
        }

        if (str_starts_with($ruleString, 'after:')) {
            $schema['type'] = 'string';
            $schema['after'] = $this->extractParameters($ruleString)[0] ?? '';
            return;
        }

        if (str_starts_with($ruleString, 'before_or_equal:')) {
            $schema['type'] = 'string';
            $schema['before_or_equal'] = $this->extractParameters($ruleString)[0] ?? '';
            return;
        }

        if (str_starts_with($ruleString, 'after_or_equal:')) {
            $schema['type'] = 'string';
            $schema['after_or_equal'] = $this->extractParameters($ruleString)[0] ?? '';
            return;
        }
    }

    private function handleValidationRules($rule, string $ruleString, array &$schema): void
    {
        if ($rule instanceof Exists || $ruleString === 'exists' || str_starts_with($ruleString, 'exists:')) {
            $schema['exists'] = $this->parseExistsRule($rule, $ruleString);
            return;
        }

        if ($rule instanceof Unique || $ruleString === 'unique' || str_starts_with($ruleString, 'unique:')) {
            $schema['unique'] = $this->parseUniqueRule($rule, $ruleString);
            return;
        }

        if ($rule instanceof Enum || $ruleString === 'enum') {
            $schema['enum'] = $this->parseEnumRule($rule);
            return;
        }

        if ($rule instanceof In || str_starts_with($ruleString, 'in:')) {
            $schema['enum'] = $this->parseInRule($rule, $ruleString);
            return;
        }

        if ($ruleString === 'not_in' || str_starts_with($ruleString, 'not_in:')) {
            $schema['not_in'] = $this->extractParameters($ruleString);
            return;
        }

        if ($rule instanceof RequiredIf || str_starts_with($ruleString, 'required_if:')) {
            $schema['required_if'] = $this->extractParameters($ruleString);
            $schema['required'] = true;
            return;
        }

        if ($rule instanceof ExcludeIf || str_starts_with($ruleString, 'exclude_if:')) {
            $schema['exclude_if'] = $this->extractParameters($ruleString);
            return;
        }

        if ($rule instanceof ProhibitedIf || str_starts_with($ruleString, 'prohibited_if:')) {
            $schema['prohibited_if'] = $this->extractParameters($ruleString);
            return;
        }
    }

    private function handleStringRules($rule, string $ruleString, array &$schema): void
    {
        if (str_starts_with($ruleString, 'min:')) {
            $params = $this->extractParameters($ruleString);
            if ($schema['type'] === 'string' || $schema['type'] === 'array') {
                $schema['minLength'] = (int) $params[0];
            } elseif ($schema['type'] === 'integer' || $schema['type'] === 'number') {
                $schema['minimum'] = (int) $params[0];
            }
            return;
        }

        if (str_starts_with($ruleString, 'max:')) {
            $params = $this->extractParameters($ruleString);
            if ($schema['type'] === 'string' || $schema['type'] === 'array') {
                $schema['maxLength'] = (int) $params[0];
            } elseif ($schema['type'] === 'integer' || $schema['type'] === 'number') {
                $schema['maximum'] = (int) $params[0];
            }
            return;
        }

        if (str_starts_with($ruleString, 'between:')) {
            $params = $this->extractParameters($ruleString);
            if (count($params) >= 2) {
                if ($schema['type'] === 'string' || $schema['type'] === 'array') {
                    $schema['minLength'] = (int) $params[0];
                    $schema['maxLength'] = (int) $params[1];
                } elseif ($schema['type'] === 'integer' || $schema['type'] === 'number') {
                    $schema['minimum'] = (int) $params[0];
                    $schema['maximum'] = (int) $params[1];
                }
            }
            return;
        }

        if (str_starts_with($ruleString, 'size:')) {
            $params = $this->extractParameters($ruleString);
            if ($schema['type'] === 'string') {
                $schema['minLength'] = (int) $params[0];
                $schema['maxLength'] = (int) $params[0];
            } elseif ($schema['type'] === 'array') {
                $schema['minItems'] = (int) $params[0];
                $schema['maxItems'] = (int) $params[0];
            } elseif ($schema['type'] === 'integer' || $schema['type'] === 'number') {
                $schema['minimum'] = (int) $params[0];
                $schema['maximum'] = (int) $params[0];
            }
            return;
        }

        if (str_starts_with($ruleString, 'regex:')) {
            $schema['pattern'] = $this->extractParameters($ruleString)[0] ?? '';
            return;
        }

        if (str_starts_with($ruleString, 'digits:')) {
            $params = $this->extractParameters($ruleString);
            $schema['type'] = 'integer';
            $schema['minLength'] = (int) $params[0];
            $schema['maxLength'] = (int) $params[0];
            return;
        }

        if (str_starts_with($ruleString, 'digits_between:')) {
            $params = $this->extractParameters($ruleString);
            if (count($params) >= 2) {
                $schema['type'] = 'integer';
                $schema['minLength'] = (int) $params[0];
                $schema['maxLength'] = (int) $params[1];
            }
            return;
        }
    }

    private function handleNumericRules($rule, string $ruleString, array &$schema): void
    {
        if (str_starts_with($ruleString, 'multiple_of:')) {
            $params = $this->extractParameters($ruleString);
            $schema['multipleOf'] = (float) $params[0];
            return;
        }

        if ($ruleString === 'numeric') {
            $schema['type'] = 'number';
            return;
        }

        if ($ruleString === 'integer') {
            $schema['type'] = 'integer';
            return;
        }

        if (str_starts_with($ruleString, 'decimal:')) {
            $params = $this->extractParameters($ruleString);
            $schema['type'] = 'number';
            if (isset($params[0])) {
                $schema['format'] = 'decimal';
                $schema['decimalPlaces'] = (int) $params[0];
            }
            return;
        }
    }

    private function handleArrayRules($rule, string $ruleString, array &$schema): void
    {
        if ($ruleString === 'array' || $ruleString === 'array:') {
            $schema['type'] = 'array';
            $schema['array'] = true;
            return;
        }

        if ($ruleString === 'object') {
            $schema['type'] = 'object';
            return;
        }

        if ($ruleString === 'distinct' || $ruleString === 'distinct:') {
            $schema['distinct'] = true;
            return;
        }
    }

    private function handleSpecialRules($rule, string $ruleString, array &$schema): void
    {
        if (str_starts_with($ruleString, 'mimes:')) {
            $schema['type'] = 'file';
            $schema['format'] = 'binary';
            $schema['mimes'] = $this->extractParameters($ruleString);
            return;
        }

        if (str_starts_with($ruleString, 'mimetypes:')) {
            $schema['type'] = 'file';
            $schema['format'] = 'binary';
            $schema['mimetypes'] = $this->extractParameters($ruleString);
            return;
        }

        if (str_starts_with($ruleString, 'extensions:')) {
            $schema['type'] = 'file';
            $schema['format'] = 'binary';
            $schema['extensions'] = $this->extractParameters($ruleString);
            return;
        }

        if ($rule instanceof ConditionalRules) {
            $schema['conditional'] = true;
            return;
        }

        if ($rule instanceof NestedRules) {
            $schema['nested'] = true;
            return;
        }
    }

    private function parseExistsRule($rule, string $ruleString): array
    {
        $result = [];

        if ($rule instanceof Exists) {
            $reflection = new \ReflectionClass($rule);

            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $result['table'] = $tableProperty->getValue($rule);

            $columnProperty = $reflection->getProperty('column');
            $columnProperty->setAccessible(true);
            $result['column'] = $columnProperty->getValue($rule);
        } elseif (str_starts_with($ruleString, 'exists:')) {
            $params = $this->extractParameters($ruleString);
            $result['table'] = $params[0] ?? '';
            $result['column'] = $params[1] ?? 'id';
        } else {
            $result['table'] = 'unknown';
            $result['column'] = 'id';
        }

        return $result;
    }

    private function parseUniqueRule($rule, string $ruleString): array
    {
        $result = [];

        if ($rule instanceof Unique) {
            $reflection = new \ReflectionClass($rule);

            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $result['table'] = $tableProperty->getValue($rule);

            $columnProperty = $reflection->getProperty('column');
            $columnProperty->setAccessible(true);
            $result['column'] = $columnProperty->getValue($rule);

            $ignoreProperty = $reflection->getProperty('ignore');
            $ignoreProperty->setAccessible(true);
            $ignore = $ignoreProperty->getValue($rule);
            if ($ignore !== null) {
                $result['ignore'] = $ignore;
            }
        } elseif (str_starts_with($ruleString, 'unique:')) {
            $params = $this->extractParameters($ruleString);
            $result['table'] = $params[0] ?? '';
            $result['column'] = $params[1] ?? 'id';
        } else {
            $result['table'] = 'unknown';
            $result['column'] = 'id';
        }

        return $result;
    }

    private function parseEnumRule($rule): array
    {
        if (!$rule instanceof Enum) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($rule);
            $typeProperty = $reflection->getProperty('type');
            $typeProperty->setAccessible(true);
            $enumClass = $typeProperty->getValue($rule);

            if (!enum_exists($enumClass)) {
                return [];
            }

            return $this->getEnumValues($enumClass);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function parseInRule($rule, string $ruleString): array
    {
        if ($rule instanceof In) {
            try {
                $reflection = new \ReflectionClass($rule);
                $valuesProperty = $reflection->getProperty('values');
                $valuesProperty->setAccessible(true);
                return $valuesProperty->getValue($rule);
            } catch (\Throwable $e) {
                return [];
            }
        }

        if (str_starts_with($ruleString, 'in:')) {
            return $this->extractParameters($ruleString);
        }

        return [];
    }

    private function getEnumValues(string $enumClass): array
    {
        if (isset($this->enumCache[$enumClass])) {
            return $this->enumCache[$enumClass];
        }

        try {
            $values = [];
            foreach ($enumClass::cases() as $case) {
                if (is_subclass_of($enumClass, \BackedEnum::class)) {
                    $values[] = $case->value;
                } else {
                    $values[] = $case->name;
                }
            }

            $this->enumCache[$enumClass] = $values;
            return $values;
        } catch (\Throwable $e) {
            $this->enumCache[$enumClass] = [];
            return [];
        }
    }

    private function extractParameters(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        if (count($parts) < 2) {
            return [];
        }

        $params = explode(',', $parts[1]);
        return array_map('trim', $params);
    }
    private function detectContentType(array $parsedRules): string
    {
        foreach ($parsedRules as $schema) {
            $type = $schema['type'] ?? null;

            if (in_array($type, ['file', 'image'], true)) {
                return 'multipart/form-data';
            }

            if (($schema['format'] ?? null) === 'binary') {
                return 'multipart/form-data';
            }

            foreach ($this->fileRules as $rule) {
                if (!empty($schema[$rule])) {
                    return 'multipart/form-data';
                }
            }

            if (($schema['array'] ?? false) === true) {
                $items = $schema['items'] ?? [];

                if (
                    in_array($items['type'] ?? null, ['file', 'image'], true) ||
                    (($items['format'] ?? null) === 'binary')
                ) {
                    return 'multipart/form-data';
                }

                foreach ($this->fileRules as $rule) {
                    if (!empty($items[$rule])) {
                        return 'multipart/form-data';
                    }
                }
            }
        }

        return 'application/json';
    }

    private function buildQueryParameters(array $parsedRules): array
    {
        $parameters = [];

        foreach ($parsedRules as $field => $schema) {
            if (str_ends_with($field, '.*')) {
                continue;
            }

            $name = $field;

            if (str_contains($name, '.*.')) {
                $parts = explode('.*.', $name);

                $name = array_shift($parts) . '[]';

                foreach ($parts as $part) {
                    $name .= '[' . $part . ']';
                }
            } elseif (str_contains($name, '.')) {
                $parts = explode('.', $name);

                $name = array_shift($parts);

                foreach ($parts as $part) {
                    $name .= '[' . $part . ']';
                }
            }

            $parameterSchema = $schema;

            /*
         * Merge wildcard validation rules into array item schema.
         *
         * Example:
         * with   => array
         * with.* => string|in:city,package,modules
         */
            if (($schema['type'] ?? null) === 'array') {
                $wildcardField = $field . '.*';

                if (isset($parsedRules[$wildcardField])) {
                    $parameterSchema['items'] = $this->buildWildcardItemSchema(
                        $parsedRules[$wildcardField]
                    );
                }
            }

            $parameter = [
                'name' => $name,
                'in' => 'query',
                'required' => $schema['required'] ?? false,
                'style' => 'form',
                'explode' => true,
                'schema' => $this->buildParameterSchema($parameterSchema),
            ];

            if (!empty($schema['description'])) {
                $parameter['description'] = $schema['description'];
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    private function buildParameterSchema(array $schema): array
    {
        $paramSchema = [
            'type' => $schema['type'] ?? 'string',
        ];

        if (($schema['type'] ?? 'string') === 'array') {
            $paramSchema['items'] = $schema['items']
                ?? $this->buildArrayItems($schema);
        } else {
            foreach (
                [
                    'format',
                    'enum',
                    'minimum',
                    'maximum',
                    'minLength',
                    'maxLength',
                    'pattern',
                ] as $key
            ) {
                if (isset($schema[$key])) {
                    $paramSchema[$key] = $schema[$key];
                }
            }
        }

        if (($schema['nullable'] ?? false) === true) {
            $paramSchema['nullable'] = true;
        }

        return $paramSchema;
    }

    private function buildRequestBodySchema(array $parsedRules): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($parsedRules as $field => $rules) {
            if (str_ends_with($field, '.*')) {
                continue;
            }

            $property = $this->buildPropertySchema($rules);

            if (($rules['required'] ?? false) === true) {
                $schema['required'][] = $field;
            }

            if (!str_contains($field, '.')) {
                $schema['properties'][$field] = $property;
                continue;
            }

            $parts = preg_split('/\.\*\./', $field);
            $segments = [];

            foreach ($parts as $part) {
                foreach (explode('.', $part) as $segment) {
                    if ($segment !== '') {
                        $segments[] = $segment;
                    }
                }
            }

            $current = &$schema['properties'];

            foreach ($segments as $index => $segment) {
                $isLast = $index === count($segments) - 1;

                $isArray = false;

                if (
                    isset($rules['nestedPath']) &&
                    preg_match('/(^|\.|\*)' . preg_quote($segment, '/') . '\.\*\./', $rules['nestedPath'])
                ) {
                    $isArray = true;
                }

                if ($isLast) {
                    $current[$segment] = $property;
                    continue;
                }

                if (!isset($current[$segment])) {
                    $current[$segment] = $isArray
                        ? [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [],
                            ],
                        ]
                        : [
                            'type' => 'object',
                            'properties' => [],
                        ];
                }

                if (($current[$segment]['type'] ?? '') === 'array') {
                    if (!isset($current[$segment]['items']['properties'])) {
                        $current[$segment]['items']['properties'] = [];
                    }

                    $current = &$current[$segment]['items']['properties'];
                } else {
                    if (!isset($current[$segment]['properties'])) {
                        $current[$segment]['properties'] = [];
                    }

                    $current = &$current[$segment]['properties'];
                }
            }

            unset($current);
        }

        $schema['required'] = array_values(array_unique($schema['required']));

        return $schema;
    }

    private function buildPropertySchema(array $schema): array
    {
        $property = [
            'type' => $schema['type'] ?? 'string',
        ];

        if (($schema['nullable'] ?? false) === true) {
            $property['nullable'] = true;
        }

        if (isset($schema['format'])) {
            $property['format'] = $schema['format'];
        }

        if (isset($schema['enum']) && !empty($schema['enum'])) {
            $property['enum'] = $schema['enum'];
        }

        foreach (['minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'multipleOf'] as $key) {
            if (isset($schema[$key])) {
                $property[$key] = $schema[$key];
            }
        }

        if (($schema['array'] ?? false) === true) {
            $property['type'] = 'array';
            $property['items'] = $this->buildArrayItems($schema);
        }

        return $property;
    }

    private function buildArrayItems(array $schema): array
    {
        $items = ['type' => 'string'];

        if (isset($schema['format'])) {
            $items['format'] = $schema['format'];
        }

        if (isset($schema['enum'])) {
            $items['enum'] = $schema['enum'];
        }

        if (isset($schema['nested']) && $schema['nested'] === true) {
            $items = ['type' => 'object'];
            if (isset($schema['nestedPath'])) {
                $parts = explode('.*.', $schema['nestedPath']);
                $lastPart = end($parts);
                $items['properties'][$lastPart] = [
                    'type' => $schema['type'] ?? 'string',
                ];
                if (isset($schema['format'])) {
                    $items['properties'][$lastPart]['format'] = $schema['format'];
                }
                if (isset($schema['enum'])) {
                    $items['properties'][$lastPart]['enum'] = $schema['enum'];
                }
            }
        }

        return $items;
    }

    private function buildFieldSchemas(array $parsedRules): array
    {
        $schemas = [];

        foreach ($parsedRules as $field => $rules) {
            if (str_ends_with($field, '.*')) {
                $parent = substr($field, 0, -2);
                $item = $this->buildWildcardItemSchema($rules);

                if (!isset($schemas[$parent])) {
                    $schemas[$parent] = [
                        'type' => 'array',
                        'required' => $rules['required'] ?? false,
                        'nullable' => $rules['nullable'] ?? false,
                        'items' => $item,
                    ];
                } else {
                    $schemas[$parent]['type'] = 'array';
                    $schemas[$parent]['items'] = $item;
                }
                continue;
            }

            if (str_contains($field, '.') && !str_contains($field, '*')) {
                $this->buildNestedFieldSchema($schemas, $field, $rules);
                continue;
            }

            $schemas[$field] = $this->buildFieldSchema($field, $rules);
        }

        return $schemas;
    }

    private function buildWildcardItemSchema(array $rules): array
    {
        $item = [
            'type' => $rules['type'] ?? 'string',
        ];

        foreach (['format', 'enum', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern'] as $key) {
            if (isset($rules[$key])) {
                $item[$key] = $rules[$key];
            }
        }

        if (($rules['nullable'] ?? false) === true) {
            $item['nullable'] = true;
        }

        return $item;
    }

    private function buildNestedFieldSchema(array &$schemas, string $field, array $rules): void
    {
        $parts = explode('.', $field);
        $current = &$schemas;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $schema = $this->buildFieldSchema($part, $rules);
                $current['properties'][$part] = $schema;
                break;
            }

            if (!isset($current['properties'][$part])) {
                $current['properties'][$part] = [
                    'type' => 'object',
                    'properties' => [],
                ];
            }

            if ($current['properties'][$part]['type'] !== 'object') {
                $current['properties'][$part]['type'] = 'object';
                $current['properties'][$part]['properties'] = [];
            }

            $current = &$current['properties'][$part];
        }
    }

    private function buildFieldSchema(string $field, array $rules): array
    {
        $schema = [
            'type' => $rules['type'] ?? 'string',
        ];

        if (($rules['required'] ?? false) === true) {
            $schema['required'] = true;
        }

        if (($rules['nullable'] ?? false) === true) {
            $schema['nullable'] = true;
        }

        foreach (['format', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'enum', 'multipleOf'] as $key) {
            if (isset($rules[$key])) {
                $schema[$key] = $rules[$key];
            }
        }

        if (($rules['array'] ?? false) === true) {
            $schema['type'] = 'array';
            $schema['items'] = $this->buildArrayItems($rules);
        }

        return $schema;
    }

    private function extractRequiredFields(array $parsedRules): array
    {
        $required = [];

        foreach ($parsedRules as $field => $rules) {
            if (($rules['required'] ?? false) === true) {
                $required[] = $field;
            }
        }

        return $required;
    }

    public function clearCache(): void
    {
        $this->cache = [];
        $this->reflectionCache = [];
        $this->enumCache = [];
    }
}
