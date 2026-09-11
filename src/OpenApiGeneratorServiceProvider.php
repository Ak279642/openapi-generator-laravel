<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use Illuminate\Support\ServiceProvider;
use OpeapiGeneratorLaravel\Console\Commands\GenerateOpenApiDocs;

final class OpenApiGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/openapi-generator.php', 'openapi-generator');

        $this->app->singleton(Generator::class, static function ($app): Generator {
            return new Generator($app['config']->get('openapi-generator', []));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/openapi-generator.php' => config_path('openapi-generator.php'),
        ], 'openapi-generator-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/openapi-generator'),
        ], 'openapi-generator-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'openapi-generator');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateOpenApiDocs::class]);
        }
    }
}
