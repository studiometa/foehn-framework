<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

/**
 * Interface for context providers.
 *
 * Context providers allow adding data to specific templates automatically.
 * They are called before each matching template is rendered.
 */
interface ContextProviderInterface
{
    /**
     * Provide additional data for the view context.
     *
     * @param array<string, mixed> $context Current template context
     * @return array<string, mixed> Modified context with additional data
     */
    public function provide(array $context): array;
}
