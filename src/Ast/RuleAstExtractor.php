<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel\Ast;

use ReflectionClass;

class RuleAstExtractor
{
    public function extract(ReflectionClass $reflection): array
    {
        $instance = $reflection->newInstanceWithoutConstructor();

        if (method_exists($instance, 'openApiRules')) {
            $rules = $instance->openApiRules();

            if (is_array($rules)) {
                return $rules;
            }
        }

        if (method_exists($instance, 'rules')) {
            $rules = $instance->rules();

            if (is_array($rules)) {
                return $rules;
            }
        }

        return [];
    }
}
