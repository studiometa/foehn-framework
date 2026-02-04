<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Hooks\Security;

use Studiometa\WPTempest\Attributes\AsFilter;

/**
 * Require authentication for REST API user enumeration endpoints.
 *
 * By default, the `/wp/v2/users` endpoint is publicly accessible and
 * reveals admin usernames, which attackers use for brute force attacks.
 *
 * This class removes the `users` endpoint for unauthenticated requests
 * while keeping all other REST API endpoints (posts, pages, etc.) public.
 *
 * Gutenberg and other authenticated admin features are not affected.
 */
final class RestApiAuth
{
    /**
     * Remove user endpoints for unauthenticated requests.
     *
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    #[AsFilter('rest_endpoints')]
    public function restrictUserEndpoints(array $endpoints): array
    {
        if (is_user_logged_in()) {
            return $endpoints;
        }

        // Remove all /wp/v2/users endpoints
        foreach ($endpoints as $route => $data) {
            if (!str_starts_with($route, '/wp/v2/users')) {
                continue;
            }

            unset($endpoints[$route]);
        }

        return $endpoints;
    }
}
