<?php

declare(strict_types=1);

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Helpers\WP;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController('search')]
final readonly class SearchController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): string
    {
        $context = $context
            ->with('search_query', get_search_query())
            ->with('found_posts', WP::query()->found_posts);

        if ($context->posts) {
            $context = $context->with('pagination', $context->posts->pagination());
        }

        return $this->view->render('pages/search', $context);
    }
}
