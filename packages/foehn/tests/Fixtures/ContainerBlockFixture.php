<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use WP_Block;

#[AsBlock(
    name: 'test/section',
    title: 'Section Block',
    category: 'design',
    allowedBlocks: ['core/heading', 'core/paragraph'],
    innerBlocksTemplate: [['core/heading', ['level' => 2]]],
    innerBlocksTemplateLock: 'insert',
)]
final class ContainerBlockFixture implements BlockInterface
{
    public static function attributes(): array
    {
        return [
            'ctaLabel' => [
                'type' => 'string',
                'default' => 'Read more',
                'label' => 'Button text',
                'help' => 'Keep it short',
            ],
            'variant' => [
                'type' => 'string',
                'options' => ['light' => 'Light', 'dark' => 'Dark'],
            ],
            'image_id' => [
                'type' => 'integer',
                'control' => 'image',
            ],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return $attributes;
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return '<div>Section</div>';
    }
}
