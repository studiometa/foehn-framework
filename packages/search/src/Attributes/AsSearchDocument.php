<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Attributes;

use Attribute;

/**
 * Make the posts of a post type searchable, through the class that turns one post into
 * a search document.
 *
 * The class implements {@see \Studiometa\Foehn\Search\Contracts\SearchDocumentInterface}.
 * One class for each post type.
 *
 *     #[AsSearchDocument(postType: 'post')]
 *     final class PostDocument implements SearchDocumentInterface
 *     {
 *         public function toDocument(WP_Post $post): ?array
 *         {
 *             return Document::fromPost($post);
 *         }
 *     }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsSearchDocument
{
    /**
     * @param string $postType The post type whose published posts go into the index
     */
    public function __construct(
        public string $postType,
    ) {}
}
