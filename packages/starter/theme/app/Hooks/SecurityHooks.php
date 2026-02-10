<?php

declare(strict_types=1);

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

final class SecurityHooks
{
    #[AsAction('init')]
    public function cleanupHead(): void
    {
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
    }

    #[AsFilter('wp_headers')]
    public function removeXPingback(array $headers): array
    {
        unset($headers['X-Pingback']);

        return $headers;
    }

    #[AsFilter('xmlrpc_enabled')]
    public function disableXmlRpc(): bool
    {
        return false;
    }

    #[AsFilter('login_errors')]
    public function genericLoginError(): string
    {
        return __('Identifiants incorrects.', 'starter-theme');
    }
}
