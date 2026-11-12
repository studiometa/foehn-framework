<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Views\TemplateContext;

/**
 * Adds a key from a trait, which inspect reads with the class that uses it.
 */
trait AddsBreadcrumbs
{
    protected function breadcrumbs(TemplateContext $context): TemplateContext
    {
        return $context->with('breadcrumbs', []);
    }
}
