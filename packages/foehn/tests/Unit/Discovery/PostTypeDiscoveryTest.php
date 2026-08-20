<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Tests\Fixtures\ConfigurablePostTypeFixture;
use Tests\Fixtures\InvalidPostTypeFixture;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\PostTypeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new PostTypeDiscovery();
});

describe('PostTypeDiscovery', function () {
    it('discovers post type attributes on classes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(PostTypeFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(PostTypeFixture::class);
        expect($items[0]['attribute']->name)->toBe('project');
        expect($items[0]['attribute']->singular)->toBe('Project');
        expect($items[0]['attribute']->plural)->toBe('Projects');
        expect($items[0]['attribute']->menuIcon)->toBe('dashicons-portfolio');
        expect($items[0]['implementsConfig'])->toBeFalse();
    });

    it('sees the configuration interface on a Timber model', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(ConfigurablePostTypeFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        // Timber\Post declares a protected constructor, so the class is not
        // instantiable — and Tempest's $class->implements() is gated on exactly that.
        // Reading it through that helper reported false for every model a real theme
        // has, which silently dropped configurePostType(): the rewrite slug a post
        // type asked for was never applied, and its archive answered 404.
        expect(new ReflectionClass(ConfigurablePostTypeFixture::class)->isInstantiable())->toBeFalse();
        expect($items[0]['implementsConfig'])->toBeTrue();
    });

    it('ignores classes without post type attribute', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('throws when class does not extend Timber Post', function () {
        expect(fn() => $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(InvalidPostTypeFixture::class),
        ))
            ->toThrow(InvalidArgumentException::class, 'must extend');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(PostTypeFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});
