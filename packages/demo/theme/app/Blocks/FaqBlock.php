<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\JsonLd\JsonLd;
use WP_Block;

/**
 * FAQ — questions and answers in an accordion, and the `FAQPage` node of the page.
 *
 * Each question is a `theme/faq-question` inner block. The block reads their
 * attributes, renders them with studiometa/ui's Accordion, and adds the same
 * questions to the JSON-LD of the page with JsonLd::add(). Føhn prints that node in
 * the footer, because the page renders its blocks after `<head>`.
 */
#[AsBlock(
    name: 'theme/faq',
    title: 'FAQ',
    category: 'text',
    icon: 'editor-help',
    description: 'Questions and answers, also given to search engines as a FAQPage.',
    keywords: ['faq', 'questions', 'accordion'],
    allowedBlocks: ['theme/faq-question'],
    innerBlocksTemplate: [
        ['theme/faq-question'],
    ],
)]
final readonly class FaqBlock implements BlockInterface
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
                'default' => 'Questions',
                'help' => 'Shown above the questions.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{title: string, items: list<array{title: string, content: string}>}
     */
    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        $items = [];

        foreach ($block->inner_blocks as $question) {
            $title = (string) ($question->attributes['question'] ?? '');
            $answer = (string) ($question->attributes['answer'] ?? '');

            if ($title !== '' && $answer !== '') {
                $items[] = ['title' => $title, 'content' => $answer];
            }
        }

        return [
            'title' => (string) ($attributes['title'] ?? ''),
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        $context = $this->compose($attributes, $content, $block);

        if ($context['items'] !== []) {
            JsonLd::add(self::node($context['items']));
        }

        return $this->view->render('blocks/faq', $context);
    }

    /**
     * The `FAQPage` node of the questions, as a plain array: a node needs no library.
     *
     * @param list<array{title: string, content: string}> $items
     * @return array<string, mixed>
     */
    public static function node(array $items): array
    {
        return [
            '@type' => 'FAQPage',
            '@id' => get_permalink() . '#faq',
            'mainEntity' => array_map(static fn(array $item): array => [
                '@type' => 'Question',
                'name' => $item['title'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['content']],
            ], $items),
        ];
    }
}
