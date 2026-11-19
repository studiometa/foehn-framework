<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use WP_Block;

/**
 * FAQ question — one question and its answer, inside a `theme/faq` block.
 *
 * The FAQ block reads the attributes of its questions and renders them itself, so
 * the template of this block is what the editor shows while an editor writes.
 */
#[AsBlock(
    name: 'theme/faq-question',
    title: 'FAQ question',
    category: 'text',
    icon: 'editor-help',
    description: 'A question and its answer.',
    parent: 'theme/faq',
)]
final readonly class FaqQuestionBlock implements BlockInterface
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
            'question' => [
                'type' => 'string',
                'default' => '',
                'label' => 'Question',
            ],
            'answer' => [
                'type' => 'string',
                'default' => '',
                'control' => 'textarea',
                'label' => 'Answer',
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
            'question' => (string) ($attributes['question'] ?? ''),
            'answer' => (string) ($attributes['answer'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/faq-question', $this->compose($attributes, $content, $block));
    }
}
