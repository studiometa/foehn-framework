<?php

declare(strict_types=1);

namespace App\Bindings;

use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Contracts\BlockBindingInterface;
use WP_Block;

/**
 * How long a post takes to read, bound to any block that accepts a binding.
 *
 * This is what a custom source is for: a value that is *computed*. A value that
 * is merely *stored* needs none of this — a key declared with #[AsPostMeta] is
 * bindable through core's own `core/post-meta` with no PHP at all. See
 * App\Models\Product, whose `price` works that way.
 *
 * Bind it in the editor, or in markup:
 *
 * ```html
 * <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"theme/reading-time"}}}} -->
 * <p></p>
 * <!-- /wp:paragraph -->
 * ```
 */
#[AsBlockBinding(name: 'theme/reading-time', label: 'Reading time', usesContext: ['postId'])]
final readonly class ReadingTime implements BlockBindingInterface
{
    /** Words a minute, near enough for a reading estimate. */
    private const int WORDS_PER_MINUTE = 200;

    public function value(array $args, WP_Block $block, string $attribute): ?string
    {
        $postId = $block->context['postId'] ?? null;

        if ($postId === null) {
            return null;
        }

        $content = (string) get_post_field('post_content', (int) $postId);
        $words = str_word_count(wp_strip_all_tags($content));

        if ($words === 0) {
            return null;
        }

        $minutes = max(1, (int) ceil($words / self::WORDS_PER_MINUTE));

        /* translators: %d: number of minutes. */
        return sprintf(_n('%d minute read', '%d minutes read', $minutes, 'starter-theme'), $minutes);
    }
}
