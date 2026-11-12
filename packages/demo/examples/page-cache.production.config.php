<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Config\PageCacheStorage;

/**
 * The page cache in the uploads bucket. An example: the demo does not use it.
 *
 * Copy it to `theme/app/page-cache.production.config.php` to turn it on in production.
 * It replaces `page-cache.config.php` there, so it starts from that file and changes
 * the storage only.
 *
 * Use bucket storage when the site runs on more than one instance: a purge on one
 * instance then reaches the pages that every instance serves. The demo is one machine,
 * where pages on disk are faster, so it stays on disk. What changes, and what a deploy
 * must then do (`wp foehn cache:clear`), is in docs/guide/page-cache.md.
 *
 * tests/smoke/page-cache-bucket.sh copies this file into the theme for the `local`
 * environment, so the example is the configuration that the test runs.
 */

/** @var PageCacheConfig $shared */
$shared = require __DIR__ . '/page-cache.config.php';

return new PageCacheConfig(
    enabled: $shared->enabled,
    storage: PageCacheStorage::Bucket,
    ttl: $shared->ttl,
    cacheControl: $shared->cacheControl,
    responseHeaders: $shared->responseHeaders,
    environments: ['production'],
    cacheQueryArgs: $shared->cacheQueryArgs,
    warmAfterPurge: $shared->warmAfterPurge,
    warmExclude: $shared->warmExclude,
);
