<?php

declare(strict_types=1);

use Studiometa\Foehn\Inspect\ProjectInspector;
use Tests\Fixtures\InspectApp\Article;
use Tests\Fixtures\InspectApp\Brand;
use Tests\Fixtures\InspectApp\CallToActionPattern;
use Tests\Fixtures\InspectApp\CardImageSize;
use Tests\Fixtures\InspectApp\GlobalContext;
use Tests\Fixtures\InspectApp\HeroBlock;
use Tests\Fixtures\InspectApp\PrimaryMenu;
use Tests\Fixtures\InspectApp\Product;
use Tests\Fixtures\InspectApp\ProductController;
use Tests\Fixtures\InspectApp\ProductsEndpoint;
use Tests\Fixtures\InspectApp\StockBinding;
use Tests\Fixtures\InspectApp\StripeWebhook;
use Tests\Fixtures\InspectApp\SyncCommand;
use Tests\Fixtures\InspectApp\Topic;

/**
 * An inspector over tests/Fixtures/InspectApp, which declares one of everything.
 */
function inspector(): ProjectInspector
{
    return new ProjectInspector(testDiscoveryRunner(bootTestContainer(), testFixturePath('InspectApp')));
}

/**
 * The row of a section whose key holds the given value.
 *
 * @return array<string, mixed>|null
 */
function inspectedRow(array $rows, string $key, mixed $value): ?array
{
    foreach ($rows as $row) {
        if ($row[$key] === $value) {
            return $row;
        }
    }

    return null;
}

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_template_directory'] = sys_get_temp_dir() . '/foehn-inspect-theme-' . uniqid();
});

afterEach(function () {
    removeTestDirectory($GLOBALS['wp_stub_template_directory']);
    unset($GLOBALS['wp_stub_template_directory']);
    tearDownTestContainer();
});

describe('ProjectInspector', function () {
    it('reports every section, in order', function () {
        expect(array_keys(inspector()->all()))->toBe(ProjectInspector::SECTIONS);
    });

    it('refuses a section that does not exist', function () {
        inspector()->section('widgets');
    })->throws(InvalidArgumentException::class, "Unknown section 'widgets'. Available sections: post-types,");

    it('reports a post type with its model, meta fields and taxonomies', function () {
        $product = inspectedRow(inspector()->section('post-types'), 'name', 'product');

        expect($product)->toMatchArray([
            'model' => Product::class,
            'declared' => true,
            'singular' => 'Product',
            'hasArchive' => true,
            // Named by the post type, and by #[AsTaxonomy(postTypes:)] on the other side.
            'taxonomies' => ['category', 'brand'],
        ]);
        expect(array_column($product['meta'], 'key'))->toBe(['price', 'sku']);
        expect($product['meta'][0])->toBe([
            'key' => 'price',
            'type' => 'number',
            'single' => true,
            'showInRest' => true,
            'default' => null,
            'description' => 'What it costs',
        ]);
    });

    it('reports a core post type that a Timber model maps to a class', function () {
        $post = inspectedRow(inspector()->section('post-types'), 'name', 'post');

        expect($post)->toMatchArray(['model' => Article::class, 'declared' => false, 'singular' => null]);
        expect(array_column($post['meta'], 'key'))->toBe(['subtitle']);
    });

    it('gives a declared and an undeclared post type the same keys', function () {
        $rows = inspector()->section('post-types');

        expect(array_keys(inspectedRow($rows, 'name', 'post')))
            ->toBe(array_keys(inspectedRow($rows, 'name', 'product')));
    });

    it('reports taxonomies with their post types, model and term meta', function () {
        $rows = inspector()->section('taxonomies');

        expect(inspectedRow($rows, 'name', 'brand'))->toMatchArray([
            'model' => Brand::class,
            'hierarchical' => true,
            'postTypes' => ['product'],
        ]);
        expect(array_column(inspectedRow($rows, 'name', 'brand')['meta'], 'key'))->toBe(['logo']);

        // Core `category` is linked from the post type's side only.
        expect(inspectedRow($rows, 'name', 'category'))->toMatchArray([
            'model' => Topic::class,
            'declared' => false,
            'postTypes' => ['product'],
        ]);
        expect(array_keys(inspectedRow($rows, 'name', 'category')))
            ->toBe(array_keys(inspectedRow($rows, 'name', 'brand')));
    });

    it('reports blocks, patterns and bindings', function () {
        $inspector = inspector();

        expect(inspectedRow($inspector->section('blocks'), 'name', 'theme/hero'))->toMatchArray([
            'title' => 'Hero',
            'class' => HeroBlock::class,
            'category' => 'design',
        ]);
        expect(inspectedRow($inspector->section('patterns'), 'name', 'theme/call-to-action'))->toMatchArray([
            'class' => CallToActionPattern::class,
            'template' => 'patterns/call-to-action',
        ]);
        expect(inspectedRow($inspector->section('bindings'), 'name', 'theme/stock'))->toMatchArray([
            'class' => StockBinding::class,
            'usesContext' => ['postId'],
        ]);
    });

    it('reports template controllers with their templates', function () {
        expect(inspectedRow(inspector()->section('templates'), 'class', ProductController::class))->toBe([
            'class' => ProductController::class,
            'templates' => ['single-product', 'archive-product'],
            'priority' => 10,
        ]);
    });

    it('reports context providers with the view names they match', function () {
        expect(inspectedRow(inspector()->section('context-providers'), 'class', GlobalContext::class))->toBe([
            'class' => GlobalContext::class,
            'templates' => ['*'],
            'priority' => 1,
        ]);
    });

    it('reports menus with the context key each one is exposed as', function () {
        expect(inspectedRow(inspector()->section('menus'), 'location', 'primary'))->toBe([
            'location' => 'primary',
            'description' => 'Main navigation',
            'class' => PrimaryMenu::class,
            'contextKey' => 'menus.primary',
        ]);
    });

    it('names an image size the way WordPress registers it', function () {
        expect(inspectedRow(inspector()->section('image-sizes'), 'class', CardImageSize::class))->toBe([
            'name' => 'card',
            'width' => 600,
            'height' => 400,
            'crop' => true,
            'class' => CardImageSize::class,
        ]);
    });

    it('reports rewrite rules and REST routes', function () {
        $inspector = inspector();

        expect(inspectedRow($inspector->section('rewrite-rules'), 'class', StripeWebhook::class))->toMatchArray([
            'regex' => '^webhook/stripe/?$',
            'queryVars' => ['foehn_route'],
            'handles' => true,
        ]);

        $routes = array_values(array_filter(
            $inspector->section('rest-routes'),
            static fn(array $route): bool => str_starts_with($route['handler'], ProductsEndpoint::class),
        ));

        expect($routes)->toBe([
            [
                'path' => '/shop/v1/products',
                'method' => 'GET',
                'permission' => null,
                'handler' => ProductsEndpoint::class . '::index',
            ],
            [
                'path' => '/shop/v1/products',
                'method' => 'POST',
                'permission' => 'edit_posts',
                'handler' => ProductsEndpoint::class . '::store',
            ],
        ]);
    });

    it('reports the project commands next to the framework ones', function () {
        $commands = array_column(inspector()->section('cli-commands'), 'command');

        expect($commands)->toContain('foehn shop:sync');
        expect($commands)->toContain('foehn inspect');
        expect(inspectedRow(inspector()->section('cli-commands'), 'command', 'foehn shop:sync')['class'])
            ->toBe(SyncCommand::class);
    });

    it('reports active, network-activated and must-use plugins with their versions', function () {
        $GLOBALS['wp_stub_plugins'] = [
            'woocommerce/woocommerce.php' => ['Name' => 'WooCommerce', 'Version' => '9.1.0'],
            'inactive/inactive.php' => ['Name' => 'Inactive', 'Version' => '1.0.0'],
            'network/network.php' => ['Name' => 'Network', 'Version' => '2.0.0'],
        ];
        $GLOBALS['wp_stub_options']['active_plugins'] = ['woocommerce/woocommerce.php'];
        $GLOBALS['wp_stub_is_multisite'] = true;
        $GLOBALS['wp_stub_site_options']['active_sitewide_plugins'] = ['network/network.php' => 1700000000];
        $GLOBALS['wp_stub_mu_plugins'] = ['bedrock-autoloader.php' => [
            'Name' => 'Bedrock Autoloader',
            'Version' => '',
        ]];

        try {
            expect(inspector()->section('plugins'))->toBe([
                [
                    'name' => 'WooCommerce',
                    'version' => '9.1.0',
                    'file' => 'woocommerce/woocommerce.php',
                    'mustUse' => false,
                ],
                ['name' => 'Network', 'version' => '2.0.0', 'file' => 'network/network.php', 'mustUse' => false],
                [
                    'name' => 'Bedrock Autoloader',
                    'version' => null,
                    'file' => 'bedrock-autoloader.php',
                    'mustUse' => true,
                ],
            ]);
        } finally {
            unset($GLOBALS['wp_stub_is_multisite']);
        }
    });

    it('reports theme supports with their arguments', function () {
        $GLOBALS['_wp_theme_features'] = ['title-tag' => true, 'html5' => [['search-form', 'gallery']]];

        expect(inspector()->section('theme-supports'))->toBe([
            ['feature' => 'html5', 'value' => [['search-form', 'gallery']]],
            ['feature' => 'title-tag', 'value' => true],
        ]);
    });

    it('reports the entry points of the theme build', function () {
        $dist = $GLOBALS['wp_stub_template_directory'] . '/dist';
        mkdir($dist . '/.vite', 0o777, true);
        file_put_contents($dist . '/.vite/manifest.json', json_encode([
            'assets/js/app.js' => ['file' => 'assets/app-123.js', 'isEntry' => true],
        ]));

        expect(inspector()->section('vite'))->toBe([
            'devServer' => null,
            'entries' => [['entry' => 'assets/js/app.js', 'file' => 'assets/app-123.js', 'css' => []]],
        ]);
    });

    it('reports no entry points for a theme that was never built', function () {
        expect(inspector()->section('vite'))->toBe(['devServer' => null, 'entries' => []]);
    });
});
