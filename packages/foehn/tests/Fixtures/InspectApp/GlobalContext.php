<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsContextProvider(templates: '*', priority: 1)]
final class GlobalContext implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->with('current_year', '2026');
    }
}
