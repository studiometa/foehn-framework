<?php

declare(strict_types=1);

/**
 * Integration assertions, run inside a booted WordPress via `wp eval-file`.
 *
 * These cover the wiring that the unit suites cannot see: the unit tests run against
 * function stubs, so a discovery that never registers anything still passes them.
 * Every check here failed at some point against a real install.
 *
 * Exits non-zero on the first failure so CI stops with a readable message.
 */

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\Security\Salts;
use Studiometa\Foehn\Settings\Settings;

// `wp eval-file` runs this inside a function, so the results live in an object
// rather than in globals a top-level `global` statement would not reach.
$results = new class {
    public int $passed = 0;

    /** @var list<string> */
    public array $failures = [];

    public function same(string $label, mixed $expected, mixed $actual): void
    {
        if ($expected === $actual) {
            $this->passed++;

            return;
        }

        $this->failures[] = sprintf(
            "%s\n    expected: %s\n    actual:   %s",
            $label,
            var_export($expected, true),
            var_export($actual, true),
        );
    }

    public function true(string $label, bool $actual): void
    {
        $this->same($label, true, $actual);
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    public function containsAll(string $label, array $expected, array $actual): void
    {
        $missing = array_values(array_diff($expected, $actual));

        $this->same($label . ($missing === [] ? '' : ' (missing: ' . implode(', ', $missing) . ')'), [], $missing);
    }
};

// ──────────────────────────────────────────────
// Kernel and config files
// ──────────────────────────────────────────────

$config = Kernel::get(FoehnConfig::class);

// theme/app/foehn.config.php opts into 7 hook classes. Before config files were
// loaded this was 0, and every security hook in the starter was silently inert.
$results->same('foehn.config.php is loaded (opt-in hooks)', 7, count($config->hooks));

$results->true('opt-in security hooks are applied', has_filter('xmlrpc_enabled') !== false);

// ──────────────────────────────────────────────
// Vendor discovery: the framework's own classes
// ──────────────────────────────────────────────

$twig = new Timber\Loader()->get_twig();

// Registered by the framework's own #[AsTwigExtension] classes, which live in
// vendor/ and were never scanned. Every starter template uses html_attributes.
$results->containsAll(
    'framework Twig functions are registered',
    ['html_attributes', 'html_classes', 'html_styles'],
    array_map(static fn(Twig\TwigFunction $function): string => $function->getName(), $twig->getFunctions()),
);

// Nothing lists the discovery classes any more: each is found because it
// implements Discovery inside a scanned location. A location that stops being
// scanned — or a cache entry restored without them — leaves the framework
// registering nothing at all, and every unit test still passes.
$results->containsAll(
    'every framework discovery is found by scanning',
    [
        Discovery\BlockDiscovery::class,
        Discovery\BlockPatternDiscovery::class,
        Discovery\CliCommandDiscovery::class,
        Discovery\ContextProviderDiscovery::class,
        Discovery\CronDiscovery::class,
        Discovery\HookDiscovery::class,
        Discovery\ImageSizeDiscovery::class,
        Discovery\JobDiscovery::class,
        Discovery\MenuDiscovery::class,
        Discovery\PostTypeDiscovery::class,
        Discovery\RestRouteDiscovery::class,
        Discovery\ShortcodeDiscovery::class,
        Discovery\TaxonomyDiscovery::class,
        Discovery\TemplateControllerDiscovery::class,
        Discovery\TimberModelDiscovery::class,
        Discovery\TwigExtensionDiscovery::class,
    ],
    array_keys(Kernel::get(DiscoveryRunner::class)->getDiscoveries()),
);

// The starter ships no ACF at all now. Its only ACF block was the one thing
// here that needed a paid plugin to run, so that path was never exercised: ACF
// Pro is not installed in CI.
$results->same(
    'the starter needs no ACF package',
    [],
    array_values(array_filter(
        array_keys(Kernel::get(DiscoveryRunner::class)->getDiscoveries()),
        static fn(string $discovery): bool => str_contains($discovery, 'Acf'),
    )),
);

// The framework's #[AsCliCommand] classes live in the same vendor package. WP-CLI
// defers command registration, so its command tree cannot be read from inside
// `wp eval-file`; what the discovery found is the readable half, and run.sh
// invokes a real command for the other half.
$commands = Kernel::get(DiscoveryRunner::class)->getDiscoveries()[CliCommandDiscovery::class];

$results->containsAll(
    'framework CLI commands are discovered',
    ['make:block', 'make:post-type', 'discovery:generate', 'discovery:clear'],
    array_map(static fn(array $item): string => $item['attribute']->name, iterator_to_array($commands->getItems())),
);

// Command stubs carry real attributes and are marked #[SkipDiscovery]. If the
// scanner ignores that attribute, scanning vendor/ registers junk post types.
$results->same(
    'command stubs are not discovered',
    [],
    array_values(array_filter(
        array_keys(get_post_types()),
        static fn(string $type): bool => str_contains($type, 'dummy') || str_contains($type, 'stub'),
    )),
);

// ──────────────────────────────────────────────
// Security keys
// ──────────────────────────────────────────────

// A site whose keys are guessable is a site whose login cookies can be forged. The
// installer generates them; before it did, every install ran on
// 'change-me-AUTH_KEY-' . md5(__DIR__), derived from a predictable path.
$results->same(
    'no security key is a placeholder',
    [],
    array_values(array_filter(
        Salts::NAMES,
        static fn(string $name): bool => (
            !defined($name) || str_starts_with((string) constant($name), Salts::PLACEHOLDER_PREFIX)
        ),
    )),
);

// ──────────────────────────────────────────────
// App discovery: the starter's own classes
// ──────────────────────────────────────────────

$results->containsAll('starter post types are registered', ['product', 'testimonial'], array_keys(get_post_types()));

$results->containsAll(
    'starter taxonomies are registered',
    ['product_category', 'product_tag'],
    array_keys(get_taxonomies()),
);

$results->containsAll(
    'starter blocks are registered',
    ['theme/section', 'theme/callout', 'theme/hero'],
    array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered()),
);

$results->containsAll(
    'starter menus are registered',
    ['header', 'footer', 'legal'],
    array_keys(get_registered_nav_menus()),
);

// A meta key registered against every post type instead of one is the mistake
// #[AsPostMeta] exists to prevent, so the subtype is what is asserted rather
// than the key alone.
$results->containsAll(
    'starter post meta is registered against its post type',
    ['price', 'sale_price'],
    array_keys(get_registered_meta_keys('post', 'product')),
);

// The point of registering it: without show_in_rest the key is invisible to the
// block editor and cannot be bound through core/post-meta.
$results->true(
    'starter post meta is exposed to REST',
    (get_registered_meta_keys('post', 'product')['price']['show_in_rest'] ?? false) !== false,
);

// ──────────────────────────────────────────────
// Settings
// ──────────────────────────────────────────────

// register_setting() is what makes options.php accept a save at all: a setting
// registered under the wrong group, or not registered, is silently discarded
// and the page looks like it simply did not work.
$registered = get_registered_settings();

$results->containsAll(
    'starter settings are registered',
    ['starter_contact_email', 'starter_show_banner', 'starter_posts_per_archive'],
    array_keys($registered),
);

$results->same(
    'starter settings are grouped under the page slug',
    'theme-settings',
    $registered['starter_show_banner']['group'] ?? null,
);

// The declared default, which get_option() alone does not answer with until the
// option has been saved once.
$results->same('a declared default is readable before any save', 12, Settings::get('starter_posts_per_archive'));

// Settings are configuration, sometimes credentials. Exposure is opt-in, which
// is the opposite of what #[AsPostMeta] does.
$results->same(
    'starter settings are not exposed through REST',
    [],
    array_values(array_filter(
        ['starter_contact_email', 'starter_show_banner', 'starter_posts_per_archive'],
        static fn(string $name): bool => ($registered[$name]['show_in_rest'] ?? false) !== false,
    )),
);

// The menu entry is the other half of a settings page, and it is registered on
// `admin_menu`, which neither a front-end request nor this file ever reaches on
// its own. WP-CLI also runs with no user, and add_submenu_page() refuses a page
// the current user cannot see — so this asks as an administrator.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

wp_set_current_user(1);

$GLOBALS['menu'] = [];
$GLOBALS['submenu'] = [];

do_action('admin_menu');

$results->true('the settings page is added under Appearance', in_array(
    'theme-settings',
    array_column($GLOBALS['submenu']['themes.php'] ?? [], 2),
    true,
));

// The form is a Twig template, like every other view in the theme. Rendering it
// through the real view engine is the only way to know the template resolves
// and its context reaches it — the unit suite renders through a fake.
$results->true('the settings form renders from its Twig template', str_contains(
    Kernel::get(ViewEngineInterface::class)->render('settings/theme-settings', [
        'settings' => ['starter_contact_email' => 'hello@example.com'],
    ]),
    'hello@example.com',
));

// ──────────────────────────────────────────────
// Block bindings
// ──────────────────────────────────────────────

// A source WordPress does not know about is not a source: a block bound to it
// renders with whatever the author typed, and says nothing about why.
$results->true(
    'the theme block binding source is registered',
    WP_Block_Bindings_Registry::get_instance()->is_registered('theme/reading-time'),
);

// The value is computed when a bound block renders, through the class the
// container builds. Rendering the markup is the only way to see all of that
// happen at once — and it needs a post in scope, because render_block() takes
// the `postId` context a source asks for from the global post and nowhere else.
$posts = get_posts(['numberposts' => 1, 'post_status' => 'publish']);

if ($posts !== []) {
    $GLOBALS['post'] = $posts[0];

    setup_postdata($posts[0]);
}

$results->true(
    'a bound block renders the computed value',
    $posts !== []
    && str_contains(
        do_blocks(
            '<!-- wp:paragraph {"metadata":{"bindings":{"content":'
            . '{"source":"theme/reading-time"}}}} --><p>unbound</p><!-- /wp:paragraph -->',
        ),
        'read',
    ),
);

wp_reset_postdata();

// Declared meta needs no source of its own: core/post-meta binds it already,
// which is the point the guide leads with.
$results->true(
    'core/post-meta is there for declared meta, with no source of our own',
    WP_Block_Bindings_Registry::get_instance()->is_registered('core/post-meta'),
);

// ──────────────────────────────────────────────
// Report
// ──────────────────────────────────────────────

if ($results->failures !== []) {
    printf("%d passed, %d FAILED\n\n", $results->passed, count($results->failures));

    foreach ($results->failures as $failure) {
        printf("  ✗ %s\n\n", $failure);
    }

    exit(1);
}

printf("%d assertions passed\n", $results->passed);
