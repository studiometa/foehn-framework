<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Errors\ErrorLog;
use Studiometa\Foehn\Errors\ErrorsCommand;

/**
 * An entry as the recorder writes it.
 *
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function errorEntry(array $fields = []): array
{
    return [
        'time' => '2026-11-12T10:00:00Z',
        'level' => 'exception',
        'class' => 'Studiometa\\Foehn\\Views\\ViewRenderingFailed',
        'message' => "Rendering the view 'pages/product' failed in partials/card.twig at line 3: Variable \"price\" does not exist.",
        'file' => 'theme/app/Controllers/ProductController.php',
        'line' => 24,
        'twig' => 'partials/card.twig:3',
        'previous' => ['Twig\\Error\\RuntimeError: Variable "price" does not exist.'],
        'stack' => [
            'theme/app/Controllers/ProductController.php:24 App\\Controllers\\ProductController->handle()',
            '+31 other frames',
        ],
        'method' => 'GET',
        'url' => '/products/lamp/',
        'template' => 'single-product',
        'controller' => 'App\\Controllers\\ProductController',
        'view' => 'pages/product.twig',
        'context' => ['post', 'site', 'user'],
        'requestId' => null,
        ...$fields,
    ];
}

/**
 * What the command printed with log() and line(), in order of call within each.
 *
 * @return list<string>
 */
function errorsOutput(): array
{
    return array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message');
}

describe('wp foehn errors', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->directory = errorLogDirectory();
        $this->log = new ErrorLog($this->directory);
        $this->command = new ErrorsCommand(new WpCli(), $this->log, new FoehnConfig(recordErrors: true));
    });

    afterEach(fn() => removeErrorLogDirectory($this->directory));

    it('prints the last entry in a few lines', function () {
        $this->log->append(errorEntry(['message' => 'Older']));
        $this->log->append(errorEntry());

        ($this->command)([], []);

        expect(errorsOutput())->toBe([
            "Studiometa\\Foehn\\Views\\ViewRenderingFailed: Rendering the view 'pages/product' failed in partials/card.twig at line 3: Variable \"price\" does not exist.",
            '  exception at 2026-11-12 10:00:00 UTC in theme/app/Controllers/ProductController.php:24',
            '  twig partials/card.twig:3',
            '  caused by Twig\\Error\\RuntimeError: Variable "price" does not exist.',
            '  GET /products/lamp/ · template single-product · controller App\\Controllers\\ProductController · view pages/product.twig',
            '  context post, site, user',
            '    theme/app/Controllers/ProductController.php:24 App\\Controllers\\ProductController->handle()',
            '    +31 other frames',
            '1 more: wp foehn errors --limit=2. Every field: --format=json.',
        ]);
    });

    it('says when nothing is recorded, and when recording is off', function () {
        (new ErrorsCommand(new WpCli(), $this->log, new FoehnConfig()))([], []);

        expect(errorsOutput())->toBe([
            'No errors recorded.',
            'Errors are not recorded in the production environment. Set recordErrors: true in app/foehn.config.php to record them.',
        ]);
    });

    it('prints the entries as JSON on one line', function () {
        $this->log->append(errorEntry(['message' => 'First']));
        $this->log->append(errorEntry(['message' => 'Second']));

        ($this->command)([], ['format' => 'json', 'limit' => '5']);

        $lines = array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message');

        expect($lines)->toHaveCount(1);
        expect(array_column(json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR), 'message'))->toBe([
            'Second',
            'First',
        ]);
    });

    it('cuts a long message in the text, and keeps it whole in JSON', function () {
        $message = str_repeat('a', 1500);
        $this->log->append(errorEntry(['message' => $message, 'previous' => ['RuntimeException: ' . $message]]));

        ($this->command)([], []);
        $output = errorsOutput();

        expect($output[0])->toBe('Studiometa\\Foehn\\Views\\ViewRenderingFailed: ' . str_repeat('a', 300) . '…');
        expect($output[3])->toBe('  caused by ' . mb_substr('RuntimeException: ' . $message, 0, 300) . '…');

        ($this->command)([], ['format' => 'json']);
        $lines = array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message');

        expect(json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR)[0]['message'])->toBe($message);
    });

    it('filters by level and by date', function () {
        $this->log->append(errorEntry([
            'level' => 'warning',
            'message' => 'Old warning',
            'time' => '2026-11-12T08:00:00Z',
        ]));
        $this->log->append(errorEntry(['level' => 'fatal', 'message' => 'Fatal']));
        $this->log->append(errorEntry(['level' => 'warning', 'message' => 'New warning']));

        ($this->command)([], ['format' => 'json', 'limit' => '10', 'level' => 'warning']);
        ($this->command)([], ['format' => 'json', 'limit' => '10', 'since' => '2026-11-12 09:00']);
        ($this->command)([], ['format' => 'json', 'limit' => '10', 'level' => 'fatal,exception']);

        $printed = array_map(
            static fn(string $line): array => array_column(
                json_decode($line, true, flags: JSON_THROW_ON_ERROR),
                'message',
            ),
            array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'),
        );

        expect($printed)->toBe([
            ['New warning', 'Old warning'],
            ['New warning', 'Fatal'],
            ['Fatal'],
        ]);
    });

    it('filters by the id of a traced request', function () {
        $this->log->append(errorEntry(['message' => 'Other request', 'requestId' => 'bbbbbb']));
        $this->log->append(errorEntry(['message' => 'Traced request', 'requestId' => 'aaaaaa']));
        $this->log->append(errorEntry(['message' => 'Not traced']));

        ($this->command)([], ['format' => 'json', 'limit' => '10', 'request' => 'aaaaaa']);

        $lines = array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message');

        expect(array_column(json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR), 'message'))->toBe([
            'Traced request',
        ]);
    });

    it('refuses an unknown level, format, limit or date', function (array $options, string $message) {
        ($this->command)([], $options);

        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])->toBe($message);
    })->with([
        'level' => [['level' => 'error'], "Unknown level 'error'. Use exception, fatal, warning, notice."],
        'format' => [['format' => 'yaml'], 'Unknown format. Use text or json.'],
        'limit' => [['limit' => '0'], '--limit must be a number of 1 or more.'],
        'date' => [['since' => 'not a date'], "'not a date' is not a date."],
    ]);

    it('clears the log', function () {
        $this->log->append(errorEntry());

        ($this->command)([], ['clear' => true]);

        expect($this->log->entries())->toBe([]);
        expect(wp_stub_get_calls('wp_cli_success')[0]['args']['message'])->toBe('Deleted the error log.');
    });
});
