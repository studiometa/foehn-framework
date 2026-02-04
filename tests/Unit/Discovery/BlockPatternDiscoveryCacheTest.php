<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Discovery\BlockPatternDiscovery;

beforeEach(function () {
    $this->discovery = new BlockPatternDiscovery();
});

describe('BlockPatternDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $attribute = new AsBlockPattern(
            name: 'my-theme/hero-pattern',
            title: 'Hero Pattern',
            categories: ['featured', 'header'],
            keywords: ['hero', 'banner'],
            blockTypes: ['core/cover', 'core/group'],
            viewportWidth: 1400,
            description: 'A full-width hero section',
            template: 'patterns/hero.twig',
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Patterns\\HeroPattern',
            'implementsInterface' => true,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['patternName'])->toBe('my-theme/hero-pattern');
        expect($cacheableData[0]['title'])->toBe('Hero Pattern');
        expect($cacheableData[0]['categories'])->toBe(['featured', 'header']);
        expect($cacheableData[0]['keywords'])->toBe(['hero', 'banner']);
        expect($cacheableData[0]['blockTypes'])->toBe(['core/cover', 'core/group']);
        expect($cacheableData[0]['viewportWidth'])->toBe(1400);
        expect($cacheableData[0]['description'])->toBe('A full-width hero section');
        expect($cacheableData[0]['templatePath'])->toBe('patterns/hero.twig');
        expect($cacheableData[0]['className'])->toBe('App\\Patterns\\HeroPattern');
        expect($cacheableData[0]['implementsInterface'])->toBeTrue();
    });

    it('handles auto-resolved template path', function () {
        $attribute = new AsBlockPattern(name: 'my-theme/cta-pattern', title: 'CTA Pattern');

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Patterns\\CtaPattern',
            'implementsInterface' => false,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['templatePath'])->toBe('patterns/cta-pattern');
    });

    it('handles inserter visibility', function () {
        $attribute = new AsBlockPattern(name: 'my-theme/internal-pattern', title: 'Internal Pattern', inserter: false);

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Patterns\\InternalPattern',
            'implementsInterface' => false,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['inserter'])->toBeFalse();
    });

    it('handles minimal configuration', function () {
        $attribute = new AsBlockPattern(name: 'my-theme/simple', title: 'Simple Pattern');

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Patterns\\SimplePattern',
            'implementsInterface' => false,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['categories'])->toBe([]);
        expect($cacheableData[0]['keywords'])->toBe([]);
        expect($cacheableData[0]['blockTypes'])->toBe([]);
        expect($cacheableData[0]['viewportWidth'])->toBe(1200);
        expect($cacheableData[0]['inserter'])->toBeTrue();
        expect($cacheableData[0]['description'])->toBeNull();
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'patternName' => 'my-theme/hero-pattern',
                'title' => 'Hero Pattern',
                'templatePath' => 'patterns/hero.twig',
                'className' => 'App\\Patterns\\HeroPattern',
                'implementsInterface' => true,
                'viewportWidth' => 1200,
                'inserter' => true,
                'categories' => [],
                'keywords' => [],
                'blockTypes' => [],
                'description' => null,
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
