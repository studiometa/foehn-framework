<?php

declare(strict_types=1);

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Timber\Site;
use Timber\Timber;

#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        return array_merge($context, [
            'site' => new Site(),
            'menus' => [
                'header' => Timber::get_menu('header'),
                'footer' => Timber::get_menu('footer'),
                'legal' => Timber::get_menu('legal'),
            ],
            'current_year' => date('Y'),
            'is_home' => is_front_page(),
        ]);
    }
}
