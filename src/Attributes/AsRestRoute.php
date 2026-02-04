<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a method as a WordPress REST API endpoint.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsRestRoute
{
    /**
     * @param string $namespace REST namespace (e.g., 'theme/v1')
     * @param string $route Route pattern (e.g., '/posts', '/posts/(?P<id>\d+)')
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string|null $permission Permission callback method name or 'public' for no auth
     * @param array<string, array<string, mixed>> $args Endpoint arguments schema
     */
    public function __construct(
        public string $namespace,
        public string $route,
        public string $method = 'GET',
        public ?string $permission = null,
        public array $args = [],
    ) {}

    /**
     * Get WordPress-compatible HTTP method constant.
     */
    public function getMethodConstant(): string
    {
        return match (strtoupper($this->method)) {
            'GET' => 'GET',
            'POST' => 'POST',
            'PUT' => 'PUT',
            'PATCH' => 'PATCH',
            'DELETE' => 'DELETE',
            default => 'GET',
        };
    }
}
