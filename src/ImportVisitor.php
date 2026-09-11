<?php

declare(strict_types=1);

namespace OpeapiGeneratorLaravel;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ImportVisitor extends NodeVisitorAbstract
{
    private array $imports = [];

    private string $namespace = '';

    public function enterNode(Node $node): void
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = $node->name?->toString() ?? '';
        }

        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $this->imports[$alias] = $use->name->toString();
            }
        }
    }

    public function getImports(): array
    {
        return $this->imports;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
