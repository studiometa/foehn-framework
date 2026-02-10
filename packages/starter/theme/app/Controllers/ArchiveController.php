<?php

declare(strict_types=1);

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Timber\Timber;

#[AsTemplateController(['archive', 'archive-*', 'front-page', 'home', 'category', 'tag', 'tax-*'])]
final readonly class ArchiveController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(): string
    {
        $context = Timber::context();

        if (isset($context['posts']) && method_exists($context['posts'], 'pagination')) {
            $context['pagination'] = $context['posts']->pagination([
                'mid_size' => 2,
                'end_size' => 1,
            ]);
        }

        $context['archive_title'] = get_the_archive_title();
        $context['archive_description'] = get_the_archive_description();

        $templates = [];

        if (is_post_type_archive()) {
            $postType = get_query_var('post_type');
            $templates[] = "pages/archive-{$postType}";
        }

        if (is_category()) {
            $templates[] = 'pages/category';
        }

        if (is_tag()) {
            $templates[] = 'pages/tag';
        }

        $templates[] = 'pages/archive';

        return $this->view->renderFirst($templates, $context);
    }
}
