<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use PhpParser\Node;

final class ArrayResponseSchemaInferrer
{
    public function infer(Node\Expr\Array_ $array): array
    {
        if ($this->isList($array)) {
            $schemas = [];

            foreach ($array->items as $item) {
                if ($item !== null) {
                    $schemas[] = $this->inferExpression($item->value);
                }
            }

            return [
                'type' => 'array',
                'items' => $this->mergeSchemas($schemas),
            ];
        }

        $properties = [];
        $required = [];

        foreach ($array->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $key = $this->key($item->key);

            if ($key === null) {
                continue;
            }

            $properties[$key] = $this->inferExpression($item->value);
            $required[] = $key;
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    public function inferExpression(Node\Expr $expr): array
    {
        if ($expr instanceof Node\Expr\Array_) {
            return $this->infer($expr);
        }

        if ($expr instanceof Node\Scalar\String_) {
            return ['type' => 'string', 'example' => $expr->value];
        }

        if ($expr instanceof Node\Scalar\Int_) {
            return ['type' => 'integer', 'example' => $expr->value];
        }

        if ($expr instanceof Node\Scalar\Float_) {
            return ['type' => 'number', 'example' => $expr->value];
        }

        if ($expr instanceof Node\Expr\ConstFetch) {
            $name = strtolower($expr->name->toString());

            if ($name === 'true' || $name === 'false') {
                return ['type' => 'boolean', 'example' => $name === 'true'];
            }

            if ($name === 'null') {
                return ['type' => 'null'];
            }
        }

        if ($expr instanceof Node\Expr\Ternary || $expr instanceof Node\Expr\Match_) {
            return [];
        }

        if ($expr instanceof Node\Expr\New_ && $expr->class instanceof Node\Name) {
            return ['type' => 'object'];
        }

        if ($expr instanceof Node\Expr\StaticCall && $expr->class instanceof Node\Name) {
            return ['type' => 'object'];
        }

        if ($expr instanceof Node\Expr\Variable) {
            return [];
        }

        return [];
    }

    private function isList(Node\Expr\Array_ $array): bool
    {
        $expected = 0;

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->key === null) {
                $expected++;
                continue;
            }

            if (!$item->key instanceof Node\Scalar\Int_ || $item->key->value !== $expected) {
                return false;
            }

            $expected++;
        }

        return true;
    }

    private function key(Node\Expr $expr): ?string
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Scalar\Int_) {
            return (string) $expr->value;
        }

        return null;
    }

    private function mergeSchemas(array $schemas): array
    {
        $schemas = array_values(array_filter($schemas, static fn (array $schema): bool => $schema !== []));

        if ($schemas === []) {
            return [];
        }

        $first = $schemas[0];

        foreach ($schemas as $schema) {
            if (($schema['type'] ?? null) !== ($first['type'] ?? null)) {
                return ['oneOf' => array_values($schemas)];
            }
        }

        return $first;
    }
}
