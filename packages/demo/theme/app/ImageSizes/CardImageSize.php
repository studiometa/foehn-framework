<?php

declare(strict_types=1);

namespace Demo\ImageSizes;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(width: 800, height: 600, crop: true)]
final class CardImageSize {}
