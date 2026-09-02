<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use Studiometa\Foehn\Verification\VerificationResult;

/**
 * Debug output is off.
 *
 * Both constants, and `WP_DEBUG_DISPLAY` is the one that actually leaks: it puts PHP
 * warnings into the response body, which on a production site means paths, table
 * prefixes and occasionally query fragments rendered to whoever asked for the page.
 * `WP_DEBUG` alone is milder — it changes what is logged and what deprecations fire —
 * but it is what turns display on by default, so a gate that accepted it would be
 * accepting the thing one config change away from the leak.
 *
 * The generated `wp-config.php` already derives `WP_DEBUG_DISPLAY` from `WP_DEBUG` and
 * the environment, so a project that has not edited it cannot fail this. Projects do
 * edit it.
 */
final readonly class DebugCheck implements Check
{
    public const NAME = 'debug';

    public function __construct(
        private bool $debug,
        private bool $debugDisplay,
    ) {}

    public function run(): array
    {
        $details = ['wp_debug' => $this->debug, 'wp_debug_display' => $this->debugDisplay];

        $enabled = array_keys(array_filter([
            'WP_DEBUG' => $this->debug,
            'WP_DEBUG_DISPLAY' => $this->debugDisplay,
        ]));

        if ($enabled !== []) {
            return [VerificationResult::fail(
                self::NAME,
                sprintf('%s enabled in production. WP_DEBUG_DISPLAY renders PHP warnings into the response body.', implode(
                    ' and ',
                    $enabled,
                )),
                $details,
            )];
        }

        return [VerificationResult::pass(self::NAME, 'WP_DEBUG and WP_DEBUG_DISPLAY are both off.', $details)];
    }
}
