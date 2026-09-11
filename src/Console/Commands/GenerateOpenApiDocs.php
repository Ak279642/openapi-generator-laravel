<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel\Console\Commands;

use Illuminate\Console\Command;
use OpeapiGeneratorLaravel\Generator;
use Throwable;

final class GenerateOpenApiDocs extends Command
{
    protected $signature = 'openapi:generate
                            {--format=json : Output format: json, yaml, or both}
                            {--output= : Output file or directory}
                            {--check : Validate the generated specification without writing files}
                            {--no-cache : Disable generator caches}';

    protected $description = 'Generate OpenAPI documentation from the Laravel application';

    public function handle(Generator $generator): int
    {
        try {
            if ($this->option('no-cache')) {
                $generator->clearCache();
            }

            $format = strtolower((string) $this->option('format'));
            if (!in_array($format, ['json', 'yaml', 'both'], true)) {
                $this->error('Invalid format. Use json, yaml, or both.');
                return self::FAILURE;
            }

            $this->info('Generating OpenAPI documentation...');

            $result = $generator->generateAndSave(
                $format,
                $this->option('output'),
                (bool) $this->option('check')
            );

            if ($this->option('check')) {
                $this->info('OpenAPI specification is valid.');
                return self::SUCCESS;
            }

            foreach ($result as $path) {
                $this->line("Generated: {$path}");
            }

            $this->info('OpenAPI documentation generated successfully.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('OpenAPI generation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
