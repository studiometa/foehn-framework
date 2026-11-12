<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

#[AsSeeder(order: 20, description: 'Sample products')]
final class SampleProductsSeeder implements SeederInterface
{
    public function run(): void {}
}
