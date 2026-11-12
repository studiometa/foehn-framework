<?php

declare(strict_types=1);

namespace Tests\Fixtures\ConventionsApp\Http;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController(templates: ['single-*', 'archive-*'])]
final class PageController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        return null;
    }
}
