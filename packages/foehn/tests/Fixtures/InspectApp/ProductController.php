<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController(templates: ['single-product', 'archive-product'])]
final class ProductController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        return null;
    }
}
