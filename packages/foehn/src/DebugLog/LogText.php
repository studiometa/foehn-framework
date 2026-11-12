<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DebugLog;

/**
 * A group of debug log entries as `wp foehn log` prints it: the message, then how
 * often, when and where.
 *
 * @phpstan-import-type Group from LogParser
 */
final class LogText
{
    /** The characters of a message the text shows. JSON has the whole message. */
    private const int MESSAGE = 300;

    /** The lines of a stack trace the text shows. JSON has all of them. */
    private const int TRACE = 3;

    /**
     * @param Group $group
     * @return list<string>
     */
    public static function lines(array $group): array
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $group['message']));
        $when = self::time($group['last']);
        $first = self::time($group['first']);

        $lines = [
            mb_strlen($message) > self::MESSAGE ? mb_substr($message, 0, self::MESSAGE) . '…' : $message,
            '  '
                . implode('', [
                    $group['level'],
                    $group['count'] > 1 ? ' ×' . $group['count'] : '',
                    $when === '' ? '' : ' at ' . $when,
                    $group['file'] === null ? '' : sprintf(' in %s:%d', $group['file'], $group['line'] ?? 0),
                    $group['count'] > 1 && $first !== '' && $first !== $when ? ', first at ' . $first : '',
                ]),
        ];

        foreach (array_slice($group['trace'], 0, self::TRACE) as $frame) {
            $lines[] = '    ' . $frame;
        }

        $more = count($group['trace']) - self::TRACE;

        if ($more > 0) {
            $lines[] = sprintf('    +%d more %s', $more, $more === 1 ? 'line' : 'lines');
        }

        return $lines;
    }

    private static function time(?string $time): string
    {
        return $time === null ? '' : str_replace(['T', 'Z'], [' ', ' UTC'], $time);
    }
}
