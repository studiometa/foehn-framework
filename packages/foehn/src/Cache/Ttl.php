<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Cache;

use DateInterval;
use DateTimeImmutable;

/**
 * Converts a PSR-16 TTL to seconds.
 *
 * @internal
 */
final class Ttl
{
    /**
     * The TTL in seconds, or `null` for no expiration. Zero or less means the entry is already expired.
     */
    public static function seconds(int|DateInterval|null $ttl): ?int
    {
        if (!$ttl instanceof DateInterval) {
            return $ttl;
        }

        $now = new DateTimeImmutable();

        return $now->add($ttl)->getTimestamp() - $now->getTimestamp();
    }

    /**
     * Whether a TTL asks to delete the entry rather than to store it.
     */
    public static function isExpired(int|DateInterval|null $ttl): bool
    {
        $seconds = self::seconds($ttl);

        return $seconds !== null && $seconds <= 0;
    }
}
