<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\AcfBlockDiscovery;
use Tests\Fixtures\AcfBlockFixture;
use Tests\Fixtures\InvalidAcfBlockFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new AcfBlockDiscovery();
});

describe('AcfBlockDiscovery', function () {
    it('discovers ACF block attributes on classes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(AcfBlockFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

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
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('throws when class does not implement AcfBlockInterface', function () {
        expect(fn() => $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(InvalidAcfBlockFixture::class),
        ))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(AcfBlockFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});
