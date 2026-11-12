<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Errors\ErrorLog;
use Studiometa\Foehn\Trace\TraceCommand;
use Studiometa\Foehn\Trace\TraceStore;

/**
 * A trace as the request tracer stores it.
 *
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function storedTrace(array $fields = []): array
{
    return [
        'id' => 'a1b2c3',
        'time' => '2026-11-12T10:00:00Z',
        'method' => 'GET',
        'url' => '/products/lamp/',
        'status' => 200,
        'location' => null,
        'durationMs' => 182,
        'queries' => 34,
        'hierarchy' => ['single-product-lamp.php', 'single-product.php', 'single.php', 'singular.php', 'index.php'],
        'templateFile' => 'theme/index.php',
        'template' => 'single-product',
        'controller' => 'App\\Controllers\\ProductController',
        'views' => [
            [
                'view' => 'pages/product.twig',
                'count' => 1,
                'keys' => ['post', 'site', 'user', 'price'],
                'candidates' => ['pages/product-lamp.twig', 'pages/product.twig'],
                'providers' => ['App\\Context\\GlobalContext', 'App\\Context\\ProductContext'],
            ],
            [
                'view' => 'partials/card.twig',
                'count' => 3,
                'keys' => ['card'],
                'candidates' => [],
                'providers' => [],
            ],
        ],
        'blocks' => ['core/paragraph' => 12, 'core/image' => 1],
        ...$fields,
    ];
}

/**
 * What the command printed with log(), in order.
 *
 * @return list<string>
 */
function traceOutput(): array
{
    return array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message');
}

/**
 * What the command printed with line(), decoded from JSON.
 */
function traceJson(): mixed
{
    $lines = array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message');

    return json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
}

function traceError(): ?string
{
    return wp_stub_get_calls('wp_cli_error')[0]['args']['message'] ?? null;
}

describe('wp foehn trace', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->directory = errorLogDirectory();
        $this->store = new TraceStore($this->directory . '/traces');
        $this->log = new ErrorLog($this->directory . '/errors');
        $this->command = new TraceCommand(new WpCli(), $this->store, $this->log, new FoehnConfig(traceRequests: true));
    });

    afterEach(function () {
        $this->store->clear();
        removeErrorLogDirectory($this->directory . '/traces');
        removeErrorLogDirectory($this->directory . '/errors');
        removeErrorLogDirectory($this->directory);
        unset($GLOBALS['wp_stub_home_url']);
    });

    it('prints the last trace in short sections, with the errors of that request', function () {
        $this->store->save(storedTrace());
        $this->log->append([
            'level' => 'warning',
            'class' => 'E_WARNING',
            'message' => 'Undefined array key "price"',
            'file' => 'theme/app/Controllers/ProductController.php',
            'line' => 24,
            'requestId' => 'a1b2c3',
        ]);
        $this->log->append(['level' => 'warning', 'message' => 'Another request', 'requestId' => 'ffffff']);

        ($this->command)([], []);

        expect(traceOutput())->toBe([
            'GET /products/lamp/ · 200 · 182 ms · 34 queries',
            '  trace a1b2c3 at 2026-11-12 10:00:00 UTC',
            'template',
            '  tried single-product-lamp.php, single-product.php, single.php, singular.php, index.php',
            '  found theme/index.php',
            '  matched single-product · controller App\\Controllers\\ProductController',
            'views',
            '  pages/product.twig · first of pages/product-lamp.twig, pages/product.twig',
            '    providers App\\Context\\GlobalContext, App\\Context\\ProductContext',
            '    context post, site, user, price',
            '  partials/card.twig ×3',
            '    context card',
            'blocks',
            '  core/paragraph ×12, core/image',
            'errors',
            '  warning E_WARNING: Undefined array key "price" · theme/app/Controllers/ProductController.php:24',
            'Every field: wp foehn trace a1b2c3 --format=json. The errors in full: wp foehn errors --request=a1b2c3 --limit=1.',
        ]);
    });

    it('cuts long lists and says how many it left out', function () {
        $this->store->save(storedTrace([
            'location' => 'https://example.com/products/lamp-2/',
            'status' => 301,
            'controller' => null,
            'views' => [[
                'view' => 'pages/product.twig',
                'count' => 1,
                'keys' => array_map(static fn(int $index): string => "key{$index}", range(1, 14)),
                'candidates' => [],
                'providers' => [],
            ]],
            'blocks' => [],
        ]));

        ($this->command)(['last'], []);
        $output = traceOutput();

        expect($output[2])->toBe('  redirects to https://example.com/products/lamp-2/');
        expect($output)->toContain('  matched single-product · no controller');
        expect($output)->toContain('    context key1, key2, key3, key4, key5, key6, key7, key8, key9, key10, … (+4)');
        expect($output)->not->toContain('blocks');
        expect(end($output))->toBe('Every field: wp foehn trace a1b2c3 --format=json.');
    });

    it('finds a trace by its id, and prints every field and its errors as JSON', function () {
        $this->store->save(storedTrace());
        usleep(2000);
        $this->store->save(storedTrace(['id' => 'ffffff', 'url' => '/other/']));
        $this->log->append(['level' => 'notice', 'message' => 'Traced', 'requestId' => 'a1b2c3']);

        ($this->command)(['a1b2c3'], ['format' => 'json']);
        $trace = traceJson();

        expect($trace['url'])->toBe('/products/lamp/');
        expect($trace['views'][0]['candidates'])->toBe(['pages/product-lamp.twig', 'pages/product.twig']);
        expect(array_column($trace['errors'], 'message'))->toBe(['Traced']);
    });

    it('requests a path of the site and prints the trace its response names', function () {
        $GLOBALS['wp_stub_home_url'] = 'https://site.test';
        $GLOBALS['wp_stub_remote_headers'] = ['X-Foehn-Trace' => 'a1b2c3'];
        $this->store->save(storedTrace());

        ($this->command)(['/products/lamp/'], []);

        $request = wp_stub_get_calls('wp_remote_get')[0]['args'];

        expect($request['url'])->toBe('https://site.test/products/lamp/');
        expect($request['args'])->toMatchArray(['redirection' => 0, 'cookies' => []]);
        expect(traceOutput()[0])->toBe('GET /products/lamp/ · 200 · 182 ms · 34 queries');
    });

    it('refuses a URL of another site', function () {
        $GLOBALS['wp_stub_home_url'] = 'https://site.test';

        ($this->command)(['https://elsewhere.test/'], []);

        expect(wp_stub_get_calls('wp_remote_get'))->toBe([]);
        expect(traceError())
            ->toBe('https://elsewhere.test/ is not a URL of this site (site.test). Give a path such as /about/.');
    });

    it('says why a response has no trace', function (array $headers, ?bool $traced, string $message) {
        $GLOBALS['wp_stub_home_url'] = 'https://site.test';
        $GLOBALS['wp_stub_remote_headers'] = $headers;
        $command = new TraceCommand(new WpCli(), $this->store, $this->log, new FoehnConfig(traceRequests: $traced));

        $command(['/about/'], []);

        expect(traceError())->toBe($message);
    })->with([
        'not traced' => [
            [],
            false,
            'Requests are not traced in the production environment. Set traceRequests: true in app/foehn.config.php to trace them.',
        ],
        'page cache' => [
            ['X-Foehn-Cache' => 'HIT'],
            true,
            'The page cache served https://site.test/about/, so no code ran. Run wp foehn cache:clear --url=https://site.test/about/ and try again.',
        ],
        'no header' => [
            [],
            true,
            'The response of https://site.test/about/ (status 200) has no X-Foehn-Trace header: WordPress did not render it as a front-end page. A file the web server serves, an admin or REST URL, or a request that ends before WordPress sends its headers is not traced.',
        ],
    ]);

    it('says when the request failed', function () {
        $GLOBALS['wp_stub_remote_error'] = true;

        ($this->command)(['/about/'], []);

        expect(traceError())->toBe('Could not request http://example.com/about/: stubbed failure');
    });

    it('lists the last requests, one per line', function () {
        $this->store->save(storedTrace());
        usleep(2000);
        $this->store->save(storedTrace(['id' => 'ffffff', 'url' => '/', 'status' => 404, 'durationMs' => 40]));

        ($this->command)(['list'], ['limit' => '5']);
        ($this->command)(['list'], ['format' => 'json']);

        expect(traceOutput())->toBe([
            'ffffff  2026-11-12 10:00:00  404  40ms  GET /',
            'a1b2c3  2026-11-12 10:00:00  200  182ms  GET /products/lamp/',
        ]);
        expect(array_keys(traceJson()[0]))->toBe(['id', 'time', 'method', 'url', 'status', 'durationMs', 'queries']);
    });

    it('says when nothing is traced, and when tracing is off', function () {
        $command = new TraceCommand(new WpCli(), $this->store, $this->log, new FoehnConfig());

        $command([], []);

        expect(traceOutput())->toBe([
            'No requests traced.',
            'Requests are not traced in the production environment. Set traceRequests: true in app/foehn.config.php to trace them.',
        ]);
    });

    it('refuses an unknown request, id, format or limit', function (array $args, array $options, string $message) {
        ($this->command)($args, $options);

        expect(traceError())->toBe($message);
    })->with([
        'request' => [
            ['about'],
            [],
            "'about' is not a request. Give a path such as /about/, a URL, a trace id, last or list.",
        ],
        'id' => [['abcdef'], [], 'No trace abcdef. The store keeps the last 100 requests: wp foehn trace list.'],
        'format' => [[], ['format' => 'yaml'], 'Unknown format. Use text or json.'],
        'limit' => [['list'], ['limit' => '0'], '--limit must be a number of 1 or more.'],
    ]);

    it('clears the traces', function () {
        $this->store->save(storedTrace());

        ($this->command)([], ['clear' => true]);

        expect($this->store->last())->toBeNull();
        expect(wp_stub_get_calls('wp_cli_success')[0]['args']['message'])->toBe('Deleted 1 trace.');
    });
});
