<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a class as an ACF Field Group.
 *
 * The class must implement AcfFieldGroupInterface.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAcfFieldGroup
{
    /**
     * @param string $name Unique field group name
     * @param string $title Display title in admin
     * @param array<string, mixed> $location Location rules (simplified or full ACF format)
     * @param string $position Position: 'acf_after_title', 'normal', 'side'
     * @param int $menuOrder Order in admin
     * @param string $style Style: 'default', 'seamless'
     * @param string $labelPlacement Label placement: 'top', 'left'
     * @param string $instructionPlacement Instruction placement: 'label', 'field'
     * @param string[] $hideOnScreen Elements to hide: 'the_content', 'excerpt', etc.
     */
    public function __construct(
        public string $name,
        public string $title,
        public array $location,
        public string $position = 'normal',
        public int $menuOrder = 0,
        public string $style = 'default',
        public string $labelPlacement = 'top',
        public string $instructionPlacement = 'label',
        public array $hideOnScreen = [],
    ) {}
}
