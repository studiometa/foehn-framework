<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_Block;

/**
 * Fact — one row of a `theme/facts` block.
 *
 * `parent` keeps it out of the inserter everywhere except directly inside
 * `theme/facts`: a label and a value mean nothing on their own.
 */
#[AsBlock(
    name: 'theme/fact',
    title: 'Fact',
    category: 'text',
    icon: 'minus',
    description: 'A label and its value.',
    parent: 'theme/facts',
)]
final readonly class FactBlock implements BlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'label' => [
                'type' => 'string',
                'default' => '',
                'label' => 'Label',
            ],
            'value' => [
                'type' => 'string',
                'default' => '',
                'label' => 'Value',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [
            'label' => (string) ($attributes['label'] ?? ''),
            'value' => (string) ($attributes['value'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/fact', $this->compose($attributes, $content, $block));
    }
}
