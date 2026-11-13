<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The `make:*` commands, run the way a coding agent runs them: no terminal, JSON out.
 *
 * The unit suite proves every generator against a temporary app directory. What only a
 * real site can show is that the commands are registered, take `--format=json` through
 * WP-CLI, and end with the right exit status instead of waiting for input. Both cases
 * use `--dry-run`, so the starter's theme is never written to.
 */

/**
 * Run a generator with stdin closed and decode what it printed.
 *
 * Site::run() merges stderr, where this image prints PHP deprecations from WP-CLI's own
 * vendor directory, so the JSON is cut from the first `{` to the last `}`.
 *
 * @return array{status: int, output: string, report: mixed}
 */
function generateOnSite(string $command): array
{
    $result = Site::run($command . ' --dry-run --format=json < /dev/null');
    $output = $result['output'];
    $start = strpos($output, '{');
    $end = strrpos($output, '}');

    $report = $start === false || $end === false ? null : json_decode(substr($output, $start, $end - $start + 1), true);

    return ['status' => $result['status'], 'output' => $output, 'report' => $report];
}

beforeEach(
    fn() => Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.'),
);

it('reports the file a generator would write, and writes nothing', function () {
    $result = generateOnSite('wp foehn make:block smoke-probe');

    expect($result['status'])->toBe(0, $result['output']);
    expect($result['report']['dryRun'] ?? null)->toBeTrue($result['output']);
    expect($result['report']['files'][0]['status'])->toBe('would-create');
    expect($result['report']['files'][0]['path'])->toEndWith('/app/Blocks/SmokeProbeBlock.php');
    expect($result['report']['files'][0]['contents'])->toContain('class SmokeProbeBlock');
    expect(Site::path('theme/app/Blocks/SmokeProbeBlock.php'))->not->toBeFile();
});

it('fails, without a prompt, when the file already exists', function () {
    $result = generateOnSite('wp foehn make:controller single --class=SingleController');

    expect($result['status'])->toBe(1, $result['output']);
    expect($result['report']['files'][0]['status'] ?? null)->toBe('exists', $result['output']);
    expect($result['output'])->toContain('Use --force to overwrite.');
});
