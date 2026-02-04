<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Contracts;

/**
 * Interface for view composers.
 *
 * View composers allow adding data to specific templates automatically.
 * They are called before each matching template is rendered.
 */
interface ViewComposerInterface
{
    /**
     * Compose additional data for the view.
     *
     * @param array<string, mixed> $context Current template context
     * @return array<string, mixed> Modified context with additional data
     */
    public function compose(array $context): array;
}
