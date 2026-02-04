# REST API

Foehn provides `#[AsRestRoute]` for creating REST API endpoints declaratively.

## Basic Endpoint

```php
<?php
// app/Rest/ProductsApi.php

namespace App\Rest;

use Studiometa\Foehn\Attributes\AsRestRoute;
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
        $products = \Timber\Timber::get_posts([
            'post_type' => 'product',
            'posts_per_page' => $request->get_param('per_page') ?? 10,
            'paged' => $request->get_param('page') ?? 1,
        ]);

        $data = array_map(fn($product) => [
            'id' => $product->ID,
            'title' => $product->title,
            'price' => $product->price(),
            'link' => $product->link(),
        ], $products);

        return new WP_REST_Response($data);
    }
}
```

**Endpoint:** `GET /wp-json/theme/v1/products`

## Route Parameters

```php
#[AsRestRoute(
    namespace: 'theme/v1',
    route: '/products/(?P<id>\d+)',
    method: 'GET',
)]
public function show(WP_REST_Request $request): WP_REST_Response
{
    $id = (int) $request->get_param('id');
    $product = \Timber\Timber::get_post($id);

    if (!$product || $product->post_type !== 'product') {
        return new WP_REST_Response(['error' => 'Product not found'], 404);
    }

    return new WP_REST_Response([
        'id' => $product->ID,
        'title' => $product->title,
        'content' => $product->content(),
        'price' => $product->price(),
    ]);
}
```

**Endpoint:** `GET /wp-json/theme/v1/products/123`

## HTTP Methods

```php
// GET request
#[AsRestRoute(namespace: 'theme/v1', route: '/items', method: 'GET')]
public function list(WP_REST_Request $request): WP_REST_Response {}

// POST request
#[AsRestRoute(namespace: 'theme/v1', route: '/items', method: 'POST')]
public function create(WP_REST_Request $request): WP_REST_Response {}

// PUT request
#[AsRestRoute(namespace: 'theme/v1', route: '/items/(?P<id>\d+)', method: 'PUT')]
public function update(WP_REST_Request $request): WP_REST_Response {}

// PATCH request
#[AsRestRoute(namespace: 'theme/v1', route: '/items/(?P<id>\d+)', method: 'PATCH')]
public function patch(WP_REST_Request $request): WP_REST_Response {}

// DELETE request
#[AsRestRoute(namespace: 'theme/v1', route: '/items/(?P<id>\d+)', method: 'DELETE')]
public function delete(WP_REST_Request $request): WP_REST_Response {}
```

## Permission Callbacks

### Public Endpoints

```php
#[AsRestRoute(
    namespace: 'theme/v1',
    route: '/products',
    method: 'GET',
    permission: 'public',
)]
public function list(WP_REST_Request $request): WP_REST_Response
{
    // Anyone can access
}
```

### Authenticated Endpoints

```php
#[AsRestRoute(
    namespace: 'theme/v1',
    route: '/orders',
    method: 'GET',
    permission: 'canViewOrders',
)]
public function listOrders(WP_REST_Request $request): WP_REST_Response
{
    // Only users with permission
}

public function canViewOrders(WP_REST_Request $request): bool
{
    return current_user_can('read');
}
```

### Admin-Only Endpoints

```php
#[AsRestRoute(
    namespace: 'theme/v1',
    route: '/admin/settings',
    method: 'POST',
    permission: 'isAdmin',
)]
public function updateSettings(WP_REST_Request $request): WP_REST_Response {}

public function isAdmin(): bool
{
    return current_user_can('manage_options');
}
```

## Request Arguments Schema

Define and validate request parameters:

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
        'page' => [
            'type' => 'integer',
            'default' => 1,
            'minimum' => 1,
        ],
        'category' => [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'orderby' => [
            'type' => 'string',
            'default' => 'date',
            'enum' => ['date', 'title', 'price', 'popularity'],
        ],
    ],
)]
public function list(WP_REST_Request $request): WP_REST_Response
{
    $perPage = $request->get_param('per_page');
    $page = $request->get_param('page');
    $category = $request->get_param('category');
    $orderby = $request->get_param('orderby');

    // Parameters are validated automatically
}
```

## Full CRUD Example

```php
<?php
// app/Rest/ProductsApi.php

namespace App\Rest;

use Studiometa\Foehn\Attributes\AsRestRoute;
use WP_REST_Request;
use WP_REST_Response;

final class ProductsApi
{
    #[AsRestRoute(
        namespace: 'theme/v1',
        route: '/products',
        method: 'GET',
        permission: 'public',
        args: [
            'per_page' => ['type' => 'integer', 'default' => 10],
            'page' => ['type' => 'integer', 'default' => 1],
        ],
    )]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => $request->get_param('per_page'),
            'paged' => $request->get_param('page'),
        ]);

        return new WP_REST_Response(
            array_map([$this, 'formatProduct'], $products)
        );
    }

    #[AsRestRoute(
        namespace: 'theme/v1',
        route: '/products/(?P<id>\d+)',
        method: 'GET',
        permission: 'public',
    )]
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $product = get_post($request->get_param('id'));

        if (!$product || $product->post_type !== 'product') {
            return new WP_REST_Response(['error' => 'Not found'], 404);
        }

        return new WP_REST_Response($this->formatProduct($product));
    }

    #[AsRestRoute(
        namespace: 'theme/v1',
        route: '/products',
        method: 'POST',
        permission: 'canCreateProduct',
        args: [
            'title' => ['type' => 'string', 'required' => true],
            'content' => ['type' => 'string', 'default' => ''],
            'price' => ['type' => 'number', 'required' => true],
        ],
    )]
    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $id = wp_insert_post([
            'post_type' => 'product',
            'post_title' => $request->get_param('title'),
            'post_content' => $request->get_param('content'),
            'post_status' => 'publish',
        ]);

        if (is_wp_error($id)) {
            return new WP_REST_Response(['error' => $id->get_error_message()], 400);
        }

        update_post_meta($id, 'price', $request->get_param('price'));

        return new WP_REST_Response(
            $this->formatProduct(get_post($id)),
            201
        );
    }

    #[AsRestRoute(
        namespace: 'theme/v1',
        route: '/products/(?P<id>\d+)',
        method: 'PUT',
        permission: 'canEditProduct',
    )]
    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        wp_update_post([
            'ID' => $id,
            'post_title' => $request->get_param('title'),
            'post_content' => $request->get_param('content'),
        ]);

        if ($request->has_param('price')) {
            update_post_meta($id, 'price', $request->get_param('price'));
        }

        return new WP_REST_Response($this->formatProduct(get_post($id)));
    }

    #[AsRestRoute(
        namespace: 'theme/v1',
        route: '/products/(?P<id>\d+)',
        method: 'DELETE',
        permission: 'canDeleteProduct',
    )]
    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        wp_delete_post($id, true);

        return new WP_REST_Response(null, 204);
    }

    // Permission callbacks
    public function canCreateProduct(): bool
    {
        return current_user_can('publish_posts');
    }

    public function canEditProduct(WP_REST_Request $request): bool
    {
        return current_user_can('edit_post', $request->get_param('id'));
    }

    public function canDeleteProduct(WP_REST_Request $request): bool
    {
        return current_user_can('delete_post', $request->get_param('id'));
    }

    private function formatProduct(\WP_Post $product): array
    {
        return [
            'id' => $product->ID,
            'title' => $product->post_title,
            'content' => $product->post_content,
            'price' => (float) get_post_meta($product->ID, 'price', true),
            'link' => get_permalink($product->ID),
        ];
    }
}
```

## Dependency Injection

```php
<?php

namespace App\Rest;

use App\Services\ProductService;
use Studiometa\Foehn\Attributes\AsRestRoute;
use WP_REST_Request;
use WP_REST_Response;

final class ProductsApi
{
    public function __construct(
        private readonly ProductService $products,
    ) {}

    #[AsRestRoute(namespace: 'theme/v1', route: '/products', method: 'GET')]
    public function list(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(
            $this->products->getAll($request->get_params())
        );
    }
}
```

## Attribute Parameters

| Parameter    | Type      | Default    | Description                       |
| ------------ | --------- | ---------- | --------------------------------- |
| `namespace`  | `string`  | _required_ | REST namespace (e.g., `theme/v1`) |
| `route`      | `string`  | _required_ | Route pattern                     |
| `method`     | `string`  | `'GET'`    | HTTP method                       |
| `permission` | `?string` | `null`     | Permission callback or `'public'` |
| `args`       | `array`   | `[]`       | Request arguments schema          |

## See Also

- [API Reference: #[AsRestRoute]](/api/as-rest-route)
