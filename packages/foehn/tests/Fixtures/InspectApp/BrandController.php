<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Views\TemplateContext;

/**
 * Adds keys inspect cannot name: they come from an array built at runtime.
 */
#[AsTemplateController(templates: 'taxonomy-brand')]
final class BrandController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        $context = $context->merge(['brand_logo' => null])->with('brand_count', 3);

        return null;
    }
}
