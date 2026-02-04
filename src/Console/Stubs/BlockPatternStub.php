<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
#[AsBlockPattern(
    name: 'theme/dummy-pattern',
    title: 'Dummy Pattern',
    description: 'A custom block pattern.',
    categories: ['featured'],
    keywords: ['custom', 'layout'],
)]
final class BlockPatternStub implements BlockPatternInterface
{
    /**
     * Compose data for the pattern template.
     *
     * @return array<string, mixed>
     */
    public function compose(): array
    {
        return [
            'title' => 'Pattern Title',
            'description' => 'Pattern description goes here.',
            // Add your pattern data here
        ];
    }
}
