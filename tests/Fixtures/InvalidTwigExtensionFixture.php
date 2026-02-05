<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsTwigExtension;

/**
 * Invalid fixture: has the attribute but doesn't extend AbstractExtension.
 */
#[AsTwigExtension]
final class InvalidTwigExtensionFixture
{
    public function getName(): string
    {
        return 'invalid_extension';
    }
}
