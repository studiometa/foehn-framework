<?php

declare(strict_types=1);

use Studiometa\WPTempest\Discovery\ViewComposerDiscovery;
use Tests\Fixtures\InvalidViewComposerFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\ViewComposerFixture;

beforeEach(function () {
    $this->discovery = new ViewComposerDiscovery();
});

describe('ViewComposerDiscovery', function () {
    it('discovers view composer attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(ViewComposerFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(ViewComposerFixture::class);
        expect($items[0]['templates'])->toBe(['single', 'page']);
        expect($items[0]['priority'])->toBe(5);
    });

    it('ignores classes without view composer attribute', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('throws when class does not implement ViewComposerInterface', function () {
        expect(fn() => $this->discovery->discover(new ReflectionClass(InvalidViewComposerFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(ViewComposerFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
