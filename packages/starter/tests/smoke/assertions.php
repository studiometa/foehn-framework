<?php

declare(strict_types=1);

/**
 * Integration assertions, run inside a booted WordPress via `wp eval-file`.
 *
 * These cover the wiring that the unit suite cannot see: it runs against function
 * stubs, so a discovery that never registers anything still passes it. Every check
 * here failed at some point against a real install.
 *
 * The starter's job is to boot. What each attribute does once it has booted is
 * asserted in packages/demo, which is where the features are — this file checks
 * that the machinery underneath them is running at all.
 *
 * Exits non-zero on the first failure so CI stops with a readable message.
 */

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Kernel;
use Studiometa\Foehn\Security\Salts;
use Studiometa\Foehn\Views\ContextProviderRegistry;

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
// loaded this was 0, and every security hook in the theme was silently inert.
$results->same('foehn.config.php is loaded (opt-in hooks)', 7, count($config->hooks));

$results->true('opt-in security hooks are applied', has_filter('xmlrpc_enabled') !== false);

// ──────────────────────────────────────────────
// Vendor discovery: the framework's own classes
// ──────────────────────────────────────────────

$twig = new Timber\Loader()->get_twig();

// Registered by the framework's own #[AsTwigExtension] classes, which live in
// vendor/ and were never scanned. Every template here uses html_attributes.
$results->containsAll(
    'framework Twig functions are registered',
    ['html_attributes', 'html_classes', 'html_styles'],
    array_map(static fn(Twig\TwigFunction $function): string => $function->getName(), $twig->getFunctions()),
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
// App discovery: the theme's own classes
// ──────────────────────────────────────────────

// The starter ships only what a theme cannot render without. Its menu locations
// are read by header.twig and footer.twig, so a project that removes them has to
// change the templates too — which makes them the honest thing to assert here.
$results->containsAll('menus are registered', ['header', 'footer', 'legal'], array_keys(get_registered_nav_menus()));

// GlobalContextProvider runs for every template. Without it the footer loses
// `current_year`, silently, on every page.
$results->true(
    'the global context provider is applied',
    Kernel::get(ContextProviderRegistry::class)->hasProviders('index'),
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
