<?php

declare(strict_types=1);

namespace App\ImageSizes;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(width: 1920, height: 1080, crop: true)]
final class HeroImageSize {}
