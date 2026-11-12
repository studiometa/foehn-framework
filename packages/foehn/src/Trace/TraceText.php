<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Trace;

/**
 * A request trace as `wp foehn trace` prints it: what the request was, then what ran,
 * in short sections. Each list is cut; `--format=json` has all of it.
 */
final class TraceText
{
    /** The items a line lists before it says how many more there are. */
    private const int ITEMS = 10;

    /** The views the text shows. */
    private const int VIEWS = 8;

    /** The errors the text shows. */
    private const int ERRORS = 5;

    /** The characters of an error message the text shows. */
    private const int MESSAGE = 160;

    /**
     * The trace in a few short sections, and how to read the rest.
     *
     * @param array<string, mixed> $trace
     * @param list<array<string, mixed>> $errors The error entries of the request, the newest first
     * @return list<string>
     */
    public static function lines(array $trace, array $errors): array
    {
        $id = self::text($trace['id'] ?? null);

        return [
            ...self::request($trace),
            ...self::template($trace),
            ...self::views(
                is_array($trace['views'] ?? null) ? array_values(array_filter($trace['views'], is_array(...))) : [],
            ),
            ...self::blocks(is_array($trace['blocks'] ?? null) ? $trace['blocks'] : []),
            ...self::errors($errors),
            sprintf(
                'Every field: wp foehn trace %s --format=json.%s',
                $id,
                $errors === []
                    ? ''
                    : sprintf(' The errors in full: wp foehn errors --request=%s --limit=%d.', $id, count($errors)),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $trace
     * @return list<string>
     */
    private static function request(array $trace): array
    {
        $lines = [
            implode(' · ', array_filter([
                trim(self::text($trace['method'] ?? null) . ' ' . self::text($trace['url'] ?? null)),
                self::text($trace['status'] ?? null),
                is_int($trace['durationMs'] ?? null) ? $trace['durationMs'] . ' ms' : '',
                is_int($trace['queries'] ?? null) ? $trace['queries'] . ' queries' : '',
            ])),
            sprintf(
                '  trace %s at %s',
                self::text($trace['id'] ?? null),
                str_replace(['T', 'Z'], [' ', ' UTC'], self::text($trace['time'] ?? null)),
            ),
        ];

        if (is_string($trace['location'] ?? null)) {
            $lines[] = '  redirects to ' . $trace['location'];
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $trace
     * @return list<string>
     */
    private static function template(array $trace): array
    {
        $lines = ['template'];
        $hierarchy = self::strings($trace['hierarchy'] ?? null);

        if ($hierarchy !== []) {
            $lines[] = '  tried ' . self::list($hierarchy);
        }

        $file = self::text($trace['templateFile'] ?? null);
        $lines[] = '  found ' . ($file === '' ? 'none' : $file);
        $template = self::text($trace['template'] ?? null);
        $controller = self::text($trace['controller'] ?? null);

        if ($template !== '') {
            $lines[] = sprintf(
                '  matched %s · %s',
                $template,
                $controller === '' ? 'no controller' : 'controller ' . $controller,
            );
        }

        return $lines;
    }

    /**
     * @param list<array<mixed>> $views
     * @return list<string>
     */
    private static function views(array $views): array
    {
        if ($views === []) {
            return [];
        }

        $lines = ['views'];

        foreach (array_slice($views, 0, self::VIEWS) as $view) {
            $count = is_int($view['count'] ?? null) ? $view['count'] : 1;
            $candidates = self::strings($view['candidates'] ?? null);
            $lines[] =
                '  '
                . implode(' · ', array_filter([
                    self::text($view['view'] ?? null) . ($count > 1 ? ' ×' . $count : ''),
                    count($candidates) > 1 ? 'first of ' . self::list($candidates) : '',
                ]));
            $providers = self::strings($view['providers'] ?? null);
            $keys = self::strings($view['keys'] ?? null);

            if ($providers !== []) {
                $lines[] = '    providers ' . self::list($providers);
            }

            if ($keys !== []) {
                $lines[] = '    context ' . self::list($keys);
            }
        }

        if (count($views) > self::VIEWS) {
            $lines[] = sprintf('  … %d more views', count($views) - self::VIEWS);
        }

        return $lines;
    }

    /**
     * @param array<mixed> $blocks
     * @return list<string>
     */
    private static function blocks(array $blocks): array
    {
        if ($blocks === []) {
            return [];
        }

        return [
            'blocks',
            '  '
                . self::list(array_map(
                    static fn(string|int $name, mixed $count): string => (
                        (string) $name . (is_int($count) && $count > 1 ? ' ×' . $count : '')
                    ),
                    array_keys($blocks),
                    array_values($blocks),
                )),
        ];
    }

    /**
     * @param list<array<string, mixed>> $errors
     * @return list<string>
     */
    private static function errors(array $errors): array
    {
        if ($errors === []) {
            return [];
        }

        $lines = ['errors'];

        foreach (array_slice($errors, 0, self::ERRORS) as $error) {
            $message = self::text($error['message'] ?? null);
            $file = self::text($error['file'] ?? null);
            $lines[] = sprintf(
                '  %s %s: %s%s',
                self::text($error['level'] ?? null),
                self::text($error['class'] ?? null),
                mb_strlen($message) > self::MESSAGE ? mb_substr($message, 0, self::MESSAGE) . '…' : $message,
                $file === '' ? '' : sprintf(' · %s:%d', $file, is_int($error['line'] ?? null) ? $error['line'] : 0),
            );
        }

        if (count($errors) > self::ERRORS) {
            $lines[] = sprintf('  … %d more errors', count($errors) - self::ERRORS);
        }

        return $lines;
    }

    /**
     * Items separated by commas, cut after ITEMS with the number left out.
     *
     * @param list<string> $items
     */
    private static function list(array $items): string
    {
        $shown = implode(', ', array_slice($items, 0, self::ITEMS));

        return count($items) > self::ITEMS ? sprintf('%s, … (+%d)', $shown, count($items) - self::ITEMS) : $shown;
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
