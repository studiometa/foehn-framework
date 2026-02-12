<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

/**
 * Interface for objects that can be converted to an array.
 *
 * Used by DTOs returned from compose() methods to flatten
 * typed objects into Twig-compatible context arrays.
 */
interface Arrayable
{
    /**
     * Convert the object to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
