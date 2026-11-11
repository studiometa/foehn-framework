<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

#[AsBlockPattern(name: 'theme/call-to-action', title: 'Call to action', categories: ['featured'])]
final class CallToActionPattern implements BlockPatternInterface
{
    public function compose(): array
    {
        return [];
    }
}
