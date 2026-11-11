<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Contracts\BlockBindingInterface;
use WP_Block;

#[AsBlockBinding(name: 'theme/stock', label: 'Stock', usesContext: ['postId'])]
final class StockBinding implements BlockBindingInterface
{
    public function value(array $args, WP_Block $block, string $attribute): ?string
    {
        return null;
    }
}
