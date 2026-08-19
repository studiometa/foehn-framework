<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery\Concerns;

use Studiometa\Foehn\Discovery\AttributeCodec;
use Studiometa\Foehn\Discovery\WpDiscoveryItems;

/**
 * Trait providing the discovery cache round trip.
 *
 * A discovered item is an array of plain values plus, for most discoveries, the
 * attribute instance that produced it. The attribute is the only part that cannot
 * go straight into a `var_export()`ed cache file, so it travels through
 * AttributeCodec and comes back as the same instance. Every other value passes
 * through untouched.
 *
 * Discoveries therefore describe no cache format of their own: apply() reads the
 * same item shape whether it was scanned or restored.
 *
 * This trait requires the class to also use the IsWpDiscovery trait which provides
 * getItems()/setItems() methods.
 *
 * @phpstan-require-implements \Studiometa\Foehn\Discovery\WpDiscovery
 */
trait CacheableDiscovery
{
    /**
     * Whether this discovery was restored from cache.
     */
    protected bool $restoredFromCache = false;

    /**
     * Export the discovered items in a form the discovery cache can write.
     *
     * @return array<string, list<array<string, mixed>>> Items grouped by location namespace
     */
    public function getCacheableData(): array
    {
        $data = [];

        foreach ($this->getItems()->toArray() as $namespace => $locationItems) {
            $data[$namespace] = array_map(self::encodeItem(...), $locationItems);
        }

        return $data;
    }

    /**
     * Restore the discovered items from cached data.
     *
     * @param array<string, list<array<string, mixed>>> $data
     */
    public function restoreFromCache(array $data): void
    {
        $items = [];

        foreach ($data as $namespace => $locationItems) {
            $items[$namespace] = array_map(self::decodeItem(...), $locationItems);
        }

        $this->setItems(WpDiscoveryItems::fromArray($items));
        $this->restoredFromCache = true;
    }

    /**
     * Check whether the items came from the cache rather than from a scan.
     */
    public function wasRestoredFromCache(): bool
    {
        return $this->restoredFromCache;
    }

    /**
     * Encode the attribute instances of a single item.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function encodeItem(array $item): array
    {
        return array_map(static fn(mixed $value): mixed => is_object($value)
            ? AttributeCodec::encode($value)
            : $value, $item);
    }

    /**
     * Rebuild the attribute instances of a single cached item.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function decodeItem(array $item): array
    {
        return array_map(static fn(mixed $value): mixed => AttributeCodec::isEncoded($value)
            ? AttributeCodec::decode($value)
            : $value, $item);
    }
}
