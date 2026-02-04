<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Hooks\Security;

use Studiometa\WPTempest\Attributes\AsAction;

/**
 * Send common security HTTP headers.
 *
 * Headers sent:
 * - `X-Content-Type-Options: nosniff` — prevents MIME-type sniffing
 * - `X-Frame-Options: SAMEORIGIN` — prevents clickjacking (legacy header,
 *   superseded by CSP frame-ancestors but still needed for older browsers)
 * - `Referrer-Policy: strict-origin-when-cross-origin` — limits referrer
 *   information sent to external sites
 * - `Permissions-Policy` — disables common browser features not needed
 *   by most WordPress sites (camera, microphone, geolocation, etc.)
 *
 * Note: these headers are a PHP-level fallback. For production sites,
 * prefer setting security headers at the web server level (Nginx/Apache)
 * for better performance and coverage of static assets.
 *
 * Headers intentionally NOT included:
 * - `X-XSS-Protection` — deprecated, can introduce vulnerabilities.
 *   Modern browsers have removed XSS auditor support (OWASP recommends
 *   `X-XSS-Protection: 0` or omitting it entirely).
 * - `Strict-Transport-Security` (HSTS) — requires careful rollout per
 *   domain, should be configured at server level.
 * - `Content-Security-Policy` — too site-specific for a generic default.
 */
final class SecurityHeaders
{
    /**
     * @codeCoverageIgnore Cannot test header() in CLI environment
     */
    #[AsAction('send_headers')]
    public function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    }
}
