<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

/**
 * Where the page cache keeps its pages: local disk ({@see Store}) or the uploads bucket
 * ({@see BucketStore}).
 *
 * The recorder, the invalidator, the sweep, the commands and the dashboard use this
 * interface and nothing below it. The drop-in reads files, so it uses {@see Store}.
 *
 * No method throws. A write that fails is a miss on the next request, and a delete that
 * fails counts nothing. Every count is the number of stored response bodies: a headers
 * sidecar goes with its body and is not counted.
 */
interface PageStore
{
    /**
     * Where the pages are, as an operator reads it: a directory or a bucket URL.
     */
    public function root(): string;

    /**
     * Store a body. Returns false when it was not written.
     *
     * @param list<string> $headers The headers the response sent.
     */
    public function put(CacheKey $key, string $body, int $status = 200, array $headers = []): bool;

    /**
     * Delete the entries of one URL: its page, its 404, its keyed variants, its sections.
     */
    public function forget(CacheKey $key): int;

    /**
     * Delete the entries of one URL and its `page/**` subtree.
     */
    public function forgetPaginated(CacheKey $key): int;

    /**
     * Delete every entry.
     */
    public function flush(): int;

    /**
     * Delete every section-cache entry, and nothing else.
     */
    public function flushSections(): int;

    /**
     * Delete every entry older than the TTL.
     */
    public function sweep(): int;

    /**
     * What is stored right now.
     *
     * @return array{files: int, bytes: int, oldest: int|null, newest: int|null}
     */
    public function stats(): array;

    /**
     * What the section cache alone holds.
     *
     * @return array{files: int, bytes: int}
     */
    public function sectionStats(): array;
}
