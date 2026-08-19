<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * `X-Foehn-Cache`, and the two headers that make it useful.
 *
 * A page cache that cannot say what it decided, and which of the four readers decided
 * it, is undebuggable in production — and the smoke test would have nothing to assert,
 * which is how a broken nginx snippet hides behind a working drop-in for a year.
 *
 * Sent when `debugHeaders` is on, which defaults to `WP_DEBUG`.
 */
final readonly class DebugHeaders
{
    public const STATE_HIT = 'HIT';
    public const STATE_MISS = 'MISS';
    public const STATE_BYPASS = 'BYPASS';

    public const VIA_PHP = 'php';
    public const VIA_NGINX = 'nginx';
    public const VIA_APACHE = 'apache';

    public static function send(
        PageCacheConfig $config,
        string $state,
        ?BypassReason $reason = null,
        string $via = self::VIA_PHP,
    ): void {
        if (!$config->wantsDebugHeaders() || headers_sent()) {
            return;
        }

        header('X-Foehn-Cache: ' . $state);
        header('X-Foehn-Cache-Via: ' . $via);

        if ($reason !== null) {
            header('X-Foehn-Cache-Reason: ' . $reason->value);
        }
    }
}
