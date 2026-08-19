<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\AcfBlockDiscovery;
use Studiometa\Foehn\Discovery\BlockDiscovery;
use Studiometa\Foehn\Discovery\BlockPatternDiscovery;
use Tests\Fixtures\ConstrainedAcfBlockFixture;
use Tests\Fixtures\ConstrainedBlockFixture;
use Tests\Fixtures\ConstrainedBlockPatternFixture;

/**
 * The optional registration arguments each block family passes to WordPress.
 *
 * They are the arguments a block only sends when the attribute sets them —
 * parent, ancestor, post types, block types — so each needs a fixture that sets
 * it, or the branch that adds it to the registration array never runs.
 */
beforeEach(function () {
    wp_stub_reset();
    $this->container = bootTestContainer();
    $this->location = testDiscoveryLocation();
});

afterEach(fn() => tearDownTestContainer());

describe('native block registration options', function () {
    beforeEach(function () {
        $this->register = function (string $fixture): array {
            $discovery = new BlockDiscovery();
            $discovery->discover($this->location, new \Tempest\Reflection\ClassReflector($fixture));
            $discovery->apply();

            wp_stub_get_calls('add_action')[0]['args']['callback']();

            return wp_stub_get_calls('register_block_type')[0]['args']['args'];
        };
    });

    it('sends parent and ancestor when the block declares them', function () {
        $args = ($this->register)(ConstrainedBlockFixture::class);

        expect($args['parent'])->toBe(['test/slider']);
        expect($args['ancestor'])->toBe(['test/carousel']);
        expect($args['keywords'])->toBe(['slide']);
    });

    it('omits parent and ancestor when the block declares neither', function () {
        $args = ($this->register)(Tests\Fixtures\BlockFixture::class);

        expect($args)->not->toHaveKey('parent');
        expect($args)->not->toHaveKey('ancestor');
    });

    it('sends allowed blocks only for a container block', function () {
        $container = ($this->register)(Tests\Fixtures\ContainerBlockFixture::class);

        expect($container['allowed_blocks'])->toBe(['core/heading', 'core/paragraph']);
    });
});

describe('ACF block registration options', function () {
    beforeEach(function () {
        $this->register = function (string $fixture): array {
            $discovery = new AcfBlockDiscovery();
            $discovery->discover($this->location, new \Tempest\Reflection\ClassReflector($fixture));
            $discovery->apply();

            wp_stub_get_calls('add_action')[0]['args']['callback']();

            return wp_stub_get_calls('acf_register_block_type')[0]['args']['config'];
        };
    });

    it('sends post types and parent when the block declares them', function () {
        $config = ($this->register)(ConstrainedAcfBlockFixture::class);

        expect($config['post_types'])->toBe(['page', 'product']);
        expect($config['parent'])->toBe(['acf/slider']);
    });

    it('omits post types and parent when the block declares neither', function () {
        $config = ($this->register)(Tests\Fixtures\AcfBlockFixture::class);

        expect($config)->not->toHaveKey('post_types');
        expect($config)->not->toHaveKey('parent');
    });
});

describe('block pattern registration options', function () {
    it('sends keywords and block types when the pattern declares them', function () {
        $engine = new class implements Studiometa\Foehn\Contracts\ViewEngineInterface {
            public function render(string $template, array|object $context = []): string
            {
                return '<!-- wp:paragraph /-->';
            }

            public function renderFirst(array $templates, array|object $context = []): string
            {
                return '';
            }

            public function exists(string $template): bool
            {
                return true;
            }

            public function share(string $key, mixed $value): void {}

            public function getShared(): array
            {
                return [];
            }
        };

        $this->container->singleton(Studiometa\Foehn\Contracts\ViewEngineInterface::class, fn() => $engine);

        $discovery = new BlockPatternDiscovery();
        $discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(ConstrainedBlockPatternFixture::class),
        );
        $discovery->apply();

        wp_stub_get_calls('add_action')[0]['args']['callback']();

        $config = wp_stub_get_calls('register_block_pattern')[0]['args']['config'];

        expect($config['keywords'])->toBe(['hero', 'header']);
        expect($config['blockTypes'])->toBe(['core/post-content']);
        expect($config)->not->toHaveKey('description');
    });
});
