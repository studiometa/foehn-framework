<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search;

use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\SearchParameters;
use Studiometa\Foehn\Search\Config\SearchConfig;
use Tempest\Container\Singleton;

/**
 * The Loupe indexes, one for each language.
 *
 * A short query has too few words for Loupe to detect its language, and a query
 * stemmed in the wrong language loses results. So each language has its own index,
 * set to that language only.
 */
#[Singleton]
final class SearchIndex
{
    /** The largest page Loupe gives. */
    private const int PAGE_SIZE = SearchParameters::MAX_LIMIT;

    /** @var array<string, Loupe> */
    private array $indexes = [];

    public function __construct(
        private readonly SearchConfig $config,
    ) {}

    /**
     * Add a document to the index of a language, or replace it.
     *
     * @param array<string, mixed> $document A document with an `id`
     */
    public function save(string $language, array $document): void
    {
        $this->index($language)->addDocument($document);
    }

    /**
     * Add many documents to the index of a language at once.
     *
     * @param list<array<string, mixed>> $documents
     */
    public function saveMany(string $language, array $documents): void
    {
        if ($documents !== []) {
            $this->index($language)->addDocuments($documents);
        }
    }

    /**
     * Remove a post from the indexes of these languages.
     *
     * @param list<string> $languages
     */
    public function delete(int $id, array $languages): void
    {
        foreach ($languages as $language) {
            $this->index($language)->deleteDocument($id);
        }
    }

    /**
     * Empty the index of a language.
     */
    public function clear(string $language): void
    {
        $this->index($language)->deleteAllDocuments();
    }

    public function count(string $language): int
    {
        return $this->index($language)->countDocuments();
    }

    /**
     * The ids of every post that matches a query, the most relevant first.
     *
     * Every page of the result, not the first one: WordPress pages and filters the ids
     * itself. An empty query matches nothing.
     *
     * @return list<int>
     */
    public function ids(string $language, string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $ids = [];
        $page = 1;

        do {
            $result = $this->index($language)->search(
                SearchParameters::create()
                    ->withQuery($query)
                    ->withAttributesToRetrieve(['id'])
                    ->withHitsPerPage(self::PAGE_SIZE)
                    ->withPage($page),
            );

            foreach ($result->getHits() as $hit) {
                $ids[] = (int) $hit['id'];
            }

            $page++;
        } while ($page <= $result->getTotalPages());

        return $ids;
    }

    /**
     * The best matches of a query, for suggestions while a visitor types.
     *
     * @return list<array{id: int, title: string, post_type: string}>
     */
    public function suggest(string $language, string $query, int $limit): array
    {
        $query = trim($query);

        if ($query === '' || $limit < 1) {
            return [];
        }

        $result = $this->index($language)->search(
            SearchParameters::create()
                ->withQuery($query)
                ->withAttributesToRetrieve(['id', 'title', 'post_type'])
                ->withLimit(min($limit, self::PAGE_SIZE)),
        );

        return array_map(static fn(array $hit): array => [
            'id' => (int) $hit['id'],
            'title' => (string) ($hit['title'] ?? ''),
            'post_type' => (string) ($hit['post_type'] ?? ''),
        ], array_values($result->getHits()));
    }

    /**
     * The Loupe index of a language, created on first use.
     */
    private function index(string $language): Loupe
    {
        return $this->indexes[$language] ??= new LoupeFactory()->create(
            $this->config->directory() . '/' . $this->directoryName($language),
            Configuration::create()
                ->withPrimaryKey('id')
                ->withSearchableAttributes($this->config->searchableAttributes)
                ->withFilterableAttributes(['post_type'])
                ->withLanguages([Languages::isoCode($language)])
                ->withMaxTotalHits($this->config->maxResults),
        );
    }

    /**
     * A language code as a directory name: nothing that leaves the directory.
     */
    private function directoryName(string $language): string
    {
        $name = (string) preg_replace('/[^a-z0-9_-]/i', '', $language);

        return $name === '' ? 'default' : $name;
    }
}
