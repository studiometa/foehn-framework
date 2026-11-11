<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Views\TemplateContext;

/**
 * A base controller that adds keys for every controller that extends it.
 */
abstract class ShopController
{
    use AddsBreadcrumbs;

    protected function shop(TemplateContext $context): TemplateContext
    {
        return $this->breadcrumbs($context)->with('shop_name', 'Shop');
    }
}
