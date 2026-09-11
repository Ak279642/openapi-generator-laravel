<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ResourcePropertyVisitor extends NodeVisitorAbstract
{
    private array $imports;
    private string $namespace;
    private int $currentDepth;
    private int $maxDepth;
    private array $properties = [];
    private ?string $targetMethod = null;
    private array $visitedNodes = [];
    private array $nestedResources = [];
    private array $variables = [];

    public function __construct(array $imports, string $namespace, int $currentDepth, int $maxDepth)
    {
        $this->imports = $imports;
        $this->namespace = $namespace;
        $this->currentDepth = $currentDepth;
        $this->maxDepth = $maxDepth;
    }

    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($node->name->toString() === 'toArray') {
                $this->targetMethod = 'toArray';
                $this->analyzeMethodBody($node);
            }
        }
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getNestedResources(): array
    {
        return $this->nestedResources;
    }

    private function analyzeMethodBody(Node\Stmt\ClassMethod $node): void
    {
        $body = $node->stmts;

        if ($body === null) {
            return;
        }

        foreach ($body as $stmt) {

            if (
                $stmt instanceof Node\Stmt\Expression &&
                $stmt->expr instanceof Node\Expr\Assign
            ) {
                $this->analyzeAssignment($stmt->expr);
                continue;
            }

            if (
                $stmt instanceof Node\Stmt\Return_ &&
                $stmt->expr !== null
            ) {
                $this->analyzeReturnExpression($stmt->expr);
            }
        }
    }

    private function analyzeAssignment(Node\Expr\Assign $assign): void
    {
        if (
            $assign->var instanceof Node\Expr\Variable &&
            is_string($assign->var->name)
        ) {
            if ($assign->expr instanceof Node\Expr\Array_) {
                $this->variables[$assign->var->name] = $assign->expr;
                return;
            }
        }

        if (
            $assign->var instanceof Node\Expr\ArrayDimFetch &&
            $assign->var->var instanceof Node\Expr\Variable &&
            is_string($assign->var->var->name)
        ) {
            $variable = $assign->var->var->name;

            if (!isset($this->variables[$variable])) {
                $this->variables[$variable] = new Node\Expr\Array_();
            }

            if (
                $assign->var->dim instanceof Node\Scalar\String_
            ) {
                $this->variables[$variable]->items[] = new Node\Expr\ArrayItem(
                    $assign->expr,
                    $assign->var->dim
                );
            }

            return;
        }

        if ($assign->expr !== null) {
            $this->analyzeExpression($assign->expr);
        }
    }
    private function analyzeReturnExpression(Node\Expr $expr): void
    {
        if ($expr instanceof Node\Expr\Array_) {
            $this->analyzeArrayExpression($expr);
            return;
        }

        if ($expr instanceof Node\Expr\Variable) {
            $this->analyzeVariable($expr);
            return;
        }

        if ($expr instanceof Node\Expr\MethodCall) {
            $this->analyzeMethodCall($expr);
            return;
        }

        if ($expr instanceof Node\Expr\StaticCall) {
            $this->analyzeStaticCall($expr);
            return;
        }

        if ($expr instanceof Node\Expr\New_) {
            $this->analyzeNewExpression($expr);
            return;
        }
    }

    private function analyzeArrayExpression(Node\Expr\Array_ $expr): void
    {
        foreach ($expr->items as $item) {
            if ($item === null || $item->key === null || $item->value === null) {
                continue;
            }

            if (!$item->key instanceof Node\Scalar\String_) {
                continue;
            }

            $key = $item->key->value;
            $this->analyzeValueExpression($key, $item->value);
        }
    }

    private function analyzeValueExpression(string $key, Node\Expr $expr): void
    {
        if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
            return;
        }

        $schema = $this->resolveExpressionType($expr);
        if ($schema !== null) {
            if (isset($schema['$ref']) && is_string($schema['$ref'])) {
                $this->nestedResources[] = $schema['$ref'];
            }

            if (isset($schema['type']) && $schema['type'] === 'array' && isset($schema['items']['$ref'])) {
                $this->nestedResources[] = $schema['items']['$ref'];
            }

            $this->properties[$key] = $schema;
        }
    }

    private function analyzeExpression(Node\Expr $expr): void
    {
        $this->resolveExpressionType($expr);
    }

    private function analyzeMethodCall(Node\Expr\MethodCall $expr): void
    {
        $this->handleMethodCall($expr);
    }

    private function analyzeStaticCall(Node\Expr\StaticCall $expr): void
    {
        $this->handleStaticCall($expr);
    }

    private function analyzeNewExpression(Node\Expr\New_ $expr): void
    {
        $this->handleNewResource($expr);
    }

    private function analyzeVariable(Node\Expr\Variable $expr): void
    {
        $this->handleVariable($expr);
    }

    private function resolveExpressionType(Node\Expr $expr): ?array
    {
        $hash = spl_object_hash($expr);
        if (isset($this->visitedNodes[$hash])) {
            return ['type' => 'object'];
        }
        $this->visitedNodes[$hash] = true;

        if ($expr instanceof Node\Expr\New_) {
            return $this->handleNewResource($expr);
        }

        if ($expr instanceof Node\Expr\StaticCall) {
            return $this->handleStaticCall($expr);
        }

        if ($expr instanceof Node\Expr\MethodCall) {
            return $this->handleMethodCall($expr);
        }

        if ($expr instanceof Node\Expr\Ternary) {
            return $this->handleTernary($expr);
        }

        if ($expr instanceof Node\Expr\Closure) {
            return $this->handleClosure($expr);
        }

        if ($expr instanceof Node\Expr\Variable) {
            return $this->handleVariable($expr);
        }

        if ($expr instanceof Node\Expr\Array_) {
            return ['type' => 'array'];
        }

        if ($expr instanceof Node\Expr\Assign) {
            if ($expr->expr !== null) {
                return $this->resolveExpressionType($expr->expr);
            }
            return null;
        }

        return $this->handlePrimitiveCast($expr);
    }

    private function handleNewResource(Node\Expr\New_ $expr): ?array
    {
        if (!$expr->class instanceof Node\Name) {
            return null;
        }

        $className = $this->resolveClassName($expr->class);
        if ($this->isResourceClass($className)) {
            return ['$ref' => $className];
        }

        foreach ($expr->args as $arg) {
            if ($arg->value !== null) {
                $result = $this->resolveExpressionType($arg->value);
                if ($result !== null && isset($result['$ref'])) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function handleStaticCall(Node\Expr\StaticCall $expr): ?array
    {
        if (!$expr->class instanceof Node\Name) {
            return null;
        }

        if (!$expr->name instanceof Node\Identifier) {
            return null;
        }

        $className = $this->resolveClassName($expr->class);
        $methodName = $expr->name->toString();

        if (!$this->isResourceClass($className)) {
            if ($this->isResourceCollectionClass($className)) {
                $resourceClass = $this->extractResourceFromCollection($className);
                if ($resourceClass !== null) {
                    return [
                        'type' => 'array',
                        'items' => ['$ref' => $resourceClass],
                    ];
                }
            }
            return null;
        }

        if ($methodName === 'make') {
            return ['$ref' => $className];
        }

        if ($methodName === 'collection') {
            return [
                'type' => 'array',
                'items' => ['$ref' => $className],
            ];
        }

        return null;
    }

    private function handleMethodCall(Node\Expr\MethodCall $expr): ?array
    {
        if (!$expr->name instanceof Node\Identifier) {
            return null;
        }

        $methodName = $expr->name->toString();

        if (in_array($methodName, ['whenLoaded', 'when', 'mergeWhen', 'whenCounted', 'whenAggregated', 'whenPivotLoaded'])) {
            return $this->handleConditionalMethod($expr);
        }

        if ($expr->var instanceof Node\Expr\StaticCall) {
            return $this->handleStaticCall($expr->var);
        }

        if ($methodName === 'collect') {
            if (!empty($expr->args)) {
                $arg = $expr->args[0]->value;
                $result = $this->resolveExpressionType($arg);
                if ($result !== null) {
                    if (isset($result['$ref'])) {
                        return [
                            'type' => 'array',
                            'items' => ['$ref' => $result['$ref']],
                        ];
                    }
                    return $result;
                }
            }
            return ['type' => 'array'];
        }

        return null;
    }

    private function handleConditionalMethod(Node\Expr\MethodCall $expr): ?array
    {
        if (empty($expr->args)) {
            return null;
        }

        $arg = $expr->args[0]->value;

        if ($arg instanceof Node\Expr\Closure) {
            $foundResource = $this->findResourceInClosure($arg);
            if ($foundResource !== null) {
                return [
                    '$ref' => $foundResource,
                    'nullable' => true,
                ];
            }
            return null;
        }

        if ($arg instanceof Node\Expr\New_) {
            $result = $this->handleNewResource($arg);
            if ($result !== null) {
                $result['nullable'] = true;
                return $result;
            }
        }

        if ($arg instanceof Node\Expr\StaticCall) {
            $result = $this->handleStaticCall($arg);
            if ($result !== null) {
                $result['nullable'] = true;
                return $result;
            }
        }

        if ($arg instanceof Node\Expr\Variable) {
            return [
                'type' => 'object',
                'nullable' => true,
                'description' => 'Conditional resource (detected via variable)',
            ];
        }

        return null;
    }

    private function findResourceInClosure(Node\Expr\Closure $closure): ?string
    {
        $stmts = $closure->stmts;
        if ($stmts === null) {
            return null;
        }

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_) {
                if ($stmt->expr !== null) {
                    if ($stmt->expr instanceof Node\Expr\New_) {
                        $class = $stmt->expr->class;
                        if ($class instanceof Node\Name) {
                            $className = $this->resolveClassName($class);
                            if ($this->isResourceClass($className)) {
                                return $className;
                            }
                        }
                    }
                    if ($stmt->expr instanceof Node\Expr\StaticCall) {
                        $class = $stmt->expr->class;
                        if ($class instanceof Node\Name) {
                            $className = $this->resolveClassName($class);
                            if ($this->isResourceClass($className)) {
                                return $className;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private function handleTernary(Node\Expr\Ternary $expr): ?array
    {
        $ifResult = null;
        $elseResult = null;

        if ($expr->if !== null) {
            $ifResult = $this->resolveExpressionType($expr->if);
        } else {
            $ifResult = $this->resolveExpressionType($expr->cond);
        }

        if ($expr->else !== null) {
            $elseResult = $this->resolveExpressionType($expr->else);
        }

        if (
            $expr->else instanceof Node\Expr\ConstFetch &&
            strtolower($expr->else->name->toString()) === 'null'
        ) {
            if ($ifResult !== null) {
                $ifResult['nullable'] = true;
                return $ifResult;
            }
        }

        if (
            $expr->if instanceof Node\Expr\ConstFetch &&
            strtolower($expr->if->name->toString()) === 'null'
        ) {
            if ($elseResult !== null) {
                $elseResult['nullable'] = true;
                return $elseResult;
            }
        }

        if ($ifResult !== null && isset($ifResult['$ref'])) {
            return $ifResult;
        }

        if ($elseResult !== null && isset($elseResult['$ref'])) {
            return $elseResult;
        }

        if ($ifResult !== null && ($ifResult['type'] ?? null) !== 'null') {
            return $ifResult;
        }

        if ($elseResult !== null && ($elseResult['type'] ?? null) !== 'null') {
            return $elseResult;
        }

        return $ifResult ?? $elseResult;
    }

    private function handleClosure(Node\Expr\Closure $closure): ?array
    {
        $stmts = $closure->stmts;
        if ($stmts === null) {
            return null;
        }

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_) {
                if ($stmt->expr !== null) {
                    return $this->resolveExpressionType($stmt->expr);
                }
            }
        }

        return null;
    }

    private function handleVariable(Node\Expr\Variable $expr): ?array
    {
        if (!is_string($expr->name)) {
            return null;
        }

        if (!isset($this->variables[$expr->name])) {
            return null;
        }

        $array = $this->variables[$expr->name];

        if ($array instanceof Node\Expr\Array_) {
            $this->analyzeArrayExpression($array);
        }

        return [
            'type' => 'object',
        ];
    }

    private function handlePrimitiveCast(Node\Expr $expr): ?array
    {
        if ($expr instanceof Node\Expr\Cast\Int_) {
            return ['type' => 'integer'];
        }

        if ($expr instanceof Node\Expr\Cast\Bool_) {
            return ['type' => 'boolean'];
        }

        if ($expr instanceof Node\Expr\Cast\Double) {
            return ['type' => 'number'];
        }

        if ($expr instanceof Node\Expr\Cast\String_) {
            return ['type' => 'string'];
        }

        if ($expr instanceof Node\Expr\MethodCall) {
            $methodName = $expr->name instanceof Node\Identifier ? $expr->name->toString() : '';
            if (in_array($methodName, ['toISOString', 'toIso8601String', 'toDateTimeString'])) {
                return [
                    'type' => 'string',
                    'format' => 'date-time',
                ];
            }
            if ($methodName === 'toDateString') {
                return [
                    'type' => 'string',
                    'format' => 'date',
                ];
            }
        }

        if ($expr instanceof Node\Expr\ConstFetch) {
            $name = $expr->name->toString();
            if (in_array($name, ['true', 'false'])) {
                return ['type' => 'boolean'];
            }
            if (in_array($name, ['null'])) {
                return ['type' => 'null'];
            }
        }

        if ($expr instanceof Node\Scalar\LNumber) {
            return ['type' => 'integer'];
        }

        if ($expr instanceof Node\Scalar\DNumber) {
            return ['type' => 'number'];
        }

        if ($expr instanceof Node\Scalar\String_) {
            return ['type' => 'string'];
        }

        if ($expr instanceof Node\Expr\Array_) {
            return ['type' => 'array'];
        }

        if ($expr instanceof Node\Expr\PropertyFetch) {
            return ['type' => 'string'];
        }

        return null;
    }

    private function resolveClassName(Node\Name $name): string
    {
        if ($name->isFullyQualified()) {
            return $name->toString();
        }

        $className = $name->toString();
        if (isset($this->imports[$className])) {
            return $this->imports[$className];
        }

        return $this->namespace . '\\' . $className;
    }

    private function isResourceClass(?string $className): bool
    {
        if ($className === null) {
            return false;
        }
        if (!class_exists($className)) {
            return false;
        }
        return is_subclass_of($className, JsonResource::class);
    }

    private function isResourceCollectionClass(?string $className): bool
    {
        if ($className === null) {
            return false;
        }
        if (!class_exists($className)) {
            return false;
        }
        return is_subclass_of($className, \Illuminate\Http\Resources\Json\ResourceCollection::class);
    }

    private function extractResourceFromCollection(string $collectionClass): ?string
    {
        try {
            $reflection = new \ReflectionClass($collectionClass);

            if ($reflection->hasProperty('collects')) {
                $property = $reflection->getProperty('collects');
                $property->setAccessible(true);
                $resourceClass = $property->getValue(null);
                if ($resourceClass && is_string($resourceClass) && $this->isResourceClass($resourceClass)) {
                    return $resourceClass;
                }
            }

            if ($reflection->hasMethod('collects')) {
                $method = $reflection->getMethod('collects');
                $method->setAccessible(true);
                $resourceClass = $method->invoke(null);
                if ($resourceClass && is_string($resourceClass) && $this->isResourceClass($resourceClass)) {
                    return $resourceClass;
                }
            }
        } catch (\Throwable $e) {
            // Skip
        }

        return null;
    }
}
