<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_Block;

/**
 * Facts — a container that accepts one kind of block, `theme/fact`.
 *
 * The other half of `parent`: FactBlock can only be inserted here, and this block
 * accepts nothing else. The rows look like the facts beside a project title.
 */
#[AsBlock(
    name: 'theme/facts',
    title: 'Facts',
    category: 'text',
    icon: 'editor-table',
    description: 'A list of label and value pairs.',
    keywords: ['facts', 'details', 'list'],
    allowedBlocks: ['theme/fact'],
    innerBlocksTemplate: [
        ['theme/fact', ['label' => 'Camera']],
        ['theme/fact', ['label' => 'Film']],
    ],
)]
final readonly class FactsBlock implements BlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function attributes(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return ['content' => $content];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/facts', $this->compose($attributes, $content, $block));
    }
}
