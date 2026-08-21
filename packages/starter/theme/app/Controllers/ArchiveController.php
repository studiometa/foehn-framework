<?php

declare(strict_types=1);

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController(['archive', 'archive-*', 'front-page', 'home', 'category', 'tag', 'tax-*'])]
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

        // Une liste, pas un nom. Un type de contenu sans gabarit dédié doit
        // retomber sur `pages/archive` : rendre un nom unique faisait lever une
        // exception, donc un 500, sur l'archive de tout type fraîchement
        // déclaré. `SingleController` procède déjà ainsi.
        //
        // `get_queried_object()->name` plutôt que `get_query_var('post_type')` :
        // celui-ci peut rendre un tableau, qui s'interpole en « Array » et
        // demande un gabarit `pages/archive-Array`.
        $templates = match (true) {
            is_post_type_archive() => [
                'pages/archive-' . (get_queried_object()->name ?? ''),
                'pages/archive',
            ],
            is_category() => ['pages/category', 'pages/archive'],
            is_tag() => ['pages/tag', 'pages/archive'],
            default => ['pages/archive'],
        };

        return $this->view->renderFirst($templates, $context);
    }
}
