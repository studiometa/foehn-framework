<?php

declare(strict_types=1);

namespace Demo\Controllers;

use Demo\Models\Project;
use Demo\Models\Testimonial;
use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;
use Timber\Timber;

/**
 * The homepage: an index of the work, not a feed of posts.
 *
 * Declared ahead of ArchiveController's `home`, which WordPress otherwise answers
 * with the blog listing.
 */
#[AsTemplateController(['front-page', 'home'])]
final readonly class FrontPageController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): string
    {
        /** @var list<Project> $projects */
        $projects = Timber::get_posts([
            'post_type' => 'project',
            'posts_per_page' => 6,
            'orderby' => 'menu_order date',
            'order' => 'ASC',
        ])->to_array();

        /** @var list<Testimonial> $testimonials */
        $testimonials = Timber::get_posts([
            'post_type' => 'testimonial',
            'posts_per_page' => 3,
        ])->to_array();

        return $this->view->render('pages/front-page', $context->with('projects', $projects)->with(
            'testimonials',
            $testimonials,
        ));
    }
}
