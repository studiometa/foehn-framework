<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Inspect\InspectCommand;
use Studiometa\Foehn\Inspect\ProjectInspector;
use Tests\Fixtures\InspectApp\GlobalContext;
use Tests\Fixtures\InspectApp\Product;
use Tests\Fixtures\InspectApp\ProductCardContext;
use Tests\Fixtures\ProblemApp\Article;
use Tests\Fixtures\ProblemApp\Gallery;
use Tests\Fixtures\ProblemApp\Shelf;
use Timber\Timber;

function inspectCommand(string $app = 'InspectApp'): InspectCommand
{
    return new InspectCommand(
        new WpCli(),
        new ProjectInspector(testDiscoveryRunner(bootTestContainer(), testFixturePath($app))),
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
    it('prints a summary as JSON by default', function () {
        inspectCommand()([], ['format' => 'json']);

        $summary = inspectJson();

        expect(array_keys($summary))->toBe(['summary', 'commands']);
        expect(array_keys($summary['summary']))->toBe(ProjectInspector::SECTIONS);
        expect($summary['summary']['menus'])->toBe(['primary']);
        expect($summary['summary']['problems'])->toBe([]);
        expect($summary['commands'])->toBe([
            'problems' => 'wp foehn inspect problems --format=json',
            'sections' => 'wp foehn inspect <section>... --format=json',
            'all' => 'wp foehn inspect --all --format=json',
        ]);
    });

    it('prints a summary table by default', function () {
        inspectCommand()([], []);

        $output = inspectOutput();

        expect($output)->toContain('menus — 1: primary');
        expect($output)->toContain('wp foehn inspect <section>...');
        expect($output)->toContain('wp foehn inspect --all');
        expect($output)->not->toMatch('/location\s+contextKey/');
    });

    it('names the problems first in the summary, and the command that gives their fix', function () {
        inspectCommand('ProblemApp')([], []);

        $output = inspectOutput();

        expect($output)->toStartWith(
            'problems — 3: '
            . Gallery::class
            . ' #[AsPostType], '
            . Article::class
            . ' #[AsPostType], '
            . Shelf::class
            . ' #[AsTaxonomy]',
        );
        expect($output)->toContain('Problems with their fix: wp foehn inspect problems');
    });

    it('leaves the problems command out of a summary without problems', function () {
        inspectCommand()([], []);

        expect(inspectOutput())->toContain('problems — 0')->not->toContain('Problems with their fix');
    });

    it('prints each problem with its reason and its fix', function () {
        inspectCommand('ProblemApp')(['problems'], []);

        $output = inspectOutput();

        expect($output)->toContain('problems — 3');
        expect($output)->toMatch('/reason\s+fix/');
        expect($output)->toContain(
            "Declare the taxonomy 'genre' with #[AsTaxonomy], or remove it from taxonomies on #[AsPostType] of "
            . Article::class
            . '.',
        );
    });

    it('prints every section as JSON with --all', function () {
        inspectCommand()([], ['format' => 'json', 'all' => true]);

        $report = inspectJson();

        expect(array_keys($report))->toBe(ProjectInspector::SECTIONS);
        expect(array_column($report['post-types'], 'model'))->toContain(Product::class);
    });

    it('prints JSON on one line', function () {
        inspectCommand()([], ['format' => 'json', 'all' => true]);

        $lines = array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message');

        expect($lines[0])->not->toContain("\n");
    });

    it('prints the named sections in the order given', function () {
        inspectCommand()(['menus', 'post-types', 'menus'], ['format' => 'json']);

        expect(array_keys(inspectJson()))->toBe(['menus', 'post-types']);
    });

    it('refuses sections together with --all', function () {
        inspectCommand()(['menus'], ['all' => true]);

        $errors = wp_stub_get_calls('wp_cli_error');

        expect($errors)->toHaveCount(1);
        expect($errors[0]['args']['message'])->toBe('Name sections or pass --all, not both.');
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

    it('prints valid JSON when a plugin header is not UTF-8', function () {
        // A Latin-1 header, as an old plugin file saved in that encoding has.
        $GLOBALS['wp_stub_plugins'] = ['old/old.php' => ['Name' => "Caf\xE9", 'Version' => '1.0']];
        $GLOBALS['wp_stub_options']['active_plugins'] = ['old/old.php'];

        inspectCommand()(['plugins'], ['format' => 'json']);

        expect(inspectJson()['plugins'][0])->toMatchArray(['name' => "Caf\u{FFFD}", 'file' => 'old/old.php']);
    });

    it('prints a table by default', function () {
        inspectCommand()(['post-types'], []);

        $output = inspectOutput();

        // product, core post through its Timber model, the framework's own page model,
        // and product_variation through a meta field.
        expect($output)->toContain('post-types — 4');
        expect($output)->toMatch('/name\s+model\s+taxonomies\s+meta/');
        expect($output)->toMatch(
            '/product\s+' . preg_quote(Product::class, '/') . '\s+category, brand\s+featured, price, sku/',
        );
        expect(wp_stub_get_calls('wp_cli_error'))->toBe([]);
    });

    it('names the options of each settings page in the table', function () {
        inspectCommand()(['settings'], []);

        expect(inspectOutput())
            ->toMatch('/shop-settings\s+\S+ShopSettings\s+shop_contact_email, shop_show_banner, shop_currency/');
    });

    it('shows the class, order and description of each seeder in the table', function () {
        inspectCommand()(['seeders'], []);

        $output = inspectOutput();

        expect($output)->toContain('seeders — 1');
        expect($output)->toMatch('/class\s+order\s+description/');
        expect($output)->toMatch('/\S+SampleProductsSeeder\s+20\s+Sample products/');
    });

    it('shows the base keys once, and the controller and context keys of each template', function () {
        inspectCommand()(['templates'], []);

        $output = inspectOutput();

        expect($output)->toContain('  every template: body_class, http_host');
        expect($output)->toMatch('/template\s+controller\s+request\s+keys/');
        expect($output)->toMatch('/single-product\s+\S+ProductController\s+in_stock, related/');
        expect($output)->toMatch('/taxonomy-brand\s+\S+BrandController\s+term\s+brand_count/');
    });

    it('prints what every view receives once, and only what differs on each row', function () {
        $theme = sys_get_temp_dir() . '/foehn-inspect-command-theme-' . uniqid();
        mkdir($theme . '/templates/pages', 0o777, true);
        mkdir($theme . '/templates/components', 0o777, true);
        touch($theme . '/templates/pages/single.twig');
        touch($theme . '/templates/components/footer.twig');
        $GLOBALS['wp_stub_template_directory'] = $theme;
        $dirname = Timber::$dirname;
        Timber::$dirname = ['templates'];

        try {
            inspectCommand()(['views'], []);
        } finally {
            Timber::$dirname = $dirname;
            unset($GLOBALS['wp_stub_template_directory']);
            removeTestDirectory($theme);
        }

        $output = inspectOutput();

        expect($output)->toContain('  every view: body_class, http_host');
        expect($output)->toContain('  every view, from ' . GlobalContext::class . ': current_year');
        expect($output)->toMatch('/pages\/single\s+' . preg_quote(ProductCardContext::class, '/') . '\s+price_label/');
        // The provider on `*` is above the table, not on each row.
        expect($output)->toMatch('/components\/footer\s*$/m');
        expect(substr_count($output, GlobalContext::class))->toBe(1);
    });

    it('prints every section as a table with --all', function () {
        inspectCommand()([], ['all' => true]);

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

    it('names every unknown section', function () {
        inspectCommand()(['menus', 'widgets', 'sidebars'], []);

        $errors = wp_stub_get_calls('wp_cli_error');

        expect($errors[0]['args']['message'])->toContain("Unknown section 'widgets', 'sidebars'");
    });

    it('refuses an unknown format', function () {
        inspectCommand()([], ['format' => 'yaml']);

        $errors = wp_stub_get_calls('wp_cli_error');

        expect($errors)->toHaveCount(1);
        expect($errors[0]['args']['message'])->toBe("Unknown format 'yaml'. Use table or json.");
    });
});
