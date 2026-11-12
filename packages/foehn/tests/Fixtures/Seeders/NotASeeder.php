<?php

declare(strict_types=1);

namespace Tests\Fixtures\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;

/**
 * The attribute without SeederInterface: discovery skips it.
 */
#[AsSeeder]
final class NotASeeder
{
    public function run(): void {}
}
