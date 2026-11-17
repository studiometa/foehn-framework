<?php

declare(strict_types=1);

namespace Tests\Fixtures\Customizer;

use Studiometa\Foehn\Attributes\AsCustomizerSection;

#[AsCustomizerSection(id: 'foehn_broken', title: 'Broken')]
final class InterfacelessSectionFixture {}
