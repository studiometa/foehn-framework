<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Psr\Cache\CacheItemPoolInterface;
use Tempest\Discovery\DiscoveryLocation;

/**
 * Finds a discovery cache that names a class the project no longer has.
 *
 * Every discovery reflects its classes when it is applied, so one deleted class in
 * the cache throws a ReflectionException on each request and each WP-CLI command,
 * `wp foehn discovery:clear` too. {@see DiscoveryRunner} asks this before it
 * restores the cache, and scans again when the answer is yes.
 */
final class StaleDiscoveryCache
{
    /**
     * Whether an entry names a discovery class or a discovered class that does not exist.
     *
     * @param list<DiscoveryLocation> $locations
     */
    public static function namesMissingClass(CacheItemPoolInterface $pool, array $locations): bool
    {
        return array_any($locations, static function (DiscoveryLocation $location) use ($pool): bool {
            $entry = $pool->getItem($location->key)->get();

            return is_array($entry) && self::entryNamesMissingClass($entry);
        });
    }

    /**
     * @param array<array-key, mixed> $entry Discovery class => the items it found in one location
     */
    private static function entryNamesMissingClass(array $entry): bool
    {
        foreach ($entry as $discoveryClass => $items) {
            if (!class_exists((string) $discoveryClass)) {
                return true;
            }

            if (is_array($items) && array_any($items, self::itemNamesMissingClass(...))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first pass stores discovery class names as plain strings. The other
     * discoveries store arrays that name their class under one of these keys.
     */
    private static function itemNamesMissingClass(mixed $item): bool
    {
        $classes = match (true) {
            is_string($item) => [$item],
            is_array($item) => [$item['className'] ?? null, $item['handlerClass'] ?? null, $item['dtoClass'] ?? null],
            default => [],
        };

        return array_any($classes, static fn(mixed $class): bool => is_string($class) && !class_exists($class));
    }
}
