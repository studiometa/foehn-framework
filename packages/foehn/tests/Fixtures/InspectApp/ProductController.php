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
        // A key named in a comment, such as ->with('commented', 1), is not one.
        $context = $context->with('related', []);

        if ($context->post !== null) {
            $context = $context->with('in_stock', true);
        }

        return null;
    }
}
