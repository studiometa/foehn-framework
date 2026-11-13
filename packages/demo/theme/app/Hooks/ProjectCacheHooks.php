<?php

declare(strict_types=1);

namespace Demo\Hooks;

use Demo\Jobs\WarmPlates;
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Contracts\CacheInterface;
use WP_Post;

use function Studiometa\Foehn\dispatch;

/**
 * Keep what is cached about the projects up to date when one changes.
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

    /**
     * Queue the plates of a published project, so its first visitor does not wait for them.
     *
     * `dispatch()` stores the job in Action Scheduler and returns: the real cron runs it
     * on its next pass. See Demo\Jobs\WarmPlatesHandler.
     */
    #[AsAction('save_post_project', acceptedArgs: 2)]
    public function warmPlates(int $postId, WP_Post $post): void
    {
        if ($post->post_status !== 'publish') {
            return;
        }

        dispatch(new WarmPlates($postId));
    }
}
