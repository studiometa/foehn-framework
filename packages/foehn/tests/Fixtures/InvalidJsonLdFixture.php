<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsJsonLd;

#[AsJsonLd(postType: 'event')]
final class InvalidJsonLdFixture {}
