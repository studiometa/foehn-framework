<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

/**
 * Configuration for REST API.
 *
 * Create a config file in your app directory:
 *
 * ```php
 * // app/rest.config.php
 * use Studiometa\Foehn\Config\RestConfig;
 *
 * return new RestConfig(
 *     defaultCapability: 'edit_posts',
 * );
 * ```
 */
final readonly class RestConfig
{
    /**
     * @param string|null $defaultCapability Default capability required for REST routes
     *                                       without explicit permission. Set to null to
     *                                       only require authentication (is_user_logged_in).
     */
    public function __construct(
        public ?string $defaultCapability = 'edit_posts',
    ) {}
}
