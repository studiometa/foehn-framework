<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTimberModel
{
    /**
     * @param string $name Post type or taxonomy slug to map to
     */
    public function __construct(
        public string $name,
    ) {}
}
