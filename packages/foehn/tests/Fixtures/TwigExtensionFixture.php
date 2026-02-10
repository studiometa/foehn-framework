<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fixture for testing TwigExtensionDiscovery.
 */
#[AsTwigExtension]
final class TwigExtensionFixture extends AbstractExtension
{
    public function getName(): string
    {
        return 'test_extension';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('test_function', fn() => 'test'),
        ];
    }
}
