<?php

declare(strict_types=1);

use Studiometa\Foehn\Search\Config\SearchConfig;
use Studiometa\Foehn\Search\SearchIndex;

// The discovery helpers are the framework's, and private to the monorepo.
require_once dirname(__DIR__, 2) . '/foehn/tests/helpers.php';

/**
 * Skip a test that needs Loupe when PHP has no `pdo_sqlite`. CI has it; a local PHP may not.
 */
function requiresLoupe(): void
{
    if (!extension_loaded('pdo_sqlite')) {
        test()->markTestSkipped('Loupe needs the pdo_sqlite extension.');
    }
}

/**
 * A search index in a new temporary directory.
 */
function testSearchIndex(?string &$directory = null): SearchIndex
{
    $directory = sys_get_temp_dir() . '/foehn-search-' . bin2hex(random_bytes(6));

    return new SearchIndex(new SearchConfig(directory: $directory));
}

/**
 * Remove a directory and its content.
 */
function removeDirectory(?string $directory): void
{
    if ($directory === null || !is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
}

/**
 * A post, stored where `get_post()` finds it.
 *
 * @param array<string, mixed> $fields
 */
function searchPost(int $id, array $fields = []): WP_Post
{
    $post = new WP_Post();
    $post->ID = $id;
    $post->post_title = 'Post ' . $id;

    foreach ($fields as $name => $value) {
        $post->{$name} = $value;
    }

    $GLOBALS['wp_stub_posts'][$id] = $post;

    return $post;
}
