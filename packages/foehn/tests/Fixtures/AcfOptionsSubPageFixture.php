<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsAcfOptionsPage;

#[AsAcfOptionsPage(
    pageTitle: 'Social Media',
    parentSlug: 'theme-settings',
    capability: 'manage_options',
)]
final class AcfOptionsSubPageFixture
{
    // This fixture doesn't implement AcfOptionsPageInterface
    // to test options pages without fields
}
