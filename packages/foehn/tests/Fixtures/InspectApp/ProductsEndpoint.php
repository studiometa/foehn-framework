<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsRestRoute;
use WP_REST_Request;

final class ProductsEndpoint
{
    #[AsRestRoute(namespace: 'shop/v1', route: '/products')]
    public function index(WP_REST_Request $request): array
    {
        return [];
    }

    #[AsRestRoute(namespace: 'shop/v1', route: '/products', method: 'POST', permission: 'edit_posts')]
    public function store(WP_REST_Request $request): array
    {
        return [];
    }
}
