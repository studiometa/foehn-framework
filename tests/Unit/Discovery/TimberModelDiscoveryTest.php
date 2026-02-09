<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Tests\Fixtures\InvalidTimberModelFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TimberModelPostFixture;
use Tests\Fixtures\TimberModelTermFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new TimberModelDiscovery();
});

describe('TimberModelDiscovery', function () {
    it('discovers post timber model attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TimberModelPostFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TimberModelPostFixture::class);
        expect($items[0]['attribute']->name)->toBe('post');
        expect($items[0]['type'])->toBe('post');
    });

    it('discovers term timber model attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TimberModelTermFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TimberModelTermFixture::class);
        expect($items[0]['attribute']->name)->toBe('category');
        expect($items[0]['type'])->toBe('term');
    });

    it('ignores classes without timber model attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('throws when class does not extend Timber Post or Term', function () {
        expect(fn() => $this->discovery->discover($this->location, new ReflectionClass(InvalidTimberModelFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must extend');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(TimberModelPostFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
