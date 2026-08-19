<?php

declare(strict_types=1);

namespace Tests\Fixtures\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;

#[AsSettingsPage(slug: 'broken-settings', title: 'Broken')]
final class InterfacelessSettingsFixture {}
