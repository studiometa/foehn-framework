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
use Studiometa\Foehn\Discovery;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\Security\Salts;

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

// The ACF discoveries ship in studiometa/foehn-acf, which requires the framework
// rather than a tempest/* package. A package like that is only scanned when it
// opts in, and the failure mode when it does not is silence — no error, no
// blocks, no field groups.
$results->containsAll(
    'the ACF package discoveries are found',
    [
        Discovery\AcfBlockDiscovery::class,
        Discovery\AcfFieldGroupDiscovery::class,
        Discovery\AcfOptionsPageDiscovery::class,
    ],
    array_keys(Kernel::get(DiscoveryRunner::class)->getDiscoveries()),
);

// The package supplies its own AcfConfig through a config file, now that the
// Kernel no longer registers one. A project's app/acf.config.php still wins.
$results->true(
    'the ACF package supplies its own config default',
    Kernel::get(Studiometa\Foehn\Config\AcfConfig::class)->transformFields,
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
    ['theme/section', 'theme/callout'],
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
