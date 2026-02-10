<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Security;

use Studiometa\Foehn\Attributes\AsAction;

/**
 * Disable the WordPress theme and plugin file editor.
 *
 * Defines the `DISALLOW_FILE_EDIT` constant to prevent editing PHP files
 * from the wp-admin dashboard (Appearance → Theme Editor, Plugins → Plugin Editor).
 *
 * This is a standard hardening measure recommended by:
 * - WordPress Codex (Hardening WordPress)
 * - OWASP WordPress Security Guide
 * - Sucuri, Wordfence, and most security plugins
 *
 * If an attacker gains admin access, they cannot inject malicious code
 * via the built-in editor.
 */
final class DisableFileEditor
{
    /**
     * Define DISALLOW_FILE_EDIT if not already set.
     *
     * Hooked early on `init` to ensure the constant is defined before
     * any admin page loads.
     */
    /**
     * @codeCoverageIgnore Cannot test define() as constants persist across tests
     */
    #[AsAction('init', priority: 1)]
    public function disableFileEditor(): void
    {
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
    }
}
