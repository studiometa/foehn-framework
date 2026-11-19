<?php

declare(strict_types=1);

/**
 * The search document of the smoke posts, registered through `SearchDocumentDiscovery`
 * as a theme class is, so the index and query hooks are the real ones.
 *
 * Needs WordPress loaded: `search.php` requires it, and `search-cli.php` requires it
 * once WP-CLI has loaded WordPress.
 */

use Studiometa\Foehn\Search\Attributes\AsSearchDocument;
use Studiometa\Foehn\Search\Contracts\SearchDocumentInterface;
use Studiometa\Foehn\Search\Discovery\SearchDocumentDiscovery;
use Studiometa\Foehn\Search\Document;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

use function Tempest\Container\get;

#[AsSearchDocument(postType: 'post')]
final class SmokeSearchDocument implements SearchDocumentInterface
{
    public function toDocument(WP_Post $post): ?array
    {
        return str_starts_with($post->post_name, 'smoke-search-') ? Document::fromPost($post) : null;
    }
}

$discovery = get(SearchDocumentDiscovery::class);
$discovery->discover(new DiscoveryLocation('Smoke\\', __DIR__), new ClassReflector(SmokeSearchDocument::class));
$discovery->apply();
