<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Security\SecurityHeaders;
use Tempest\Discovery\DiscoveryCacheStrategy;

describe('FoehnConfig', function () {
    it('can be instantiated with defaults', function () {
        $config = new FoehnConfig();

        expect($config->discoveryCacheStrategy)->toBe(DiscoveryCacheStrategy::NONE);
        expect($config->discoveryCachePath)->toBeNull();
        expect($config->hooks)->toBe([]);
        expect($config->isDiscoveryCacheEnabled())->toBeFalse();
        expect($config->debug)->toBeFalse();
        expect($config->isDebugEnabled())->toBeFalse();
    });

    it('can be instantiated with debug enabled', function () {
        $config = new FoehnConfig(debug: true);

        expect($config->debug)->toBeTrue();
        expect($config->isDebugEnabled())->toBeTrue();
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

        it('creates config with debug false by default', function () {
            $config = FoehnConfig::fromArray([]);

            expect($config->debug)->toBeFalse();
            expect($config->isDebugEnabled())->toBeFalse();
        });

        it('creates config with debug enabled', function () {
            $config = FoehnConfig::fromArray([
                'debug' => true,
            ]);

            expect($config->debug)->toBeTrue();
            expect($config->isDebugEnabled())->toBeTrue();
        });

        it('creates config with debug disabled', function () {
            $config = FoehnConfig::fromArray([
                'debug' => false,
            ]);

            expect($config->debug)->toBeFalse();
            expect($config->isDebugEnabled())->toBeFalse();
        });
        it('creates config with the cli namespace of the project', function () {
            expect(FoehnConfig::fromArray([])->cliNamespace)->toBe('app');
            expect(FoehnConfig::fromArray(['cli_namespace' => 'site'])->cliNamespace)->toBe('site');
        });
    });
    describe('recordsErrors', function () {
        afterEach(fn() => wp_stub_reset());

        it('records errors in the local and development environments by default', function (
            string $environment,
            bool $records,
        ) {
            $GLOBALS['wp_stub_environment_type'] = $environment;

            expect(new FoehnConfig()->recordsErrors())->toBe($records);
        })->with([
            'local' => ['local', true],
            'development' => ['development', true],
            'staging' => ['staging', false],
            'production' => ['production', false],
        ]);

        it('follows recordErrors when a project sets it', function () {
            $GLOBALS['wp_stub_environment_type'] = 'local';

            expect(new FoehnConfig(recordErrors: false)->recordsErrors())->toBeFalse();

            $GLOBALS['wp_stub_environment_type'] = 'production';

            expect(new FoehnConfig(recordErrors: true)->recordsErrors())->toBeTrue();
            expect(FoehnConfig::fromArray(['record_errors' => true])->recordErrors)->toBeTrue();
            expect(FoehnConfig::fromArray([])->recordErrors)->toBeNull();
        });
    });

    describe('tracesRequests', function () {
        afterEach(fn() => wp_stub_reset());

        it('traces requests in the local and development environments by default', function (
            string $environment,
            bool $traces,
        ) {
            $GLOBALS['wp_stub_environment_type'] = $environment;

            expect(new FoehnConfig()->tracesRequests())->toBe($traces);
        })->with([
            'local' => ['local', true],
            'development' => ['development', true],
            'staging' => ['staging', false],
            'production' => ['production', false],
        ]);

        it('follows traceRequests when a project sets it', function () {
            $GLOBALS['wp_stub_environment_type'] = 'local';

            expect(new FoehnConfig(traceRequests: false)->tracesRequests())->toBeFalse();

            $GLOBALS['wp_stub_environment_type'] = 'production';

            expect(new FoehnConfig(traceRequests: true)->tracesRequests())->toBeTrue();
            expect(FoehnConfig::fromArray(['trace_requests' => true])->traceRequests)->toBeTrue();
            expect(FoehnConfig::fromArray([])->traceRequests)->toBeNull();
        });
    });
});
