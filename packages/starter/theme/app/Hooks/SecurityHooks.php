<?php

declare(strict_types=1);

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Project-specific security hooks.
 *
 * Note: common security hooks (CleanHeadTags, DisableEmoji, DisableXmlRpc,
 * YouTubeNoCookieHooks) are activated via foehn.config.php.
 */
final class SecurityHooks
{
    #[AsFilter('login_errors')]
    public function genericLoginError(): string
    {
        return __('Identifiants incorrects.', 'starter-theme');
    }
}
