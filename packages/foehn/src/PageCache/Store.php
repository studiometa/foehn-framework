<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * What the page cache keeps, and the only code allowed to write it.
 *
 * Every write is atomic — a sibling temporary file, then `rename()` — because the
 * readers are three other processes and one of them is nginx, which will happily serve
 * a half-written page to a visitor if given the chance. `rename()` within one
 * filesystem is the single operation that makes a reader see either the old file or the
 * new one, and never the middle of either.
 *
 * Which paths may be touched at all is {@see CacheDirectory}'s question.
 */
final readonly class Store
{
    public function __construct(
        private PageCacheConfig $config,
    ) {}

    /**
     * The cache root.
     */
    public function root(): string
    {
        return $this->config->getPath();
    }

    /**
     * The absolute file a key maps to, or null when it would leave the cache root.
     */
    public function file(CacheKey $key, int $status = 200): ?string
    {
        return $this->directory()->resolve($key->relativePath($status));
    }

    /**
     * The absolute file holding this entry's recorded headers, if it may be written.
     */
    public function headersFile(CacheKey $key, int $status = 200): ?string
    {
        return $this->directory()->resolve($key->headersRelativePath($status));
    }

    /**
     * Whether a key has a stored file.
     */
    public function has(CacheKey $key, int $status = 200): bool
    {
        $file = $this->file($key, $status);

        return $file !== null && is_file($file);
    }

    /**
     * Store a body, atomically. Returns false when it could not be written.
     *
     * A false is never an error to show a visitor: the page has already been rendered
     * and is on its way out. It only means the next request pays for a render too.
     */
    public function put(CacheKey $key, string $body, int $status = 200, array $headers = []): bool
    {
        $file = $this->file($key, $status);

        if ($file === null || !CacheKey::isWritableFilename(basename($file))) {
            return false;
        }

        $parent = dirname($file);

        if (!is_dir($parent) && !mkdir($parent, 0o755, true) && !is_dir($parent)) {
            return false;
        }

        // Re-checked now that the directory exists and realpath() can resolve it: a
        // symlink inside the cache tree would otherwise write outside it.
        if ($this->file($key) === null) {
            return false;
        }

        if (!$this->writeAtomically($file, $body)) {
            return false;
        }

        // The headers are written after the body and never instead of it: a hit with the
        // headers missing is the response this cache has always sent, while a headers
        // file with no body is an entry nothing will ever read.
        $kept = StoredHeaders::keep($headers);
        $headersFile = $this->headersFile($key, $status);

        if ($headersFile === null) {
            return true;
        }

        if ($kept === []) {
            // A page that recorded nothing worth replaying must not keep a stale file
            // from the last time it did.
            if (is_file($headersFile)) {
                unlink($headersFile);
            }

            return true;
        }

        $this->writeAtomically($headersFile, StoredHeaders::encode($kept));

        return true;
    }

    /**
     * The headers recorded with a stored entry, or none.
     *
     * @return list<string>
     */
    public function headers(CacheKey $key, int $status = 200): array
    {
        $file = $this->headersFile($key, $status);

        if ($file === null || !is_file($file)) {
            return [];
        }

        $contents = file_get_contents($file);

        return $contents === false ? [] : StoredHeaders::decode($contents);
    }

    /**
     * Read a stored body, or null when there is none or it has expired.
     */
    public function get(CacheKey $key, int $status = 200): ?string
    {
        $file = $this->file($key, $status);

        if ($file === null || !is_file($file) || $this->isExpired($file)) {
            return null;
        }

        $body = file_get_contents($file);

        return $body === false ? null : $body;
    }

    /**
     * Whether a stored file has outlived the configured TTL.
     *
     * A `ttl` of 0 means "until something purges it", which is the honest default for a
     * cache whose invalidation is event-driven.
     */
    public function isExpired(string $file): bool
    {
        if ($this->config->ttl <= 0) {
            return false;
        }

        $modified = filemtime($file);

        return $modified === false || ($modified + $this->config->ttl) < time();
    }

    /**
     * Delete the entries of one URL's directory.
     *
     * `index*.html` rather than `index.html`, so every variant slot the filename
     * reserves — the keyed query args, the 404, the section fragments — is purged with
     * the page it belongs to rather than left behind. That glob is the invariant this
     * method is really about, and it has a regression test of its own.
     *
     * @return int Stored response bodies removed.
     */
    public function forget(CacheKey $key): int
    {
        $directory = $this->directory()->resolve($key->relativeDirectory());

        if ($directory === null || !is_dir($directory)) {
            return 0;
        }

        $removed = 0;

        foreach ((array) glob($directory . '/index*.html{,' . CacheKey::HEADERS_SUFFIX . '}', GLOB_BRACE) as $file) {
            if (!is_string($file)) {
                continue;
            }

            $body = CacheKey::isWritableFilename(basename($file));

            if (unlink($file) && $body) {
                $removed++;
            }
        }

        $this->directory()->pruneUpwards($directory);

        return $removed;
    }

    /**
     * Delete one URL's directory and its `page/**` subtree.
     *
     * An archive's pagination is stale whenever the archive is: `/blog/page/2/` holds
     * the same posts `/blog/` does, one screen further down.
     *
     * @return int Stored response bodies removed.
     */
    public function forgetPaginated(CacheKey $key): int
    {
        $removed = $this->forget($key);
        $pages = $this->directory()->resolve($key->relativeDirectory() . '/page');

        if ($pages === null || !is_dir($pages)) {
            return $removed;
        }

        $removed += $this->directory()->deleteTree($pages);

        // `forget()` pruned as far as the `page/` directory, which still existed at
        // that point. The archive's own directory is only empty now.
        $this->directory()->pruneUpwards(dirname($pages));

        return $removed;
    }

    /**
     * Empty the cache.
     *
     * @return int Stored response bodies removed.
     */
    public function flush(): int
    {
        $directory = $this->directory();
        $removed = 0;

        foreach ((array) glob($this->root() . '/*') as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            if (is_dir($entry) && !is_link($entry)) {
                $removed += $directory->deleteTree($entry);

                continue;
            }

            $body = CacheKey::isWritableFilename(basename($entry));

            if (unlink($entry) && $body) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Delete every section-cache entry, and nothing else.
     *
     * A section entry is a stored variant whose canonical query carries
     * `foehn_sections` — the fragments a filtered archive fetches, which go stale for
     * their own reasons and are the cheapest thing to rebuild. Whole pages and the other
     * keyed variants that share the directory are left where they are; that selectivity
     * is the entire point of the operation, so it has a test per neighbour it must not
     * touch.
     *
     * Runs whether or not caching is currently enabled. A release that had it on can
     * have left files behind, and an operator turning it off is exactly the person who
     * wants them gone.
     *
     * @return int Stored response bodies removed.
     */
    public function flushSections(): int
    {
        $directory = $this->directory();
        $removed = 0;
        $touched = [];

        foreach ($directory->walk($this->root()) as $entry) {
            if ($entry->isDir()) {
                continue;
            }

            if (!CacheKey::isSectionEntry($entry->getFilename())) {
                continue;
            }

            $body = CacheKey::isWritableFilename($entry->getFilename());
            $parent = $entry->getPath();

            if (!unlink($entry->getPathname())) {
                continue;
            }

            $touched[$parent] = true;

            if ($body) {
                $removed++;
            }
        }

        // Pruned after the walk rather than inside it: the iterator is reading the
        // directory this would remove, and CHILD_FIRST does not help — a section entry
        // sits beside the page it belongs to, not below it.
        foreach (array_keys($touched) as $parent) {
            $directory->pruneUpwards($parent);
        }

        return $removed;
    }

    /**
     * Delete every file older than the TTL, and prune the directories left empty.
     *
     * nginx's `try_files` cannot check a file's age, and neither can `mod_rewrite`, so
     * this is not an optimisation: with a `ttl` set, the sweep interval is the real
     * bound on how stale a served page can be.
     *
     * @return int Stored response bodies removed.
     */
    public function sweep(): int
    {
        if ($this->config->ttl <= 0) {
            return 0;
        }

        $directory = $this->directory();
        $removed = 0;
        $emptied = [];

        foreach ($directory->walk($this->root()) as $entry) {
            if ($entry->isDir()) {
                // CHILD_FIRST, so the deepest directory is seen first — which is the
                // order an emptied tree has to collapse in.
                $emptied[] = $entry->getPathname();

                continue;
            }

            if (!$this->isExpired($entry->getPathname())) {
                continue;
            }

            $body = CacheKey::isWritableFilename($entry->getFilename());

            if (unlink($entry->getPathname()) && $body) {
                $removed++;
            }
        }

        foreach ($emptied as $path) {
            if (!$directory->isEmpty($path)) {
                continue;
            }

            rmdir($path);
        }

        return $removed;
    }

    /**
     * What is in the cache right now.
     *
     * @return array{files: int, bytes: int, oldest: int|null, newest: int|null}
     */
    public function stats(): array
    {
        $files = 0;
        $bytes = 0;
        $oldest = null;
        $newest = null;

        foreach ($this->directory()->walk($this->root()) as $entry) {
            if ($entry->isDir()) {
                continue;
            }

            // Bodies, not files: a stored entry may have a `.headers` sibling, and
            // `cache:status` reporting twice as many pages as there are would be a
            // number nobody could act on. The bytes are every file, because that is
            // what the disk holds.
            if (str_ends_with($entry->getFilename(), '.html')) {
                $files++;
            }

            $bytes += (int) $entry->getSize();
            $modified = (int) $entry->getMTime();
            $oldest = $oldest === null ? $modified : min($oldest, $modified);
            $newest = $newest === null ? $modified : max($newest, $modified);
        }

        return ['files' => $files, 'bytes' => $bytes, 'oldest' => $oldest, 'newest' => $newest];
    }

    private function directory(): CacheDirectory
    {
        return new CacheDirectory($this->root());
    }

    /**
     * Write through a sibling temporary file, so no reader ever sees a partial page.
     */
    private function writeAtomically(string $file, string $body): bool
    {
        $temporary = $file . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($temporary, $body) === false) {
            return false;
        }

        chmod($temporary, 0o644);

        if (rename($temporary, $file)) {
            return true;
        }

        unlink($temporary);

        return false;
    }
}
