<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use WP_Block;

#[AsBlock(
    name: 'test/slide',
    title: 'Slide Block',
    category: 'design',
    keywords: ['slide'],
    parent: 'test/slider',
    ancestor: ['test/carousel'],
)]
final class ConstrainedBlockFixture implements BlockInterface
{
    public static function attributes(): array
    {
        return [];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return '';
    }
}
