<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_Block;

/**
 * Section wrapper — a container block.
 *
 * Prose lives in inner blocks using core blocks, structured data lives in the
 * sidebar. The heading and the body text are edited in the canvas with the core
 * blocks the editor already has; the section itself only carries its layout
 * choices as attributes.
 */
#[AsBlock(
    name: 'theme/section',
    title: 'Section',
    category: 'design',
    icon: 'layout',
    description: 'A section wrapper. Its content is edited in the canvas with core blocks.',
    keywords: ['section', 'container', 'wrapper'],
    supports: [
        'align' => ['wide', 'full'],
    ],
    allowedBlocks: ['core/heading', 'core/paragraph', 'core/image', 'theme/callout'],
    innerBlocksTemplate: [
        ['core/heading', ['level' => 2, 'placeholder' => 'Section title']],
        ['core/paragraph', ['placeholder' => 'Introduce the section here.']],
    ],
    // Explicitly unlocked rather than left unset: the template is a starting
    // point, and an author may add to it or remove from it.
    innerBlocksTemplateLock: false,
)]
final readonly class SectionBlock implements BlockInterface
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
            'background' => [
                'type' => 'string',
                'default' => 'none',
                'label' => 'Background',
                'options' => [
                    'none' => 'None',
                    'light' => 'Light',
                    'dark' => 'Dark',
                ],
            ],
            'spacing' => [
                'type' => 'string',
                'default' => 'medium',
                'control' => 'select',
                'options' => ['small', 'medium', 'large'],
                'help' => 'Vertical padding around the section content.',
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
            'background' => $attributes['background'] ?? 'none',
            'spacing' => $attributes['spacing'] ?? 'medium',
            'content' => $content,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/section', $this->compose($attributes, $content, $block));
    }
}
