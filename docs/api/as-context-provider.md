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

| Parameter   | Type            | Default | Description                              |
| ----------- | --------------- | ------- | ---------------------------------------- |
| `templates` | `string\|array` | —       | View name pattern(s) to match (required) |
| `priority`  | `int`           | `10`    | Execution priority (lower = earlier)     |

## Template Patterns

A pattern matches the view name passed to `ViewEngine::render()`, which is the path of the Twig file without `.twig`. It does not match WordPress template names such as `single-product`: those are what `#[AsTemplateController]` claims.

- `'pages/single'` — Exact match
- `'pages/single*'` — Wildcard (matches `pages/single`, `pages/single-product`, etc.)
- `'*'` — Global (every view, blocks and patterns included)
- `['pages/home', 'pages/front-page']` — Multiple views

`wp foehn inspect views` shows which providers match each Twig file of the theme.

## Usage

### Global Provider

```php
<?php

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsContextProvider('*')]
final class GlobalContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context
            ->with('current_year', date('Y'))
            ->with('is_home', is_front_page());
    }
}
```

### Template-Specific

```php
use App\Models\Product;

#[AsContextProvider('pages/single-product')]
final class ProductContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        $product = $context->post(Product::class);

        if (!$product) {
            return $context;
        }

        return $context->with('related', $product->relatedProducts(4));
    }
}
```

### Wildcard Pattern

```php
#[AsContextProvider('pages/archive*')]
final class ArchiveContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        if (!$context->posts) {
            return $context;
        }

        return $context->with('pagination', $context->posts->pagination());
    }
}
```

### Multiple Templates

```php
#[AsContextProvider(['pages/home', 'pages/front-page'])]
final class HomeContextProvider implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->with('featured', $this->getFeaturedPosts());
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
use Studiometa\Foehn\Views\TemplateContext;

interface ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext;
}
```

## Related

- [Guide: Context Providers](/guide/context-providers)
- [`ContextProviderInterface`](./context-provider-interface)
- [`#[AsTemplateController]`](./as-template-controller)
