# RestConfig

Configuration class for REST API endpoints.

## Signature

```php
<?php

namespace Studiometa\Foehn\Config;

final readonly class RestConfig
{
    /**
     * @param string|null $defaultCapability Default capability required for REST routes.
     */
    public function __construct(
        public ?string $defaultCapability = 'edit_posts',
    );
}
```

## Properties

| Property            | Type           | Default        | Description                                        |
| ------------------- | -------------- | -------------- | -------------------------------------------------- |
| `defaultCapability` | `string\|null` | `'edit_posts'` | Default WordPress capability for protected routes  |

## Usage

Create a config file in your app directory:

```php
<?php
// app/rest.config.php

use Studiometa\Foehn\Config\RestConfig;

return new RestConfig(
    defaultCapability: 'edit_posts',
);
```

### Permission Behavior

The `defaultCapability` controls what happens when a `#[AsRestRoute]` does not specify an explicit `permission`:

| `defaultCapability` | Result                                                        |
| ------------------- | ------------------------------------------------------------- |
| `'edit_posts'`      | User must have the `edit_posts` capability (default)          |
| `'manage_options'`  | User must be an administrator                                 |
| `null`              | Any authenticated user (`is_user_logged_in()`)                |

Individual routes can override this with the `permission` parameter:

```php
#[AsRestRoute(route: '/public-data', permission: 'public')]
public function publicEndpoint(): array { /* ... */ }

#[AsRestRoute(route: '/admin-only', permission: 'canManage')]
public function adminEndpoint(): array { /* ... */ }
```

### Relaxing Default Permissions

For APIs where most routes are public:

```php
return new RestConfig(
    defaultCapability: null, // Only requires login by default
);
```

## Related

- [Guide: REST API](/guide/rest-api)
- [`#[AsRestRoute]`](./as-rest-route)
- [Guide: Configuration](/guide/configuration)
