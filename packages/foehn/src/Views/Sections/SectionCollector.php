<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Sections;

/**
 * Page-local section declarations collected while the page template runs.
 */
final class SectionCollector
{
    /** @var array<string, array<string, mixed>> */
    private array $declarations = [];

    /** @param array<string, mixed> $context */
    public function declare(string $name, array $context): bool
    {
        if ($this->has($name)) {
            return false;
        }

        $this->declarations[$name] = $context;

        return true;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->declarations);
    }

    /** @return array<string, mixed> */
    public function context(string $name): array
    {
        return $this->declarations[$name] ?? [];
    }
}
