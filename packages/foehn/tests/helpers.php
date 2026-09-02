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
): \Studiometa\Foehn\Discovery\TemplateControllerDiscovery {
    $request ??= new \Studiometa\Foehn\Views\Sections\SectionRequest('GET', '/');

    return new \Studiometa\Foehn\Discovery\TemplateControllerDiscovery(
        $request,
        $collector ?? new \Studiometa\Foehn\Views\Sections\SectionCollector(),
        testSectionResponse($request, $pageCacheConfig),
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

    return new \Studiometa\Foehn\Discovery\DiscoveryRunner(
        $container,
        new \Tempest\Discovery\DiscoveryCache(\Tempest\Discovery\DiscoveryCacheStrategy::NONE, $pool),
        $pool,
        new \Studiometa\Foehn\Discovery\DiscoveryLocations($appPath),
        $config,
    );
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
 * Generate, generate valid PHP, leave no placeholder, preview, refuse, force,
 * and reject a missing name — the same lines in every command. Shared rather
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

        it("{$label} refuses to overwrite without --force", function () use ($class, $args, $relative) {
            $path = "{$this->appPath}/{$relative}";

            ($this->make)($class)($args, []);
            file_put_contents($path, '<?php // hand-edited');
            wp_stub_reset();

            ($this->make)($class)($args, []);

            expect(file_get_contents($path))->toBe('<?php // hand-edited');
            expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);
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
            ($this->make)($class)([], []);

            expect(wp_stub_get_calls('wp_cli_error'))->toHaveCount(1);

            $directory = dirname("{$this->appPath}/{$case['path']}");

            expect(is_dir($directory) ? (glob($directory . '/*.php') ?: []) : [])->toBeEmpty();
        });
    }
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
