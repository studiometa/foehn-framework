<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
use Tempest\Discovery\SkipDiscovery;
use WP_Block;

#[SkipDiscovery]
#[AsBlock(
    name: 'theme/dummy-interactive-block',
    title: 'Dummy Interactive Block',
    category: 'theme',
    icon: 'block-default',
    description: 'An interactive block using the WordPress Interactivity API.',
    keywords: ['interactive', 'custom'],
    interactivity: true,
    supports: [
        'align' => true,
        'html' => false,
    ],
)]
final class InteractiveBlockStub implements InteractiveBlockInterface
{
    /**
     * Define block attributes schema.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'count' => [
                'type' => 'number',
                'default' => 0,
            ],
        ];
    }

    /**
     * Define initial state for the Interactivity API store.
     *
     * This state is shared across all instances of this block.
     *
     * @return array<string, mixed>
     */
    public static function initialState(): array
    {
        return [
            'isActive' => false,
        ];
    }

    /**
     * Define initial context for this block instance.
     *
     * This context is specific to each block instance.
     *
     * @param array<string, mixed> $attributes Block attributes
     * @return array<string, mixed>
     */
    public function initialContext(array $attributes): array
    {
        return [
            'count' => $attributes['count'] ?? 0,
        ];
    }

    /**
     * Compose data for the block template.
     *
     * @param array<string, mixed> $attributes Block attributes from the editor
     * @param string $content Inner block content
     * @param WP_Block $block Block instance
     * @return array<string, mixed> Data passed to the Twig template
     */
    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [
            'attributes' => $attributes,
            'content' => $content,
            'initial_count' => $attributes['count'] ?? 0,
        ];
    }

    /**
     * Render the block.
     *
     * Return an empty string to use the default template rendering.
     *
     * @param array<string, mixed> $attributes Block attributes
     * @param string $content Inner block content
     * @param WP_Block $block Block instance
     * @return string Rendered HTML (empty to use template)
     */
    public function render(array $attributes, string $content, WP_Block $block): string
    {
        // Return empty string to use the default Twig template rendering
        return '';
    }
}
