<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Mark a class as a seeder: code that writes the sample content of a site.
 *
 * The class must implement `SeederInterface`. Discovery collects seeders and
 * registers nothing with WordPress: `wp foehn seed` runs them, all of them by
 * `order` or one with `--class`.
 *
 * A seeder must be idempotent. It finds what it writes by slug, meta or option
 * name before it creates it, so a second run updates the content and never
 * duplicates it.
 *
 * Usage:
 *
 *     #[AsSeeder(order: 20, description: 'Sample events')]
 *     final class EventSeeder implements SeederInterface
 *     {
 *         public function run(): void
 *         {
 *             Upsert::post('event', 'spring-fair', ['post_title' => 'Spring fair']);
 *         }
 *     }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsSeeder
{
    /**
     * @param int $order Run order: lower runs first, then by class name
     * @param string $description What the seeder writes, shown by `wp foehn inspect`
     */
    public function __construct(
        public int $order = 10,
        public string $description = '',
    ) {}
}
