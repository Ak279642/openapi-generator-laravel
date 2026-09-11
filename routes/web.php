<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OpeapiGeneratorLaravel\Http\Controllers\OpenApiDocumentationController;

if (config('openapi-generator.enabled', true)) {
    Route::middleware(config('openapi-generator.middleware', []))->group(function (): void {
        Route::get(config('openapi-generator.swagger_path', 'docs/swagger'), [OpenApiDocumentationController::class, 'swagger'])
            ->name('openapi-generator.swagger');
        Route::get(config('openapi-generator.scalar_path', 'docs/scalar'), [OpenApiDocumentationController::class, 'scalar'])
            ->name('openapi-generator.scalar');
        Route::get(config('openapi-generator.json_path', 'docs/openapi.json'), [OpenApiDocumentationController::class, 'json'])
            ->name('openapi-generator.json');
        Route::get(config('openapi-generator.yaml_path', 'docs/openapi.yaml'), [OpenApiDocumentationController::class, 'yaml'])
            ->name('openapi-generator.yaml');
    });
}
