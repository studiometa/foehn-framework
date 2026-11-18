<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Tests\Fixtures;

use Studiometa\Foehn\Places\Models\Place;
use Timber\Term;

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

    public function title(): string
    {
        return (string) ($this->values['title'] ?? 'Place ' . $this->ID);
    }

    public function link(): string
    {
        return (string) ($this->values['link'] ?? 'https://example.com/place/' . $this->ID . '/');
    }

    /**
     * @return list<Term>
     */
    public function types(): array
    {
        $types = [];

        foreach ($this->values['types'] ?? [] as $name) {
            $term = new \ReflectionClass(Term::class)->newInstanceWithoutConstructor();
            $term->name = $name;
            $types[] = $term;
        }

        return $types;
    }

    protected function field(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }
}
