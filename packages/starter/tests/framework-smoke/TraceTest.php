<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Client;
use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `wp foehn trace`, against a real WordPress in the `local` environment.
 *
 * The unit suite proves what the tracer keeps and what the command prints. What only
 * a real site can show is that WordPress sends the X-Foehn-Trace header on a page,
 * that the trace is on disk by the time the response ends, and that the command can
 * request the site from inside the container.
 */

/**
 * The value the command printed as JSON.
 *
 * Site::run() merges stderr, where this image prints PHP deprecations, so the JSON is
 * the last line that decodes to an array.
 *
 * @return array<mixed>
 */
function printedTrace(string $command): array
{
    $result = Site::run($command);

    foreach (array_reverse(explode("\n", $result['output'])) as $line) {
        $value = json_decode(trim($line), true);

        if (is_array($value)) {
            return $value;
        }
    }

    throw new RuntimeException("`{$command}` printed no JSON:\n{$result['output']}");
}

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');

    Site::run('wp foehn trace --clear');
});

afterEach(fn() => Site::run('wp foehn trace --clear'));

it('sends the trace id of a page in a header, and stores what the page ran', function () {
    $response = Client::get((string) Site::url() . '/');
    $id = $response->header('X-Foehn-Trace');

    expect($response->status)->toBe(200);
    expect($id)->toMatch('/^[0-9a-f]{6}$/');

    $trace = printedTrace("wp foehn trace {$id} --format=json");

    expect($trace['id'])->toBe($id);
    expect($trace['url'])->toBe('/');
    expect($trace['status'])->toBe(200);
    expect($trace['hierarchy'])->toContain('index.php');
    expect($trace['templateFile'])->toBeString();
    expect($trace['views'])->not->toBe([]);
    expect($trace['views'][0]['keys'])->toContain('site');
    expect($trace['queries'])->toBeInt();
    expect($trace['errors'])->toBe([]);
});

it('requests a path itself, and prints the trace in a few lines', function () {
    $result = Site::run('wp foehn trace /');

    expect($result['status'])->toBe(0, $result['output']);
    expect($result['output'])->toContain('GET / · 200', "\ntemplate\n", "\nviews\n", 'Every field: wp foehn trace ');
    expect(substr_count(trim($result['output']), "\n"))->toBeLessThan(40);

    // A browser asks for robots.txt after the page: it must not become the last trace.
    Client::get((string) Site::url() . '/robots.txt');

    expect(printedTrace('wp foehn trace list --format=json'))->toHaveCount(1);
});

it('gives the errors of a request its id', function () {
    Site::run('wp foehn errors --clear');
    Site::run('wp foehn trace /');
    $id = printedTrace('wp foehn trace last --format=json')['id'];

    expect(printedTrace("wp foehn errors --request={$id} --format=json"))->toBe([]);
    expect(printedTrace('wp foehn trace /trace-smoke-missing-page/ --format=json')['status'])->toBe(404);
});
