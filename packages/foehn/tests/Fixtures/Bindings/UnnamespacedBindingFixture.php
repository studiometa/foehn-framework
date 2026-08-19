<?php

declare(strict_types=1);

namespace Tests\Fixtures\Bindings;

use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Contracts\BlockBindingInterface;
use WP_Block;

#[AsBlockBinding(name: 'reading-time', label: 'Reading time')]
final class UnnamespacedBindingFixture implements BlockBindingInterface
{
    public function value(array $args, WP_Block $block, string $attribute): ?string
    {
        return null;
    }
}
