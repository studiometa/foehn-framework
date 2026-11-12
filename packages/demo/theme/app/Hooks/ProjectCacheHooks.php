<?php

declare(strict_types=1);

namespace Demo\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Contracts\CacheInterface;

/**
 * Forget what is cached about the projects when one changes.
 *
 * Every value cached under the `projects` tag goes at once, whatever its key: the
 * code that caches a value and the code that forgets it share a tag, not a list.
 */
final readonly class ProjectCacheHooks
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    #[AsAction('save_post_project')]
    public function flush(): void
    {
        $this->cache->flushTag('projects');
    }
}
