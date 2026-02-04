<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Contracts;

/**
 * Interface for interactive Gutenberg blocks using the Interactivity API.
 *
 * Extends BlockInterface with methods for managing reactive state.
 */
interface InteractiveBlockInterface extends BlockInterface
{
    /**
     * Define initial state for the Interactivity API store.
     *
     * This state is shared across all instances of this block type.
     *
     * @return array<string, mixed> Global state data
     */
    public static function initialState(): array;

    /**
     * Define initial context for this block instance.
     *
     * This context is specific to each block instance and
     * will be serialized to data-wp-context attribute.
     *
     * @param array<string, mixed> $attributes Block attributes
     * @return array<string, mixed> Per-instance context data
     */
    public function initialContext(array $attributes): array;
}
