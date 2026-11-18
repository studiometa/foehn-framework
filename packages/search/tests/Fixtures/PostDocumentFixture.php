<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Tests\Fixtures;

use Studiometa\Foehn\Search\Attributes\AsSearchDocument;
use Studiometa\Foehn\Search\Contracts\SearchDocumentInterface;
use Studiometa\Foehn\Search\Document;
use WP_Post;

#[AsSearchDocument(postType: 'post')]
final class PostDocumentFixture implements SearchDocumentInterface
{
    public function toDocument(WP_Post $post): ?array
    {
        if ($post->post_name === 'private-notes') {
            return null;
        }

        return [...Document::fromPost($post), 'reference' => 'REF-' . $post->ID];
    }
}
