<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Where a page cache is allowed to touch the filesystem, and how it lets go of a tree.
 *
 * Separate from {@see Store} because these are two different questions. Store answers
 * "what does a page cache keep"; this answers "which absolute paths may it write, and
 * what is left behind when it deletes one". The second question is the one with the
 * security consequence, so it gets its own small surface and its own tests.
 */
final readonly class CacheDirectory
{
    public string $root;

    public function __construct(string $root)
    {
        // Without its trailing slash, so that `$root . '/'` is the one separator every
        // containment check compares against.
        $this->root = rtrim($root, '/');
    }

    /**
     * An absolute path, if and only if it sits inside the root.
     *
     * Null rather than an exception: a path that fails this check is a request to
     * bypass the cache, and there is nobody to report an exception to halfway through
     * rendering a page.
     */
    public function resolve(string $relative): ?string
    {
        $path = rtrim((string) preg_replace('#/+#', '/', $this->root . '/' . $relative), '/');

        // Lexically first, because the file may not exist yet and realpath() cannot
        // resolve what has not been written.
        //
        // The separator in `$root . '/'` is load-bearing: without it a sibling
        // directory named `…-evil` would read as contained by `…`.
        if ($path !== $this->root && !str_starts_with($path, $this->root . '/')) {
            return null;
        }

        if (str_contains($path, '/../') || str_ends_with($path, '/..')) {
            return null;
        }

        // The root is inside itself, and has no parent inside the root to check.
        if ($path === $this->root) {
            return $path;
        }

        $parent = realpath(dirname($path));
        $root = realpath($this->root);

        // Neither resolving is the state of a cache nothing has written to yet.
        if ($parent === false || $root === false) {
            return $path;
        }

        // A symlink dropped into the tree is a path that looks contained and is not.
        // realpath() is the only thing that can tell.
        if ($parent !== $root && !str_starts_with($parent, $root . '/')) {
            return null;
        }

        return $path;
    }

    /**
     * Delete a directory and everything under it. Returns the number of files removed.
     */
    public function deleteTree(string $directory): int
    {
        if ($this->resolveAbsolute($directory) === null) {
            return 0;
        }

        $removed = 0;

        foreach ($this->walk($directory) as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());

                continue;
            }

            $removed += (int) unlink($entry->getPathname());
        }

        rmdir($directory);

        return $removed;
    }

    /**
     * Remove the directories a delete has just emptied, up to the root.
     *
     * Without this a purged site keeps the shape of every URL it ever cached, which is
     * a directory tree nobody asked for and a `find` nobody enjoys.
     */
    public function pruneUpwards(string $directory): void
    {
        $root = realpath($this->root);

        while ($root !== false && $directory !== $root && str_starts_with($directory, $root . '/')) {
            if (!$this->isEmpty($directory)) {
                return;
            }

            rmdir($directory);
            $directory = dirname($directory);
        }
    }

    public function isEmpty(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        return !new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS)->valid();
    }

    /**
     * Every entry under a directory, deepest first — the order a tree collapses in.
     *
     * A generator, so an entry is read before the caller deletes it rather than a
     * whole tree being listed and then walked over holes.
     *
     * @return iterable<SplFileInfo>
     */
    public function walk(string $directory): iterable
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            yield $entry;
        }
    }

    /**
     * The containment check for a path that is already absolute.
     */
    private function resolveAbsolute(string $path): ?string
    {
        if ($path !== $this->root && !str_starts_with($path, $this->root . '/')) {
            return null;
        }

        return $this->resolve(substr($path, strlen($this->root) + 1));
    }
}
