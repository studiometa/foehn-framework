<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsAcfFieldGroup;

#[AsAcfFieldGroup(
    name: 'invalid_group',
    title: 'Invalid Group',
    location: ['post_type' => 'post'],
)]
final class InvalidAcfFieldGroupFixture
{
    // Missing AcfFieldGroupInterface implementation
}
