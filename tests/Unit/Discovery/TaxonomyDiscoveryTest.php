<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TaxonomyDiscovery;
use Tests\Fixtures\InvalidTaxonomyFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\TaxonomyFixture;

beforeEach(function () {
    $this->discovery = new TaxonomyDiscovery();
});

describe('TaxonomyDiscovery', function () {
    it('discovers taxonomy attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(TaxonomyFixture::class));

        $items = $this->discovery->getItems();

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
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('throws when class does not extend Timber Term', function () {
        expect(fn() => $this->discovery->discover(new ReflectionClass(InvalidTaxonomyFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must extend');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(TaxonomyFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
