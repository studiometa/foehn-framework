<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Discovery\AcfFieldGroupDiscovery;

beforeEach(function () {
    $this->discovery = new AcfFieldGroupDiscovery();
});

describe('AcfFieldGroupDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $attribute = new AsAcfFieldGroup(
            name: 'property_fields',
            title: 'Property Details',
            location: ['post_type' => 'property'],
            position: 'acf_after_title',
            menuOrder: 10,
            style: 'seamless',
            labelPlacement: 'left',
            instructionPlacement: 'field',
            hideOnScreen: ['the_content', 'excerpt'],
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Fields\\PropertyFields',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['name'])->toBe('property_fields');
        expect($cacheableData[0]['title'])->toBe('Property Details');
        expect($cacheableData[0]['location'])->toBe(['post_type' => 'property']);
        expect($cacheableData[0]['position'])->toBe('acf_after_title');
        expect($cacheableData[0]['menuOrder'])->toBe(10);
        expect($cacheableData[0]['style'])->toBe('seamless');
        expect($cacheableData[0]['labelPlacement'])->toBe('left');
        expect($cacheableData[0]['instructionPlacement'])->toBe('field');
        expect($cacheableData[0]['hideOnScreen'])->toBe(['the_content', 'excerpt']);
        expect($cacheableData[0]['className'])->toBe('App\\Fields\\PropertyFields');
    });

    it('handles minimal configuration', function () {
        $attribute = new AsAcfFieldGroup(
            name: 'minimal_fields',
            title: 'Minimal Fields',
            location: ['post_type' => 'post'],
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Fields\\MinimalFields',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['position'])->toBe('normal');
        expect($cacheableData[0]['menuOrder'])->toBe(0);
        expect($cacheableData[0]['style'])->toBe('default');
        expect($cacheableData[0]['labelPlacement'])->toBe('top');
        expect($cacheableData[0]['instructionPlacement'])->toBe('label');
        expect($cacheableData[0]['hideOnScreen'])->toBe([]);
    });

    it('handles full ACF location format', function () {
        $location = [
            [
                ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
                ['param' => 'post_status', 'operator' => '!=', 'value' => 'draft'],
            ],
            [
                ['param' => 'page_template', 'operator' => '==', 'value' => 'page-shop.php'],
            ],
        ];

        $attribute = new AsAcfFieldGroup(
            name: 'complex_fields',
            title: 'Complex Fields',
            location: $location,
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Fields\\ComplexFields',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['location'])->toBe($location);
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'name' => 'property_fields',
                'title' => 'Property Details',
                'location' => ['post_type' => 'property'],
                'position' => 'acf_after_title',
                'menuOrder' => 0,
                'style' => 'seamless',
                'labelPlacement' => 'left',
                'instructionPlacement' => 'field',
                'hideOnScreen' => ['the_content'],
                'className' => 'App\\Fields\\PropertyFields',
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('can handle multiple items', function () {
        $attribute1 = new AsAcfFieldGroup(
            name: 'post_fields',
            title: 'Post Fields',
            location: ['post_type' => 'post'],
        );

        $attribute2 = new AsAcfFieldGroup(
            name: 'page_fields',
            title: 'Page Fields',
            location: ['post_type' => 'page'],
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute1,
            'className' => 'App\\Fields\\PostFields',
        ]);
        $ref->invoke($this->discovery, [
            'attribute' => $attribute2,
            'className' => 'App\\Fields\\PageFields',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(2);
        expect($cacheableData[0]['name'])->toBe('post_fields');
        expect($cacheableData[1]['name'])->toBe('page_fields');
    });
});
