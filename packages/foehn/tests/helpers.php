<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Shared test helpers
 |--------------------------------------------------------------------------
 |
 | Private to the monorepo, and deliberately not published: sixty lines serving
 | people who write custom discoveries is a public API surface bought for a
 | niche. `packages/acf` reads them from here rather than owning a copy.
 |
 */

/**
 * Boot a GenericContainer and set it as the global Tempest instance.
 * Returns the container for further configuration.
 */
function bootTestContainer(): \Tempest\Container\GenericContainer
{
    $container = new \Tempest\Container\GenericContainer();
    \Tempest\Container\GenericContainer::setInstance($container);
    $container->singleton(\Tempest\Container\Container::class, fn() => $container);

    return $container;
}

/**
 * Tear down the global Tempest container instance.
 */
function tearDownTestContainer(): void
{
    \Tempest\Container\GenericContainer::setInstance(null);
}

/**
 * A template controller discovery with inert section services.
 */
function testTemplateControllerDiscovery(
    ?\Studiometa\Foehn\Views\Sections\SectionRequest $request = null,
    ?\Studiometa\Foehn\Views\Sections\SectionCollector $collector = null,
    ?\Studiometa\Foehn\Config\PageCacheConfig $pageCacheConfig = null,
    ?\Studiometa\Foehn\Discovery\DiscoveryProblems $problems = null,
    ?\Studiometa\Foehn\Trace\RequestTrace $requestTrace = null,
): \Studiometa\Foehn\Discovery\TemplateControllerDiscovery {
    $request ??= new \Studiometa\Foehn\Views\Sections\SectionRequest('GET', '/');

    return new \Studiometa\Foehn\Discovery\TemplateControllerDiscovery(
        $request,
        $collector ?? new \Studiometa\Foehn\Views\Sections\SectionCollector(),
        testSectionResponse($request, $pageCacheConfig),
        $problems ?? new \Studiometa\Foehn\Discovery\DiscoveryProblems(),
        $requestTrace ?? new \Studiometa\Foehn\Trace\RequestTrace(),
    );
}

/**
 * A section response wired to a page cache that is off unless a test turns it on.
 *
 * Off is the state most tests want: the cache decides nothing about the HTML, only about
 * the headers beside it.
 */
function testSectionResponse(
    ?\Studiometa\Foehn\Views\Sections\SectionRequest $request = null,
    ?\Studiometa\Foehn\Config\PageCacheConfig $pageCacheConfig = null,
): \Studiometa\Foehn\Views\Sections\SectionResponse {
    return new \Studiometa\Foehn\Views\Sections\SectionResponse(
        $request ?? new \Studiometa\Foehn\Views\Sections\SectionRequest('GET', '/'),
        new \Studiometa\Foehn\PageCache\Bypass($pageCacheConfig ?? new \Studiometa\Foehn\Config\PageCacheConfig()),
    );
}

/**
 * A discovery location pointing at a real directory.
 *
 * Tempest's DiscoveryLocation resolves its path with realpath(), so a made-up path
 * cannot be used: the constructor would fail on it.
 */
function testDiscoveryLocation(string $namespace = 'App\\', ?string $path = null): \Tempest\Discovery\DiscoveryLocation
{
    return new \Tempest\Discovery\DiscoveryLocation($namespace, $path ?? testAppPath());
}

/**
 * A location that reads as a vendor package, for the discoveries that treat those
 * differently — framework hook classes stay opt-in rather than registering because
 * they were scanned.
 */
function testVendorLocation(string $namespace = 'Studiometa\\Foehn\\'): \Tempest\Discovery\DiscoveryLocation
{
    $path = sys_get_temp_dir() . '/foehn-tests/vendor/studiometa/foehn/src';

    if (!is_dir($path)) {
        mkdir($path, 0o777, true);
    }

    return new \Tempest\Discovery\DiscoveryLocation($namespace, $path);
}

/**
 * Restore a discovery from what another one would have written to the cache.
 *
 * The items go through a real cache pool, the same path production takes, so a
 * value that cannot survive the round trip fails here rather than on a deploy.
 * Returns the target for chaining.
 *
 * @template T of \Tempest\Discovery\Discovery
 * @param T $target A fresh discovery to restore into
 * @return T
 */
function restoreThroughCacheFile(
    \Tempest\Discovery\Discovery $source,
    \Tempest\Discovery\Discovery $target,
    ?\Tempest\Discovery\DiscoveryLocation $location = null,
): \Tempest\Discovery\Discovery {
    $location ??= testDiscoveryLocation();

    $directory = sys_get_temp_dir() . '/foehn-tests/cache-' . uniqid('', true);

    $cache = new \Tempest\Discovery\DiscoveryCache(
        \Tempest\Discovery\DiscoveryCacheStrategy::FULL,
        new \Symfony\Component\Cache\Adapter\PhpFilesAdapter(directory: $directory),
    );

    try {
        $cache->store($location, [$source]);

        /** @var array<class-string, \Tempest\Discovery\DiscoveryItems> $restored */
        $restored = $cache->restore($location);
    } finally {
        removeTestDirectory($directory);
    }

    $target->setItems(new \Tempest\Discovery\DiscoveryItems()->addForLocation(
        $location,
        $restored[$source::class] ?? [],
    ));

    return $target;
}

/**
 * Run a discovery over a fixture class, the way DiscoveryRunner does.
 *
 * Tests seed items through the discover() interface rather than reaching into the
 * protected addItem(), so what they exercise is what production calls.
 *
 * @param class-string $fixture
 */
function discoverFixture(
    \Tempest\Discovery\Discovery $discovery,
    string $fixture,
    ?\Tempest\Discovery\DiscoveryLocation $location = null,
): \Tempest\Discovery\DiscoveryLocation {
    $location ??= testDiscoveryLocation();

    $discovery->discover($location, new \Tempest\Reflection\ClassReflector($fixture));

    return $location;
}

/**
 * Run a discovery over a class it must reject, and return what it reported.
 *
 * A rejected class is skipped, not registered: the helper asserts that the
 * discovery collected nothing, so each test only states the problem.
 *
 * @param \Closure(\Studiometa\Foehn\Discovery\DiscoveryProblems): \Tempest\Discovery\Discovery $make
 * @return list<array{class: string, attribute: string, reason: string, fix: string|null}>
 */
function rejectedProblems(\Closure $make, string $fixture): array
{
    $problems = new \Studiometa\Foehn\Discovery\DiscoveryProblems();
    $discovery = $make($problems);

    discoverFixture($discovery, $fixture);

    expect(iterator_to_array($discovery->getItems(), false))->toBe([]);

    return $problems->all();
}

/**
 * An app directory outside any Composer project.
 *
 * Discovery locations are built from the Composer root above the app path, so a
 * path inside this repository would pull the whole framework into every scan.
 */
function testAppPath(): string
{
    $path = sys_get_temp_dir() . '/foehn-tests/app';

    if (!is_dir($path)) {
        mkdir($path, 0o777, true);
    }

    return $path;
}

/**
 * A fixture directory, used as an app path.
 *
 * Unlike testAppPath(), this one is inside the repository, so the locations built
 * above it include the framework's own source — which is where the discovery
 * classes live now that nothing lists them.
 */
function testFixturePath(string $name): string
{
    return __DIR__ . '/Fixtures/' . $name;
}

/**
 * A DiscoveryRunner wired for tests: nothing is cached and the pool never touches
 * the filesystem.
 */
function testDiscoveryRunner(
    \Tempest\Container\Container $container,
    ?string $appPath = null,
    ?\Studiometa\Foehn\Config\FoehnConfig $config = null,
): \Studiometa\Foehn\Discovery\DiscoveryRunner {
    $pool = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
    $locations = new \Studiometa\Foehn\Discovery\DiscoveryLocations($appPath);

    // As the Kernel does: a discovery that needs the locations, such as
    // CliCommandDiscovery through InstalledPackages, gets the runner's.
    $container->singleton(\Studiometa\Foehn\Discovery\DiscoveryLocations::class, fn() => $locations);

    return new \Studiometa\Foehn\Discovery\DiscoveryRunner(
        $container,
        new \Tempest\Discovery\DiscoveryCache(\Tempest\Discovery\DiscoveryCacheStrategy::NONE, $pool),
        $pool,
        $locations,
        $config,
    );
}

/**
 * The conventions of a project whose classes discovery reads from $discoveredPath.
 *
 * Discovery runs here, before a test writes into $appPath classes that no autoloader
 * can load: what the runner holds is the project as it was before the command.
 */
function testProjectConventions(
    string $appPath,
    ?string $discoveredPath = null,
): \Studiometa\Foehn\Console\ProjectConventions {
    $runner = testDiscoveryRunner(bootTestContainer(), $discoveredPath ?? $appPath);
    $runner->getDiscoveries();

    return new \Studiometa\Foehn\Console\ProjectConventions($appPath, $runner);
}

/**
 * A CLI command discovery with the namespaces of the packages a Composer root installs.
 *
 * With no root, no location is in a package: every command is the project's.
 */
function testCliCommandDiscovery(
    ?\Studiometa\Foehn\Config\FoehnConfig $config = null,
    ?string $composerRoot = null,
    ?\Studiometa\Foehn\Discovery\DiscoveryProblems $problems = null,
): \Studiometa\Foehn\Discovery\CliCommandDiscovery {
    return new \Studiometa\Foehn\Discovery\CliCommandDiscovery(
        new \Tempest\Container\GenericContainer(),
        $problems ?? new \Studiometa\Foehn\Discovery\DiscoveryProblems(),
        $config ?? new \Studiometa\Foehn\Config\FoehnConfig(),
        new \Studiometa\Foehn\Discovery\InstalledPackages(
            new \Studiometa\Foehn\Discovery\DiscoveryLocations($composerRoot === null ? null : $composerRoot . '/app'),
        ),
    );
}

/**
 * A Composer root in a temporary directory, with an `app` directory and packages.
 *
 * Each package is installed at `vendor/<name>` with its classes in `src/`, and its
 * extra written to installed.json. A package with `'symlink' => true` is installed
 * as a path repository with symlinks: its files are in `packages/<name>`, outside
 * vendor/, and `vendor/<name>` links to them.
 *
 * @param array<string, array{extra?: array<string, mixed>, symlink?: bool}> $packages
 */
function testComposerRoot(array $packages): string
{
    $root = sys_get_temp_dir() . '/foehn-composer-root-' . uniqid();
    mkdir($root . '/app', 0o777, true);
    mkdir($root . '/vendor/composer', 0o777, true);
    file_put_contents($root . '/composer.json', '{}');

    $installed = [];

    foreach ($packages as $name => $package) {
        $vendorPath = $root . '/vendor/' . $name;

        if ($package['symlink'] ?? false) {
            mkdir($root . '/packages/' . $name . '/src', 0o777, true);
            mkdir(dirname($vendorPath), 0o777, true);
            symlink($root . '/packages/' . $name, $vendorPath);
        } else {
            mkdir($vendorPath . '/src', 0o777, true);
        }

        $installed[] = [
            'name' => $name,
            'autoload' => ['psr-4' => ['Acme\\' => 'src/']],
            'extra' => $package['extra'] ?? [],
            'install-path' => '../' . $name,
        ];
    }

    file_put_contents($root . '/vendor/composer/installed.json', json_encode(['packages' => $installed]));

    return $root;
}

/**
 * Delete a Composer root testComposerRoot() made, its symlinks first.
 */
function removeTestComposerRoot(string $root): void
{
    foreach (glob($root . '/vendor/*/*') ?: [] as $path) {
        if (is_link($path)) {
            unlink($path);
        }
    }

    removeTestDirectory($root);
}

/**
 * Delete a directory and everything below it.
 */
function removeTestDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }

    @rmdir($directory);
}

/**
 * Assert the behaviours every make: command shares, once per command.
 *
 * Generate, generate valid PHP, leave no placeholder, preview, report as JSON,
 * refuse, force, and reject a missing name — the shared Scaffolder and the same
 * lines in every command. Shared rather
 * than copied because `packages/acf` ships three of these commands and two
 * copies of the contract would drift.
 *
 * Expects the calling suite's beforeEach to have set $this->appPath and a
 * $this->make factory.
 *
 * @param array<string, array{class: class-string, args: list<string>, path: string}> $contracts
 */
function makeCommandContractSuite(array $contracts): void
{
    foreach ($contracts as $label => $case) {
        $class = $case['class'];
        $args = $case['args'];
        $relative = $case['path'] . '.php';

        it("{$label} generates its file", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, []);

            $path = "{$this->appPath}/{$relative}";

            expect($path)->toBeFile();
            expect(file_get_contents($path))->toContain('declare(strict_types=1);');
            expect(wp_stub_get_calls('wp_cli_success'))->toHaveCount(1);
            expect(wp_stub_get_calls('wp_cli_error'))->toBeEmpty();
        });

        it("{$label} generates valid PHP", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, []);

            exec('php -l ' . escapeshellarg("{$this->appPath}/{$relative}") . ' 2>&1', $output, $status);

            expect($status)->toBe(0, implode("\n", $output));
        });

        it("{$label} leaves no placeholder behind", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, []);

            $contents = (string) file_get_contents("{$this->appPath}/{$relative}");

            expect($contents)->not->toContain('dummy');
            expect($contents)->not->toContain('Dummy');
        });

        it("{$label} writes nothing on --dry-run but previews the file", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, ['dry-run' => true]);

            expect(file_exists("{$this->appPath}/{$relative}"))->toBeFalse();
            expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();

            $logged = implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message'));

            expect($logged)->toContain('Would create:');
        });

        it("{$label} reports the file it would write as JSON on --dry-run", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, ['dry-run' => true, 'format' => 'json']);

            $path = "{$this->appPath}/{$relative}";
            $lines = wp_stub_get_calls('wp_cli_line');

            expect(file_exists($path))->toBeFalse();
            expect($lines)->toHaveCount(1);

            $report = json_decode($lines[0]['args']['message'], true, flags: JSON_THROW_ON_ERROR);

            expect($report['dryRun'])->toBeTrue();
            expect($report['files'])->toHaveCount(1);
            expect($report['files'][0]['path'])->toBe($path);
            expect($report['files'][0]['status'])->toBe('would-create');
            expect($report['files'][0]['contents'])->toContain('declare(strict_types=1);');
            expect(wp_stub_get_calls('wp_cli_error'))->toBeEmpty();
        });

        it("{$label} reports the file it wrote as JSON", function () use ($class, $args, $relative) {
            ($this->make)($class)($args, ['format' => 'json']);

            $path = "{$this->appPath}/{$relative}";
            $lines = wp_stub_get_calls('wp_cli_line');

            expect($path)->toBeFile();
            expect($lines)->toHaveCount(1);

            $report = json_decode($lines[0]['args']['message'], true, flags: JSON_THROW_ON_ERROR);

            expect($report['dryRun'])->toBeFalse();
            expect($report['files'])->toBe([['path' => $path, 'status' => 'created']]);
            expect($report['notes'])->toBeArray();
            expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();
        });

        it("{$label} refuses to overwrite without --force", function () use ($class, $args, $relative) {
            $path = "{$this->appPath}/{$relative}";

            ($this->make)($class)($args, []);
            file_put_contents($path, '<?php // hand-edited');
            wp_stub_reset();

            ($this->make)($class)($args, []);

            expect(file_get_contents($path))->toBe('<?php // hand-edited');
            expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
            expect(wp_stub_get_calls('wp_cli_error')[0]['args']['exit'])->toBeTrue();
            expect(wp_stub_get_calls('wp_cli_success'))->toBeEmpty();
        });

        it("{$label} overwrites with --force", function () use ($class, $args, $relative) {
            $path = "{$this->appPath}/{$relative}";

            ($this->make)($class)($args, []);
            file_put_contents($path, '<?php // hand-edited');

            ($this->make)($class)($args, ['force' => true]);

            expect(file_get_contents($path))->toContain('declare(strict_types=1);');
        });

        it("{$label} reports a missing name instead of generating", function () use ($class, $case) {
            ($this->make)($class)([], ['format' => 'json']);

            // An error that exits, never a question: a run without a terminal must end.
            expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
            expect(wp_stub_get_calls('wp_cli_error')[0]['args']['exit'])->toBeTrue();
            expect(wp_stub_get_calls('wp_cli_line'))->toBeEmpty();

            $directory = dirname("{$this->appPath}/{$case['path']}");

            expect(is_dir($directory) ? (glob($directory . '/*.php') ?: []) : [])->toBeEmpty();
        });
    }
}

/**
 * A verification report of the `updates` profile, holding the checks a test cares about.
 */
function testVerificationReport(\Studiometa\Foehn\Verification\VerificationResult ...$checks): \Studiometa\Foehn\Verification\VerificationReport
{
    return new \Studiometa\Foehn\Verification\VerificationReport(
        \Studiometa\Foehn\Verification\VerificationProfile::Updates,
        array_values($checks),
    );
}

/**
 * A directory a verification report can be written into, guaranteed not to be shared.
 */
function testVerificationDirectory(): string
{
    $directory = sys_get_temp_dir() . '/foehn-tests/verify-' . uniqid('', true);

    mkdir($directory, 0o777, true);

    return $directory;
}

/**
 * A directory for a page cache under test, guaranteed not to be shared.
 */
function pageCacheRoot(): string
{
    return sys_get_temp_dir() . '/foehn-tests/page-cache-' . uniqid('', true);
}

/**
 * A page cache store rooted at a temporary directory.
 */
function pageCacheStore(string $root, int $ttl = 0): \Studiometa\Foehn\PageCache\Store
{
    return new \Studiometa\Foehn\PageCache\Store(new \Studiometa\Foehn\Config\PageCacheConfig(
        enabled: true,
        path: $root,
        ttl: $ttl,
    ));
}

/**
 * The one runtime deletion path, rooted at a temporary directory.
 */
function pageCacheInvalidator(string $root, bool $enabled = true): \Studiometa\Foehn\PageCache\Invalidator
{
    $config = new \Studiometa\Foehn\Config\PageCacheConfig(enabled: $enabled, path: $root);

    return new \Studiometa\Foehn\PageCache\Invalidator($config, new \Studiometa\Foehn\PageCache\Store($config));
}

/**
 * The `$_SERVER` of an ordinary anonymous GET for the site's own host.
 *
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function pageCacheServer(array $overrides = []): array
{
    return [
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST' => 'example.com',
        'REQUEST_URI' => '/blog/',
        ...$overrides,
    ];
}

/**
 * The keyed query args a project asked for, without the ones the framework always adds.
 *
 * `foehn_sections` is keyed on every configuration — see
 * {@see \Studiometa\Foehn\Config\PageCacheConfig::RESERVED_QUERY_ARGS} — and a test about
 * how a *project's* list is normalised has nothing to say about it.
 *
 * @return array<string, string>
 */
function projectCacheQueryArgs(\Studiometa\Foehn\Config\PageCacheConfig $config): array
{
    return array_diff_key(
        $config->getCacheQueryArgs(),
        array_flip(\Studiometa\Foehn\Config\PageCacheConfig::RESERVED_QUERY_ARGS),
    );
}

/**
 * The eligibility rules, on a config that has the cache switched on.
 */
function pageCacheBypass(?\Studiometa\Foehn\Config\PageCacheConfig $config = null): \Studiometa\Foehn\PageCache\Bypass
{
    return new \Studiometa\Foehn\PageCache\Bypass(
        $config ?? new \Studiometa\Foehn\Config\PageCacheConfig(enabled: true, environments: ['production']),
    );
}

/**
 * The warmer, on the given config, with the eligibility rules of that config.
 */
function pageCacheWarmer(\Studiometa\Foehn\Config\PageCacheConfig $config): \Studiometa\Foehn\PageCache\Warmer
{
    return new \Studiometa\Foehn\PageCache\Warmer($config, new \Studiometa\Foehn\PageCache\Bypass($config));
}

/**
 * A response body long enough and complete enough to be storable.
 */
function pageCacheBody(string $extra = ''): string
{
    return '<html><body>' . $extra . str_repeat('x', 300) . '</body></html>';
}

/**
 * A published post the stubs will resolve by id.
 */
function pageCachePost(int $id, string $type = 'post', string $name = 'hello-world'): WP_Post
{
    $post = new WP_Post();
    $post->ID = $id;
    $post->post_type = $type;
    $post->post_name = $name;
    $post->post_status = 'publish';
    $post->post_author = 7;
    $post->post_date = '2026-08-19 09:30:00';

    $GLOBALS['wp_stub_posts'][$id] = $post;

    return $post;
}

/**
 * A term whose archive URL the stubs can build.
 */
function pageCacheTerm(int $id, string $slug, string $taxonomy): WP_Term
{
    $term = new WP_Term();
    $term->term_id = $id;
    $term->slug = $slug;
    $term->taxonomy = $taxonomy;

    return $term;
}

/**
 * The admin cache handlers, with `exit` replaced by a counter.
 *
 * A handler that really exited would take the test runner with it, so the terminating
 * call is a collaborator — see `CacheActions::$halt`. The count is what proves a handler
 * stopped where it said it would.
 */
function adminCacheActions(\Studiometa\Foehn\PageCache\Invalidator $invalidator): \Studiometa\Foehn\Admin\CacheActions
{
    $GLOBALS['wp_stub_halted'] = 0;

    return new \Studiometa\Foehn\Admin\CacheActions($invalidator, static function (): void {
        $GLOBALS['wp_stub_halted']++;
    });
}

/**
 * Present the request an authorised POST to one of the cache actions looks like.
 *
 * Everything the handlers read lives in superglobals, so a test that wants to assert what
 * they do has to set them — and a test that wants to assert what they refuse has to set
 * all but one. Named arguments make the missing one the point of each case.
 *
 * @param array<string, string> $extra Additional POST fields, for the ones a handler must ignore.
 */
function adminCacheRequest(
    string $method = 'POST',
    bool $capable = true,
    ?string $nonce = null,
    ?int $postId = null,
    array $extra = [],
): void {
    $_SERVER['REQUEST_METHOD'] = $method;
    $GLOBALS['wp_stub_user_can'][\Studiometa\Foehn\Admin\CacheActions::CAPABILITY] = $capable;

    $_POST = $extra;

    if ($nonce !== null) {
        $_POST['_wpnonce'] = $nonce;
    }

    if ($postId !== null) {
        $_POST[\Studiometa\Foehn\Admin\CacheActions::POST_ID_FIELD] = (string) $postId;
    }
}

/**
 * Forget every superglobal a cache-action test wrote.
 *
 * `wp_stub_reset()` reaches neither the superglobals nor
 * `$GLOBALS['wp_stub_environment_type']`, and a leaked nonce would authorise the next
 * test's request for it.
 */
function adminCacheRequestReset(): void
{
    $_POST = [];
    $_GET = [];
    unset($_SERVER['REQUEST_METHOD'], $GLOBALS['wp_stub_halted'], $GLOBALS['wp_stub_environment_type']);
}

/**
 * A project root in a temporary directory, with an `app/` directory and the given
 * files, for code that reads `vendor/` and `node_modules/` under the root.
 *
 * Built at run time rather than kept under Fixtures/: `vendor/` and `node_modules/`
 * are ignored by many global gitignore files, so a committed copy could go missing
 * from a contributor's tree without git saying so.
 *
 * @param array<string, string> $files Contents by path relative to the root
 * @return string The root; its `app/` is the app path
 */
function testProjectRoot(array $files): string
{
    $root = sys_get_temp_dir() . '/foehn-tests/project-' . bin2hex(random_bytes(4));

    mkdir($root . '/app', 0o777, true);
    file_put_contents($root . '/composer.json', '{}');

    foreach ($files as $path => $contents) {
        if (!is_dir(dirname($root . '/' . $path))) {
            mkdir(dirname($root . '/' . $path), 0o777, true);
        }

        file_put_contents($root . '/' . $path, $contents);
    }

    return $root;
}

/**
 * A temporary directory for an error log, which a test removes with removeErrorLogDirectory().
 */
function errorLogDirectory(): string
{
    return sys_get_temp_dir() . '/foehn-error-log-' . bin2hex(random_bytes(4));
}

function removeErrorLogDirectory(string $directory): void
{
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
}

/**
 * Load Action Scheduler's schedule, action and cron expression classes.
 *
 * Action Scheduler is a WordPress plugin with its own autoloader, which needs
 * WordPress. Its schedule classes do not: these are the real ones, so a test can
 * check which date a schedule gives, not what a stub says. The `as_*()` functions
 * stay stubbed in wp-stubs.php.
 */
function loadActionSchedulerSchedules(): void
{
    if (class_exists('ActionScheduler_CronSchedule', false)) {
        return;
    }

    $root = \Composer\InstalledVersions::getInstallPath('woocommerce/action-scheduler');

    foreach ([
        'lib/cron-expression/CronExpression_FieldInterface.php',
        'lib/cron-expression/CronExpression_AbstractField.php',
        'lib/cron-expression/CronExpression_MinutesField.php',
        'lib/cron-expression/CronExpression_HoursField.php',
        'lib/cron-expression/CronExpression_DayOfMonthField.php',
        'lib/cron-expression/CronExpression_MonthField.php',
        'lib/cron-expression/CronExpression_DayOfWeekField.php',
        'lib/cron-expression/CronExpression_YearField.php',
        'lib/cron-expression/CronExpression_FieldFactory.php',
        'lib/cron-expression/CronExpression.php',
        'classes/ActionScheduler_DateTime.php',
        'classes/schedules/ActionScheduler_Schedule.php',
        'deprecated/ActionScheduler_Schedule_Deprecated.php',
        'classes/abstracts/ActionScheduler_Abstract_Schedule.php',
        'classes/abstracts/ActionScheduler_Abstract_RecurringSchedule.php',
        'classes/schedules/ActionScheduler_CronSchedule.php',
        'classes/schedules/ActionScheduler_IntervalSchedule.php',
        'classes/actions/ActionScheduler_Action.php',
        'classes/ActionScheduler_ScheduleDeserializer.php',
    ] as $file) {
        require_once $root . '/' . $file;
    }
}
