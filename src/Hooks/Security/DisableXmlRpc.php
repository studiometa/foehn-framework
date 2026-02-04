<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Hooks\Security;

use Studiometa\WPTempest\Attributes\AsFilter;

/**
 * Disable XML-RPC entirely.
 *
 * XML-RPC is the legacy remote publishing protocol, superseded by the REST API.
 * It is the #1 brute force attack vector (wp.xmlrpc.php accepts unlimited
 * login attempts via system.multicall) and a common DDoS amplification target
 * via pingback requests.
 *
 * This class:
 * - Disables the XML-RPC server via the `xmlrpc_enabled` filter
 * - Removes the X-Pingback HTTP header
 * - Removes the pingback link from wp_head
 *
 * ⚠️  Only use this if your site does not rely on XML-RPC clients
 * (e.g. the WordPress mobile app, Jetpack, or third-party integrations).
 * Most modern sites can safely disable it.
 */
final class DisableXmlRpc
{
    /**
     * Disable the XML-RPC server.
     */
    #[AsFilter('xmlrpc_enabled')]
    public function disableXmlRpc(): bool
    {
        return false;
    }

    /**
     * Remove the X-Pingback header from HTTP responses.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    #[AsFilter('wp_headers')]
    public function removePingbackHeader(array $headers): array
    {
        unset($headers['X-Pingback']);

        return $headers;
    }

    /**
     * Remove the pingback and pingback discovery link tags from wp_head.
     */
    #[AsFilter('bloginfo_url', acceptedArgs: 2)]
    public function removePingbackUrl(string $output, string $show): string
    {
        if ($show === 'pingback_url') {
            return '';
        }

        return $output;
    }
}
