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
    public function file(CacheKey $key): ?string
    {
        return $this->directory()->resolve($key->relativePath());
    }

    /**
     * Whether a key has a stored file.
     */
    public function has(CacheKey $key): bool
    {
        $file = $this->file($key);

        return $file !== null && is_file($file);
    }

    /**
     * Store a body, atomically. Returns false when it could not be written.
     *
     * A false is never an error to show a visitor: the page has already been rendered
     * and is on its way out. It only means the next request pays for a render too.
     */
    public function put(CacheKey $key, string $body): bool
    {
        $file = $this->file($key);

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

        return $this->writeAtomically($file, $body);
    }

    /**
     * Read a stored body, or null when there is none or it has expired.
     */
    public function get(CacheKey $key): ?string
    {
        $file = $this->file($key);

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
     * `index*.html` rather than `index.html`, so the variant slot the filename reserves
     * is purged with the page it belongs to rather than left behind.
     */
    public function forget(CacheKey $key): int
    {
        $directory = $this->directory()->resolve($key->relativeDirectory());

        if ($directory === null || !is_dir($directory)) {
            return 0;
        }

        $removed = 0;

        foreach ((array) glob($directory . '/index*.html') as $file) {
            if (!is_string($file)) {
                continue;
            }

            $removed += (int) unlink($file);
        }

        $this->directory()->pruneUpwards($directory);

        return $removed;
    }

    /**
     * Delete one URL's directory and its `page/**` subtree.
     *
     * An archive's pagination is stale whenever the archive is: `/blog/page/2/` holds
     * the same posts `/blog/` does, one screen further down.
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
     * Empty the cache. Returns the number of files deleted.
     */
    public function flush(): int
    {
        $directory = $this->directory();
        $removed = 0;

        foreach ((array) glob($this->root() . '/*') as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            $removed += is_dir($entry) && !is_link($entry) ? $directory->deleteTree($entry) : (int) unlink($entry);
        }

        return $removed;
    }

    /**
     * Delete every file older than the TTL, and prune the directories left empty.
     *
     * nginx's `try_files` cannot check a file's age, and neither can `mod_rewrite`, so
     * this is not an optimisation: with a `ttl` set, the sweep interval is the real
     * bound on how stale a served page can be.
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

            $removed += (int) unlink($entry->getPathname());
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

            $files++;
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
