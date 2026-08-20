<?php

declare(strict_types=1);

namespace Demo\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

/**
 * Editorial pages — À propos, and anything else written in the editor.
 *
 * `pages/page-{slug}` first, so a page that wants its own layout gets one by adding
 * a template rather than by adding a controller.
 */
#[AsTemplateController(['page'])]
final readonly class PageController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): string
    {
        $post = $context->post;

        if ($post && post_password_required($post->ID)) {
            return $this->view->render('pages/password', $context);
        }

        return $this->view->renderFirst(["pages/page-{$post?->slug}", 'pages/page'], $context);
    }
}
