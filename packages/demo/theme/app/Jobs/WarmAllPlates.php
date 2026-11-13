<?php

declare(strict_types=1);

namespace Demo\Jobs;

use Demo\Models\Project;
use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Jobs\CronInterval;

use function Studiometa\Foehn\dispatch;

/**
 * Once a day, queue the plates of every project again.
 *
 * ImageCacheHooks forgets the transforms of a photograph that changes, and a save
 * only queues the project that was saved. This catches the rest: a photograph cropped
 * in the media library, a bucket emptied by hand. A plate already cut costs one
 * request that nginx answers from the bucket.
 *
 * An interval, because the time of day does not matter: the first run is at the first
 * request after a deploy, and every 24 hours after it.
 */
#[AsCron(CronInterval::Daily, group: 'demo')]
final class WarmAllPlates
{
    public function __invoke(): void
    {
        foreach (Project::all() as $project) {
            dispatch(new WarmPlates($project->ID));
        }
    }
}
