<?php

declare(strict_types=1);

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Timber\Timber;

#[AsTemplateController('404')]
final readonly class Error404Controller
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function __invoke(): string
    {
        return $this->view->render('pages/404', Timber::context());
    }
}
