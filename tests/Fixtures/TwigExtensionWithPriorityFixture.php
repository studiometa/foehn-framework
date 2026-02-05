<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Fixture for testing TwigExtensionDiscovery with custom priority.
 */
#[AsTwigExtension(priority: 5)]
final class TwigExtensionWithPriorityFixture extends AbstractExtension
{
    public function getName(): string
    {
        return 'test_extension_priority';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('test_filter', fn(string $value) => strtoupper($value)),
        ];
    }
}
