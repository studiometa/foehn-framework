<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `wp foehn seed` and `make:seeder`, against a real WordPress.
 *
 * The unit suite runs seeders from fixture classes. What only a real site can show is
 * that both commands are registered and end with the right exit status. The starter
 * ships no seeder, and `make:seeder` runs with `--dry-run`, so nothing is written.
 */

beforeEach(
    fn() => Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.'),
);

it('says how to create a seeder when the project has none', function () {
    $result = Site::run('wp foehn seed < /dev/null');

    expect($result['status'])->toBe(0, $result['output']);
    expect($result['output'])->toContain('No seeders found. Create one with: wp foehn make:seeder <name>');
});

it('reports the seeder make:seeder would write, and writes nothing', function () {
    $result = Site::run('wp foehn make:seeder events --dry-run --format=json < /dev/null');
    $output = $result['output'];
    $start = strpos($output, '{');
    $end = strrpos($output, '}');
    $report = $start === false || $end === false ? null : json_decode(substr($output, $start, $end - $start + 1), true);

    expect($result['status'])->toBe(0, $output);
    expect($report['files'][0]['status'] ?? null)->toBe('would-create', $output);
    expect($report['files'][0]['path'])->toEndWith('/app/Seeders/EventsSeeder.php');
    expect($report['files'][0]['contents'])->toContain('#[AsSeeder(');
    expect(Site::path('theme/app/Seeders/EventsSeeder.php'))->not->toBeFile();
});
