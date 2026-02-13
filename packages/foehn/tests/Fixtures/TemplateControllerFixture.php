<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController(templates: ['single', 'page'], priority: 10)]
final class TemplateControllerFixture implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        return '<h1>Hello</h1>';
    }
}
