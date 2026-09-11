<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel\Http\Controllers;

use Illuminate\Http\Response;
use OpeapiGeneratorLaravel\Generator;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class OpenApiDocumentationController
{
    public function __construct(private readonly Generator $generator)
    {
    }

    public function swagger(): Response
    {
        return response()->view('openapi-generator::swagger', [
            'specUrl' => route('openapi-generator.json'),
            'scalarUrl' => route('openapi-generator.scalar'),
            'config' => config('openapi-generator'),
        ]);
    }

    public function scalar(): Response
    {
        return response()->view('openapi-generator::scalar', [
            'specUrl' => route('openapi-generator.json'),
            'swaggerUrl' => route('openapi-generator.swagger'),
            'config' => config('openapi-generator'),
        ]);
    }

    public function json(): SymfonyResponse
    {
        $spec = $this->generator->generate();
        return response()->json($spec, 200, ['Cache-Control' => 'no-store']);
    }

    public function yaml(): SymfonyResponse
    {
        $yaml = $this->generator->generateYaml();
        return response($yaml, 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
