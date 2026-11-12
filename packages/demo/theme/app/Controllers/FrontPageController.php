<?php

declare(strict_types=1);

namespace Demo\Controllers;

use Demo\Models\Project;
use Demo\Models\Testimonial;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\CacheInterface;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

/**
 * The homepage: an index of the work, not a feed of posts.
 *
 * `front-page` only. The posts live on the Journal page, which is the posts page in
 * Settings > Reading, so `home` is that listing and ArchiveController renders it.
 */
#[AsTemplateController('front-page')]
final readonly class FrontPageController implements TemplateControllerInterface
{
    /** The cache key of the portfolio counts, flushed with the `projects` tag. */
    public const string STATS_KEY = 'portfolio-stats';

    public function __construct(
        private ViewEngineInterface $view,
        private CacheInterface $cache,
    ) {}

    public function handle(TemplateContext $context): string
    {
        // The model's query builder, not raw WP_Query arguments: the post type comes
        // from the model, and the result is a list of Project.
        $projects = Project::query()->orderBy('menu_order', 'ASC')->limit(6)->get();
        $testimonials = Testimonial::query()->limit(3)->get();

        return $this->view->render('pages/front-page', $context
            ->with('projects', $projects)
            ->with('testimonials', $testimonials)
            ->with('stats', $this->stats()));
    }

    /**
     * How many series and photographs the portfolio holds.
     *
     * Counting the photographs reads every gallery, so the answer is cached for a day
     * under the `projects` tag. ProjectCacheHooks flushes the tag when a project is
     * saved, so the count is never a day late.
     *
     * @return array{series: int, photographs: int}
     */
    private function stats(): array
    {
        return $this->cache->tags(['projects'])->remember(self::STATS_KEY, DAY_IN_SECONDS, static fn(): array => [
            'series' => Project::count(),
            'photographs' => array_sum(array_map(
                static fn(Project $project): int => $project->photographCount(),
                Project::all(),
            )),
        ]);
    }
}
