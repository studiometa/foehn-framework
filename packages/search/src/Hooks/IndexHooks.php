<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Hooks;

use Studiometa\Foehn\Contracts\JobDispatcher;
use Studiometa\Foehn\Search\Jobs\IndexPosts;
use Studiometa\Foehn\Search\Jobs\IndexPostsHandler;
use Studiometa\Foehn\Search\SearchDocuments;
use Tempest\Container\Singleton;
use WP_Post;

use function Tempest\Container\get;

/**
 * Index a post again when it, or one of its terms, changes.
 *
 * {@see \Studiometa\Foehn\Search\Discovery\SearchDocumentDiscovery} adds these methods
 * to their WordPress hooks: `save_post`, `deleted_post`, `set_object_terms`,
 * `edited_term`, `delete_term` and `shutdown`.
 *
 * One save fires several of these hooks (the terms are set before `save_post`), so the
 * ids are collected during the request and indexed once, at `shutdown`, when the post
 * and its terms and fields are all saved. Through an #[AsJob] job when Action Scheduler
 * is there, in the request when it is not.
 *
 * A singleton, so every hook of the request adds to the same list.
 */
#[Singleton]
final class IndexHooks
{
    /** @var array<int, true> */
    private array $pending = [];

    public function __construct(
        private readonly SearchDocuments $documents,
        private readonly JobDispatcher $dispatcher,
    ) {}

    /**
     * A save, a change of status (trash and restore included) or a publication.
     */
    public function onSavePost(int $postId, WP_Post $post): void
    {
        if ($post->post_type === 'revision' || defined('DOING_AUTOSAVE') && constant('DOING_AUTOSAVE')) {
            return;
        }

        $this->queuePost($post);
    }

    public function onDeletedPost(int $postId, mixed $post = null): void
    {
        if ($post instanceof WP_Post) {
            $this->queuePost($post);
        }
    }

    /**
     * Terms added to or removed from a post.
     */
    public function onSetObjectTerms(int $objectId): void
    {
        $post = get_post($objectId);

        if ($post instanceof WP_Post) {
            $this->queuePost($post);
        }
    }

    /**
     * A renamed term: the documents of its posts carry its name.
     */
    public function onEditedTerm(int $termId, int $termTaxonomyId, string $taxonomy): void
    {
        $ids = get_objects_in_term($termId, $taxonomy);

        if (is_array($ids)) {
            $this->queue(array_map(intval(...), $ids));
        }
    }

    /**
     * A deleted term, with the posts it had.
     *
     * @param list<int|string> $objectIds
     */
    public function onDeleteTerm(
        int $termId,
        int $termTaxonomyId,
        string $taxonomy,
        mixed $deleted,
        array $objectIds,
    ): void {
        $this->queue(array_map(intval(...), $objectIds));
    }

    /**
     * Index the posts this request changed.
     */
    public function flush(): void
    {
        if ($this->pending === []) {
            return;
        }

        $job = new IndexPosts(array_keys($this->pending));
        $this->pending = [];

        if ($this->dispatcher->isAvailable()) {
            $this->dispatcher->dispatch($job);

            return;
        }

        get(IndexPostsHandler::class)($job);
    }

    /**
     * The ids waiting for {@see IndexHooks::flush()}.
     *
     * @return list<int>
     */
    public function pending(): array
    {
        return array_keys($this->pending);
    }

    private function queuePost(WP_Post $post): void
    {
        if ($this->documents->has($post->post_type)) {
            $this->pending[$post->ID] = true;
        }
    }

    /**
     * @param list<int> $ids
     */
    private function queue(array $ids): void
    {
        if ($this->documents->postTypes() === []) {
            return;
        }

        foreach ($ids as $id) {
            if ($id < 1) {
                continue;
            }

            $this->pending[$id] = true;
        }
    }
}
