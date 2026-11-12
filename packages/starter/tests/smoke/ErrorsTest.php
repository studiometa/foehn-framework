<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `wp foehn errors`, against a real WordPress in the `local` environment.
 *
 * The unit suite proves what the recorder writes for each kind of error. What only a
 * real site can show is that the kernel starts the recorder, that an uncaught
 * exception still ends the process with PHP's fatal error, and that the command reads
 * back what the recorder wrote.
 */

/**
 * The entries the command printed as JSON.
 *
 * Site::run() merges stderr, so the JSON is cut from the first `[` to the last `]`.
 *
 * @return list<array<string, mixed>>
 */
function recordedErrors(): array
{
    $output = Site::run('wp foehn errors --format=json --limit=5')['output'];
    $start = strpos($output, '[');
    $end = strrpos($output, ']');
    $entries =
        $start === false || $end === false ? null : json_decode(substr($output, $start, $end - $start + 1), true);

    return is_array($entries) ? $entries : [];
}

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');

    Site::run('wp foehn errors --clear');
});

afterEach(fn() => Site::run('wp foehn errors --clear'));

it('records an uncaught exception, and PHP still ends the process with its fatal error', function () {
    $result = Site::run("wp eval 'throw new RuntimeException(\"Errors smoke test\");'");

    expect($result['status'])->not->toBe(0, $result['output']);
    expect($result['output'])->toContain('Errors smoke test');

    $entries = recordedErrors();

    expect($entries)->toHaveCount(1);
    expect($entries[0]['level'])->toBe('exception');
    expect($entries[0]['class'])->toBe(RuntimeException::class);
    expect($entries[0]['message'])->toBe('Errors smoke test');
    expect($entries[0]['method'])->toBe('CLI');
    expect($entries[0]['url'])->toContain('wp eval');
    expect($entries[0])->toHaveKey('requestId');
});

it('prints the last error in a few lines', function () {
    Site::run("wp eval 'throw new LogicException(\"Errors smoke text\");'");

    $output = Site::run('wp foehn errors')['output'];

    expect($output)->toContain('LogicException: Errors smoke text');
    expect(substr_count(trim($output), "\n"))->toBeLessThan(15);
});

it('clears the log', function () {
    Site::run("wp eval 'throw new RuntimeException(\"Errors smoke clear\");'");
    Site::run('wp foehn errors --clear');

    expect(recordedErrors())->toBe([]);
});
