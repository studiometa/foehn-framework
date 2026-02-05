<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(name: 'hero_banner', width: 1920, height: 1080)]
final class ImageSizeWithNameFixture {}
