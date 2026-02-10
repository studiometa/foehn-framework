<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;

#[AsContextProvider(templates: ['single', 'page'], priority: 5)]
final class ContextProviderFixture implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        return array_merge($context, ['foo' => 'bar']);
    }
}
