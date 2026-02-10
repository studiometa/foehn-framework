<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\DiscoveryCache;
use Tempest\Core\DiscoveryCacheStrategy;

beforeEach(function () {
    // Create a temp directory for each test
    $this->tempDir = sys_get_temp_dir() . '/foehn-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    // Clean up temp directory
    if (is_dir($this->tempDir)) {
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }
});

describe('DiscoveryCache', function () {
    it('reports disabled when strategy is NONE', function () {
        $config = new FoehnConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::NONE,
            discoveryCachePath: $this->tempDir,
        );
        $cache = new DiscoveryCache($config);

        expect($cache->isEnabled())->toBeFalse();
    });

    it('reports disabled when strategy is FULL but no stored strategy', function () {
        $config = new FoehnConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
            discoveryCachePath: $this->tempDir,
        );
        $cache = new DiscoveryCache($config);

        // No stored strategy means invalid cache
        expect($cache->isEnabled())->toBeFalse();
        expect($cache->isValid())->toBeFalse();
    });

    it('reports enabled when strategy matches stored strategy', function () {
        $config = new FoehnConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
            discoveryCachePath: $this->tempDir,
        );
        $cache = new DiscoveryCache($config);

        // Store the strategy
        $cache->storeStrategy(DiscoveryCacheStrategy::FULL);

        expect($cache->isEnabled())->toBeTrue();
        expect($cache->isValid())->toBeTrue();
    });

    it('reports invalid when strategy mismatch', function () {
        $config = new FoehnConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
            discoveryCachePath: $this->tempDir,
        );
        $cache = new DiscoveryCache($config);

        // Store a different strategy
        $cache->storeStrategy(DiscoveryCacheStrategy::PARTIAL);

        expect($cache->isEnabled())->toBeFalse();
        expect($cache->isValid())->toBeFalse();
    });

    it('can store and restore data', function () {
        $config = new FoehnConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
            discoveryCachePath: $this->tempDir,
        );
        $cache = new DiscoveryCache($config);

        $data = [
            'TestDiscovery' => [
                ['className' => 'App\\Test', 'name' => 'test'],
            ],
        ];

        $cache->store($data);
        $cache->storeStrategy(DiscoveryCacheStrategy::FULL);

        expect($cache->exists())->toBeTrue();

        $restored = $cache->restore();
        expect($restored)->toBe($data);
    });

    it('returns null when restoring non-existent cache', function () {
        $config = new FoehnConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
            discoveryCachePath: $this->tempDir,
        );
        $cache = new DiscoveryCache($config);
        $cache->storeStrategy(DiscoveryCacheStrategy::FULL);

        expect($cache->restore())->toBeNull();
    });

    it('can clear the cache', function () {
        $config = new FoehnConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
            discoveryCachePath: $this->tempDir,
        );
        $cache = new DiscoveryCache($config);

        $cache->store(['test' => []]);
        $cache->storeStrategy(DiscoveryCacheStrategy::FULL);

        expect($cache->exists())->toBeTrue();

        $cache->clear();

        expect($cache->exists())->toBeFalse();
        expect($cache->isValid())->toBeFalse();
    });

    it('returns correct strategy', function () {
        $config = new FoehnConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::PARTIAL,
            discoveryCachePath: $this->tempDir,
        );
        $cache = new DiscoveryCache($config);

        expect($cache->getStrategy())->toBe(DiscoveryCacheStrategy::PARTIAL);
    });
});
