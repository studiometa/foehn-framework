<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(location: 'primary', description: 'Main navigation')]
final class PrimaryMenu {}
