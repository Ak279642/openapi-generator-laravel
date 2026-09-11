<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;



use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\File;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class ResourceScanner
{
    private array $cache = [];
    private array $processingStack = [];
    private int $maxDepth = 3;
    private array $imports = [];
    private string $namespace = '';
    private array $fileCache = [];
    private array $processingResources = [];
    private array $completedResources = [];


    public function scan(array $controllers = []): array
    {
        $resources = [];
        $resourceClasses = $this->findResourceClasses();

        $this->processingResources = [];
        $this->completedResources = [];
        foreach ($resourceClasses as $resourceClass) {
            $result = $this->scanClass($resourceClass);

            if ($result === null) {
                continue;
            }

            $resources[$resourceClass] = $result;
        }

        return $resources;
    }

    public function scanClass(string $resourceClass): ?array
    {
        if (isset($this->completedResources[$resourceClass])) {
            return $this->completedResources[$resourceClass];
        }

        if (isset($this->processingResources[$resourceClass])) {
            return null;
        }

        $this->processingResources[$resourceClass] = true;

        try {
            $this->processingStack = [];

            $properties = $this->extractPropertiesWithDepth($resourceClass, 0);

            if ($properties === null) {
                unset($this->processingResources[$resourceClass]);
                return null;
            }

            $result = [
                'properties' => $properties,
            ];

            $this->cache[$resourceClass] = $result;
            $this->completedResources[$resourceClass] = $result;

            unset($this->processingResources[$resourceClass]);

            return $result;
        } catch (\Throwable) {
            unset($this->processingResources[$resourceClass]);

            return null;
        }
    }

    private function findResourceClasses(): array
    {
        $classes = [];
        $paths = [
            app_path('Http/Resources'),
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = File::allFiles($path);
            foreach ($files as $file) {
                $class = $this->getClassFromFile($file->getPathname());
                if ($class && is_subclass_of($class, JsonResource::class)) {
                    /** @var class-string<JsonResource> $class */
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    private function getClassFromFile(string $filePath): ?string
    {
        try {
            $contents = file_get_contents($filePath);
            if ($contents === false) {
                return null;
            }

            preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch);
            $namespace = $namespaceMatch[1] ?? '';

            preg_match('/class\s+([^\s]+)/', $contents, $classMatch);
            $className = $classMatch[1] ?? '';

            if (empty($namespace) || empty($className)) {
                return null;
            }

            $fullClass = $namespace . '\\' . $className;

            if (!class_exists($fullClass)) {
                return null;
            }

            /** @var class-string $fullClass */
            return $fullClass;
        } catch (\Throwable $e) {
            return null;
        }
    }
    private function extractPropertiesWithDepth(string $resourceClass, int $currentDepth): ?array
    {
        if ($currentDepth >= $this->maxDepth) {
            return [];
        }

        if (in_array($resourceClass, $this->processingStack, true)) {
            return [];
        }

        $this->processingStack[] = $resourceClass;

        try {
            $reflection = new ReflectionClass($resourceClass);

            $properties = [];

            if ($reflection->hasMethod('toArray')) {
                $method = $reflection->getMethod('toArray');

                if (
                    $method->getDeclaringClass()->getName() ===
                    $reflection->getName()
                ) {
                    $properties = $this->extractPropertiesFromMethodAST(
                        $method,
                        $currentDepth
                    );
                }
            }

            if (
                empty($properties) &&
                !$reflection->isSubclassOf(JsonResource::class)
            ) {
                $properties = $this->extractPropertiesFromAttributes(
                    $reflection
                );
            }

            array_pop($this->processingStack);

            return $properties;
        } catch (\Throwable) {
            array_pop($this->processingStack);

            return null;
        }
    }

    private function extractPropertiesFromMethodAST(ReflectionMethod $method, int $currentDepth): array
    {
        $properties = [];

        $fileName = $method->getFileName();

        if (!$fileName) {
            return $properties;
        }

        $code = $this->getFileContent($fileName);

        if ($code === null) {
            return $properties;
        }

        $this->extractImportsAndNamespace($code);
        $this->namespace = $method->getDeclaringClass()->getNamespaceName();

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);

            if ($ast === null) {
                return [];
            }

            $visitor = new ResourcePropertyVisitor(
                $this->imports,
                $this->namespace,
                $currentDepth,
                $this->maxDepth
            );

            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            $properties = $visitor->getProperties();

            if (
                $method->getDeclaringClass()->getName() ===
                EnumResource::class
            ) {
                $properties = [
                    'value' => [
                        'type' => 'string',
                    ],
                    'label' => [
                        'type' => 'string',
                    ],
                    'bg_color' => [
                        'type' => 'string',
                    ],
                    'text_color' => [
                        'type' => 'string',
                    ],
                ];
            }

            if ($currentDepth !== 0) {
                return $properties;
            }

            foreach (array_unique($visitor->getNestedResources()) as $nestedClass) {

                if (isset($this->completedResources[$nestedClass])) {
                    continue;
                }

                if (isset($this->processingResources[$nestedClass])) {
                    continue;
                }

                $this->scanClass($nestedClass);
            }

            return $properties;
        } catch (\Throwable) {
            return [];
        }
    }

    private function getFileContent(string $fileName): ?string
    {
        if (isset($this->fileCache[$fileName])) {
            return $this->fileCache[$fileName];
        }

        $content = file_get_contents($fileName);
        if ($content !== false) {
            $this->fileCache[$fileName] = $content;
        }

        return $content;
    }

    private function extractImportsAndNamespace(string $code): void
    {
        $this->imports = [];
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
            if ($ast === null) {
                return;
            }

            $traverser = new NodeTraverser();
            $visitor = new class extends NodeVisitorAbstract {
                public array $imports = [];
                public string $namespace = '';

                public function enterNode(Node $node): void
                {
                    if ($node instanceof Node\Stmt\Namespace_) {
                        if ($node->name !== null) {
                            $this->namespace = $node->name->toString();
                        }
                    }

                    if ($node instanceof Node\Stmt\Use_) {
                        foreach ($node->uses as $use) {
                            $alias = $use->getAlias()->name;
                            $this->imports[$alias] = $use->name->toString();
                        }
                    }

                    if ($node instanceof Node\Stmt\GroupUse) {
                        $prefix = $node->prefix->toString();
                        foreach ($node->uses as $use) {
                            $alias = $use->getAlias()->name;
                            $this->imports[$alias] = $prefix . '\\' . $use->name->toString();
                        }
                    }
                }
            };

            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            $this->imports = $visitor->imports;
        } catch (\Throwable $e) {
            // Skip
        }
    }

    private function extractPropertiesFromAttributes(
        ReflectionClass $reflection
    ): array {
        if ($reflection->isSubclassOf(JsonResource::class)) {
            return [];
        }

        $properties = [];

        foreach (
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC)
            as $property
        ) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            if ($property->isStatic()) {
                continue;
            }

            $type = $property->getType();

            $properties[$property->getName()] = [
                'type' => $type?->getName() ?? 'string',
            ];
        }

        return $properties;
    }

    public function extractResourceFromCollection(string $collectionClass): ?string
    {
        if (!is_subclass_of($collectionClass, \Illuminate\Http\Resources\Json\ResourceCollection::class)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($collectionClass);

            // Check $collects property
            if ($reflection->hasProperty('collects')) {
                $property = $reflection->getProperty('collects');
                $property->setAccessible(true);
                $resourceClass = $property->getValue(null);
                if ($resourceClass && is_string($resourceClass) && is_subclass_of($resourceClass, JsonResource::class)) {
                    return $resourceClass;
                }
            }

            // Check collects() method
            if ($reflection->hasMethod('collects')) {
                $method = $reflection->getMethod('collects');
                $method->setAccessible(true);
                $resourceClass = $method->invoke(null);
                if ($resourceClass && is_string($resourceClass) && is_subclass_of($resourceClass, JsonResource::class)) {
                    return $resourceClass;
                }
            }
        } catch (\Throwable $e) {
            // Skip
        }

        return null;
    }

    public function isResource(string $class): bool
    {
        return is_subclass_of($class, JsonResource::class);
    }

    public function getResourceSchemaName(string $resourceClass): string
    {
        $name = class_basename($resourceClass);
        return str_replace('Resource', '', $name);
    }

    public function clearCache(): void
    {
        $this->cache = [];
        $this->fileCache = [];
        $this->processingStack = [];
    }
}
