<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a class that gives the schema.org JSON-LD nodes of a post.
 *
 * The class must implement JsonLdProviderInterface. Its nodes go in the graph of
 * Rank Math when Rank Math prints one, else in a `<script type="application/ld+json">`
 * that Føhn prints in `<head>`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsJsonLd
{
    /**
     * @param string|list<string> $postType The post types whose single pages get the nodes.
     *                                      Examples: 'post', ['event', 'place']
     */
    public function __construct(
        public string|array $postType,
    ) {}

    /**
     * Get the post types as a list.
     *
     * @return list<string>
     */
    public function getPostTypes(): array
    {
        return is_array($this->postType) ? array_values($this->postType) : [$this->postType];
    }
}
