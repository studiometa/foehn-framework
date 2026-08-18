<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;
use Tempest\Discovery\SkipDiscovery;
use WP_Block;

/**
 * No editor JavaScript is needed: Foehn derives the sidebar controls from the
 * attributes() schema below, and the editor preview comes from this class's own
 * server-side rendering.
 */
#[SkipDiscovery]
#[AsBlock(
    name: 'theme/dummy-block',
    title: 'Dummy Block',
    category: 'theme',
    icon: 'block-default',
    description: 'A custom block.',
    keywords: ['custom'],
    supports: [
        'align' => true,
        'html' => false,
    ],
)]
final class BlockStub implements BlockInterface
{
    /**
     * Define block attributes schema.
     *
     * Beyond the WordPress keys (type, default, enum), four optional keys describe
     * how the attribute is edited in the block editor sidebar:
     *
     * - 'label'   Field label, defaults to a humanized attribute name
     * - 'help'    Help text under the field
     * - 'control' 'text', 'textarea', 'toggle', 'number', 'select' or 'image',
     *             derived from 'type' when omitted
     * - 'options' Choices of a select, as a list or a value => label map
     *
     * @return array<string, array<string, mixed>>
     */
    public static function attributes(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'default' => '',
                'label' => 'Title',
                'help' => 'Shown as the block heading.',
            ],
            // 'variant' => [
            //     'type' => 'string',
            //     'default' => 'light',
            //     'control' => 'select',
            //     'options' => ['light' => 'Light', 'dark' => 'Dark'],
            // ],
            // 'imageId' => [
            //     'type' => 'integer',
            //     'control' => 'image',
            // ],
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
            'title' => $attributes['title'] ?? '',
            // Add your custom data here
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
        // Or return custom HTML here to bypass template rendering
        return '';
    }
}
