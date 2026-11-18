<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Config;

/**
 * Configuration of the search.
 *
 * ```php
 * // app/search.config.php
 * use Studiometa\Foehn\Search\Config\SearchConfig;
 *
 * return new SearchConfig(
 *     searchableAttributes: ['title', 'terms', 'excerpt', 'content', 'reference'],
 * );
 * ```
 */
final readonly class SearchConfig
{
    /**
     * @param string|null $directory Where the indexes are stored, one directory for each language. A directory
     *                               the web server does not serve. Null is `storage/search` in the project root,
     *                               beside the web root.
     * @param list<string> $searchableAttributes The document fields the search reads, the most important first:
     *                                           a match in an earlier field ranks higher.
     * @param int $maxResults The most results one search gives. Loupe is made for fewer than about 50,000
     *                        documents, so the default does not cut any result of an index Loupe is made for.
     * @param int $suggestions The number of suggestions the REST endpoint gives.
     * @param int $suggestionsMaxAge The `max-age` of the `Cache-Control` header of the suggestions, in seconds.
     */
    public function __construct(
        public ?string $directory = null,
        public array $searchableAttributes = ['title', 'terms', 'excerpt', 'content'],
        public int $maxResults = 50_000,
        public int $suggestions = 5,
        public int $suggestionsMaxAge = 300,
    ) {}

    /**
     * The directory of the indexes.
     *
     * With the Bedrock layout of the starter, `WP_CONTENT_DIR` is `<project>/web/wp-content`,
     * so the default is `<project>/storage/search`, outside the web root.
     */
    public function directory(): string
    {
        if ($this->directory !== null) {
            return rtrim($this->directory, '/');
        }

        $content = defined('WP_CONTENT_DIR')
            ? (string) constant('WP_CONTENT_DIR')
            : sys_get_temp_dir() . '/web/wp-content';

        return dirname($content, 2) . '/storage/search';
    }
}
