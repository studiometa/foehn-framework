<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\CliCommandFixture;
use Tests\Fixtures\InvalidCliCommandFixture;
use Tests\Fixtures\NoAttributeFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new CliCommandDiscovery(new GenericContainer());
});

describe('CliCommandDiscovery', function () {
    it('discovers CLI command attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(CliCommandFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(CliCommandFixture::class);
        expect($items[0]['name'])->toBe('test:run');
        expect($items[0]['description'])->toBe('Run a test command');
        expect($items[0]['longDescription'])->toBe('This is a long description for the test command.');
    });

    it('ignores classes without CLI command attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('ignores classes that do not implement CliCommandInterface', function () {
        $this->discovery->discover($this->location, new ReflectionClass(InvalidCliCommandFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(CliCommandFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
