<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(width: 1200, height: 630, crop: true)]
final class ImageSizeFixture {}
