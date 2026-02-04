# #[AsFilter]

Register a method as a WordPress filter hook handler.

## Signature

```php
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsFilter
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
| `hook`         | `string` | —       | The WordPress filter hook name           |
| `priority`     | `int`    | `10`    | Priority for the hook                    |
| `acceptedArgs` | `int`    | `1`     | Number of arguments the callback accepts |

## Usage

### Basic Usage

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsFilter;

final class ContentFilters
{
    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }
}
```

### Modifying Content

```php
#[AsFilter('the_content')]
public function filterContent(string $content): string
{
    return '<div class="content-wrapper">' . $content . '</div>';
}
```

### With Multiple Arguments

```php
#[AsFilter('wp_nav_menu_items', priority: 10, acceptedArgs: 2)]
public function addSearchToMenu(string $items, object $args): string
{
    if ($args->theme_location === 'primary') {
        $items .= '<li>' . get_search_form(false) . '</li>';
    }

    return $items;
}
```

### Multiple Filters

The attribute is repeatable:

```php
#[AsFilter('the_title')]
#[AsFilter('single_post_title')]
public function formatTitle(string $title): string
{
    return ucwords($title);
}
```

## Related

- [Guide: Hooks](/guide/hooks)
- [`#[AsAction]`](./as-action)
