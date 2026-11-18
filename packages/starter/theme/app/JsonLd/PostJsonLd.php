<?php

declare(strict_types=1);

namespace App\JsonLd;

use Spatie\SchemaOrg\Schema;
use Studiometa\Foehn\Attributes\AsJsonLd;
use Studiometa\Foehn\Contracts\JsonLdProviderInterface;
use WP_Post;

/**
 * The schema.org `BlogPosting` node of a post.
 *
 * Without an SEO plugin, Føhn prints it in a `<script type="application/ld+json">`. With
 * Rank Math, it goes in the graph of Rank Math, which gives posts an `Article` node of
 * its own: set the schema type of posts to "None" in Rank Math > Titles & Meta > Posts,
 * or delete this class.
 *
 * @see https://studiometa.github.io/foehn-framework/guide/json-ld
 */
#[AsJsonLd(postType: 'post')]
final class PostJsonLd implements JsonLdProviderInterface
{
    public function nodes(WP_Post $post): array
    {
        $url = (string) get_permalink($post);
        $published = get_post_datetime($post, 'date', 'gmt');
        $modified = get_post_datetime($post, 'modified', 'gmt');

        $node = Schema::blogPosting()
            ->identifier($url . '#article')
            ->url($url)
            ->mainEntityOfPage($url)
            ->headline(get_the_title($post))
            ->author(Schema::person()->name((string) get_the_author_meta('display_name', (int) $post->post_author)));

        if ($published !== false) {
            $node->datePublished($published);
        }

        if ($modified !== false) {
            $node->dateModified($modified);
        }

        return [$node];
    }
}
