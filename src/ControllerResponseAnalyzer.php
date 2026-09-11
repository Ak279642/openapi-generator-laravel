<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use ReflectionMethod;

class ControllerResponseAnalyzer
{
    private array $imports = [];
    private string $namespace = '';
    private array $result = [
        'found' => false,
        'resourceClass' => null,
        'isCollection' => false,
    ];
    private array $variables = [];
    private array $methodCache = [];
    private array $methodStack = [];


    public function analyze(ReflectionMethod $method): array
    {
        $this->reset();

        $code = $this->readFileContent($method);

        if ($code === null) {
            return $this->result;
        }

        try {
            $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();

            $ast = $parser->parse($code);

            if (!is_array($ast)) {
                return $this->result;
            }


            $this->extractImportsAndNamespaceFromAst($ast);

            $this->cacheMethods($ast);

            $methodName = $method->getName();

            if (!isset($this->methodCache[$methodName])) {
                return $this->result;
            }

            $this->analyzeMethod($this->methodCache[$methodName]);
        } catch (\Throwable) {
        }

        return $this->result;
    }

    private function cacheMethods(array $ast): void
    {
        $traverser = new NodeTraverser();

        $visitor = new class extends NodeVisitorAbstract
        {
            public array $methods = [];

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\ClassMethod) {
                    $this->methods[$node->name->toString()] = $node;
                }
            }
        };

        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);

        $this->methodCache = $visitor->methods;
    }

    private function readFileContent(ReflectionMethod $method): ?string
    {
        $fileName = $method->getFileName();
        if ($fileName === false) {
            return null;
        }

        $content = file_get_contents($fileName);
        return $content === false ? null : $content;
    }



    private function analyzeMethod(Node\Stmt\ClassMethod $methodNode): void
    {
        if ($methodNode->stmts === null) {
            return;
        }

        foreach ($methodNode->stmts as $statement) {
            $this->analyzeStatement($statement);

            if ($this->result['found']) {
                return;
            }
        }
    }

    private function analyzeStatement(Node\Stmt $statement): void
    {
        if ($this->result['found']) {
            return;
        }

        if ($statement instanceof Node\Stmt\Return_) {

            if ($statement->expr !== null) {
                $this->analyzeExpression($statement->expr);
            }

            return;
        }

        if ($statement instanceof Node\Stmt\Expression) {

            $this->analyzeExpression(
                $statement->expr
            );

            return;
        }

        if ($statement instanceof Node\Stmt\If_) {

            foreach ($statement->stmts as $stmt) {
                $this->analyzeStatement($stmt);

                if ($this->result['found']) {
                    return;
                }
            }

            foreach ($statement->elseifs as $elseif) {

                foreach ($elseif->stmts as $stmt) {

                    $this->analyzeStatement($stmt);

                    if ($this->result['found']) {
                        return;
                    }
                }
            }

            if ($statement->else !== null) {

                foreach ($statement->else->stmts as $stmt) {

                    $this->analyzeStatement($stmt);

                    if ($this->result['found']) {
                        return;
                    }
                }
            }

            return;
        }

        if ($statement instanceof Node\Stmt\Foreach_) {

            foreach ($statement->stmts as $stmt) {

                $this->analyzeStatement($stmt);

                if ($this->result['found']) {
                    return;
                }
            }

            return;
        }

        if ($statement instanceof Node\Stmt\TryCatch) {

            foreach ($statement->stmts as $stmt) {

                $this->analyzeStatement($stmt);

                if ($this->result['found']) {
                    return;
                }
            }

            foreach ($statement->catches as $catch) {

                foreach ($catch->stmts as $stmt) {

                    $this->analyzeStatement($stmt);

                    if ($this->result['found']) {
                        return;
                    }
                }
            }

            if ($statement->finally !== null) {

                foreach ($statement->finally->stmts as $stmt) {

                    $this->analyzeStatement($stmt);

                    if ($this->result['found']) {
                        return;
                    }
                }
            }

            return;
        }

        if ($statement instanceof Node\Stmt\Switch_) {

            foreach ($statement->cases as $case) {

                foreach ($case->stmts as $stmt) {

                    $this->analyzeStatement($stmt);

                    if ($this->result['found']) {
                        return;
                    }
                }
            }
        }
    }

    private function analyzeExpression(Node\Expr $expr): void
    {
        if ($this->result['found']) {
            return;
        }

        if ($expr instanceof Node\Expr\Assign) {

            if (
                $expr->var instanceof Node\Expr\Variable &&
                is_string($expr->var->name)
            ) {

                $old = $this->result;

                $this->result = [
                    'found' => false,
                    'resourceClass' => null,
                    'isCollection' => false,
                ];

                $this->analyzeExpression(
                    $expr->expr
                );

                if ($this->result['found']) {

                    $this->variables[$expr->var->name] = [
                        'resourceClass' =>
                        $this->result['resourceClass'],
                        'isCollection' =>
                        $this->result['isCollection'],
                    ];
                }

                $this->result = $old;
            }

            return;
        }
        if ($expr instanceof Node\Expr\Variable) {
            $this->handleVariable($expr);
            return;
        }

        if ($expr instanceof Node\Expr\StaticCall) {
            $this->handleStaticCall($expr);
            return;
        }

        if ($expr instanceof Node\Expr\New_) {
            $this->handleNewExpression($expr);
            return;
        }

        if ($expr instanceof Node\Expr\MethodCall) {
            $this->handleMethodCall($expr);
            return;
        }

        if ($expr instanceof Node\Expr\FuncCall) {
            $this->handleFunctionCall($expr);
            return;
        }

        if ($expr instanceof Node\Expr\Array_) {
            foreach ($expr->items as $item) {
                if ($item !== null && $item->value !== null) {
                    $this->analyzeExpression($item->value);

                    if ($this->result['found']) {
                        return;
                    }
                }
            }

            return;
        }

        if ($expr instanceof Node\Expr\Ternary) {

            if ($expr->if !== null) {
                $this->analyzeExpression($expr->if);

                if ($this->result['found']) {
                    return;
                }
            }

            $this->analyzeExpression($expr->else);

            return;
        }

        if ($expr instanceof Node\Expr\Match_) {

            foreach ($expr->arms as $arm) {

                $this->analyzeExpression($arm->body);

                if ($this->result['found']) {
                    return;
                }
            }

            return;
        }

        if ($expr instanceof Node\Expr\BinaryOp) {

            $this->analyzeExpression($expr->left);

            if ($this->result['found']) {
                return;
            }

            $this->analyzeExpression($expr->right);

            return;
        }

        if ($expr instanceof Node\Expr\Closure) {

            foreach ($expr->stmts as $stmt) {

                $this->analyzeStatement($stmt);

                if ($this->result['found']) {
                    return;
                }
            }
        }
    }


    private function handleFunctionCall(Node\Expr\FuncCall $expr): void
    {
        if (!$expr->name instanceof Node\Name) {
            return;
        }

        $name = strtolower($expr->name->toString());

        if ($name === 'response') {

            $this->analyzeArguments($expr->args);

            return;
        }

        $this->analyzeArguments($expr->args);
    }



    private function handleStaticCall(Node\Expr\StaticCall $expr): void
    {
        if (!$expr->class instanceof Node\Name) {
            return;
        }

        if (!$expr->name instanceof Node\Identifier) {
            return;
        }

        $class = $this->resolveClassName($expr->class);

        $method = strtolower(
            $expr->name->toString()
        );

        if ($method === 'make' && (str_ends_with($class, 'Response') || str_contains($class, '\\Responses\\'))) {

            $this->analyzeArguments(
                $expr->args
            );

            return;
        }

        if (
            $this->isResourceClass($class)
        ) {

            $this->setResult(
                $class,
                $method === 'collection'
            );

            return;
        }

        if (
            $this->isResourceCollection($class)
        ) {

            $collects = $this->resolveCollectedResource(
                $class
            );

            if ($collects !== null) {

                $this->setResult(
                    $collects,
                    true
                );
            }

            return;
        }

        $this->analyzeArguments(
            $expr->args
        );
    }

    private function extractImportsAndNamespaceFromAst(array $ast): void
    {
        $traverser = new NodeTraverser();

        $visitor = new ImportVisitor();

        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);

        $this->imports = $visitor->getImports();
        $this->namespace = $visitor->getNamespace();
    }
    private function handleNewExpression(Node\Expr\New_ $expr): void
    {
        if ($expr->class instanceof Node\Name) {

            $class = $this->resolveClassName($expr->class);

            if ($this->isResourceClass($class)) {

                $this->setResult($class, false);
                return;
            }

            if ($this->isResourceCollection($class)) {

                $collects = $this->resolveCollectedResource($class);

                if ($collects !== null) {
                    $this->setResult($collects, true);
                    return;
                }
            }

            if (
                is_a($class, \Illuminate\Http\JsonResponse::class, true) ||
                is_a($class, \Symfony\Component\HttpFoundation\JsonResponse::class, true) ||
                is_a($class, \Illuminate\Http\Response::class, true)
            ) {

                foreach ($expr->args as $arg) {

                    $this->analyzeExpression($arg->value);

                    if ($this->result['found']) {
                        return;
                    }
                }
            }
        }

        foreach ($expr->args as $arg) {

            $this->analyzeExpression($arg->value);

            if ($this->result['found']) {
                return;
            }
        }
    }
    private function handleMethodCall(Node\Expr\MethodCall $expr): void
    {
        if (!$expr->name instanceof Node\Identifier) {
            return;
        }

        $method = strtolower($expr->name->toString());

        if (in_array($method, ['additional', 'response', 'json'], true)) {
            if ($expr->var instanceof Node\Expr) {
                $this->analyzeExpression($expr->var);

                if ($this->result['found']) {
                    return;
                }
            }

            foreach ($expr->args as $arg) {
                $this->analyzeExpression($arg->value);

                if ($this->result['found']) {
                    return;
                }
            }

            return;
        }

        if (
            $expr->var instanceof Node\Expr\Variable &&
            $expr->var->name === 'this'
        ) {
            $this->analyzeControllerMethod($expr->name->toString());

            if ($this->result['found']) {
                return;
            }
        }

        if ($expr->var instanceof Node\Expr) {
            $this->analyzeExpression($expr->var);

            if ($this->result['found']) {
                return;
            }
        }

        foreach ($expr->args as $arg) {
            $this->analyzeExpression($arg->value);

            if ($this->result['found']) {
                return;
            }
        }
    }
    private function analyzeControllerMethod(string $method): void
    {
        if (isset($this->methodStack[$method])) {
            return;
        }

        if (!isset($this->methodCache[$method])) {
            return;
        }

        $this->methodStack[$method] = true;

        $this->analyzeMethod(
            $this->methodCache[$method]
        );

        unset(
            $this->methodStack[$method]
        );
    }

    private function setResult(
        string $resourceClass,
        bool $isCollection
    ): void {

        $this->result = [
            'found' => true,
            'resourceClass' => $resourceClass,
            'isCollection' => $isCollection,
            'status' => 200,
            'responseType' => 'resource',
            'headers' => [],
        ];
    }

    private function resolveClassName(Node\Name $name): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $class = $name->toString();

        if (isset($this->imports[$class])) {
            return $this->imports[$class];
        }

        if ($this->namespace !== '') {
            return $this->namespace . '\\' . $class;
        }

        return $class;
    }

    private function isResourceClass(?string $class): bool
    {
        if ($class === null) {
            return false;
        }

        if (!class_exists($class)) {
            return false;
        }

        return is_subclass_of(
            $class,
            \Illuminate\Http\Resources\Json\JsonResource::class
        );
    }


    private function handleVariable(Node\Expr\Variable $expr): void
    {
        if (!is_string($expr->name)) {
            return;
        }

        if (!isset($this->variables[$expr->name])) {
            return;
        }

        $resource = $this->variables[$expr->name];

        $this->setResult(
            $resource['resourceClass'],
            $resource['isCollection']
        );
    }
    private function isResourceCollection(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        return is_subclass_of(
            $class,
            \Illuminate\Http\Resources\Json\ResourceCollection::class
        );
    }


    private function resolveCollectedResource(string $collection): ?string
    {
        try {

            $reflection = new \ReflectionClass($collection);

            if ($reflection->hasProperty('collects')) {

                $property = $reflection->getProperty('collects');

                $property->setAccessible(true);

                $instance = $reflection->newInstanceWithoutConstructor();

                $collects = $property->getValue($instance);

                if (
                    is_string($collects) &&
                    class_exists($collects)
                ) {
                    return $collects;
                }
            }

            if ($reflection->hasMethod('collects')) {

                $method = $reflection->getMethod('collects');

                if ($method->isPublic()) {

                    $instance = $reflection->newInstanceWithoutConstructor();

                    $collects = $method->invoke($instance);

                    if (
                        is_string($collects) &&
                        class_exists($collects)
                    ) {
                        return $collects;
                    }
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function analyzeArguments(array $args): void
    {
        foreach ($args as $arg) {

            $this->analyzeExpression(
                $arg->value
            );

            if ($this->result['found']) {
                return;
            }
        }
    }
    private function reset(): void
    {
        $this->imports = [];
        $this->namespace = '';
        $this->variables = [];
        $this->methodCache = [];
        $this->methodStack = [];

        $this->result = [
            'found' => false,
            'resourceClass' => null,
            'isCollection' => false,
            'status' => 200,
            'responseType' => 'resource',
            'headers' => [],
        ];
    }
}
