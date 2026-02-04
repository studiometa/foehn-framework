<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\AcfBlockDiscovery;
use Tests\Fixtures\AcfBlockFixture;
use Tests\Fixtures\InvalidAcfBlockFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->discovery = new AcfBlockDiscovery();
});

describe('AcfBlockDiscovery', function () {
    it('discovers ACF block attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(AcfBlockFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(AcfBlockFixture::class);
        expect($items[0]['attribute']->name)->toBe('testimonial');
        expect($items[0]['attribute']->title)->toBe('Testimonial');
        expect($items[0]['attribute']->description)->toBe('A testimonial block.');
        expect($items[0]['attribute']->category)->toBe('formatting');
        expect($items[0]['attribute']->icon)->toBe('format-quote');
        expect($items[0]['attribute']->keywords)->toBe(['quote', 'testimonial']);
    });

    it('ignores classes without ACF block attribute', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('throws when class does not implement AcfBlockInterface', function () {
        expect(fn() => $this->discovery->discover(new ReflectionClass(InvalidAcfBlockFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(AcfBlockFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
