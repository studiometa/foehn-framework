<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsShortcode;
use Studiometa\WPTempest\Discovery\ShortcodeDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->discovery = new ShortcodeDiscovery();
    $this->discovery->setItems(new DiscoveryItems());
    $this->location = new DiscoveryLocation(
        namespace: 'App\\Test',
        path: __DIR__,
    );
});

/**
 * Create a mock method reflector.
 */
function createMethodReflector(string $className, string $methodName): object
{
    $classReflector = new class ($className) {
        public function __construct(private string $name) {}

        public function getName(): string
        {
            return $this->name;
        }
    };

    return new class ($classReflector, $methodName) {
        public function __construct(
            private object $class,
            private string $method,
        ) {}

        public function getDeclaringClass(): object
        {
            return $this->class;
        }

        public function getName(): string
        {
            return $this->method;
        }
    };
}

describe('ShortcodeDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $attribute = new AsShortcode('my_shortcode');
        $methodReflector = createMethodReflector('App\\Shortcodes\\MyShortcode', 'render');

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'method' => $methodReflector,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'tag' => 'my_shortcode',
            'className' => 'App\\Shortcodes\\MyShortcode',
            'methodName' => 'render',
        ]);
    });

    it('handles multiple shortcodes', function () {
        $attribute1 = new AsShortcode('gallery');
        $attribute2 = new AsShortcode('button');

        $methodReflector1 = createMethodReflector('App\\Shortcodes\\Gallery', 'renderGallery');
        $methodReflector2 = createMethodReflector('App\\Shortcodes\\Button', 'renderButton');

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute1,
            'method' => $methodReflector1,
        ]);
        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute2,
            'method' => $methodReflector2,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(2);
        expect($cacheableData[0]['tag'])->toBe('gallery');
        expect($cacheableData[1]['tag'])->toBe('button');
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'tag' => 'cached_shortcode',
                'className' => 'App\\Shortcodes\\CachedShortcode',
                'methodName' => 'handle',
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles shortcodes from same class', function () {
        $attribute1 = new AsShortcode('link');
        $attribute2 = new AsShortcode('external_link');

        $methodReflector1 = createMethodReflector('App\\Shortcodes\\LinkShortcodes', 'renderLink');
        $methodReflector2 = createMethodReflector('App\\Shortcodes\\LinkShortcodes', 'renderExternalLink');

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute1,
            'method' => $methodReflector1,
        ]);
        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute2,
            'method' => $methodReflector2,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(2);
        expect($cacheableData[0]['className'])->toBe('App\\Shortcodes\\LinkShortcodes');
        expect($cacheableData[1]['className'])->toBe('App\\Shortcodes\\LinkShortcodes');
        expect($cacheableData[0]['methodName'])->toBe('renderLink');
        expect($cacheableData[1]['methodName'])->toBe('renderExternalLink');
    });
});
