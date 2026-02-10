<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for WordPress block markup comments.
 *
 * Provides helper functions to generate block comment syntax
 * used in block patterns and template parts.
 *
 * Usage:
 * ```twig
 * {{ wp_block_start('heading', { level: 2 }) }}
 * <h2 class="wp-block-heading">Title</h2>
 * {{ wp_block_end('heading') }}
 *
 * {# Or use the shorthand for self-closing style #}
 * {{ wp_block('paragraph', { align: 'center' }, '<p>Content</p>') }}
 * ```
 */
#[AsTwigExtension]
final class BlockMarkupExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'wp_block_markup';
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('wp_block_start', [$this, 'blockStart'], ['is_safe' => ['html']]),
            new TwigFunction('wp_block_end', [$this, 'blockEnd'], ['is_safe' => ['html']]),
            new TwigFunction('wp_block', [$this, 'block'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Generate opening block comment.
     *
     * @param string $name Block name (e.g., 'heading', 'core/paragraph', 'theme/hero')
     * @param array<string, mixed> $attributes Block attributes
     * @return string Opening comment
     *
     * @example
     * {{ wp_block_start('heading') }}
     * <!-- wp:heading -->
     *
     * {{ wp_block_start('paragraph', { align: 'center' }) }}
     * <!-- wp:paragraph {"align":"center"} -->
     *
     * {{ wp_block_start('group', { layout: { type: 'constrained' } }) }}
     * <!-- wp:group {"layout":{"type":"constrained"}} -->
     */
    public function blockStart(string $name, array $attributes = []): string
    {
        $blockName = $this->normalizeBlockName($name);

        if ($attributes === []) {
            return sprintf('<!-- wp:%s -->', $blockName);
        }

        $json = json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return sprintf('<!-- wp:%s %s -->', $blockName, $json);
    }

    /**
     * Generate closing block comment.
     *
     * @param string $name Block name
     * @return string Closing comment
     *
     * @example
     * {{ wp_block_end('heading') }}
     * <!-- /wp:heading -->
     */
    public function blockEnd(string $name): string
    {
        return sprintf('<!-- /wp:%s -->', $this->normalizeBlockName($name));
    }

    /**
     * Generate complete block with opening comment, content, and closing comment.
     *
     * @param string $name Block name
     * @param array<string, mixed> $attributes Block attributes
     * @param string $content Inner content/HTML
     * @return string Complete block markup
     *
     * @example
     * {{ wp_block('paragraph', {}, '<p>Hello world</p>') }}
     * <!-- wp:paragraph -->
     * <p>Hello world</p>
     * <!-- /wp:paragraph -->
     *
     * {{ wp_block('heading', { level: 2 }, '<h2>Title</h2>') }}
     * <!-- wp:heading {"level":2} -->
     * <h2>Title</h2>
     * <!-- /wp:heading -->
     */
    public function block(string $name, array $attributes = [], string $content = ''): string
    {
        return $this->blockStart($name, $attributes) . "\n" . $content . "\n" . $this->blockEnd($name);
    }

    /**
     * Normalize block name to include core namespace if not specified.
     *
     * @param string $name Block name
     * @return string Normalized block name
     */
    private function normalizeBlockName(string $name): string
    {
        // If already namespaced (contains /), return as-is
        if (str_contains($name, '/')) {
            return $name;
        }

        // Core blocks don't need the core/ prefix in comments
        return $name;
    }
}
