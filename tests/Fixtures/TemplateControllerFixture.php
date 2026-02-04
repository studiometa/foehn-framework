<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsTemplateController;
use Studiometa\WPTempest\Contracts\TemplateControllerInterface;

#[AsTemplateController(templates: ['single', 'page'], priority: 10)]
final class TemplateControllerFixture implements TemplateControllerInterface
{
    public function handle(): ?string
    {
        return '<h1>Hello</h1>';
    }
}
