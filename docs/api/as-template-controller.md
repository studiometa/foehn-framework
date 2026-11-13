# #[AsTemplateController]

Register a class as a template controller that handles full template rendering.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTemplateController
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
| `priority`  | `int`           | `10`    | Which controller wins a shared template |

## Template Patterns

Uses WordPress template hierarchy names:

- `'single'` — Single posts
- `'single-product'` — Single product posts
- `'archive'` — Archive pages
- `'category'` — Category archives
- `'home'` — Blog home
- `'front-page'` — Static front page
- `'404'` — Not found
- `'single-*'` — Wildcard pattern
- `['home', 'front-page']` — Multiple templates

When several controllers claim the same template, the one with the lowest `priority` renders it, and on a tie the one discovered last. A template name is looked up exactly first; failing that, wildcard patterns are tried in `priority` order and the first that matches wins. `wp foehn inspect templates` shows the controller of each template and the ones it overrides.

## Usage

### Basic Controller

```php
<?php

namespace App\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Views\TemplateContext;

#[AsTemplateController('single')]
final class SingleController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(TemplateContext $context): ?string
    {
        return $this->view->render('single', $context);
    }
}
```

### Return Null to Pass Through

Return `null` to let WordPress handle the template:

```php
#[AsTemplateController('single')]
final class SingleController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        // Only handle products
        if ($context->post?->post_type !== 'product') {
            return null;
        }

        return $this->view->render('single-product', $context);
    }
}
```

### Multiple Templates

```php
#[AsTemplateController(['home', 'front-page'])]
final class HomeController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        return $this->view->render('pages/home', $context->with('featured', $this->getFeaturedPosts()));
    }
}
```

### Wildcard Pattern

```php
#[AsTemplateController('archive-*')]
final class ArchiveController implements TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string
    {
        // $context->posts holds the posts of the main query.
        // In Twig, use {{ posts.pagination }} for the pagination.
        return $this->view->render('archive', $context);
    }
}
```

## Required Interface

Classes must implement `TemplateControllerInterface`:

```php
interface TemplateControllerInterface
{
    public function handle(TemplateContext $context): ?string;
}
```

## Related

- [Guide: Template Controllers](/guide/template-controllers)
- [`TemplateControllerInterface`](./template-controller-interface)
- [`#[AsContextProvider]`](./as-context-provider)
