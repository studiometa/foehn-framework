# #[AsAction]

Register a method as a WordPress action hook handler.

## Signature

```php
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsAction
{
    public function __construct(
        public string $hook,
        public int $priority = 10,
        public int $acceptedArgs = 1,
    ) {}
}
```

## Parameters

| Parameter      | Type     | Default | Description                              |
| -------------- | -------- | ------- | ---------------------------------------- |
| `hook`         | `string` | —       | The WordPress action hook name           |
| `priority`     | `int`    | `10`    | Priority for the hook                    |
| `acceptedArgs` | `int`    | `1`     | Number of arguments the callback accepts |

## Usage

### Basic Usage

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;

final class ThemeHooks
{
    #[AsAction('after_setup_theme')]
    public function setupTheme(): void
    {
        add_theme_support('post-thumbnails');
    }
}
```

### With Priority

```php
#[AsAction('init', priority: 5)]
public function earlyInit(): void
{
    // Runs early (lower number = earlier)
}

#[AsAction('init', priority: 20)]
public function lateInit(): void
{
    // Runs later
}
```

### With Arguments

```php
#[AsAction('save_post', priority: 10, acceptedArgs: 3)]
public function onSavePost(int $postId, \WP_Post $post, bool $update): void
{
    if ($post->post_type !== 'product') {
        return;
    }

    // Handle product save
}
```

### Multiple Actions

The attribute is repeatable:

```php
#[AsAction('admin_init')]
#[AsAction('init')]
public function initialize(): void
{
    // Runs on both hooks
}
```

## Related

- [Guide: Hooks](/guide/hooks)
- [`#[AsFilter]`](./as-filter)
