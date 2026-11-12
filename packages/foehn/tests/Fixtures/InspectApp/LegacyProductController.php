<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Views\TemplateContext;

/**
 * Claims a template ProductController claims too, with a higher priority, so it loses.
 */
#[AsTemplateController(templates: 'single-product', priority: 20)]
final class LegacyProductController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        return null;
    }
}
