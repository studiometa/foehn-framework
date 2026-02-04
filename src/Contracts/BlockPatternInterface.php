<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

/**
 * Interface for block patterns with dynamic content.
 *
 * Implement this interface to provide dynamic data to pattern templates.
 */
interface BlockPatternInterface
{
    /**
     * Compose data for the pattern template.
     *
     * @return array<string, mixed> Context variables for the template
     */
    public function compose(): array;
}
