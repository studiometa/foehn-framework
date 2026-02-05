# #[AsContextProvider]

Register a class as a context provider that adds data to specific templates.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsContextProvider
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

### Global Provider

```php
<?php

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;

#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['site_name'] = get_bloginfo('name');
        $context['primary_menu'] = \Timber\Timber::get_menu('primary');

        return $context;
    }
}
```

### Template-Specific

```php
#[AsContextProvider('single-product')]
final class ProductContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['related'] = $context['post']->relatedProducts(4);
        return $context;
    }
}
```

### Wildcard Pattern

```php
#[AsContextProvider('archive-*')]
final class ArchiveContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['pagination'] = \Timber\Timber::get_pagination();
        return $context;
    }
}
```

### Multiple Templates

```php
#[AsContextProvider(['home', 'front-page'])]
final class HomeContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['featured'] = $this->getFeaturedPosts();
        return $context;
    }
}
```

### With Priority

```php
// Runs first
#[AsContextProvider('*', priority: 5)]
final class BaseContextProvider implements ContextProviderInterface {}

// Runs last
#[AsContextProvider('*', priority: 20)]
final class FinalContextProvider implements ContextProviderInterface {}
```

## Required Interface

Classes must implement `ContextProviderInterface`:

```php
interface ContextProviderInterface
{
    public function provide(array $context): array;
}
```

## Related

- [Guide: Context Providers](/guide/context-providers)
- [`ContextProviderInterface`](./context-provider-interface)
- [`#[AsTemplateController]`](./as-template-controller)
