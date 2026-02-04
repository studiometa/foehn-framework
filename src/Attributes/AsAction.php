<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Mark a method as a WordPress action hook handler.
 *
 * Usage:
 * ```php
 * #[AsAction('init')]
 * public function onInit(): void { }
 *
 * #[AsAction('admin_init', priority: 20)]
 * public function onAdminInit(): void { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsAction
{
    /**
     * @param string $hook The WordPress action hook name
     * @param int $priority Priority for the hook (default: 10)
     * @param int $acceptedArgs Number of arguments the callback accepts (default: 1)
     */
    public function __construct(
        public string $hook,
        public int $priority = 10,
        public int $acceptedArgs = 1,
    ) {}
}
