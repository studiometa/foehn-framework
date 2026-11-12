<?php

declare(strict_types=1);

namespace Demo\Jobs;

use Demo\Models\Photograph;
use Demo\Models\Project;
use Studiometa\Foehn\Attributes\AsJob;
use Studiometa\Foehn\Contracts\ImageTransformer;
use Timber\Timber;

/**
 * Request each plate of a project once, so the transformer caches it.
 *
 * Glide cuts a plate on its first request, about 350 ms, and nginx serves it from the
 * bucket after that, about 25 ms. Without this job, the first visitor of a new series
 * pays for each of its plates. Demo\Hooks\ProjectCacheHooks dispatches it when a
 * project is saved, and Demo\Jobs\WarmAllPlates once a day for every project.
 *
 * A job and not a request made on save: the editor who saves the project does not
 * wait for twenty transforms.
 */
#[AsJob(group: 'demo')]
final readonly class WarmPlatesHandler
{
    public function __construct(
        private ImageTransformer $transformer,
    ) {}

    public function __invoke(WarmPlates $job): void
    {
        $project = Timber::get_post($job->projectId);

        if (!$project instanceof Project || $project->post_status !== 'publish') {
            return;
        }

        foreach ($project->photographs() as $photograph) {
            foreach (Photograph::PLATE_WIDTHS as $width) {
                wp_remote_get($this->transformer->url($photograph->src(), $photograph->plate($width)), [
                    'timeout' => 30,
                ]);
            }
        }
    }
}
