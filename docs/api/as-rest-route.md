# #[AsRestRoute]

Register a method as a WordPress REST API endpoint.

## Signature

```php
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsRestRoute
{
    public function __construct(
        public string $namespace,
        public string $route,
        public string $method = 'GET',
        public ?string $permission = null,
        public array $args = [],
    ) {}

    public function getMethodConstant(): string {}
}
```

## Parameters

| Parameter    | Type      | Default | Description                       |
| ------------ | --------- | ------- | --------------------------------- |
| `namespace`  | `string`  | —       | REST namespace (e.g., `theme/v1`) |
| `route`      | `string`  | —       | Route pattern (required)          |
| `method`     | `string`  | `'GET'` | HTTP method                       |
| `permission` | `?string` | `null`  | Permission callback or `'public'` |
| `args`       | `array`   | `[]`    | Request arguments schema          |

## HTTP Methods

- `GET` — Read operations
- `POST` — Create operations
- `PUT` — Full update operations
- `PATCH` — Partial update operations
- `DELETE` — Delete operations

## Usage

### Basic Endpoint

```php
<?php

namespace App\Rest;

use Studiometa\WPTempest\Attributes\AsRestRoute;
use WP_REST_Request;
use WP_REST_Response;

final class ProductsApi
{
    #[AsRestRoute(
        namespace: 'theme/v1',
        route: '/products',
        method: 'GET',
    )]
    public function list(WP_REST_Request $request): WP_REST_Response
    {
        $products = get_posts(['post_type' => 'product']);
        return new WP_REST_Response($products);
    }
}
```

**Endpoint:** `GET /wp-json/theme/v1/products`

### Route Parameters

```php
#[AsRestRoute(
    namespace: 'theme/v1',
    route: '/products/(?P<id>\d+)',
    method: 'GET',
)]
public function show(WP_REST_Request $request): WP_REST_Response
{
    $id = (int) $request->get_param('id');
    $product = get_post($id);

    if (!$product) {
        return new WP_REST_Response(['error' => 'Not found'], 404);
    }

    return new WP_REST_Response($product);
}
```

### Public Endpoint

```php
#[AsRestRoute(
    namespace: 'theme/v1',
    route: '/products',
    method: 'GET',
    permission: 'public',
)]
public function list(WP_REST_Request $request): WP_REST_Response
{
    // No authentication required
}
```

### Protected Endpoint

```php
#[AsRestRoute(
    namespace: 'theme/v1',
    route: '/orders',
    method: 'GET',
    permission: 'canViewOrders',
)]
public function listOrders(WP_REST_Request $request): WP_REST_Response
{
    // Only authenticated users with permission
}

public function canViewOrders(WP_REST_Request $request): bool
{
    return current_user_can('read');
}
```

### With Arguments Schema

```php
#[AsRestRoute(
    namespace: 'theme/v1',
    route: '/products',
    method: 'GET',
    args: [
        'per_page' => [
            'type' => 'integer',
            'default' => 10,
            'minimum' => 1,
            'maximum' => 100,
        ],
        'category' => [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ],
    ],
)]
public function list(WP_REST_Request $request): WP_REST_Response
{
    $perPage = $request->get_param('per_page');
    $category = $request->get_param('category');
    // ...
}
```

### Multiple Methods

```php
#[AsRestRoute(namespace: 'theme/v1', route: '/items', method: 'GET')]
public function index(WP_REST_Request $request): WP_REST_Response {}

#[AsRestRoute(namespace: 'theme/v1', route: '/items', method: 'POST')]
public function store(WP_REST_Request $request): WP_REST_Response {}

#[AsRestRoute(namespace: 'theme/v1', route: '/items/(?P<id>\d+)', method: 'PUT')]
public function update(WP_REST_Request $request): WP_REST_Response {}

#[AsRestRoute(namespace: 'theme/v1', route: '/items/(?P<id>\d+)', method: 'DELETE')]
public function destroy(WP_REST_Request $request): WP_REST_Response {}
```

## Related

- [Guide: REST API](/guide/rest-api)
