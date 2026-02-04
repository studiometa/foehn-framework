<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Tests\Fixtures\InvalidTimberModelFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TimberModelPostFixture;
use Tests\Fixtures\TimberModelTermFixture;

beforeEach(function () {
    $this->discovery = new TimberModelDiscovery();
});

describe('TimberModelDiscovery', function () {
    it('discovers post timber model attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(TimberModelPostFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TimberModelPostFixture::class);
        expect($items[0]['attribute']->name)->toBe('post');
        expect($items[0]['type'])->toBe('post');
    });

    it('discovers term timber model attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(TimberModelTermFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TimberModelTermFixture::class);
        expect($items[0]['attribute']->name)->toBe('category');
        expect($items[0]['type'])->toBe('term');
    });

    it('ignores classes without timber model attribute', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('throws when class does not extend Timber Post or Term', function () {
        expect(fn() => $this->discovery->discover(new ReflectionClass(InvalidTimberModelFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must extend');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(TimberModelPostFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
