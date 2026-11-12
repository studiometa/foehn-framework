<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `wp foehn seed` and `make:seeder`, against a real WordPress.
 *
 * The unit suite runs seeders from fixture classes. What only a real site can show is
 * that both commands are registered and end with the right exit status. The starter
 * ships one seeder, MenusSeeder, and `make:seeder` runs with `--dry-run`, so it writes
 * no file.
 */

beforeEach(
    fn() => Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.'),
);

it('runs the starter seeder twice and creates nothing the second time', function () {
    $first = Site::run('wp foehn seed < /dev/null');
    $menus = Site::wp('wp menu list --format=count');
    $items = Site::wp('wp menu item list Header --format=count');
    $second = Site::run('wp foehn seed < /dev/null');

    expect($first['status'])->toBe(0, $first['output']);
    expect($first['output'])->toContain('App\\Seeders\\MenusSeeder');
    expect($second['status'])->toBe(0, $second['output']);
    expect((int) $items)->toBeGreaterThan(0);
    expect(Site::wp('wp menu list --format=count'))->toBe($menus);
    expect(Site::wp('wp menu item list Header --format=count'))->toBe($items);
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

it('refuses to seed or generate in production without --force', function () {
    foreach (['wp foehn seed', 'wp foehn make:seeder events'] as $command) {
        $result = Site::run("env WP_ENVIRONMENT_TYPE=production {$command} < /dev/null");

        expect($result['status'])->toBe(1, $result['output']);
        expect($result['output'])->toContain("changes content or files, and wp_get_environment_type() is 'production'");
    }

    expect(Site::path('theme/app/Seeders/EventsSeeder.php'))->not->toBeFile();
});

it('runs a dry run in production, and declares --force', function () {
    $seed = Site::run('env WP_ENVIRONMENT_TYPE=production wp foehn seed --dry-run < /dev/null');
    $help = Site::run('env WP_ENVIRONMENT_TYPE=production wp help foehn seed < /dev/null');

    expect($seed['status'])->toBe(0, $seed['output']);
    expect($help['output'])->toContain('[--force]');
});
