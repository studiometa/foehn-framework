<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Security\SecurityHeaders;
use Tempest\Core\DiscoveryCacheStrategy;

describe('FoehnConfig', function () {
    it('can be instantiated with defaults', function () {
        $config = new FoehnConfig();

        expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::NONE);
        expect($config->discoveryCachePath)->toBeNull();
        expect($config->hooks)->toBe([]);
        expect($config->isDiscoveryCacheEnabled())->toBeFalse();
        expect($config->timberTemplatesDir)->toBe(['templates']);
        expect($config->acfTransformFields)->toBeTrue();
    });

    it('can be instantiated with full strategy', function () {
        $config = new FoehnConfig(discoveryCacheStrategy: DiscoveryCacheStrategy::FULL);

        expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::FULL);
        expect($config->isDiscoveryCacheEnabled())->toBeTrue();
    });

    it('can be instantiated with partial strategy', function () {
        $config = new FoehnConfig(discoveryCacheStrategy: DiscoveryCacheStrategy::PARTIAL);

        expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::PARTIAL);
        expect($config->isDiscoveryCacheEnabled())->toBeTrue();
    });

    it('can be instantiated with custom cache path', function () {
        $config = new FoehnConfig(discoveryCachePath: '/custom/path');

        expect($config->getDiscoveryCachePath())->toBe('/custom/path');
    });

    it('uses default cache path when not specified', function () {
        $config = new FoehnConfig();

        // Should use sys_get_temp_dir() fallback since WP_CONTENT_DIR is not defined
        $path = $config->getDiscoveryCachePath();
        expect($path)->toContain('foehn/discovery');
    });

    describe('fromArray', function () {
        it('creates config from empty array', function () {
            $config = FoehnConfig::fromArray([]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::NONE);
            expect($config->discoveryCachePath)->toBeNull();
        });

        it('creates config with discovery_cache true', function () {
            $config = FoehnConfig::fromArray([
                'discovery_cache' => true,
            ]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::FULL);
        });

        it('creates config with discovery_cache false', function () {
            $config = FoehnConfig::fromArray([
                'discovery_cache' => false,
            ]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::NONE);
        });

        it('creates config with discovery_cache full', function () {
            $config = FoehnConfig::fromArray([
                'discovery_cache' => 'full',
            ]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::FULL);
        });

        it('creates config with discovery_cache partial', function () {
            $config = FoehnConfig::fromArray([
                'discovery_cache' => 'partial',
            ]);

            expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::PARTIAL);
        });

        it('creates config with custom cache path', function () {
            $config = FoehnConfig::fromArray([
                'discovery_cache_path' => '/my/cache/path',
            ]);

            expect($config->discoveryCachePath)->toBe('/my/cache/path');
            expect($config->getDiscoveryCachePath())->toBe('/my/cache/path');
        });

        it('creates config with default timber templates dir', function () {
            $config = FoehnConfig::fromArray([]);

            expect($config->timberTemplatesDir)->toBe(['templates']);
        });

        it('creates config with custom timber templates dir', function () {
            $config = FoehnConfig::fromArray([
                'timber_templates_dir' => ['views', 'templates'],
            ]);

            expect($config->timberTemplatesDir)->toBe(['views', 'templates']);
        });

        it('creates config with hooks array', function () {
            $config = FoehnConfig::fromArray([
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
            $config = FoehnConfig::fromArray([]);

            expect($config->hooks)->toBe([]);
        });

        it('creates config with acf_transform_fields true by default', function () {
            $config = FoehnConfig::fromArray([]);

            expect($config->acfTransformFields)->toBeTrue();
        });

        it('creates config with acf_transform_fields disabled', function () {
            $config = FoehnConfig::fromArray([
                'acf_transform_fields' => false,
            ]);

            expect($config->acfTransformFields)->toBeFalse();
        });

        it('creates config with acf_transform_fields enabled', function () {
            $config = FoehnConfig::fromArray([
                'acf_transform_fields' => true,
            ]);

            expect($config->acfTransformFields)->toBeTrue();
        });
    });
});
