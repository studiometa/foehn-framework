<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use RuntimeException;
use Studiometa\Foehn\Config\FoehnConfig;
use Tempest\Core\DiscoveryCacheStrategy;

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
     */
    public function isValid(): bool
    {
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
        $strategyFile = $this->getStrategyFilePath();

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        if (file_exists($strategyFile)) {
            unlink($strategyFile);
        }

        // Clear opcode cache if available
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($cacheFile, true);
        }
    }

    /**
     * Store the cache strategy.
     */
    public function storeStrategy(DiscoveryCacheStrategy $strategy): void
    {
        $cacheDir = $this->config->getDiscoveryCachePath();

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0o755, true);
        }

        file_put_contents($this->getStrategyFilePath(), $strategy->value);
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
}
