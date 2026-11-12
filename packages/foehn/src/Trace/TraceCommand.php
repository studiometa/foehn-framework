<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Trace;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Errors\ErrorLog;
use Studiometa\Foehn\Helpers\Env;

#[AsCliCommand(name: 'trace', description: 'Show what ran for a front-end request', longDescription: <<<'DOC'
    ## DESCRIPTION

    Prints what a front-end request ran: the method, URL, status, time and
    number of queries, the template files WordPress tried and the one it
    found, the WordPress template and its controller, the Twig views
    rendered (with the views `renderFirst()` tried, the context providers
    and the context keys of each), the blocks rendered, and the errors the
    request recorded.

    Requests are traced in the `local` and `development` environments. Set
    `traceRequests` in `app/foehn.config.php` (FoehnConfig) to change this.
    Each traced request sends its id in the `X-Foehn-Trace` response
    header. The last 100 traces are kept in `wp-content/cache/foehn/traces`.

    ## OPTIONS

    [<request>]
    : A path such as `/about/` or a URL of the site: the command requests
      it, then prints its trace. A trace id, as the X-Foehn-Trace header
      gives it. `last` (the default): the last traced request. `list`: the
      last traced requests, one per line.

    [--limit=<n>]
    : With `list`, how many requests to print. Defaults to 10.

    [--format=<format>]
    : Output format. text (default) or json.

    [--clear]
    : Delete every trace.

    ## EXAMPLES

        # Request a page and print what ran
        wp foehn trace /about/

        # The last request a browser made
        wp foehn trace last

        # Every field of one request, for an agent
        wp foehn trace 3fa2c1 --format=json

        # The last 20 requests
        wp foehn trace list --limit=20
    DOC)]
final readonly class TraceCommand implements CliCommandInterface
{
    /** @var list<string> */
    private const array FORMATS = ['text', 'json'];

    /** How long to wait for the trace of a request the command made, in steps of 50 ms. */
    private const int WAIT_STEPS = 20;

    public function __construct(
        private WpCli $cli,
        private TraceStore $store,
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
            $deleted = $this->store->clear();
            $this->cli->success(match ($deleted) {
                0 => 'No traces to delete.',
                1 => 'Deleted 1 trace.',
                default => sprintf('Deleted %d traces.', $deleted),
            });

            return;
        }

        $format = $assocArgs['format'] ?? 'text';

        if (!is_string($format) || !in_array($format, self::FORMATS, true)) {
            $this->cli->error('Unknown format. Use text or json.');

            return;
        }

        $request = $args[0] ?? 'last';

        if ($request === 'list') {
            $this->list($assocArgs['limit'] ?? '10', $format);

            return;
        }

        $trace = match (true) {
            $request === 'last' => $this->store->last(),
            preg_match(TraceStore::ID_PATTERN, $request) === 1 => $this->store->find($request),
            str_starts_with($request, '/') || preg_match('#^https?://#i', $request) === 1 => $this->request($request),
            default => false,
        };

        if ($trace === false) {
            $this->cli->error(sprintf(
                "'%s' is not a request. Give a path such as /about/, a URL, a trace id, last or list.",
                $request,
            ));

            return;
        }

        if ($trace === null) {
            $this->notFound($request);

            return;
        }

        $id = $trace['id'] ?? null;
        $errors = array_values(array_filter(
            $this->log->entries(),
            static fn(array $entry): bool => is_string($id) && ($entry['requestId'] ?? null) === $id,
        ));

        if ($format === 'json') {
            $this->cli->line(self::json([...$trace, 'errors' => $errors]));

            return;
        }

        foreach (TraceText::lines($trace, $errors) as $line) {
            $this->cli->log($line);
        }
    }

    /**
     * Request a page of the site, then read the trace whose id its response gives.
     *
     * @return array<string, mixed>|null The trace, or null after an error message
     */
    private function request(string $request): ?array
    {
        $url = str_starts_with($request, '/') ? home_url($request) : $request;
        $host = parse_url(home_url('/'), PHP_URL_HOST);

        if (strtolower((string) parse_url($url, PHP_URL_HOST)) !== strtolower((string) $host)) {
            $this->cli->error(sprintf(
                '%s is not a URL of this site (%s). Give a path such as /about/.',
                $url,
                (string) $host,
            ));

            return null;
        }

        // No cookies and no redirects: the trace of an anonymous visit to that URL, and
        // of that URL only. A redirect is traced too, with the place it sends to.
        // The certificate is not checked: the request goes to this site only, a local
        // site often has a certificate WordPress's own CA bundle does not know, and the
        // response gives nothing but an id the command reads back from its own disk.
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'cookies' => [],
            'redirection' => 0,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            $this->cli->error(sprintf('Could not request %s: %s', $url, $response->get_error_message()));

            return null;
        }

        $id = wp_remote_retrieve_header($response, RequestTracer::HEADER);

        if (!is_string($id) || preg_match(TraceStore::ID_PATTERN, $id) !== 1) {
            $this->cli->error($this->noHeader($url, $response));

            return null;
        }

        // The site writes the trace when the request ends: wait for it a moment.
        for ($step = 0; $step < self::WAIT_STEPS; $step++) {
            $trace = $this->store->find($id);

            if ($trace !== null) {
                return $trace;
            }

            usleep(50_000);
        }

        $this->cli->error(sprintf(
            'The response of %s has the trace id %s, but %s has no trace for it. Check that the site can write there.',
            $url,
            $id,
            $this->store->directory(),
        ));

        return null;
    }

    /**
     * Why a response has no X-Foehn-Trace header, from what the response says.
     *
     * @param array<string, mixed> $response
     */
    private function noHeader(string $url, array $response): string
    {
        if (!$this->config->tracesRequests()) {
            return sprintf(
                'Requests are not traced in the %s environment. Set traceRequests: true in app/foehn.config.php to trace them.',
                Env::get(),
            );
        }

        $cache = wp_remote_retrieve_header($response, 'X-Foehn-Cache');

        if (is_string($cache) && strtoupper($cache) === 'HIT') {
            return sprintf(
                'The page cache served %s, so no code ran. Run wp foehn cache:clear --url=%s and try again.',
                $url,
                $url,
            );
        }

        return sprintf(
            'The response of %s (status %s) has no %s header: WordPress did not render it as a front-end page. A file the web server serves, an admin or REST URL, or a request that ends before WordPress sends its headers is not traced.',
            $url,
            (string) wp_remote_retrieve_response_code($response),
            RequestTracer::HEADER,
        );
    }

    private function notFound(string $request): void
    {
        if (preg_match(TraceStore::ID_PATTERN, $request) === 1) {
            $this->cli->error(sprintf(
                'No trace %s. The store keeps the last %d requests: wp foehn trace list.',
                $request,
                TraceStore::MAX_TRACES,
            ));

            return;
        }

        if ($request === 'last') {
            $this->cli->log('No requests traced.');
            $this->notTraced();
        }
    }

    private function list(mixed $limit, string $format): void
    {
        if (!is_string($limit) || !ctype_digit($limit) || (int) $limit < 1) {
            $this->cli->error('--limit must be a number of 1 or more.');

            return;
        }

        $summaries = array_map(static fn(array $trace): array => array_intersect_key($trace, array_flip([
            'id',
            'time',
            'method',
            'url',
            'status',
            'durationMs',
            'queries',
        ])), $this->store->latest(min((int) $limit, TraceStore::MAX_TRACES)));

        if ($format === 'json') {
            $this->cli->line(self::json($summaries));

            return;
        }

        if ($summaries === []) {
            $this->cli->log('No requests traced.');
            $this->notTraced();
        }

        foreach ($summaries as $trace) {
            $this->cli->log(implode('  ', array_map(
                static fn(mixed $value): string => is_scalar($value) ? (string) $value : '-',
                [
                    $trace['id'] ?? null,
                    str_replace(['T', 'Z'], [' ', ''], is_string($trace['time'] ?? null) ? $trace['time'] : ''),
                    $trace['status'] ?? null,
                    is_int($trace['durationMs'] ?? null) ? $trace['durationMs'] . 'ms' : null,
                    trim(
                        (is_string($trace['method'] ?? null) ? $trace['method'] : '')
                        . ' '
                        . (is_string($trace['url'] ?? null) ? $trace['url'] : ''),
                    ),
                ],
            )));
        }
    }

    private function notTraced(): void
    {
        if ($this->config->tracesRequests()) {
            return;
        }

        $this->cli->log(sprintf(
            'Requests are not traced in the %s environment. Set traceRequests: true in app/foehn.config.php to trace them.',
            Env::get(),
        ));
    }

    private static function json(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
