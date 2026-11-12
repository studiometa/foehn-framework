<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Errors;

/**
 * An error entry as `wp foehn errors` prints it: what failed, where, on which request.
 */
final class EntryText
{
    /** The stack frames the text shows. JSON has all of them. */
    private const int FRAMES = 5;

    /**
     * One entry, in a few lines: what failed, where, on which request.
     *
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    public static function lines(array $entry): array
    {
        $lines = [sprintf('%s: %s', self::text($entry['class'] ?? null), self::text($entry['message'] ?? null))];
        $where = self::text($entry['file'] ?? null);
        $lines[] = sprintf(
            '  %s at %s%s',
            self::text($entry['level'] ?? null),
            str_replace(['T', 'Z'], [' ', ' UTC'], self::text($entry['time'] ?? null)),
            $where === '' ? '' : sprintf(' in %s:%d', $where, is_int($entry['line'] ?? null) ? $entry['line'] : 0),
        );

        if (is_string($entry['twig'] ?? null)) {
            $lines[] = '  twig ' . $entry['twig'];
        }

        foreach (self::strings($entry['previous'] ?? null) as $previous) {
            $lines[] = '  caused by ' . $previous;
        }

        $request = array_filter([
            trim(self::text($entry['method'] ?? null) . ' ' . self::text($entry['url'] ?? null)),
            self::labelled('template', $entry['template'] ?? null),
            self::labelled('controller', $entry['controller'] ?? null),
            self::labelled('view', $entry['view'] ?? null),
        ]);

        if ($request !== []) {
            $lines[] = '  ' . implode(' · ', $request);
        }

        $context = self::strings($entry['context'] ?? null);

        if ($context !== []) {
            $lines[] = '  context ' . implode(', ', $context);
        }

        $stack = self::strings($entry['stack'] ?? null);

        if (count($stack) > self::FRAMES) {
            $stack = [...array_slice($stack, 0, self::FRAMES - 1), '…'];
        }

        foreach ($stack as $frame) {
            $lines[] = '    ' . $frame;
        }

        return $lines;
    }

    private static function labelled(string $label, mixed $value): string
    {
        return is_string($value) && $value !== '' ? $label . ' ' . $value : '';
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
