# #[AsViewComposer]

Register a class as a view composer that adds data to specific templates.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsViewComposer
{
    public function __construct(
        public string|array $templates,
        public int $priority = 10,
    ) {}

    public function getTemplates(): array {}
}
```

## Parameters

| Parameter   | Type            | Default | Description                             |
| ----------- | --------------- | ------- | --------------------------------------- |
| `templates` | `string\|array` | —       | Template pattern(s) to match (required) |
| `priority`  | `int`           | `10`    | Execution priority (lower = earlier)    |

## Template Patterns

- `'single'` — Exact match
- `'single-*'` — Wildcard (matches `single-post`, `single-product`, etc.)
- `'*'` — Global (all templates)
- `['home', 'front-page']` — Multiple templates

## Usage

### Global Composer

```php
<?php

namespace App\Views\Composers;

use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;

#[AsViewComposer('*')]
final class GlobalComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $context['site_name'] = get_bloginfo('name');
        $context['primary_menu'] = \Timber\Timber::get_menu('primary');

        return $context;
    }
}
```

### Template-Specific

```php
#[AsViewComposer('single-product')]
final class ProductComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $context['related'] = $context['post']->relatedProducts(4);
        return $context;
    }
}
```

### Wildcard Pattern

```php
#[AsViewComposer('archive-*')]
final class ArchiveComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $context['pagination'] = \Timber\Timber::get_pagination();
        return $context;
    }
}
```

### Multiple Templates

```php
#[AsViewComposer(['home', 'front-page'])]
final class HomeComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $context['featured'] = $this->getFeaturedPosts();
        return $context;
    }
}
```

### With Priority

```php
// Runs first
#[AsViewComposer('*', priority: 5)]
final class BaseComposer implements ViewComposerInterface {}

// Runs last
#[AsViewComposer('*', priority: 20)]
final class FinalComposer implements ViewComposerInterface {}
```

## Required Interface

Classes must implement `ViewComposerInterface`:

```php
interface ViewComposerInterface
{
    public function compose(array $context): array;
}
```

## Related

- [Guide: View Composers](/guide/view-composers)
- [`ViewComposerInterface`](./view-composer-interface)
- [`#[AsTemplateController]`](./as-template-controller)
