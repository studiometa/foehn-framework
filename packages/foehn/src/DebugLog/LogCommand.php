<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DebugLog;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Errors\SourcePaths;

/**
 * @phpstan-import-type Entry from LogParser
 * @phpstan-import-type Group from LogParser
 */
#[AsCliCommand(
    name: 'log',
    description: 'Show the PHP and WordPress debug log, with repeated entries grouped',
    longDescription: <<<'DOC'
        ## DESCRIPTION

        Reads the debug log WordPress writes when WP_DEBUG and WP_DEBUG_LOG are on:
        `wp-content/debug.log`, or the path WP_DEBUG_LOG holds. PHP writes its
        warnings, notices and fatal errors there, and WordPress, plugins and the
        theme their `error_log()` messages.

        Each entry gives its level, its message, its file and line, and the stack
        trace of a fatal error. Entries with the same level, message, file and line
        are one group, with how many there are and when the first and the last were
        written. The groups seen last come first.

        Only the last 8 MB of the file are read.

        ## OPTIONS

        [--limit=<n>]
        : How many groups to print. Defaults to 20.

        [--level=<level>]
        : Only these levels, separated by commas: fatal, warning, notice,
          deprecated, log. `log` is a message without a PHP error type, such as an
          `error_log()` call.

        [--since=<date>]
        : Only entries written at or after this date. A date or a relative date
          such as `-10 minutes`.

        [--grep=<text>]
        : Only entries whose message, file or stack trace contains this text. Case
          does not matter.

        [--format=<format>]
        : Output format. text (default) or json.

        ## EXAMPLES

            # The last 20 groups
            wp foehn log

            # The fatal errors and warnings of the last hour, for an agent
            wp foehn log --level=fatal,warning --since=-1hour --format=json

            # Every entry about one class
            wp foehn log --grep=ProductController --limit=5
        DOC,
)]
final readonly class LogCommand implements CliCommandInterface
{
    /** @var list<string> */
    private const array FORMATS = ['text', 'json'];

    private const string NOT_WRITING = 'WP_DEBUG is off, so WordPress writes nothing new to this log. Set WP_DEBUG=true in .env, or define WP_DEBUG as true in wp-config.php.';

    public function __construct(
        private WpCli $cli,
        private DebugLog $log,
        private SourcePaths $paths,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $format = $assocArgs['format'] ?? 'text';
        $limit = $assocArgs['limit'] ?? '20';
        $levels = $assocArgs['level'] ?? null;
        $since = $assocArgs['since'] ?? null;
        $grep = $assocArgs['grep'] ?? null;

        if (!is_string($format) || !in_array($format, self::FORMATS, true)) {
            $this->cli->error('Unknown format. Use text or json.');

            return;
        }

        if (!is_string($limit) || !ctype_digit($limit) || (int) $limit < 1) {
            $this->cli->error('--limit must be a number of 1 or more.');

            return;
        }

        if ($grep !== null && (!is_string($grep) || $grep === '')) {
            $this->cli->error('--grep must be the text to find, such as --grep=ProductController.');

            return;
        }

        $levels = is_string($levels) ? array_values(array_filter(array_map(trim(...), explode(',', $levels)))) : [];
        $unknown = array_diff($levels, LogParser::LEVELS);

        if ($unknown !== []) {
            $this->cli->error(sprintf(
                "Unknown level '%s'. Use %s.",
                implode("', '", $unknown),
                implode(', ', LogParser::LEVELS),
            ));

            return;
        }

        $from = null;

        if (is_string($since)) {
            try {
                $from = new DateTimeImmutable($since, new DateTimeZone('UTC'))
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:s\Z');
            } catch (DateMalformedStringException) {
                $this->cli->error(sprintf("'%s' is not a date.", $since));

                return;
            }
        }

        if ($this->log->path === null) {
            $this->cli->error(
                'The debug log is off: WP_DEBUG_LOG is not set. Set WP_DEBUG=true in .env: the wp-config.php of a Føhn project then sets WP_DEBUG_LOG, and WordPress writes the log to wp-content/debug.log. With another wp-config.php, define WP_DEBUG and WP_DEBUG_LOG as true.',
            );

            return;
        }

        $read = $this->log->read();

        if ($read === null && is_file($this->log->path)) {
            $this->cli->error(sprintf(
                'Cannot read %s: the user that runs WP-CLI needs read access to it.',
                $this->paths->relative($this->log->path),
            ));

            return;
        }

        $groups = $read === null
            ? []
            : LogParser::group(self::matching(LogParser::entries($read['text'], $this->paths), $levels, $from, $grep));
        $shown = array_slice($groups, 0, (int) $limit);
        $cut =
            $read !== null && $read['size'] > DebugLog::MAX_BYTES
                ? sprintf(
                    'Read the last %d MB of the %d MB log: older entries are not counted.',
                    (DebugLog::MAX_BYTES / 1024) / 1024,
                    (int) ceil(($read['size'] / 1024) / 1024),
                )
                : null;

        if ($format === 'json') {
            $this->cli->line((string) json_encode(
                $shown,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            ));

            // Standard error, so the JSON on standard output stays valid.
            if ($cut !== null) {
                $this->cli->warning($cut);
            }

            if (!$this->log->writing) {
                $this->cli->warning(self::NOT_WRITING);
            }

            return;
        }

        $this->renderText($shown, count($groups) - count($shown), (int) $limit, $read === null, $cut);
    }

    /**
     * @param list<Group> $groups
     */
    private function renderText(array $groups, int $more, int $limit, bool $missing, ?string $cut): void
    {
        $file = $this->paths->relative((string) $this->log->path);

        if ($groups === []) {
            $this->cli->log(sprintf(
                $missing ? 'No debug log at %s: nothing was written to it yet.' : 'No entries in %s.',
                $file,
            ));
        }

        foreach ($groups as $index => $group) {
            if ($index > 0) {
                $this->cli->line('');
            }

            foreach (LogText::lines($group) as $line) {
                $this->cli->log($line);
            }
        }

        if ($more > 0) {
            $this->cli->line('');
            $this->cli->log(sprintf(
                '%d more: wp foehn log --limit=%d. Every field: --format=json.',
                $more,
                $limit + $more,
            ));
        }

        if ($cut !== null) {
            $this->cli->log($cut);
        }

        if (!$this->log->writing) {
            $this->cli->log(self::NOT_WRITING);
        }
    }

    /**
     * The entries of these levels, written at or after a date, whose message, file or
     * stack trace contains a text.
     *
     * @param iterable<Entry> $entries
     * @param list<string> $levels
     * @return Generator<int, Entry>
     */
    private static function matching(iterable $entries, array $levels, ?string $from, ?string $grep): Generator
    {
        foreach ($entries as $entry) {
            if ($levels !== [] && !in_array($entry['level'], $levels, true)) {
                continue;
            }

            if ($from !== null && ($entry['time'] === null || $entry['time'] < $from)) {
                continue;
            }

            if (
                $grep !== null
                && stripos(implode("\n", [$entry['message'], $entry['file'] ?? '', ...$entry['trace']]), $grep)
                    === false
            ) {
                continue;
            }

            yield $entry;
        }
    }
}
