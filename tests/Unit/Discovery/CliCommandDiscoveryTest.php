<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\CliCommandFixture;
use Tests\Fixtures\InvalidCliCommandFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->discovery = new CliCommandDiscovery(new GenericContainer());
});

describe('CliCommandDiscovery', function () {
    it('discovers CLI command attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(CliCommandFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(CliCommandFixture::class);
        expect($items[0]['name'])->toBe('test:run');
        expect($items[0]['description'])->toBe('Run a test command');
        expect($items[0]['longDescription'])->toBe('This is a long description for the test command.');
    });

    it('ignores classes without CLI command attribute', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('ignores classes that do not implement CliCommandInterface', function () {
        $this->discovery->discover(new ReflectionClass(InvalidCliCommandFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(CliCommandFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
