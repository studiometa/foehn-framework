<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Skills;

/**
 * The top-level scalar fields of a SKILL.md frontmatter block.
 *
 * Agent Skills put `name` and `description` there. This reads plain, single-quoted
 * and double-quoted values, and folded (`>`) or literal (`|`) block values. Nested
 * mappings and lists are skipped. There is no YAML parser among Føhn's dependencies,
 * and two fields do not justify one.
 */
final class Frontmatter
{
    /**
     * @return array<string, string>
     */
    public static function parse(string $contents): array
    {
        $match = [];

        if (preg_match('/\A---\R(.*?)\R---(?:\R|\z)/s', $contents, $match) !== 1) {
            return [];
        }

        $lines = preg_split('/\R/', $match[1]) ?: [];
        $fields = [];
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $field = [];

            if (preg_match('/^([A-Za-z0-9_-]+):[ \t]*(.*)$/', $lines[$index], $field) !== 1) {
                continue;
            }

            $value = rtrim($field[2]);
            $block = [];

            if (preg_match('/^([>|])[+-]?$/', $value, $block) !== 1) {
                $fields[$field[1]] = self::scalar($value);

                continue;
            }

            // A block value runs over the indented and blank lines that follow.
            $body = [];

            while (($index + 1) < $count && preg_match('/^([ \t]+\S.*|[ \t]*)$/', $lines[$index + 1]) === 1) {
                $body[] = trim($lines[++$index]);
            }

            $fields[$field[1]] = trim(implode($block[1] === '>' ? ' ' : "\n", $body));
        }

        return $fields;
    }

    private static function scalar(string $value): string
    {
        $length = strlen($value);

        if ($length >= 2 && $value[0] === "'" && $value[$length - 1] === "'") {
            return str_replace("''", "'", substr($value, 1, -1));
        }

        if ($length >= 2 && $value[0] === '"' && $value[$length - 1] === '"') {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }
}
