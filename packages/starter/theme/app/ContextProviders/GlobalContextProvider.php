<?php

declare(strict_types=1);

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;

/**
 * Global context provider.
 *
 * Adds shared data to all templates. Note that:
 * - `site`, `user`, `post`, `posts` are provided by TemplateContext
 * - `menus` are auto-injected by MenuDiscovery
 */
#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context
            ->with('current_year', date('Y'))
            ->with('is_home', is_front_page());
    }
}
