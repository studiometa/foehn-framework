<?php

declare(strict_types=1);

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

/**
 * Pages.
 *
 * Sans ce contrôleur, les pages — le seul type de contenu que tout site
 * WordPress possède — passaient à côté de la couche de vue : elles retombaient
 * sur le rendu par défaut de WordPress, et un `pages/page.twig` ajouté au thème
 * n'était jamais lu.
 *
 * Le gabarit est choisi sur le slug, pas sur le chemin complet de la page : un
 * chemin cesse d'être juste dès qu'on déplace la page dans l'arborescence, et
 * la hiérarchie de gabarits de WordPress se fonde elle aussi sur le slug.
 */
#[AsTemplateController(['page', 'page-*'])]
final readonly class PageController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): string
    {
        $post = $context->post;

        if ($post && post_password_required($post->ID)) {
            return $this->view->renderFirst(['pages/password', 'pages/page'], $context);
        }

        return $this->view->renderFirst([
            "pages/page-{$post?->slug}",
            'pages/page',
        ], $context);
    }
}
