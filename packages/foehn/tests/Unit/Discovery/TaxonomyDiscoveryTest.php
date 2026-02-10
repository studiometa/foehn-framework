<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TaxonomyDiscovery;
use Tests\Fixtures\InvalidTaxonomyFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TaxonomyFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new TaxonomyDiscovery();
});

describe('TaxonomyDiscovery', function () {
    it('discovers taxonomy attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TaxonomyFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(TaxonomyFixture::class);
        expect($items[0]['attribute']->name)->toBe('project_category');
        expect($items[0]['attribute']->singular)->toBe('Category');
        expect($items[0]['attribute']->plural)->toBe('Categories');
        expect($items[0]['attribute']->hierarchical)->toBeTrue();
        expect($items[0]['attribute']->postTypes)->toBe(['project']);
        expect($items[0]['implementsConfig'])->toBeFalse();
    });

    it('ignores classes without taxonomy attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('throws when class does not extend Timber Term', function () {
        expect(fn() => $this->discovery->discover($this->location, new ReflectionClass(InvalidTaxonomyFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must extend');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(TaxonomyFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
