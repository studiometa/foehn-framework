<?php

declare(strict_types=1);

namespace Demo\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController(['archive', 'archive-*', 'category', 'tag', 'tax-*'])]
final readonly class ArchiveController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): string
    {
        if ($context->posts && method_exists($context->posts, 'pagination')) {
            $context = $context->with('pagination', $context->posts->pagination([
                'mid_size' => 2,
                'end_size' => 1,
            ]));
        }

        $context = $context->with('archive_title', get_the_archive_title())->with(
            'archive_description',
            get_the_archive_description(),
        );

        // renderFirst rather than render: a post type archive with no template of
        // its own falls back to the generic one instead of throwing.
        $templates = match (true) {
            is_post_type_archive() => ['pages/archive-' . get_query_var('post_type'), 'pages/archive'],
            is_category() => ['pages/category', 'pages/archive'],
            is_tag() => ['pages/tag', 'pages/archive'],
            is_tax() => ['pages/taxonomy-' . get_queried_object()?->taxonomy, 'pages/archive'],
            default => ['pages/archive'],
        };

        return $this->view->renderFirst($templates, $context);
    }
}
