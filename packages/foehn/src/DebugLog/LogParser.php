<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DebugLog;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Studiometa\Foehn\Errors\SourcePaths;

/**
 * The entries of a debug log, and the groups of the entries that repeat.
 *
 * Each entry starts with the date PHP writes, `[12-Nov-2026 10:00:00 UTC]`. A line
 * without that date continues the entry above it: the stack trace of a fatal error, or
 * a message of several lines, such as an `error_log(print_r(...))`.
 *
 * @phpstan-type Entry array{time: string|null, level: string, message: string, file: string|null, line: int|null, trace: list<string>}
 * @phpstan-type Group array{level: string, message: string, file: string|null, line: int|null, count: int, first: string|null, last: string|null, trace: list<string>}
 */
final class LogParser
{
    /** The levels of an entry. `log` is a message without a PHP error type, such as an `error_log()` call. */
    public const array LEVELS = ['fatal', 'warning', 'notice', 'deprecated', 'log'];

    /** The characters of a message an entry keeps. */
    private const int MESSAGE = 2000;

    /** The lines of a stack trace an entry keeps. */
    private const int TRACE = 50;

    private const string HEADER = '/^\[(\d{1,2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}(?: [^\]]+)?)\] (.*)$/';

    /** The PHP error types, as PHP writes them after `PHP `. */
    private const array TYPES = [
        'Fatal error' => 'fatal',
        'Recoverable fatal error' => 'fatal',
        'Parse error' => 'fatal',
        'Warning' => 'warning',
        'Notice' => 'notice',
        'Deprecated' => 'deprecated',
        'Strict Standards' => 'deprecated',
    ];

    /**
     * The entries of a log, the oldest first.
     *
     * Lines before the first date belong to an entry that starts before the text: they
     * are left out. The entries come one by one: a log of a few megabytes can hold a
     * hundred thousand of them.
     *
     * @return Generator<int, Entry>
     */
    public static function entries(string $text, SourcePaths $paths): Generator
    {
        /** @var array{time: string, lines: list<string>}|null $raw */
        $raw = null;

        foreach (explode("\n", $text) as $line) {
            $line = rtrim($line, "\r");
            $match = [];

            if (preg_match(self::HEADER, $line, $match) !== 1) {
                if ($raw !== null && trim($line) !== '') {
                    $raw['lines'][] = $line;
                }

                continue;
            }

            // Xdebug writes each frame of its stack trace with a date of its own.
            if ($raw !== null && preg_match('/^PHP (Stack trace:|\s+\d+\. )/', $match[2]) === 1) {
                $raw['lines'][] = substr($match[2], 4);

                continue;
            }

            if ($raw !== null) {
                yield self::entry($raw, $paths);
            }

            $raw = ['time' => $match[1], 'lines' => [$match[2]]];
        }

        if ($raw !== null) {
            yield self::entry($raw, $paths);
        }
    }

    /**
     * The entries that repeat as one group each, the group seen last first.
     *
     * Entries repeat when they have the same level, message, file and line. A group
     * keeps the stack trace of its last entry.
     *
     * @param iterable<Entry> $entries The oldest first
     * @return list<Group>
     */
    public static function group(iterable $entries): array
    {
        /** @var array<string, Group> $groups */
        $groups = [];

        foreach ($entries as $entry) {
            $key = implode("\0", [$entry['level'], $entry['message'], $entry['file'] ?? '', $entry['line'] ?? '']);
            $group = $groups[$key] ?? null;

            // Remove and add it again, so the order of the groups is the order of their last entry.
            unset($groups[$key]);

            $groups[$key] = [
                'level' => $entry['level'],
                'message' => $entry['message'],
                'file' => $entry['file'],
                'line' => $entry['line'],
                'count' => ($group['count'] ?? 0) + 1,
                'first' => $group === null ? $entry['time'] : $group['first'],
                'last' => $entry['time'],
                'trace' => $entry['trace'],
            ];
        }

        return array_reverse(array_values($groups));
    }

    /**
     * @param array{time: string, lines: list<string>} $raw
     * @return Entry
     */
    private static function entry(array $raw, SourcePaths $paths): array
    {
        $lines = $raw['lines'];
        $first = (string) array_shift($lines);
        $level = 'log';
        $match = [];

        if (preg_match('/^PHP ([A-Za-z ]+?):\s+(.*)$/', $first, $match) === 1) {
            $type = self::TYPES[$match[1]] ?? null;
            $level = $type ?? $level;
            $first = $type === null ? $first : $match[2];
        }

        $message = [$first];
        $trace = [];
        $thrown = null;
        $inTrace = false;

        foreach ($lines as $line) {
            if (trim($line) === 'Stack trace:') {
                $inTrace = true;

                continue;
            }

            if (preg_match('/^\s+thrown in (.+?) on line (\d+)$/', $line, $match) === 1) {
                $thrown = [$match[1], (int) $match[2]];

                continue;
            }

            if ($inTrace) {
                $trace[] = self::frame(trim($line), $paths);

                continue;
            }

            $message[] = $line;
        }

        $text = implode("\n", $message);
        [$file, $number] = $thrown ?? [null, null];
        $location = self::location($text);

        if ($location !== null) {
            [$text, $file, $number] = $location;
        }

        return [
            'time' => self::time($raw['time']),
            'level' => $level,
            'message' => mb_strlen($text) > self::MESSAGE ? mb_substr($text, 0, self::MESSAGE) . '…' : $text,
            'file' => $file === null ? null : $paths->relative($file),
            'line' => $number,
            'trace' => array_slice($trace, 0, self::TRACE),
        ];
    }

    /**
     * The message without the place PHP adds at its end, and that place.
     *
     * PHP ends a warning with `in /path/file.php on line 12`, and an uncaught
     * exception with `in /path/file.php:12`.
     *
     * @return array{string, string, int}|null
     */
    private static function location(string $message): ?array
    {
        foreach (['/^(.*) in ([^\n]+?) on line (\d+)$/s', '/^(.*) in ([^\n]+?):(\d+)$/s'] as $pattern) {
            $match = [];

            if (preg_match($pattern, $message, $match) === 1) {
                return [$match[1], $match[2], (int) $match[3]];
            }
        }

        return null;
    }

    /**
     * A frame of a stack trace, `#0 /path/file.php(12): call()`, with a shorter path.
     */
    private static function frame(string $line, SourcePaths $paths): string
    {
        return (string) preg_replace_callback(
            '/^(#\d+ )(\/[^(]+)\(/',
            static fn(array $match): string => $match[1] . $paths->relative($match[2]) . '(',
            $line,
        );
    }

    /**
     * The date PHP writes, in UTC and ISO 8601 as the error log writes it.
     */
    private static function time(string $date): ?string
    {
        try {
            return new DateTimeImmutable($date, new DateTimeZone('UTC'))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        } catch (DateMalformedStringException) {
            return null;
        }
    }
}
