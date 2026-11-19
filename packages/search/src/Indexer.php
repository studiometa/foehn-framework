<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search;

use WP_Post;

use function Tempest\Container\get;

/**
 * Keeps the indexes in step with the posts.
 *
 * A post goes into the index of its language when it is published, its post type has
 * an #[AsSearchDocument] class and that class gives a document, and into the index of
 * every language when the multilingual plugin does not translate its post type. In
 * every other case (draft, trash, deleted, password, another language) it leaves
 * every index.
 */
final readonly class Indexer
{
    /** The number of posts read and indexed at a time by {@see Indexer::rebuild()}. */
    private const int BATCH = 200;

    public function __construct(
        private SearchDocuments $documents,
        private SearchIndex $index,
        private Languages $languages,
    ) {}

    /**
     * The document of a post, or null when the post does not belong in the index.
     *
     * @return array<string, mixed>|null
     */
    public function document(WP_Post $post): ?array
    {
        $className = $this->documents->classFor($post->post_type);

        if ($className === null || $post->post_status !== 'publish' || $post->post_password !== '') {
            return null;
        }

        $fields = get($className)->toDocument($post);

        return $fields === null ? null : [...$fields, 'id' => $post->ID, 'post_type' => $post->post_type];
    }

    /**
     * Index a post again, in its languages, and take it out of the other indexes.
     */
    public function index(int $postId): void
    {
        $post = get_post($postId);
        $document = $post instanceof WP_Post ? $this->document($post) : null;
        $languages = $document !== null ? $this->languages->of($post) : [];

        $this->index->delete($postId, array_values(array_diff($this->languages->all(), $languages)));

        if ($document === null) {
            return;
        }

        foreach ($languages as $language) {
            $this->index->save($language, $document);
        }
    }

    /**
     * Empty every index and index every published post again.
     *
     * @return array<string, int> The number of documents of each language
     */
    public function rebuild(): array
    {
        $languages = $this->languages->all();
        $counts = array_fill_keys($languages, 0);

        foreach ($languages as $language) {
            $this->index->clear($language);
        }

        $postTypes = $this->documents->postTypes();
        $page = 1;

        while ($postTypes !== []) {
            $posts = get_posts([
                'post_type' => $postTypes,
                'post_status' => 'publish',
                'posts_per_page' => self::BATCH,
                'paged' => $page,
                'orderby' => 'ID',
                'order' => 'ASC',
                // Every language: Polylang and WPML limit a query to the current one.
                'lang' => '',
                'suppress_filters' => false,
                'no_found_rows' => true,
            ]);

            $batch = [];

            foreach ($posts as $post) {
                $document = $post instanceof WP_Post ? $this->document($post) : null;

                if ($document === null) {
                    continue;
                }

                foreach ($this->languages->of($post) as $language) {
                    $batch[$language][] = $document;
                    $counts[$language]++;
                }
            }

            foreach ($batch as $language => $documents) {
                $this->index->saveMany($language, $documents);
            }

            if (count($posts) < self::BATCH) {
                break;
            }

            $page++;
        }

        return $counts;
    }
}
