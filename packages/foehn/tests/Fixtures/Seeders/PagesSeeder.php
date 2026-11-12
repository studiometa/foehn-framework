<?php

declare(strict_types=1);

namespace Tests\Fixtures\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

#[AsSeeder(order: 20, description: 'Pages')]
final class PagesSeeder implements SeederInterface
{
    public function run(): void
    {
        wp_stub_record('seeder_run', ['class' => self::class]);
    }
}
