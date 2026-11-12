<?php

declare(strict_types=1);

namespace Demo\Controllers;

use Demo\Models\Project;
use Demo\Models\Testimonial;
use Studiometa\Foehn\Attributes\AsTemplateController;
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
    public function __construct(
        private ViewEngineInterface $view,
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
            ->with('series_count', Project::count()));
    }
}
