<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Concerns;

use ReflectionClass;
use ReflectionProperty;
use Studiometa\Foehn\Contracts\Arrayable;

/**
 * Reflection-based toArray() implementation for DTOs.
 *
 * Converts all public instance properties to an associative array,
 * mapping camelCase property names to snake_case keys by default.
 *
 * Nested Arrayable objects are recursively flattened.
 */
trait HasToArray
{
    public function toArray(): array
    {
        $result = [];

        foreach (new ReflectionClass($this)->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) {
                continue;
            }

            if (!$prop->isInitialized($this)) {
                continue;
            }

            $value = $prop->getValue($this);
            $key = $this->propertyToKey($prop->getName());

            $result[$key] = match (true) {
                $value instanceof Arrayable => $value->toArray(),
                is_array($value) => $this->arrayToArray($value),
                default => $value,
            };
        }

        return $result;
    }

    /**
     * Convert a camelCase property name to a snake_case key.
     *
     * Override to customize key mapping (e.g. return $name for camelCase keys).
     */
    protected function propertyToKey(string $name): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }

    /**
     * Recursively convert Arrayable items in arrays.
     *
     * @param array<mixed> $items
     * @return array<mixed>
     */
    private function arrayToArray(array $items): array
    {
        return array_map(static fn(mixed $v): mixed => $v instanceof Arrayable ? $v->toArray() : $v, $items);
    }
}
