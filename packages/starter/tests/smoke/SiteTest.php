<?php

declare(strict_types=1);

/**
 * The site boots, serves its home page and runs the theme of this project.
 *
 * Needs a started ddev project: `ddev start`, then `composer test:smoke`. The unit tests
 * run against WordPress function stubs, so they pass even when WordPress cannot boot.
 * This test asks the real site. Add a test here for each page a release must not break.
 */

/**
 * Run a WP-CLI command in the ddev web container and return the last line it printed.
 */
function smokeWp(string $command): string
{
    $output = [];
    $status = 0;

    exec('ddev exec ' . escapeshellarg('cd /var/www/html && wp ' . $command) . ' 2>/dev/null', $output, $status);

    expect($status)->toBe(0, "`ddev exec wp {$command}` failed. Is ddev started?");

    $lines = array_values(array_filter(
        array_map('trim', $output),
        static fn(string $line): bool => $line !== '' && !str_contains($line, 'Deprecated'),
    ));

    return $lines === [] ? '' : $lines[array_key_last($lines)];
}

it('serves the home page with no PHP error', function () {
    $home = rtrim(smokeWp('option get home'), '/');
    $context = stream_context_create([
        'http' => ['ignore_errors' => true, 'follow_location' => 0],
        // ddev serves a local certificate.
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $body = (string) file_get_contents($home . '/', false, $context);
    $status = http_get_last_response_headers()[0] ?? '';

    expect($status)->toMatch('#^HTTP/\S+ 200\b#', "{$home}/ answered {$status}");
    // A PHP error in a template can come with a 200, so the body is checked too.
    expect(preg_match('/Fatal error|Uncaught|Parse error/i', $body))->toBe(0, 'the home page contains a PHP error');
});

it('runs the theme of this project', function () {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

    expect(smokeWp('option get stylesheet'))->toBe($composer['extra']['foehn']['theme-name']);
});
