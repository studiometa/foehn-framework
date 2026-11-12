<?php

declare(strict_types=1);

namespace Tests\Fixtures\Seeders\Archive;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

/**
 * A second seeder with the short name PagesSeeder.
 */
#[AsSeeder(order: 30, description: 'Archived pages')]
final class PagesSeeder implements SeederInterface
{
    public function run(): void
    {
        wp_stub_record('seeder_run', ['class' => self::class]);
    }
}
