<?php

declare(strict_types=1);

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Timber\Timber;

#[AsTemplateController(['single', 'single-*'])]
final readonly class SingleController implements TemplateControllerInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function handle(): string
    {
        $context = Timber::context();
        $post = $context['post'];

        if (post_password_required($post->ID)) {
            return $this->view->render('pages/password', $context);
        }

        $templates = [
            "pages/single-{$post->post_type}-{$post->slug}",
            "pages/single-{$post->post_type}",
            'pages/single',
        ];

        return $this->view->renderFirst($templates, $context);
    }
}
