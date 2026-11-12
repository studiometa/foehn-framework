<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

/**
 * Ordering helpers for report rows.
 */
final class Rows
{
    /**
     * @param array<array-key, array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function sorted(array $rows): array
    {
        return self::sortedBy($rows, 'name');
    }

    /**
     * Rows in a stable order, so two runs over the same project print the same report.
     *
     * @param array<array-key, array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function sortedBy(array $rows, string $key): array
    {
        $rows = array_values($rows);

        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a[$key], (string) $b[$key]));

        return $rows;
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    public static function unique(array $values): array
    {
        return array_values(array_unique(array_filter($values, is_string(...))));
    }
}
