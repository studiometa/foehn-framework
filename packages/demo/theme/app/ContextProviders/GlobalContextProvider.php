<?php

declare(strict_types=1);

namespace Demo\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Settings\Settings;
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
        // Settings::get() answers with the declared default before the option has
        // ever been saved, which get_option() does not, and with the declared type
        // — so the footer can test `show_banner` as a boolean rather than against
        // the empty string WordPress stores for an unchecked box.
        return $context
            ->with('current_year', date('Y'))
            ->with('is_home', is_front_page())
            ->with('contact_email', Settings::get('demo_contact_email'))
            ->with('show_banner', Settings::get('demo_show_banner'));
    }
}
