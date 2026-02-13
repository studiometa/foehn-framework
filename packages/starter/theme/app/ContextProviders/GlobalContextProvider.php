<?php

declare(strict_types=1);

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;

#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        return array_merge($context, [
            'current_year' => date('Y'),
            'is_home' => is_front_page(),
        ]);
    }
}
