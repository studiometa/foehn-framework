<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

/**
 * One URL to render into the cache before a visitor asks for it.
 *
 * The payload of a {@see Warmer} job: a warm walks the sitemap and dispatches one of
 * these per URL, so a site with two thousand pages does not try to render two thousand
 * pages inside a single WP-CLI process.
 */
final readonly class WarmUrl
{
    public function __construct(
        public string $url,
    ) {}
}
