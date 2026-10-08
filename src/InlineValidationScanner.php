<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use ReflectionMethod;

final class InlineValidationScanner
{
    public function scan(ReflectionMethod $method): ?array
    {
        $file = $method->getFileName();

        if ($file === false || !is_file($file)) {
            return null;
        }

        $code = file_get_contents($file);

        if ($code === false) {
            return null;
        }

        try {
            $ast = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse($code);

            if (!is_array($ast)) {
                return null;
            }

            $visitor = new class($method->getName()) extends NodeVisitorAbstract
            {
                public ?Node\Stmt\ClassMethod $method = null;

                public function __construct(private readonly string $methodName)
                {
                }

                public function enterNode(Node $node)
                {
                    if (
                        $node instanceof Node\Stmt\ClassMethod &&
                        $node->name->toString() === $this->methodName
                    ) {
                        $this->method = $node;
                    }

                    return null;
                }
            };

            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            if (!$visitor->method instanceof Node\Stmt\ClassMethod) {
                return null;
            }

            $rules = $this->findValidationRules($visitor->method);

            if ($rules === []) {
                return null;
            }

            return $this->buildRequestInfo($rules);
        } catch (\Throwable) {
            return null;
        }
    }

    private function findValidationRules(Node\Stmt\ClassMethod $method): array
    {
        $rules = [];

        $traverser = new NodeTraverser();
        $visitor = new class extends NodeVisitorAbstract
        {
            public array $ruleArrays = [];

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier) {
                    $name = strtolower($node->name->toString());

                    if ($name === 'validate' && isset($node->args[0]) && $node->args[0]->value instanceof Node\Expr\Array_) {
                        $this->ruleArrays[] = $node->args[0]->value;
                    }
                }

                if (
                    $node instanceof Node\Expr\StaticCall &&
                    $node->name instanceof Node\Identifier &&
                    strtolower($node->name->toString()) === 'make' &&
                    isset($node->args[1]) &&
                    $node->args[1]->value instanceof Node\Expr\Array_
                ) {
                    $class = $node->class instanceof Node\Name ? strtolower($node->class->getLast()) : '';

                    if ($class === 'validator') {
                        $this->ruleArrays[] = $node->args[1]->value;
                    }
                }

                return null;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($method->stmts ?? []);

        foreach ($visitor->ruleArrays as $array) {
            $rules = array_replace($rules, $this->arrayToRules($array));
        }

        return $rules;
    }

    private function arrayToRules(Node\Expr\Array_ $array): array
    {
        $rules = [];

        foreach ($array->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $field = $this->scalarValue($item->key);

            if (!is_string($field) || $field === '') {
                continue;
            }

            $rules[$field] = $this->ruleValue($item->value);
        }

        return $rules;
    }

    private function ruleValue(Node\Expr $expr): array|string
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if (!$expr instanceof Node\Expr\Array_) {
            return [];
        }

        $rules = [];

        foreach ($expr->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->value instanceof Node\Scalar\String_) {
                $rules[] = $item->value->value;
                continue;
            }

            if (
                $item->value instanceof Node\Expr\StaticCall &&
                $item->value->name instanceof Node\Identifier
            ) {
                $name = strtolower($item->value->name->toString());

                if (in_array($name, ['in', 'notin'], true) && isset($item->value->args[0])) {
                    $values = $this->literalArrayValues($item->value->args[0]->value);

                    if ($values !== []) {
                        $rules[] = ($name === 'in' ? 'in:' : 'not_in:') . implode(',', $values);
                    }
                }
            }
        }

        return $rules;
    }

    private function literalArrayValues(Node\Expr $expr): array
    {
        if (!$expr instanceof Node\Expr\Array_) {
            return [];
        }

        $values = [];

        foreach ($expr->items as $item) {
            if ($item === null) {
                continue;
            }

            $value = $this->scalarValue($item->value);

            if (is_scalar($value)) {
                $values[] = (string) $value;
            }
        }

        return $values;
    }

    private function buildRequestInfo(array $rules): array
    {
        $properties = [];
        $required = [];
        $queryParameters = [];
        $multipart = false;

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $schema = $this->schemaFromRules($ruleList);

            if (($schema['format'] ?? null) === 'binary') {
                $multipart = true;
            }

            if (in_array('required', $ruleList, true) || in_array('filled', $ruleList, true)) {
                $required[] = $field;
            }

            $this->setNestedProperty($properties, $field, $schema);
        }

        foreach ($properties as $name => $schema) {
            $queryParameters[] = [
                'name' => $name,
                'in' => 'query',
                'required' => in_array($name, $required, true),
                'schema' => $schema,
            ];
        }

        $requestBody = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $requestBody['required'] = array_values(array_unique(array_map(
                static fn (string $field): string => explode('.', $field)[0],
                $required
            )));
        }

        return [
            'class' => null,
            'source' => 'inline-validation',
            'fields' => $properties,
            'required' => $required,
            'contentType' => $multipart ? 'multipart/form-data' : 'application/json',
            'isMultipart' => $multipart,
            'queryParameters' => $queryParameters,
            'requestBody' => $requestBody,
        ];
    }

    private function schemaFromRules(array $rules): array
    {
        $schema = ['type' => 'string'];

        foreach ($rules as $rule) {
            if (!is_string($rule)) {
                continue;
            }

            [$name, $parameters] = array_pad(explode(':', $rule, 2), 2, null);
            $values = $parameters === null ? [] : str_getcsv($parameters);

            switch ($name) {
                case 'integer':
                    $schema['type'] = 'integer';
                    break;
                case 'numeric':
                case 'decimal':
                    $schema['type'] = 'number';
                    break;
                case 'boolean':
                case 'accepted':
                case 'declined':
                    $schema['type'] = 'boolean';
                    break;
                case 'array':
                    $schema['type'] = 'array';
                    $schema['items'] ??= [];
                    break;
                case 'json':
                case 'object':
                    $schema['type'] = 'object';
                    break;
                case 'email':
                    $schema['type'] = 'string';
                    $schema['format'] = 'email';
                    break;
                case 'url':
                    $schema['type'] = 'string';
                    $schema['format'] = 'uri';
                    break;
                case 'uuid':
                    $schema['type'] = 'string';
                    $schema['format'] = 'uuid';
                    break;
                case 'date':
                    $schema['type'] = 'string';
                    $schema['format'] = 'date';
                    break;
                case 'file':
                case 'image':
                case 'mimes':
                case 'mimetypes':
                case 'extensions':
                    $schema['type'] = 'string';
                    $schema['format'] = 'binary';
                    break;
                case 'in':
                    if ($values !== []) {
                        $schema['enum'] = $values;
                    }
                    break;
                case 'min':
                    if (isset($values[0])) {
                        $this->applyMinMax($schema, 'min', (float) $values[0]);
                    }
                    break;
                case 'max':
                    if (isset($values[0])) {
                        $this->applyMinMax($schema, 'max', (float) $values[0]);
                    }
                    break;
                case 'nullable':
                    $schema['nullable'] = true;
                    break;
            }
        }

        return $schema;
    }

    private function applyMinMax(array &$schema, string $kind, float $value): void
    {
        $type = $schema['type'] ?? 'string';

        if ($type === 'array') {
            $schema[$kind === 'min' ? 'minItems' : 'maxItems'] = (int) $value;
        } elseif (in_array($type, ['integer', 'number'], true)) {
            $schema[$kind === 'min' ? 'minimum' : 'maximum'] = $value;
        } else {
            $schema[$kind === 'min' ? 'minLength' : 'maxLength'] = (int) $value;
        }
    }

    private function setNestedProperty(array &$properties, string $field, array $schema): void
    {
        $parts = explode('.', $field);
        $cursor =& $properties;

        foreach ($parts as $index => $part) {
            $last = $index === array_key_last($parts);

            if ($part === '*') {
                continue;
            }

            if ($last) {
                $cursor[$part] = $schema;
                return;
            }

            $nextIsArray = ($parts[$index + 1] ?? null) === '*';

            if (!isset($cursor[$part])) {
                $cursor[$part] = $nextIsArray
                    ? ['type' => 'array', 'items' => ['type' => 'object', 'properties' => []]]
                    : ['type' => 'object', 'properties' => []];
            }

            if ($nextIsArray) {
                $cursor =& $cursor[$part]['items']['properties'];
                $index++;
            } else {
                $cursor =& $cursor[$part]['properties'];
            }
        }
    }

    private function scalarValue(Node $node): mixed
    {
        return match (true) {
            $node instanceof Node\Scalar\String_ => $node->value,
            $node instanceof Node\Scalar\Int_ => $node->value,
            $node instanceof Node\Scalar\Float_ => $node->value,
            $node instanceof Node\Expr\ConstFetch && strtolower($node->name->toString()) === 'true' => true,
            $node instanceof Node\Expr\ConstFetch && strtolower($node->name->toString()) === 'false' => false,
            $node instanceof Node\Expr\ConstFetch && strtolower($node->name->toString()) === 'null' => null,
            default => null,
        };
    }
}
