<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Data\ImageData;
use WP_Block;

/**
 * Callout box — a native block with no editor JavaScript.
 *
 * Every sidebar control Foehn can derive is exercised here: a text field from a
 * plain string, a textarea and an image picker from an explicit `control`, a
 * select from `options`, a number from an integer, and a toggle from a boolean.
 */
#[AsBlock(
    name: 'theme/callout',
    title: 'Callout',
    category: 'widgets',
    icon: 'megaphone',
    description: 'A callout box. Its sidebar controls come from the attribute schema alone.',
    keywords: ['callout', 'notice', 'alert'],
    supports: [
        'align' => ['wide', 'full'],
    ],
)]
final readonly class CalloutBlock implements BlockInterface
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
            'title' => [
                'type' => 'string',
                'default' => '',
                'help' => 'Shown as the callout heading.',
            ],
            'body' => [
                'type' => 'string',
                'default' => '',
                'control' => 'textarea',
                'help' => 'Short supporting text. Anything longer belongs in inner blocks.',
            ],
            'tone' => [
                'type' => 'string',
                'default' => 'info',
                'label' => 'Tone',
                'options' => [
                    'info' => 'Information',
                    'success' => 'Success',
                    'warning' => 'Warning',
                ],
            ],
            'iconId' => [
                'type' => 'integer',
                'control' => 'image',
                'label' => 'Icon',
            ],
            'columns' => [
                'type' => 'integer',
                'default' => 1,
                'help' => 'Number of columns the body flows into.',
            ],
            'dismissible' => [
                'type' => 'boolean',
                'default' => false,
                'label' => 'Dismissible',
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
            'title' => $attributes['title'] ?? '',
            'body' => $attributes['body'] ?? '',
            'tone' => $attributes['tone'] ?? 'info',
            'icon' => ImageData::fromAttachmentId($attributes['iconId'] ?? null),
            'columns' => $attributes['columns'] ?? 1,
            'dismissible' => $attributes['dismissible'] ?? false,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/callout', $this->compose($attributes, $content, $block));
    }
}
