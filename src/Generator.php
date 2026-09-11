<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class Generator
{
    private RouteScanner $routeScanner;
    private ControllerScanner $controllerScanner;
    private FormRequestScanner $formRequestScanner;
    private ResourceScanner $resourceScanner;
    private SchemaBuilder $schemaBuilder;
    private OpenApiWriter $writer;
    private SchemaRegistry $registry;
    private TypeResolver $typeResolver;

    public function __construct(private readonly array $config = [])
    {
        $this->registry = new SchemaRegistry();
        $this->typeResolver = new TypeResolver();
        $this->resourceScanner = new ResourceScanner();
        $this->routeScanner = new RouteScanner($config['scan'] ?? []);
        $this->controllerScanner = new ControllerScanner();
        $this->formRequestScanner = new FormRequestScanner();
        $this->schemaBuilder = new SchemaBuilder($this->registry, $this->typeResolver, $this->resourceScanner);
        $this->writer = new OpenApiWriter($this->registry, $config);
    }

    public function generate(): array
    {
        $routes = $this->routeScanner->scan();
        $controllers = $this->controllerScanner->scan($routes);
        $requests = $this->formRequestScanner->scan($controllers);
        $resources = $this->resourceScanner->scan($controllers);
        $this->schemaBuilder->build($resources, $requests);

        return $this->writer->write($routes, $controllers, $requests, $resources);
    }

    public function generateYaml(): string
    {
        return Yaml::dump($this->generate(), 12, 2, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    public function generateAndSave(string $format = 'json', ?string $output = null, bool $checkOnly = false): array
    {
        $spec = $this->generate();
        $this->validate($spec);

        if ($checkOnly) {
            return [];
        }

        $format = strtolower($format);
        $directory = $this->config['output']['directory'] ?? storage_path('app/openapi');
        $jsonName = $this->config['output']['json'] ?? 'openapi.json';
        $yamlName = $this->config['output']['yaml'] ?? 'openapi.yaml';

        if ($output !== null) {
            if ($format === 'both') {
                $directory = $output;
            } elseif ($format === 'json' && pathinfo($output, PATHINFO_EXTENSION) === 'json') {
                $jsonName = basename($output);
                $directory = dirname($output);
            } elseif ($format === 'yaml' && in_array(pathinfo($output, PATHINFO_EXTENSION), ['yaml', 'yml'], true)) {
                $yamlName = basename($output);
                $directory = dirname($output);
            } else {
                $directory = $output;
            }
        }

        $this->ensureDirectory($directory);
        $written = [];

        if (in_array($format, ['json', 'both'], true)) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $jsonName;
            $this->atomicWrite($path, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
            $written[] = $path;
        }

        if (in_array($format, ['yaml', 'both'], true)) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $yamlName;
            $this->atomicWrite($path, Yaml::dump($spec, 12, 2, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            $written[] = $path;
        }

        return $written;
    }

    public function validate(array $spec): void
    {
        foreach (['openapi', 'info', 'paths'] as $required) {
            if (!array_key_exists($required, $spec)) {
                throw new RuntimeException("Generated OpenAPI document is missing '{$required}'.");
            }
        }

        if (!is_array($spec['paths'])) {
            throw new RuntimeException('Generated OpenAPI paths must be an object.');
        }
    }

    public function clearCache(): void
    {
        $this->routeScanner->clearCache();
        $this->controllerScanner->clearCache();
        $this->resourceScanner->clearCache();
        $this->schemaBuilder->clearCache();
        $this->registry->clearCache();
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create output directory: {$directory}");
        }
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $contents, LOCK_EX) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Unable to write OpenAPI file: {$path}");
        }
    }
}
