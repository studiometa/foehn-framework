<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Tempest\Discovery\DiscoveryLocation;
use Tests\Fixtures\CliCommandFixture;
use Tests\Fixtures\InvalidCliCommandFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = testCliCommandDiscovery(problems: $this->problems = new DiscoveryProblems());
});

describe('CliCommandDiscovery', function () {
    it('discovers CLI command attributes on classes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(CliCommandFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(CliCommandFixture::class);
        expect($items[0]['attribute']->name)->toBe('test:run');
        expect($items[0]['attribute']->description)->toBe('Run a test command');
        expect($items[0]['attribute']->longDescription)->toBe('This is a long description for the test command.');
    });

    it('ignores classes without CLI command attribute', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('ignores classes that do not implement CliCommandInterface', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(InvalidCliCommandFixture::class),
        );

        expect($this->discovery->getItems())->toHaveCount(0);
        expect($this->problems->all()[0]['attribute'])->toBe('#[AsCliCommand]');
        expect($this->problems->all()[0]['fix'])
            ->toBe(
                'Declare '
                . InvalidCliCommandFixture::class
                . ' with `implements \\Studiometa\\Foehn\\Console\\CliCommandInterface`.',
            );
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(CliCommandFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });

    it('checks a command that writes against the environment before it runs it', function () {
        // apply() registers only under WP-CLI, and defining WP_CLI here would turn it on
        // for every test after this one: a separate process keeps it to this case.
        $script = <<<'PHP'
            require %s;
            define('WP_CLI', true);
            $GLOBALS['wp_stub_environment_type'] = 'production';
            $container = new Tempest\Container\GenericContainer();
            $discovery = new Studiometa\Foehn\Discovery\CliCommandDiscovery(
                $container,
                new Studiometa\Foehn\Discovery\DiscoveryProblems(),
                new Studiometa\Foehn\Config\FoehnConfig(),
                new Studiometa\Foehn\Discovery\InstalledPackages(new Studiometa\Foehn\Discovery\DiscoveryLocations()),
            );
            $discovery->discover(
                new Tempest\Discovery\DiscoveryLocation('App\\', sys_get_temp_dir()),
                new Tempest\Reflection\ClassReflector(Tests\Fixtures\WritingCliCommandFixture::class),
            );
            $discovery->apply();
            $callback = wp_stub_get_calls('wp_cli_add_command')[0]['args']['callback'];
            foreach ([[], ['dry-run' => true], ['force' => true]] as $assocArgs) {
                $GLOBALS['wp_stub_calls'] = [];
                $callback([], $assocArgs);
                echo json_encode([
                    'ran' => count(wp_stub_get_calls('fixture_command_ran')),
                    'errors' => count(wp_stub_get_calls('wp_cli_error')),
                ]), "\n";
            }
            PHP;

        $output = [];
        $status = 0;
        exec(
            'php -r '
            . escapeshellarg(sprintf($script, var_export(dirname(__DIR__, 2) . '/bootstrap.php', true)))
            . ' 2>&1',
            $output,
            $status,
        );

        expect($status)->toBe(0, implode("\n", $output));
        expect($output)->toBe([
            '{"ran":0,"errors":1}',
            '{"ran":1,"errors":0}',
            '{"ran":1,"errors":0}',
        ]);
    });
});

/**
 * The command names of the items a discovery holds, in order.
 *
 * @return list<string>
 */
function commandNames(CliCommandDiscovery $discovery): array
{
    return array_map($discovery->commandName(...), iterator_to_array($discovery->getItems()));
}

/**
 * The location of a package of a testComposerRoot(), as Tempest builds it.
 */
function packageLocation(string $root, string $name): DiscoveryLocation
{
    return new DiscoveryLocation('Acme\\', $root . '/vendor/' . $name . '/src');
}

describe('CliCommandDiscovery command names', function () {
    beforeEach(function () {
        $this->root = testComposerRoot([
            'studiometa/foehn' => [],
            'studiometa/foehn-acf' => [],
            'acme/shop-tools' => [],
            'acme/declared' => ['extra' => ['foehn' => ['cli-namespace' => 'shop']]],
            'acme/invalid' => ['extra' => ['foehn' => ['cli-namespace' => 'two words']]],
            'acme/takes-foehn' => ['extra' => ['foehn' => ['cli-namespace' => 'foehn']]],
            'acme/foehn' => [],
        ]);
        $this->problems = new DiscoveryProblems();
        $this->discovery = testCliCommandDiscovery(composerRoot: $this->root, problems: $this->problems);
    });

    afterEach(fn() => removeTestComposerRoot($this->root));

    it('puts a project command under wp app', function () {
        discoverFixture(
            $this->discovery,
            CliCommandFixture::class,
            new DiscoveryLocation('App\\', $this->root . '/app'),
        );

        expect(commandNames($this->discovery))->toBe(['app test:run']);
    });

    it('puts a project command under the namespace FoehnConfig gives', function () {
        $discovery = testCliCommandDiscovery(new FoehnConfig(cliNamespace: 'site'), $this->root);

        discoverFixture($discovery, CliCommandFixture::class, new DiscoveryLocation('App\\', $this->root . '/app'));
        discoverFixture($discovery, CliCommandFixture::class, packageLocation($this->root, 'studiometa/foehn'));

        expect(commandNames($discovery))->toBe(['site test:run', 'foehn test:run']);
    });

    it('keeps a framework command under wp foehn', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, packageLocation($this->root, 'studiometa/foehn'));

        expect(commandNames($this->discovery))->toBe(['foehn test:run']);
    });

    it('keeps a framework command under wp foehn when the framework is a symlinked path repository', function () {
        $root = testComposerRoot(['studiometa/foehn' => ['symlink' => true]]);
        $discovery = testCliCommandDiscovery(composerRoot: $root);
        $location = packageLocation($root, 'studiometa/foehn');

        try {
            // The location resolves to the real path, outside vendor/.
            expect($location->path)->toBe(realpath($root . '/packages/studiometa/foehn/src'));
            expect($location->isVendor())->toBeFalse();

            discoverFixture($discovery, CliCommandFixture::class, $location);

            expect(commandNames($discovery))->toBe(['foehn test:run']);
        } finally {
            removeTestComposerRoot($root);
        }
    });

    it('puts a package command under the package name without its vendor', function () {
        discoverFixture(
            $this->discovery,
            CliCommandFixture::class,
            packageLocation($this->root, 'studiometa/foehn-acf'),
        );
        discoverFixture($this->discovery, CliCommandFixture::class, packageLocation($this->root, 'acme/shop-tools'));

        expect(commandNames($this->discovery))->toBe(['foehn-acf test:run', 'shop-tools test:run']);
    });

    it('puts a package command under the namespace the package declares', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, packageLocation($this->root, 'acme/declared'));

        expect(commandNames($this->discovery))->toBe(['shop test:run']);
    });

    it('reports the commands of a package whose namespace is not valid', function (string $package) {
        discoverFixture($this->discovery, CliCommandFixture::class, packageLocation($this->root, $package));

        expect($this->discovery->getItems())->toHaveCount(0);
        expect($this->problems->all())->toHaveCount(1);
        expect($this->problems->all()[0]['reason'])
            ->toContain(sprintf('The package %s has no valid WP-CLI namespace', $package));
        expect($this->problems->all()[0]['fix'])
            ->toBe(sprintf(
                'Set "extra": {"foehn": {"cli-namespace": "<name>"}} in the composer.json of %s.',
                $package,
            ));
    })->with(['acme/invalid', 'acme/takes-foehn', 'acme/foehn']);

    it('treats a directory of the project as the project, even inside a package directory', function () {
        // The monorepo maps its tests to packages/foehn/tests: in the package
        // directory, but not one of its PSR-4 directories.
        mkdir($this->root . '/vendor/studiometa/foehn/tests');
        discoverFixture(
            $this->discovery,
            CliCommandFixture::class,
            new DiscoveryLocation('Tests\\', $this->root . '/vendor/studiometa/foehn/tests'),
        );

        expect(commandNames($this->discovery))->toBe(['app test:run']);
    });

    it('treats the app directory as the project, even when a package installs the theme', function () {
        // A theme that the site's composer.json requires, and Composer installs
        // outside vendor/: its app directory is in installed.json, but its
        // commands are the project's.
        $root = testComposerRoot([]);
        $app = $root . '/web/themes/theme/app';
        mkdir($app, 0o777, true);
        file_put_contents($root . '/vendor/composer/installed.json', json_encode([
            'packages' => [[
                'name' => 'acme/theme',
                'autoload' => ['psr-4' => ['App\\' => 'app/']],
                'install-path' => '../../web/themes/theme',
            ]],
        ]));

        $discovery = new CliCommandDiscovery(
            new \Tempest\Container\GenericContainer(),
            new DiscoveryProblems(),
            new FoehnConfig(),
            new \Studiometa\Foehn\Discovery\InstalledPackages(new \Studiometa\Foehn\Discovery\DiscoveryLocations($app)),
        );

        try {
            discoverFixture($discovery, CliCommandFixture::class, new DiscoveryLocation('App\\', $app));

            expect(commandNames($discovery))->toBe(['app test:run']);
        } finally {
            removeTestComposerRoot($root);
        }
    });

    it('registers each command with WP-CLI under its namespace, and none of a clash', function () {
        // `WP_CLI` is a constant: defined here, it would leave every later test in
        // the run believing it was under WP-CLI, so the case runs in a subprocess.
        $script = <<<'PHP'
            require %1$s;
            define('WP_CLI', true);
            $root = %2$s;
            $discovery = new Studiometa\Foehn\Discovery\CliCommandDiscovery(
                new Tempest\Container\GenericContainer(),
                new Studiometa\Foehn\Discovery\DiscoveryProblems(),
                new Studiometa\Foehn\Config\FoehnConfig(),
                new Studiometa\Foehn\Discovery\InstalledPackages(new Studiometa\Foehn\Discovery\DiscoveryLocations($root . '/app')),
            );
            $class = new Tempest\Reflection\ClassReflector(Tests\Fixtures\CliCommandFixture::class);
            $other = new Tempest\Reflection\ClassReflector(Tests\Fixtures\OtherCliCommandFixture::class);
            $discovery->discover(new Tempest\Discovery\DiscoveryLocation('App\\', $root . '/app'), $class);
            $discovery->discover(new Tempest\Discovery\DiscoveryLocation('Acme\\', $root . '/vendor/studiometa/foehn/src'), $class);
            $discovery->discover(new Tempest\Discovery\DiscoveryLocation('Acme\\', $root . '/vendor/acme/shop-tools/src'), $class);
            // acme/declared and acme/shop-tools both put test:run under one namespace.
            $discovery->discover(new Tempest\Discovery\DiscoveryLocation('Acme\\', $root . '/vendor/acme/declared/src'), $other);
            $discovery->apply();
            echo implode(',', array_column(array_column(wp_stub_get_calls('wp_cli_add_command'), 'args'), 'name')), "\n";
            echo implode("\n", array_column(array_column(wp_stub_get_calls('wp_cli_warning'), 'args'), 'message'));
            PHP;

        $root = testComposerRoot([
            'studiometa/foehn' => [],
            'acme/shop-tools' => [],
            'acme/declared' => ['extra' => ['foehn' => ['cli-namespace' => 'shop-tools']]],
        ]);

        $output = [];
        $status = 0;

        try {
            exec(
                'php -r '
                . escapeshellarg(sprintf(
                    $script,
                    var_export(dirname(__DIR__, 2) . '/bootstrap.php', true),
                    var_export($root, true),
                ))
                . ' 2>&1',
                $output,
                $status,
            );
        } finally {
            removeTestComposerRoot($root);
        }

        $printed = implode("\n", $output);

        expect($status)->toBe(0, $printed);
        expect($output[0])->toBe('app test:run,foehn test:run');
        expect($output[1])
            ->toBe(
                '`wp shop-tools test:run` is declared by Tests\Fixtures\CliCommandFixture and '
                . 'Tests\Fixtures\OtherCliCommandFixture. None of them is registered: give each a command name of its own.',
            );
    });
});
