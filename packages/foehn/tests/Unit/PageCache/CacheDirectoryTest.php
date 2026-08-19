<?php

declare(strict_types=1);

use Studiometa\Foehn\PageCache\CacheDirectory;

/**
 * Containment is the check with the security consequence, so it gets its own tests
 * rather than being covered incidentally by the store's.
 */

describe('CacheDirectory', function () {
    beforeEach(function () {
        $this->root = pageCacheRoot();
        mkdir($this->root, 0o777, true);
        $this->directory = new CacheDirectory($this->root);
    });

    afterEach(function () {
        removeTestDirectory($this->root);
    });

    it('counts the cache root itself as inside', function () {
        expect($this->directory->resolve(''))->toBe($this->root);
    });

    it('strips a trailing slash from what it resolves', function () {
        expect($this->directory->resolve('example.com/blog/'))->toBe($this->root . '/example.com/blog');
    });

    it('resolves a directory that does not exist yet', function () {
        // Which is the state of every path in a cache nothing has written to.
        expect($this->directory->resolve('example.com/blog/index.html'))
            ->toBe($this->root . '/example.com/blog/index.html');
    });

    it('collapses a doubled slash rather than writing an empty segment', function () {
        expect($this->directory->resolve('example.com//blog/index.html'))
            ->toBe($this->root . '/example.com/blog/index.html');
    });

    it('refuses a path that climbs out of the root', function (string $relative) {
        expect($this->directory->resolve($relative))->toBeNull();
    })->with([
        ['../evil/index.html'],
        ['example.com/../../evil/index.html'],
        ['..'],
        ['example.com/..'],
    ]);

    it('refuses a sibling directory that merely shares a prefix', function () {
        // `/tmp/cache-evil` starts with `/tmp/cache`, and a `str_starts_with` without
        // the separator would have called it contained.
        $sibling = new CacheDirectory($this->root . '-evil');

        expect($sibling->resolve('index.html'))->toBe($this->root . '-evil/index.html');
        expect($this->directory->resolve('../' . basename($this->root) . '-evil/index.html'))->toBeNull();
    });

    it('refuses a path that leaves the root through a symlink', function () {
        $outside = pageCacheRoot();
        mkdir($outside, 0o777, true);
        symlink($outside, $this->root . '/escape');

        try {
            expect($this->directory->resolve('escape/index.html'))->toBeNull();
        } finally {
            unlink($this->root . '/escape');
            removeTestDirectory($outside);
        }
    });

    it('has nothing to walk in a directory that is not there', function () {
        expect(iterator_to_array($this->directory->walk($this->root . '/missing')))->toBe([]);
    });

    it('reports an empty directory, and a missing one as not empty', function () {
        mkdir($this->root . '/empty');

        expect($this->directory->isEmpty($this->root . '/empty'))->toBeTrue();
        expect($this->directory->isEmpty($this->root . '/missing'))->toBeFalse();
    });

    it('deletes a tree and everything below it', function () {
        mkdir($this->root . '/a/b/c', 0o777, true);
        file_put_contents($this->root . '/a/b/c/index.html', 'x');
        file_put_contents($this->root . '/a/b/index.html', 'x');

        expect($this->directory->deleteTree($this->root . '/a'))->toBe(2);
        expect($this->root . '/a')->not->toBeDirectory();
    });

    it('refuses to delete a tree outside its root', function () {
        $outside = pageCacheRoot();
        mkdir($outside, 0o777, true);
        file_put_contents($outside . '/keep.html', 'x');

        try {
            expect($this->directory->deleteTree($outside))->toBe(0);
            expect($outside . '/keep.html')->toBeFile();
        } finally {
            removeTestDirectory($outside);
        }
    });

    it('prunes upwards only as far as the root', function () {
        mkdir($this->root . '/a/b/c', 0o777, true);

        $this->directory->pruneUpwards($this->root . '/a/b/c');

        expect($this->root . '/a')->not->toBeDirectory();
        expect($this->root)->toBeDirectory();
    });

    it('stops pruning at the first directory that still holds something', function () {
        mkdir($this->root . '/a/b/c', 0o777, true);
        file_put_contents($this->root . '/a/index.html', 'x');

        $this->directory->pruneUpwards($this->root . '/a/b/c');

        expect($this->root . '/a/b')->not->toBeDirectory();
        expect($this->root . '/a')->toBeDirectory();
    });
});
