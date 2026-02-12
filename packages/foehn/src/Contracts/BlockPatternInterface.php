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
     * May return a plain array or an Arrayable DTO (which will be
     * flattened to array before rendering).
     *
     * @return array<string, mixed>|Arrayable Context variables for the template
     */
    public function compose(): array|Arrayable;
}
