<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use WP_Block;

/**
 * Computes the value a block attribute is bound to.
 *
 * Implemented alongside #[AsBlockBinding].
 */
interface BlockBindingInterface
{
    /**
     * The value for one bound attribute of one block.
     *
     * @param array<string, mixed> $args What the binding declared in the block's
     *   markup, e.g. `['key' => 'price']` for a source that takes an argument
     * @param WP_Block $block The block being rendered. Its `context` holds the
     *   keys the source asked for through `usesContext`
     * @param string $attribute Which attribute is being bound — a source bound to
     *   both `url` and `text` of a button is asked twice, once for each
     * @return string|null Null leaves the attribute as the block author wrote it
     */
    public function value(array $args, WP_Block $block, string $attribute): ?string;
}
