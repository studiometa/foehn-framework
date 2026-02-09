<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery\BlockPatternDiscovery;
use Tests\Fixtures\BlockPatternFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    $container = bootTestContainer();

    // Register a stub ViewEngine that returns rendered content
    $container->singleton(ViewEngineInterface::class, fn() => new class implements ViewEngineInterface {
        public function render(string $template, array $context = []): string
        {
            return '<div>Pattern: ' . $template . '</div>';
        }

        public function renderFirst(array $templates, array $context = []): string
        {
            return $this->render($templates[0], $context);
        }

        public function exists(string $template): bool
        {
            return true;
        }

        public function share(string $key, mixed $value): void
        {
        }

        public function getShared(): array
        {
            return [];
        }
    });

    $this->discovery = new BlockPatternDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('BlockPatternDiscovery apply', function () {
    it('registers init action for pattern registration', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockPatternFixture::class));
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');

        expect($actions)->toHaveCount(1);
        expect($actions[0]['args']['hook'])->toBe('init');
    });

    it('registers block patterns when init callback is invoked', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockPatternFixture::class));
        $this->discovery->apply();

        // Simulate WordPress calling the init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $patterns = wp_stub_get_calls('register_block_pattern');

        expect($patterns)->toHaveCount(1);
        expect($patterns[0]['args']['name'])->toBe('test/hero-pattern');
        expect($patterns[0]['args']['config']['title'])->toBe('Hero Pattern');
        expect($patterns[0]['args']['config']['categories'])->toBe(['featured']);
        expect($patterns[0]['args']['config']['keywords'])->toBe(['hero']);
        expect($patterns[0]['args']['config']['description'])->toBe('A hero pattern.');
        expect($patterns[0]['args']['config']['content'])->toContain('Pattern:');
    });

    it('registers no patterns when no items discovered', function () {
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');
        expect($actions)->toHaveCount(1);

        $callback = $actions[0]['args']['callback'];
        $callback();

        expect(wp_stub_get_calls('register_block_pattern'))->toBeEmpty();
    });
});
