<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

/**
 * Where the page cache keeps the pages it stores.
 */
enum PageCacheStorage: string
{
    /**
     * Files under `PageCacheConfig::$path`, served by nginx, Apache or the drop-in.
     */
    case Disk = 'disk';

    /**
     * Objects under `cache/pages/` in the `humanmade/s3-uploads` bucket, served by nginx.
     *
     * For a site with more than one instance: every instance reads and purges one cache.
     * A hit is slower than from disk, the drop-in does not read, and Apache is not
     * supported.
     */
    case Bucket = 'bucket';
}
