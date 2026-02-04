<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Hooks;

use Studiometa\WPTempest\Attributes\AsAction;

/**
 * Security-related HTTP headers.
 *
 * Sends common security headers:
 * - X-Content-Type-Options: nosniff
 * - X-Frame-Options: SAMEORIGIN
 * - X-XSS-Protection: 1; mode=block
 * - Referrer-Policy: strict-origin-when-cross-origin
 */
final class SecurityHooks
{
    /**
     * Send security headers.
     */
    #[AsAction('send_headers')]
    public function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
