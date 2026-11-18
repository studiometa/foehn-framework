<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use WP_Block;

#[AsBlock(name: 'test/teaser', title: 'Teaser')]
final class PrepublishBlockFixture implements BlockInterface
{
    public static function attributes(): array
    {
        return [
            'title' => ['type' => 'string'],
            'titleLevel' => ['type' => 'integer', 'control' => 'headingLevel', 'default' => 2],
            'imageId' => ['type' => 'integer', 'control' => 'image', 'label' => 'Background'],
            'images' => ['type' => 'array', 'control' => 'gallery'],
            'file' => ['type' => 'integer', 'control' => 'file'],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return $attributes;
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return '<div>Teaser</div>';
    }
}
