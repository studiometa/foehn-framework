<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\CliCommandFixture;
use Tests\Fixtures\InvalidCliCommandFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new CliCommandDiscovery(new GenericContainer(), $this->problems = new DiscoveryProblems());
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
