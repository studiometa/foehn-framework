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
});
