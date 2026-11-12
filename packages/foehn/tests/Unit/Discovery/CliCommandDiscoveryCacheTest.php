<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCliCommand;
use Tests\Fixtures\CliCommandFixture;
use Tests\Fixtures\WritingCliCommandFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = testCliCommandDiscovery();
});

describe('CliCommandDiscovery caching', function () {
    it('keeps every item under its location namespace', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, testCliCommandDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, CliCommandFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, testCliCommandDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

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

    it('restores whether the command writes, so a warm cache still guards it', function () {
        discoverFixture($this->discovery, WritingCliCommandFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, testCliCommandDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute']->writes)->toBeTrue();
    });
});
