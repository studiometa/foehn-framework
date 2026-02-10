<?php

declare(strict_types=1);

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Timber\Timber;

#[AsTemplateController('search')]
final readonly class SearchController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(): string
    {
        $context = Timber::context();

        $context['search_query'] = get_search_query();
        $context['found_posts'] = $GLOBALS['wp_query']->found_posts;

        if (isset($context['posts'])) {
            $context['pagination'] = $context['posts']->pagination();
        }

        return $this->view->render('pages/search', $context);
    }
}
