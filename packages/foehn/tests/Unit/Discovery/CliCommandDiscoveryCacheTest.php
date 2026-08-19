<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\CliCommandFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new CliCommandDiscovery(new GenericContainer());
});

describe('CliCommandDiscovery caching', function () {
    it('keeps every item under its location namespace', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new CliCommandDiscovery(new GenericContainer()));

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, $this->location);

        $item = restoreThroughCacheFile(
            $this->discovery,
            new CliCommandDiscovery(new GenericContainer()),
        )->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsCliCommand::class)
            ->and($item['attribute']->name)
            ->toBe('test:run')
            ->and($item['attribute']->description)
            ->toBe('Run a test command')
            ->and($item['attribute']->longDescription)
            ->toBe('This is a long description for the test command.')
            ->and($item['className'])
            ->toBe(CliCommandFixture::class);
    });
});
