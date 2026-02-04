<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Contracts\BlockInterface;
use WP_Block;

#[AsBlock(
    name: 'test/hero',
    title: 'Hero Block',
    category: 'design',
    icon: 'cover-image',
    description: 'A hero block.',
    keywords: ['hero', 'banner'],
)]
final class BlockFixture implements BlockInterface
{
    public static function attributes(): array
    {
        return ['title' => ['type' => 'string']];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return $attributes;
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return '<div>Hero</div>';
    }
}
