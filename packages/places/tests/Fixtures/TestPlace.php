<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Tests\Fixtures;

use Studiometa\Foehn\Places\Models\Place;

/**
 * A place whose values come from an array instead of post meta.
 */
final class TestPlace extends Place
{
    /** @var array<string, mixed> */
    public array $values = [];

    /**
     * @param array<string, mixed> $values
     */
    public static function with(int $id, array $values): self
    {
        $place = new \ReflectionClass(self::class)->newInstanceWithoutConstructor();
        $place->ID = $id;
        $place->values = $values;

        return $place;
    }

    protected function field(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }
}
