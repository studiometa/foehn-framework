<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\AcfFieldGroupDiscovery;
use Tests\Fixtures\AcfFieldGroupFixture;
use Tests\Fixtures\InvalidAcfFieldGroupFixture;
use Tests\Fixtures\NoAttributeFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new AcfFieldGroupDiscovery();
});

describe('AcfFieldGroupDiscovery', function () {
    it('discovers ACF field group attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfFieldGroupFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(AcfFieldGroupFixture::class);
        expect($items[0]['attribute']->name)->toBe('property_fields');
        expect($items[0]['attribute']->title)->toBe('Property Details');
        expect($items[0]['attribute']->location)->toBe(['post_type' => 'property']);
        expect($items[0]['attribute']->position)->toBe('acf_after_title');
        expect($items[0]['attribute']->menuOrder)->toBe(0);
        expect($items[0]['attribute']->style)->toBe('seamless');
        expect($items[0]['attribute']->labelPlacement)->toBe('left');
        expect($items[0]['attribute']->instructionPlacement)->toBe('field');
        expect($items[0]['attribute']->hideOnScreen)->toBe(['the_content', 'excerpt']);
    });

    it('ignores classes without ACF field group attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('throws when class does not implement AcfFieldGroupInterface', function () {
        expect(fn () => $this->discovery->discover($this->location, new ReflectionClass(InvalidAcfFieldGroupFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(AcfFieldGroupFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});

describe('AcfFieldGroupDiscovery::parseLocation', function () {
    it('parses simplified location format', function () {
        $discovery = new AcfFieldGroupDiscovery();

        $result = $discovery->parseLocation(['post_type' => 'product']);

        expect($result)->toBe([
            [
                ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
            ],
        ]);
    });

    it('parses simplified location with multiple conditions', function () {
        $discovery = new AcfFieldGroupDiscovery();

        $result = $discovery->parseLocation([
            'post_type' => 'page',
            'page_template' => 'page-faq.php',
        ]);

        expect($result)->toBe([
            [
                ['param' => 'post_type', 'operator' => '==', 'value' => 'page'],
                ['param' => 'page_template', 'operator' => '==', 'value' => 'page-faq.php'],
            ],
        ]);
    });

    it('passes through full ACF format unchanged', function () {
        $discovery = new AcfFieldGroupDiscovery();

        $fullFormat = [
            [
                ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
                ['param' => 'post_status', 'operator' => '!=', 'value' => 'draft'],
            ],
            [
                ['param' => 'page_template', 'operator' => '==', 'value' => 'page-shop.php'],
            ],
        ];

        $result = $discovery->parseLocation($fullFormat);

        expect($result)->toBe($fullFormat);
    });

    it('handles empty location array', function () {
        $discovery = new AcfFieldGroupDiscovery();

        $result = $discovery->parseLocation([]);

        expect($result)->toBe([[]]);
    });
});
