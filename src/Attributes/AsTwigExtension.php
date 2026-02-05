<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Mark a class as a Twig extension.
 *
 * The class must extend Twig\Extension\AbstractExtension.
 *
 * ```php
 * #[AsTwigExtension]
 * final class MyExtension extends AbstractExtension
 * {
 *     public function getFunctions(): array
 *     {
 *         return [
 *             new TwigFunction('my_helper', [$this, 'myHelper']),
 *         ];
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTwigExtension
{
    public function __construct(
        /**
         * Priority for loading the extension.
         * Lower values load first.
         */
        public int $priority = 10,
    ) {}
}
