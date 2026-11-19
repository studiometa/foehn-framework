<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Discovery;

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Studiometa\Foehn\Search\Attributes\AsSearchDocument;
use Studiometa\Foehn\Search\Contracts\SearchDocumentInterface;
use Studiometa\Foehn\Search\Hooks\IndexHooks;
use Studiometa\Foehn\Search\Query\SearchQuery;
use Studiometa\Foehn\Search\SearchDocuments;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use WP_Post;
use WP_Query;

use function Tempest\Container\get;

/**
 * Discovers the classes marked with #[AsSearchDocument], records them in
 * {@see SearchDocuments}, and adds the search to WordPress when there is one.
 *
 * Føhn does not register the #[AsAction] methods of a package (see
 * {@see \Studiometa\Foehn\Discovery\HookDiscovery}), so the hooks are added here, in the
 * discovery phase, on `init`. A site with no search document gets none of them.
 */
#[AsDiscovery(phase: DiscoveryPhase::Main)]
final class SearchDocumentDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function __construct(
        private readonly SearchDocuments $documents,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class) || !$class->implements(SearchDocumentInterface::class)) {
            return;
        }

        $attribute = $class->getAttribute(AsSearchDocument::class);

        if ($attribute === null) {
            return;
        }

        $this->addItem($location, [
            'postType' => $attribute->postType,
            'className' => $class->getName(),
        ]);
    }

    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            $this->documents->register($item['postType'], $item['className']);
        }

        if ($this->documents->postTypes() === []) {
            return;
        }

        $this->addIndexHooks();
        $this->addQueryHooks();
    }

    /**
     * Index a post again when it or one of its terms changes. The hook objects are
     * resolved when a hook fires, not here.
     */
    private function addIndexHooks(): void
    {
        $hooks = static fn(): IndexHooks => get(IndexHooks::class);

        add_action('save_post', static fn(int $id, WP_Post $post) => $hooks()->onSavePost($id, $post), 20, 2);
        add_action(
            'deleted_post',
            static fn(int $id, mixed $post = null) => $hooks()->onDeletedPost($id, $post),
            10,
            2,
        );
        add_action('set_object_terms', static fn(int $objectId) => $hooks()->onSetObjectTerms($objectId));
        add_action(
            'edited_term',
            static fn(int $termId, int $termTaxonomyId, string $taxonomy) => $hooks()->onEditedTerm(
                $termId,
                $termTaxonomyId,
                $taxonomy,
            ),
            10,
            3,
        );
        add_action(
            'delete_term',
            static fn(
                int $termId,
                int $termTaxonomyId,
                string $taxonomy,
                mixed $deleted,
                array $objectIds,
            ) => $hooks()->onDeleteTerm($termId, $termTaxonomyId, $taxonomy, $deleted, $objectIds),
            10,
            5,
        );
        add_action('shutdown', static fn() => $hooks()->flush());
    }

    /**
     * Answer the search page from the index.
     */
    private function addQueryHooks(): void
    {
        add_action('pre_get_posts', static fn(WP_Query $query) => get(SearchQuery::class)->onPreGetPosts($query));
        add_filter(
            'posts_search',
            static fn(string $search, WP_Query $query): string => get(SearchQuery::class)->withoutLikeSearch(
                $search,
                $query,
            ),
            10,
            2,
        );
    }
}
