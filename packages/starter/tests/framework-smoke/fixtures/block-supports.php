<?php

declare(strict_types=1);

/**
 * A Føhn block with block supports, rendered from saved block markup.
 *
 * The block goes through `BlockRenderer`, as a discovered block does, with a template
 * root that already has a class and a style. Core adds the support attributes in
 * `apply_block_supports()` and the 7.1 states in a `render_block` filter; both must end
 * on that root.
 *
 * Run by `BlockSupportsTest` through `wp eval-file`. One line of output, because
 * `Site::wp()` hands back the last line of a command.
 */

use Studiometa\Foehn\Blocks\BlockRenderer;
use Studiometa\Foehn\Contracts\BlockInterface;

$template = new class implements BlockInterface {
    public static function attributes(): array
    {
        return [];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return '<section class="smoke" style="color:black"><p>Smoke</p></section>';
    }
};

register_block_type('smoke/supports', [
    'api_version' => 3,
    'title' => 'Smoke supports',
    'render_callback' => static fn(
        array $attributes,
        string $content,
        WP_Block $block,
    ): string => new BlockRenderer()->render($template, $attributes, $content, $block),
    'supports' => [
        'align' => ['wide', 'full'],
        'anchor' => true,
        'color' => ['background' => true],
        'spacing' => ['padding' => true],
        'html' => false,
    ],
]);

$attributes = [
    'align' => 'wide',
    'anchor' => 'smoke-anchor',
    'className' => 'smoke-extra',
    'style' => [
        'color' => ['background' => '#ff0000'],
        'spacing' => ['padding' => ['top' => '2rem']],
        '@mobile' => ['spacing' => ['padding' => ['top' => '1rem']]],
    ],
];

echo str_replace("\n", '', do_blocks(sprintf('<!-- wp:smoke/supports %s /-->', wp_json_encode($attributes))));
