<?php

declare(strict_types=1);

use Studiometa\WPTempest\Config\WpTempestConfig;
use Studiometa\WPTempest\Hooks\Cleanup\CleanHeadTags;
use Studiometa\WPTempest\Hooks\Security\SecurityHeaders;
use Tempest\Core\DiscoveryCacheStrategy;

describe('WpTempestConfig', function () {
    it('can be instantiated with defaults', function () {
        $config = new WpTempestConfig();

        expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::NONE);
        expect($config->discoveryCachePath)->toBeNull();
        expect($config->hooks)->toBe([]);
        expect($config->isDiscoveryCacheEnabled())->toBeFalse();
        expect($config->timberTemplatesDir)->toBe(['templates']);
        expect($config->acfTransformFields)->toBeTrue();
    });

    it('can be instantiated with full strategy', function () {
        $config = new WpTempestConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
        );

        expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::FULL);
        expect($config->isDiscoveryCacheEnabled())->toBeTrue();
    });

    it('can be instantiated with partial strategy', function () {
        $config = new WpTempestConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::PARTIAL,
        );

        expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::PARTIAL);
        expect($config->isDiscoveryCacheEnabled())->toBeTrue();
    });

    it('can be instantiated with custom cache path', function () {
        $config = new WpTempestConfig(
            discoveryCachePath: '/custom/path',
        );

        expect($config->getDiscoveryCachePath())->toBe('/custom/path');
    });

    it('uses default cache path when not specified', function () {
        $config = new WpTempestConfig();

        // Should use sys_get_temp_dir() fallback since WP_CONTENT_DIR is not defined
        $path = $config->getDiscoveryCachePath();
        expect($path)->toContain('wp-tempest/discovery');
    });

    describe('fromArray', function () {
        it('creates config from empty array', function () {
            $config = WpTempestConfig::fromArray([]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::NONE);
            expect($config->discoveryCachePath)->toBeNull();
        });

        it('creates config with discovery_cache true', function () {
            $config = WpTempestConfig::fromArray([
                'discovery_cache' => true,
            ]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::FULL);
        });

        it('creates config with discovery_cache false', function () {
            $config = WpTempestConfig::fromArray([
                'discovery_cache' => false,
            ]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::NONE);
        });

        it('creates config with discovery_cache full', function () {
            $config = WpTempestConfig::fromArray([
                'discovery_cache' => 'full',
            ]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::FULL);
        });

        it('creates config with discovery_cache partial', function () {
            $config = WpTempestConfig::fromArray([
                'discovery_cache' => 'partial',
            ]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::PARTIAL);
        });

        it('creates config with custom cache path', function () {
            $config = WpTempestConfig::fromArray([
                'discovery_cache_path' => '/my/cache/path',
            ]);

            expect($config->discoveryCachePath)->toBe('/my/cache/path');
            expect($config->getDiscoveryCachePath())->toBe('/my/cache/path');
        });

        it('creates config with default timber templates dir', function () {
            $config = WpTempestConfig::fromArray([]);

            expect($config->timberTemplatesDir)->toBe(['templates']);
        });

        it('creates config with custom timber templates dir', function () {
            $config = WpTempestConfig::fromArray([
                'timber_templates_dir' => ['views', 'templates'],
            ]);

            expect($config->timberTemplatesDir)->toBe(['views', 'templates']);
        });

        it('creates config with hooks array', function () {
            $config = WpTempestConfig::fromArray([
                'hooks' => [
                    CleanHeadTags::class,
                    SecurityHeaders::class,
                ],
            ]);

            expect($config->hooks)->toBe([
                CleanHeadTags::class,
                SecurityHeaders::class,
            ]);
        });

        it('creates config with empty hooks by default', function () {
            $config = WpTempestConfig::fromArray([]);

            expect($config->hooks)->toBe([]);
        });

        it('creates config with acf_transform_fields true by default', function () {
            $config = WpTempestConfig::fromArray([]);

            expect($config->acfTransformFields)->toBeTrue();
        });

        it('creates config with acf_transform_fields disabled', function () {
            $config = WpTempestConfig::fromArray([
                'acf_transform_fields' => false,
            ]);

            expect($config->acfTransformFields)->toBeFalse();
        });

        it('creates config with acf_transform_fields enabled', function () {
            $config = WpTempestConfig::fromArray([
                'acf_transform_fields' => true,
            ]);

            expect($config->acfTransformFields)->toBeTrue();
        });
    });
});
