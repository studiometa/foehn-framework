<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\CliCommandFixture;
use Tests\Fixtures\InvalidCliCommandFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new CliCommandDiscovery(
        new GenericContainer(),
        $this->problems = new DiscoveryProblems(),
        new FoehnConfig(),
    );
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

describe('CliCommandDiscovery command names', function () {
    it('puts a project command under wp app', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, $this->location);

        $item = iterator_to_array($this->discovery->getItems())[0];

        expect($this->discovery->commandName($item))->toBe('app test:run');
    });

    it('keeps a framework command under wp foehn', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, testVendorLocation());

        $item = iterator_to_array($this->discovery->getItems())[0];

        expect($this->discovery->commandName($item))->toBe('foehn test:run');
    });

    it('keeps a framework command under wp foehn when the package is a symlinked path repository', function () {
        // A path repository with symlinks resolves outside vendor/, so the
        // namespace is what says the class is the framework's.
        discoverFixture($this->discovery, CliCommandFixture::class, testDiscoveryLocation('Studiometa\\Foehn\\'));

        $item = iterator_to_array($this->discovery->getItems())[0];

        expect($this->discovery->commandName($item))->toBe('foehn test:run');
    });

    it('puts a project command under the namespace FoehnConfig gives', function () {
        $discovery = new CliCommandDiscovery(
            new GenericContainer(),
            new DiscoveryProblems(),
            new FoehnConfig(cliNamespace: 'site'),
        );

        discoverFixture($discovery, CliCommandFixture::class, $this->location);
        discoverFixture($discovery, CliCommandFixture::class, testVendorLocation());

        $names = array_map($discovery->commandName(...), iterator_to_array($discovery->getItems()));

        expect($names)->toBe(['site test:run', 'foehn test:run']);
    });

    it('registers each command with WP-CLI under its namespace', function () {
        // `WP_CLI` is a constant: defined here, it would leave every later test in
        // the run believing it was under WP-CLI, so the case runs in a subprocess.
        $script = <<<'PHP'
            require %s;
            define('WP_CLI', true);
            $discovery = new Studiometa\Foehn\Discovery\CliCommandDiscovery(
                new Tempest\Container\GenericContainer(),
                new Studiometa\Foehn\Discovery\DiscoveryProblems(),
                new Studiometa\Foehn\Config\FoehnConfig(),
            );
            $class = new Tempest\Reflection\ClassReflector(Tests\Fixtures\CliCommandFixture::class);
            $discovery->discover(new Tempest\Discovery\DiscoveryLocation('App\\', sys_get_temp_dir()), $class);
            $discovery->discover(new Tempest\Discovery\DiscoveryLocation('Studiometa\\Foehn\\', sys_get_temp_dir()), $class);
            $discovery->apply();
            echo implode(',', array_column(array_column(wp_stub_get_calls('wp_cli_add_command'), 'args'), 'name'));
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

        $printed = implode("\n", $output);

        expect($status)->toBe(0, $printed);
        expect($printed)->toBe('app test:run,foehn test:run');
    });
});
