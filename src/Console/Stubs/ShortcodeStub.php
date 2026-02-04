<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console\Stubs;

use Studiometa\WPTempest\Attributes\AsShortcode;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
final class ShortcodeStub
{
    /**
     * Handle the [dummy-shortcode] shortcode.
     *
     * Usage: [dummy-shortcode title="Hello" /]
     * With content: [dummy-shortcode]Content here[/dummy-shortcode]
     *
     * @param array<string, string>|string $atts Shortcode attributes
     * @param string|null $content Content between opening and closing tags
     * @return string Rendered HTML
     */
    #[AsShortcode(tag: 'dummy-shortcode')]
    public function handle(array|string $atts, ?string $content = null): string
    {
        // Normalize attributes (WP passes empty string if no attributes)
        $atts = is_array($atts) ? $atts : [];

        // Define defaults and merge with provided attributes
        $attributes = shortcode_atts([
            'title' => 'Default Title',
            'class' => '',
        ], $atts);

        // Build output
        $class = $attributes['class'] !== '' ? ' class="' . esc_attr($attributes['class']) . '"' : '';
        $html = '<div' . $class . '>';
        $html .= '<h3>' . esc_html($attributes['title']) . '</h3>';

        if ($content !== null && $content !== '') {
            $html .= '<div class="content">' . wp_kses_post($content) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
