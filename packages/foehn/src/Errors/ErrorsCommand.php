<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Errors;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Helpers\Env;
use Studiometa\Foehn\Trace\TraceStore;

#[AsCliCommand(
    name: 'errors',
    description: 'Show the last errors, with what the request rendered',
    longDescription: <<<'DOC'
        ## DESCRIPTION

        Prints the last errors the site recorded: uncaught exceptions, fatal errors,
        and the warnings and notices of the theme's own code. Each entry gives the
        class, the message, the file and line, the Twig template and line when the
        error comes from a view, a short stack of the theme's frames, the request
        (method and URL, or the WP-CLI command), the WordPress template, the
        controller, the view and its context keys.

        Errors are recorded in the `local` and `development` environments. Set
        `recordErrors` in `app/foehn.config.php` (FoehnConfig) to change this. The
        log is `wp-content/cache/foehn/errors/errors.jsonl`, one JSON object per
        line, in at most 3 files of 256 KB.

        With no option, it prints the last entry, short enough to read whole.

        ## OPTIONS

        [--limit=<n>]
        : How many entries to print, the newest first. Defaults to 1.

        [--level=<level>]
        : Only these levels, separated by commas: exception, fatal, warning, notice.

        [--since=<date>]
        : Only entries recorded at or after this date. A date or a relative date
          such as `-10 minutes`.

        [--request=<id>]
        : Only the entries of one traced request: the id in its X-Foehn-Trace
          header, as `wp foehn trace` prints it.

        [--format=<format>]
        : Output format. text (default) or json.

        [--clear]
        : Delete the log.

        ## EXAMPLES

            # The last error
            wp foehn errors

            # The exceptions and fatal errors of the last hour, for an agent
            wp foehn errors --level=exception,fatal --since=-1hour --limit=10 --format=json

            # Start again from an empty log
            wp foehn errors --clear
        DOC,
)]
final readonly class ErrorsCommand implements CliCommandInterface
{
    /** @var list<string> */
    private const array FORMATS = ['text', 'json'];

    public function __construct(
        private WpCli $cli,
        private ErrorLog $log,
        private FoehnConfig $config,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        if (($assocArgs['clear'] ?? null) !== null) {
            $deleted = $this->log->clear();
            $this->cli->success($deleted === 0 ? 'The error log is empty.' : 'Deleted the error log.');

            return;
        }

        $format = $assocArgs['format'] ?? 'text';
        $limit = $assocArgs['limit'] ?? '1';
        $levels = $assocArgs['level'] ?? null;
        $since = $assocArgs['since'] ?? null;
        $request = $assocArgs['request'] ?? null;

        if (!is_string($format) || !in_array($format, self::FORMATS, true)) {
            $this->cli->error('Unknown format. Use text or json.');

            return;
        }

        if (!is_string($limit) || !ctype_digit($limit) || (int) $limit < 1) {
            $this->cli->error('--limit must be a number of 1 or more.');

            return;
        }

        if ($request !== null && (!is_string($request) || preg_match(TraceStore::ID_PATTERN, $request) !== 1)) {
            $this->cli->error('--request must be the id of a traced request: 6 characters such as 3fa2c1.');

            return;
        }

        $levels = is_string($levels) ? array_values(array_filter(array_map(trim(...), explode(',', $levels)))) : [];
        $unknown = array_diff($levels, EntryBuilder::LEVELS);

        if ($unknown !== []) {
            $this->cli->error(sprintf(
                "Unknown level '%s'. Use %s.",
                implode("', '", $unknown),
                implode(', ', EntryBuilder::LEVELS),
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

        $entries = array_values(array_filter(
            $this->log->entries(),
            static fn(array $entry): bool => (
                ($levels === [] || in_array($entry['level'] ?? null, $levels, true))
                && ($from === null || is_string($entry['time'] ?? null) && $entry['time'] >= $from)
                && ($request === null || ($entry['requestId'] ?? null) === $request)
            ),
        ));
        $shown = array_slice($entries, 0, (int) $limit);

        if ($format === 'json') {
            $this->cli->line((string) json_encode(
                $shown,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            ));

            return;
        }

        $this->renderText($shown, count($entries) - count($shown), (int) $limit);
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function renderText(array $entries, int $more, int $limit): void
    {
        if ($entries === []) {
            $this->cli->log('No errors recorded.');
        }

        foreach ($entries as $index => $entry) {
            if ($index > 0) {
                $this->cli->line('');
            }

            foreach (EntryText::lines($entry) as $line) {
                $this->cli->log($line);
            }
        }

        if ($more > 0) {
            $this->cli->line('');
            $this->cli->log(sprintf(
                '%d more: wp foehn errors --limit=%d. Every field: --format=json.',
                $more,
                $limit + $more,
            ));
        }

        if (!$this->config->recordsErrors()) {
            $this->cli->log(sprintf(
                'Errors are not recorded in the %s environment. Set recordErrors: true in app/foehn.config.php to record them.',
                Env::get(),
            ));
        }
    }
}
