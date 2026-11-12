<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsContextProvider(templates: ['pages/single', 'components/card-*'], priority: 5)]
final class ProductCardContext implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->with('price_label', 'Price');
    }
}
