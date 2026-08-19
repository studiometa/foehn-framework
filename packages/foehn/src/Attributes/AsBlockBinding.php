<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a block bindings source.
 *
 * Read this first, because the common case needs none of it: WordPress already
 * ships `core/post-meta`, and a key declared with #[AsPostMeta] — `single`,
 * `showInRest` — is bindable through it with no source of your own. A custom
 * source is for a value that is *computed*: a formatted price, a reading time,
 * a figure from an external service, something assembled from several keys.
 *
 * Usage:
 * ```php
 * #[AsBlockBinding(
 *     name: 'theme/reading-time',
 *     label: 'Reading time',
 *     usesContext: ['postId'],
 * )]
 * final readonly class ReadingTime implements BlockBindingInterface
 * {
 *     public function value(array $args, WP_Block $block, string $attribute): ?string
 *     {
 *         // …
 *     }
 * }
 * ```
 *
 * Bound in the editor, or in markup:
 * ```html
 * <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"theme/reading-time"}}}} -->
 * <p></p>
 * <!-- /wp:paragraph -->
 * ```
 *
 * @see \Studiometa\Foehn\Contracts\BlockBindingInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBlockBinding
{
    /**
     * @param string $name The source name, as `namespace/name`. WordPress
     *   requires the slash and refuses anything else
     * @param string $label Shown in the editor's binding UI
     * @param list<string> $usesContext Block context keys the value needs, e.g.
     *   `postId` or `postType`. WordPress passes nothing the source did not ask for
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $usesContext = [],
    ) {}
}
