<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Discovery\SettingsPageDiscovery;
use Studiometa\Foehn\Inspect\ProjectInspector;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Studiometa\Foehn\Views\TemplateContext;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tests\Fixtures\InspectApp\Article;
use Tests\Fixtures\InspectApp\Brand;
use Tests\Fixtures\InspectApp\BrandController;
use Tests\Fixtures\InspectApp\CallToActionPattern;
use Tests\Fixtures\InspectApp\CardImageSize;
use Tests\Fixtures\InspectApp\GlobalContext;
use Tests\Fixtures\InspectApp\HeroBlock;
use Tests\Fixtures\InspectApp\LegacyProductController;
use Tests\Fixtures\InspectApp\PrimaryMenu;
use Tests\Fixtures\InspectApp\Product;
use Tests\Fixtures\InspectApp\ProductCardContext;
use Tests\Fixtures\InspectApp\ProductController;
use Tests\Fixtures\InspectApp\ProductsEndpoint;
use Tests\Fixtures\InspectApp\SampleProductsSeeder;
use Tests\Fixtures\InspectApp\ShopSettings;
use Tests\Fixtures\InspectApp\StockBinding;
use Tests\Fixtures\InspectApp\StripeWebhook;
use Tests\Fixtures\InspectApp\SyncCommand;
use Tests\Fixtures\InspectApp\Topic;
use Timber\Site;
use Timber\Timber;

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
    $this->dirname = Timber::$dirname;
    Timber::$dirname = ['templates'];
});

afterEach(function () {
    removeTestDirectory($GLOBALS['wp_stub_template_directory']);
    unset($GLOBALS['wp_stub_template_directory']);
    Timber::$dirname = $this->dirname;
    tearDownTestContainer();
});

describe('ProjectInspector', function () {
    it('reports every section, in order', function () {
        expect(array_keys(inspector()->all()))->toBe(ProjectInspector::SECTIONS);
    });

    it('summarizes each section by the names of its rows', function () {
        $summary = inspector()->summary();

        expect(array_keys($summary))->toBe(ProjectInspector::SECTIONS);
        expect($summary['post-types'])->toContain('product');
        expect($summary['menus'])->toBe(['primary']);
        expect($summary['templates'])->toContain('single-product');
        expect($summary['cli-commands'])->toContain('foehn inspect');
        expect($summary['seeders'])->toBe([SampleProductsSeeder::class]);
    });

    it('reports the same model from a warm discovery cache', function () {
        $directory = sys_get_temp_dir() . '/foehn-tests/inspect-cache-' . uniqid('', true);
        $pool = new PhpFilesAdapter(directory: $directory);
        $cache = new DiscoveryCache(DiscoveryCacheStrategy::FULL, $pool);
        $locations = new DiscoveryLocations(testFixturePath('InspectApp'));
        $runner = static fn(): DiscoveryRunner => new DiscoveryRunner(bootTestContainer(), $cache, $pool, $locations);

        try {
            // The first runner scans and writes the cache; the second restores from it,
            // through the same var_export() files production reads.
            $cold = new ProjectInspector($runner())->all();
            $warmRunner = $runner();
            $warm = new ProjectInspector($warmRunner)->all();
            $restored = $warmRunner->wasRestoredFromCache($locations->app());
        } finally {
            removeTestDirectory($directory);
        }

        expect($restored)->toBeTrue();
        expect($warm)->toBe($cold);
        expect($warm)->toBe(inspector()->all());
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
        // `featured` has no subtype, so WordPress registers it for every post type.
        expect(array_column($product['meta'], 'key'))->toBe(['featured', 'price', 'sku']);
        expect($product['meta'][1])->toBe([
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
        expect(array_column($post['meta'], 'key'))->toBe(['featured', 'subtitle']);
    });

    it('reports a post type that only a meta field names', function () {
        $variation = inspectedRow(inspector()->section('post-types'), 'name', 'product_variation');

        expect($variation)->toMatchArray(['model' => null, 'declared' => false, 'taxonomies' => []]);
        expect(array_column($variation['meta'], 'key'))->toBe(['featured', 'gtin']);
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

    it('reports settings pages with the option, type, default and sanitizer of each setting', function () {
        expect(inspector()->section('settings'))->toBe([
            [
                'page' => 'shop-settings',
                'title' => 'Shop settings',
                'class' => ShopSettings::class,
                'optionGroup' => 'shop-settings',
                'settings' => [
                    [
                        'option' => 'shop_contact_email',
                        'type' => 'string',
                        'default' => '',
                        'sanitize' => 'sanitize_email',
                        'sanitizeDeclared' => true,
                        'sanitizeOnWpCli' => true,
                        'showInRest' => false,
                    ],
                    [
                        'option' => 'shop_show_banner',
                        'type' => 'boolean',
                        'default' => true,
                        // None declared: the type's own sanitizer still runs.
                        'sanitize' => 'rest_sanitize_boolean',
                        'sanitizeDeclared' => false,
                        'sanitizeOnWpCli' => true,
                        'showInRest' => false,
                    ],
                    [
                        'option' => 'shop_currency',
                        'type' => 'string',
                        'default' => 'EUR',
                        'sanitize' => ShopSettings::class . '::normalizeCurrency',
                        'sanitizeDeclared' => true,
                        'sanitizeOnWpCli' => true,
                        'showInRest' => false,
                    ],
                ],
            ],
        ]);
    });

    it('registers settings on init, so their sanitizers run under WP-CLI', function () {
        // `sanitizeOnWpCli` holds because WP-CLI fires `init` before a command runs. A
        // move to a hook WP-CLI does not fire, such as `admin_init`, must change it.
        expect(DiscoveryRunner::phaseOf(SettingsPageDiscovery::class))->toBe(DiscoveryPhase::Main);
    });

    it('reports the attributes a block stores, without the editor-only keys', function () {
        expect(inspectedRow(inspector()->section('blocks'), 'name', 'theme/hero')['attributes'])->toBe([
            ['name' => 'title', 'type' => 'string', 'default' => '', 'enum' => null],
            ['name' => 'align', 'type' => 'string', 'default' => 'left', 'enum' => ['left', 'center']],
            ['name' => 'imageId', 'type' => 'integer', 'default' => null, 'enum' => null],
        ]);
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

    it('reports the controller of each template, and the context keys once per controller', function () {
        $templates = inspector()->section('templates');

        expect($templates['base'])->toBe([
            'body_class',
            'http_host',
            'menus',
            'post',
            'posts',
            'site',
            'theme',
            'user',
            'wp_title',
        ]);
        expect(inspectedRow($templates['templates'], 'template', 'single-product'))->toBe([
            'template' => 'single-product',
            'controller' => ProductController::class,
            // Claims it too, with priority 20: the lower priority wins.
            'overridden' => [LegacyProductController::class],
            'request' => [],
        ]);
        expect($templates['controllers'][ProductController::class])->toBe([
            'priority' => 10,
            'keys' => ['in_stock', 'related'],
            'partial' => false,
        ]);
        expect($templates['controllers'][LegacyProductController::class]['priority'])->toBe(20);
    });

    it('adds the queried term to a term archive, and says when a controller sets keys at runtime', function () {
        $templates = inspector()->section('templates');
        $brand = inspectedRow($templates['templates'], 'template', 'taxonomy-brand');

        expect($brand['controller'])->toBe(BrandController::class);
        expect($brand['request'])->toBe(['term']);
        expect($templates['controllers'][BrandController::class])->toBe([
            'priority' => 10,
            'keys' => ['brand_count'],
            'partial' => true,
        ]);
    });

    it('reports context providers with the view names they match and the keys they add', function () {
        expect(inspectedRow(inspector()->section('context-providers'), 'class', GlobalContext::class))->toBe([
            'class' => GlobalContext::class,
            'templates' => ['*'],
            'priority' => 1,
            'keys' => ['current_year'],
            'partial' => false,
        ]);
    });

    it('reports each Twig file with the providers its view name matches, and what every view shares once', function () {
        $theme = $GLOBALS['wp_stub_template_directory'];
        mkdir($theme . '/templates/pages', 0o777, true);
        mkdir($theme . '/templates/components', 0o777, true);
        touch($theme . '/templates/pages/single.twig');
        touch($theme . '/templates/components/footer.twig');
        touch($theme . '/templates/pages/notes.txt');

        expect(inspector()->section('views'))->toBe([
            'base' => ['body_class', 'http_host', 'menus', 'post', 'posts', 'site', 'theme', 'user', 'wp_title'],
            'providers' => [
                GlobalContext::class => ['priority' => 1, 'keys' => ['current_year'], 'partial' => false],
                ProductCardContext::class => ['priority' => 5, 'keys' => ['price_label'], 'partial' => false],
            ],
            'everyView' => [GlobalContext::class],
            'views' => [
                ['view' => 'components/footer', 'file' => 'templates/components/footer.twig', 'providers' => []],
                [
                    'view' => 'pages/single',
                    'file' => 'templates/pages/single.twig',
                    'providers' => [ProductCardContext::class],
                ],
            ],
        ]);
    });

    it('lists in everyView only the providers on `*`, whatever files the theme holds', function () {
        // ProductCardContext matches every file listed here, but not a view added in
        // another directory: it stays on the row.
        $theme = $GLOBALS['wp_stub_template_directory'];
        mkdir($theme . '/templates/pages', 0o777, true);
        touch($theme . '/templates/pages/single.twig');

        $views = inspector()->section('views');

        expect($views['everyView'])->toBe([GlobalContext::class]);
        expect($views['views'][0]['providers'])->toBe([ProductCardContext::class]);
    });

    it('names a view with a numeric name as a string', function () {
        $theme = $GLOBALS['wp_stub_template_directory'];
        mkdir($theme . '/templates', 0o777, true);
        touch($theme . '/templates/404.twig');

        expect(inspector()->section('views')['views'][0]['view'])->toBe('404');
    });

    it('lists the providers on `*` when the theme has no Twig file', function () {
        $views = inspector()->section('views');

        expect($views['everyView'])->toBe([GlobalContext::class]);
        expect(array_keys($views['providers']))->toBe([GlobalContext::class]);
        expect($views['views'])->toBe([]);
    });

    it('runs providers for a view in the order the registry runs them', function () {
        $theme = $GLOBALS['wp_stub_template_directory'];
        mkdir($theme . '/templates/pages', 0o777, true);
        mkdir($theme . '/templates/components', 0o777, true);
        touch($theme . '/templates/pages/single.twig');
        // A second view that only the provider on `*` matches, so that the view above
        // lists the other provider on its row.
        touch($theme . '/templates/components/footer.twig');

        $registry = new ContextProviderRegistry();
        // Registered in the reverse order of their priorities: the order must come from
        // the priorities, as it does in the registry.
        $registry->register(['pages/single'], new ProductCardContext(), 5);
        $registry->register(['*'], new GlobalContext(), 1);

        $site = new ReflectionClass(Site::class)->newInstanceWithoutConstructor();
        $context = $registry->provide('pages/single', new TemplateContext(null, null, $site, null));
        $added = array_values(array_diff(array_keys($context->toArray()), ['post', 'posts', 'site', 'user']));

        $views = inspector()->section('views');
        $view = inspectedRow($views['views'], 'view', 'pages/single');
        $classes = [...$views['everyView'], ...$view['providers']];
        // Every provider of a view runs in priority order.
        usort(
            $classes,
            static fn(string $a, string $b): int => (
                $views['providers'][$a]['priority'] <=> $views['providers'][$b]['priority']
            ),
        );
        $keys = array_merge(...array_map(
            static fn(string $class): array => $views['providers'][$class]['keys'],
            $classes,
        ));

        expect($keys)->toBe($added);
    });

    it('lists a view a child theme overrides once, with the child file', function () {
        $child = sys_get_temp_dir() . '/foehn-inspect-child-' . uniqid();
        $GLOBALS['wp_stub_stylesheet_directory'] = $child;
        mkdir($child . '/templates/pages', 0o777, true);
        mkdir($GLOBALS['wp_stub_template_directory'] . '/templates/pages', 0o777, true);
        touch($child . '/templates/pages/single.twig');
        touch($GLOBALS['wp_stub_template_directory'] . '/templates/pages/single.twig');
        touch($GLOBALS['wp_stub_template_directory'] . '/templates/pages/page.twig');

        try {
            expect(array_column(inspector()->section('views')['views'], 'view'))->toBe(['pages/page', 'pages/single']);
        } finally {
            removeTestDirectory($child);
            unset($GLOBALS['wp_stub_stylesheet_directory']);
        }
    });

    it('passes over namespaced Timber directories instead of failing', function () {
        mkdir($GLOBALS['wp_stub_template_directory'] . '/templates/pages', 0o777, true);
        touch($GLOBALS['wp_stub_template_directory'] . '/templates/pages/single.twig');
        Timber::$dirname = ['templates', 'shop' => ['shop-templates']];

        expect(array_column(inspector()->section('views')['views'], 'view'))->toBe(['pages/single']);
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

    it('reports the seeders in the order wp foehn seed runs them', function () {
        expect(inspector()->section('seeders'))->toBe([
            ['class' => SampleProductsSeeder::class, 'order' => 20, 'description' => 'Sample products'],
        ]);
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
