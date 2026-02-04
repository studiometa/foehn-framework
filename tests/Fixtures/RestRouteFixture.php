<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsRestRoute;
use WP_REST_Request;

final class RestRouteFixture
{
    #[AsRestRoute(namespace: 'test/v1', route: '/items')]
    public function getItems(WP_REST_Request $request): array
    {
        return [];
    }

    #[AsRestRoute(namespace: 'test/v1', route: '/items', method: 'POST', permission: 'public')]
    public function createItem(WP_REST_Request $request): array
    {
        return [];
    }

    #[AsRestRoute(namespace: 'test/v1', route: '/items/(?P<id>\d+)', args: ['id' => ['type' => 'integer']])]
    public function getItem(WP_REST_Request $request): array
    {
        return [];
    }
}
