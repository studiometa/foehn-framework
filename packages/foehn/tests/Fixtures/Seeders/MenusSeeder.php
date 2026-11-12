<?php

declare(strict_types=1);

namespace Tests\Fixtures\Seeders;

use RuntimeException;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

/**
 * The same order as PagesSeeder, so the class name decides; and the default description.
 * Throws the message in `$GLOBALS['seeder_failure']` when a test sets one.
 */
#[AsSeeder(order: 20)]
final class MenusSeeder implements SeederInterface
{
    public function run(): void
    {
        $failure = $GLOBALS['seeder_failure'] ?? null;

        if (is_string($failure)) {
            throw new RuntimeException($failure);
        }

        wp_stub_record('seeder_run', ['class' => self::class]);
    }
}
