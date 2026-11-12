<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * `wp foehn inspect`, against a real WordPress.
 *
 * The unit suite proves the report against stubs and fixture classes. What only a real
 * site can show is that the command is registered, boots, and prints JSON a program can
 * decode, with what the starter actually declares in it.
 */

/**
 * Run the command and decode what it printed.
 *
 * Site::run() merges stderr, where this image prints PHP deprecations from WP-CLI's own
 * vendor directory, so the JSON is cut from the first `{` to the last `}`.
 *
 * @return array{status: int, output: string, report: mixed}
 */
function inspectSite(string $arguments = ''): array
{
    $result = Site::run(trim('wp foehn inspect --format=json ' . $arguments));
    $output = $result['output'];
    $start = strpos($output, '{');
    $end = strrpos($output, '}');

    $report = $start === false || $end === false ? null : json_decode(substr($output, $start, $end - $start + 1), true);

    return ['status' => $result['status'], 'output' => $output, 'report' => $report];
}

beforeEach(
    fn() => Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.'),
);

it('prints a summary of the project model as JSON', function () {
    $result = inspectSite();

    expect($result['status'])->toBe(0, $result['output']);
    expect($result['report'])->toBeArray($result['output']);
    expect(array_keys($result['report']['summary']))
        ->toContain('problems', 'post-types', 'settings', 'templates', 'views', 'menus', 'plugins', 'vite');
});

it('reports no problems: every class the starter declares registers', function () {
    $report = inspectSite('problems')['report'];

    expect($report['problems'])->toBe([]);
});

it('reports no problems in discovery:status either', function () {
    $result = Site::run('wp foehn discovery:status');

    expect($result['status'])->toBe(0, $result['output']);
    expect($result['output'])->toContain('Problems: 0');
});

it('reports what the starter declares', function () {
    $report = inspectSite('--all')['report'];

    expect(array_column($report['templates']['templates'], 'controller'))
        ->toContain('App\\Controllers\\SingleController');
    expect(array_column($report['context-providers'], 'class'))
        ->toContain('App\\ContextProviders\\GlobalContextProvider');
    expect(array_column($report['menus'], 'location'))->toContain('header');
    expect(array_column($report['cli-commands'], 'command'))->toContain('foehn inspect');
});

it('reports the controller of a template and the context of a Twig view', function () {
    $report = inspectSite('templates views')['report'];

    $single = array_values(array_filter(
        $report['templates']['templates'],
        static fn(array $row): bool => $row['template'] === 'single',
    ));
    $view = array_values(array_filter(
        $report['views']['views'],
        static fn(array $row): bool => $row['view'] === 'pages/single',
    ));

    expect($single[0]['controller'])->toBe('App\\Controllers\\SingleController');
    expect($report['templates']['base'])->toContain('post', 'site', 'menus');
    expect($view[0]['file'])->toBe('templates/pages/single.twig');
    // The starter's global provider matches every view, so it is listed once.
    expect($report['views']['everyView'])->toContain('App\\ContextProviders\\GlobalContextProvider');
    expect($report['views']['providers']['App\\ContextProviders\\GlobalContextProvider']['keys'])
        ->toContain('current_year');
});

it('prints the named sections on their own', function () {
    $result = inspectSite('menus post-types');

    expect($result['status'])->toBe(0, $result['output']);
    expect(array_keys($result['report']))->toBe(['menus', 'post-types']);
});

it('refuses an unknown section', function () {
    $result = Site::run('wp foehn inspect widgets');

    expect($result['status'])->not->toBe(0);
    expect($result['output'])->toContain("Unknown section 'widgets'");
});
