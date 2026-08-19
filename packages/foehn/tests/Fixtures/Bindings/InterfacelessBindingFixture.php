<?php

declare(strict_types=1);

namespace Tests\Fixtures\Bindings;

use Studiometa\Foehn\Attributes\AsBlockBinding;

#[AsBlockBinding(name: 'theme/broken', label: 'Broken')]
final class InterfacelessBindingFixture {}
