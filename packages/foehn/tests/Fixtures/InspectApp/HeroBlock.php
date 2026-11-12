<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use WP_Block;

#[AsBlock(name: 'theme/hero', title: 'Hero', category: 'design')]
final class HeroBlock implements BlockInterface
{
    public static function attributes(): array
    {
        return [
            'title' => ['type' => 'string', 'default' => '', 'label' => 'Title'],
            'align' => ['type' => 'string', 'enum' => ['left', 'center'], 'default' => 'left'],
            'imageId' => ['type' => 'integer', 'control' => 'image'],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return $attributes;
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return '';
    }
}
