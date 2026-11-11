<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Inspect\InspectCommand;
use Studiometa\Foehn\Inspect\ProjectInspector;
use Tests\Fixtures\InspectApp\Product;

function inspectCommand(): InspectCommand
{
    return new InspectCommand(
        new WpCli(),
        new ProjectInspector(testDiscoveryRunner(bootTestContainer(), testFixturePath('InspectApp'))),
    );
}

/**
 * What the command printed with log() and line(), in that order.
 */
function inspectOutput(): string
{
    return implode("\n", [
        ...array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'),
        ...array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message'),
    ]);
}

/**
 * The JSON the command printed, decoded.
 *
 * @return array<string, mixed>
 */
function inspectJson(): array
{
    $lines = array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message');

    expect($lines)->toHaveCount(1);

    return json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(fn() => wp_stub_reset());

afterEach(fn() => tearDownTestContainer());

describe('wp foehn inspect', function () {
    it('prints every section as JSON', function () {
        inspectCommand()([], ['format' => 'json']);

        $report = inspectJson();

        expect(array_keys($report))->toBe(ProjectInspector::SECTIONS);
        expect(array_column($report['post-types'], 'model'))->toContain(Product::class);
    });

    it('prints one section as JSON under its own name', function () {
        inspectCommand()(['menus'], ['format' => 'json']);

        expect(inspectJson())->toBe([
            'menus' => [
                [
                    'location' => 'primary',
                    'description' => 'Main navigation',
                    'class' => 'Tests\\Fixtures\\InspectApp\\PrimaryMenu',
                    'contextKey' => 'menus.primary',
                ],
            ],
        ]);
    });

    it('prints a table by default', function () {
        inspectCommand()(['post-types'], []);

        $output = inspectOutput();

        // product, core post through its Timber model, and the framework's own page model.
        expect($output)->toContain('post-types — 3');
        expect($output)->toMatch('/name\s+model\s+taxonomies\s+meta/');
        expect($output)->toMatch('/product\s+' . preg_quote(Product::class, '/') . '\s+category, brand\s+price, sku/');
        expect(wp_stub_get_calls('wp_cli_error'))->toBe([]);
    });

    it('prints every section as a table', function () {
        inspectCommand()([], []);

        $output = inspectOutput();

        foreach (ProjectInspector::SECTIONS as $section) {
            expect($output)->toContain($section . ' —');
        }
    });

    it('refuses an unknown section and names the valid ones', function () {
        inspectCommand()(['widgets'], []);

        $errors = wp_stub_get_calls('wp_cli_error');

        expect($errors)->toHaveCount(1);
        expect($errors[0]['args']['message'])->toContain("Unknown section 'widgets'");
        expect($errors[0]['args']['message'])->toContain('post-types, taxonomies');
        expect(wp_stub_get_calls('wp_cli_line'))->toBe([]);
    });

    it('refuses an unknown format', function () {
        inspectCommand()([], ['format' => 'yaml']);

        $errors = wp_stub_get_calls('wp_cli_error');

        expect($errors)->toHaveCount(1);
        expect($errors[0]['args']['message'])->toBe("Unknown format 'yaml'. Use table or json.");
    });
});
