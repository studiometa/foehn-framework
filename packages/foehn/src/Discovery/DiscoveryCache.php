<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use RuntimeException;
use Studiometa\Foehn\Config\FoehnConfig;
use Tempest\Discovery\DiscoveryCacheStrategy;

/**
 * Manages discovery cache for Foehn.
 *
 * This cache stores serialized discovery items to avoid runtime reflection
 * overhead in production environments.
 */
final class DiscoveryCache
{
    private const CACHE_FILE = 'discoveries.php';
    private const STRATEGY_FILE = 'strategy';
    private const VERSION_FILE = 'version';

    /**
     * Shape version of the cached discovery items.
     *
     * A cache file is only readable by the code that wrote it: every discovery reads
     * its item keys without defaults, so a file written by another Foehn version would
     * half-load and fail. Bump this whenever the shape of any cached item changes, and
     * a cache from another version is rejected instead of being restored.
     */
    private const SCHEMA_VERSION = '1';

    public function __construct(
        private readonly FoehnConfig $config,
    ) {}

    /**
     * Check if caching is enabled.
     */
    public function isEnabled(): bool
    {
        if (!$this->config->isDiscoveryCacheEnabled()) {
            return false;
        }

        // Check if the stored strategy matches the configured strategy
        return $this->isValid();
    }

    /**
     * Check if the cache is valid.
     *
     * A cache is only valid when it was written for the configured strategy and by a
     * Foehn version that agrees on the item shape.
     */
    public function isValid(): bool
    {
        if ($this->getStoredVersion() !== self::SCHEMA_VERSION) {
            return false;
        }

        $storedStrategy = $this->getStoredStrategy();

        if ($storedStrategy === null) {
            return false;
        }

        return $storedStrategy === $this->config->discoveryCacheStrategy;
    }

    /**
     * Check if cache exists.
     */
    public function exists(): bool
    {
        return file_exists($this->getCacheFilePath());
    }

    /**
     * Get the configured strategy.
     */
    public function getStrategy(): DiscoveryCacheStrategy
    {
        return $this->config->discoveryCacheStrategy;
    }

    /**
     * Restore cached discovery data.
     *
     * @return array<string, array<string, list<array<string, mixed>>>>|null
     */
    public function restore(): ?array
    {
        if (!$this->isEnabled() || !$this->exists()) {
            return null;
        }

        $cacheFile = $this->getCacheFilePath();

        // Use require for PHP file cache (fast opcode cache)
        /** @var array<string, array<string, list<array<string, mixed>>>>|null $data */
        $data = require $cacheFile;

        return is_array($data) ? $data : null;
    }

    /**
     * Store discovery data to cache.
     *
     * @param array<string, array<string, list<array<string, mixed>>>> $data Keyed by discovery class name
     */
    public function store(array $data): void
    {
        $cacheDir = $this->config->getDiscoveryCachePath();

        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0o755, true)) {
                throw new RuntimeException("Could not create discovery cache directory: {$cacheDir}");
            }
        }

        // Store as PHP file for opcode caching
        $content =
            "<?php\n\ndeclare(strict_types=1);\n\n// Auto-generated discovery cache - do not edit\n// Generated: "
            . date('Y-m-d H:i:s')
            . "\n\nreturn "
            . var_export($data, true)
            . ";\n";

        $cacheFile = $this->getCacheFilePath();

        if (file_put_contents($cacheFile, $content) === false) {
            throw new RuntimeException("Could not write discovery cache file: {$cacheFile}");
        }

        // Store the strategy
        $this->storeStrategy($this->config->discoveryCacheStrategy);
    }

    /**
     * Clear the discovery cache.
     */
    public function clear(): void
    {
        $cacheFile = $this->getCacheFilePath();

        foreach ([$cacheFile, $this->getStrategyFilePath(), $this->getVersionFilePath()] as $file) {
            if (!file_exists($file)) {
                continue;
            }

            unlink($file);
        }

        // Clear opcode cache if available
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($cacheFile, true);
        }
    }

    /**
     * Store the cache strategy, stamped with the schema version it was written for.
     */
    public function storeStrategy(DiscoveryCacheStrategy $strategy): void
    {
        $cacheDir = $this->config->getDiscoveryCachePath();

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0o755, true);
        }

        file_put_contents($this->getStrategyFilePath(), $strategy->value);
        file_put_contents($this->getVersionFilePath(), self::SCHEMA_VERSION);
    }

    /**
     * Get the schema version the cache on disk was written for.
     */
    private function getStoredVersion(): ?string
    {
        $versionFile = $this->getVersionFilePath();

        if (!file_exists($versionFile)) {
            return null;
        }

        $value = file_get_contents($versionFile);

        return $value === false ? null : trim($value);
    }

    /**
     * Get the stored cache strategy.
     */
    private function getStoredStrategy(): ?DiscoveryCacheStrategy
    {
        $strategyFile = $this->getStrategyFilePath();

        if (!file_exists($strategyFile)) {
            return null;
        }

        $value = file_get_contents($strategyFile);

        if ($value === false) {
            return null;
        }

        return DiscoveryCacheStrategy::resolveFromInput(trim($value));
    }

    /**
     * Get the cache file path.
     */
    private function getCacheFilePath(): string
    {
        return $this->config->getDiscoveryCachePath() . '/' . self::CACHE_FILE;
    }

    /**
     * Get the strategy file path.
     */
    private function getStrategyFilePath(): string
    {
        return $this->config->getDiscoveryCachePath() . '/' . self::STRATEGY_FILE;
    }

    /**
     * Get the schema version file path.
     */
    private function getVersionFilePath(): string
    {
        return $this->config->getDiscoveryCachePath() . '/' . self::VERSION_FILE;
    }
}
