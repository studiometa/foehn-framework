<?php

declare(strict_types=1);

namespace App\Data;

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;
use Studiometa\Foehn\Data\ImageData;
use Studiometa\Foehn\Data\LinkData;

/**
 * Typed context DTO for the Hero block.
 *
 * Properties are automatically converted to snake_case Twig variables:
 * - $title      → {{ title }}
 * - $subtitle   → {{ subtitle }}
 * - $background → {{ background.src }}, {{ background.alt }}, etc.
 * - $cta        → {{ cta.url }}, {{ cta.title }}, {{ cta.target }}
 * - $height     → {{ height }}
 */
final readonly class HeroContext implements Arrayable
{
    use HasToArray;

    public function __construct(
        public string $title,
        public ?string $subtitle = null,
        public ?ImageData $background = null,
        public ?LinkData $cta = null,
        public string $height = 'medium',
    ) {}
}
