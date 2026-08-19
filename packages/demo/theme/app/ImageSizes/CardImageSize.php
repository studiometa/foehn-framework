<?php

declare(strict_types=1);

namespace Demo\ImageSizes;

use Studiometa\Foehn\Attributes\AsImageSize;

#[AsImageSize(width: 400, height: 300, crop: true)]
final class CardImageSize {}
