<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsImageSize;

/**
 * No name: the size registers as `card`, derived from the class name.
 */
#[AsImageSize(width: 600, height: 400, crop: true)]
final class CardImageSize {}
