<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * A plain class with no discovery attributes — should be ignored by all discoveries.
 */
final class NoAttributeFixture
{
    public function doSomething(): void
    {
    }
}
