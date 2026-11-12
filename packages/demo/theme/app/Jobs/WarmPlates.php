<?php

declare(strict_types=1);

namespace Demo\Jobs;

/**
 * Cut the plates of one project before a visitor asks for them.
 *
 * The payload of a job: Demo\Jobs\WarmPlatesHandler does the work. `dispatch()` stores
 * it in Action Scheduler, so it holds the post ID and not the post.
 */
final readonly class WarmPlates
{
    public function __construct(
        public int $projectId,
    ) {}
}
