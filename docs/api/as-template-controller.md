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
| `priority`  | `int`           | `10`    | Priority for template_include filter    |

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

## Usage

### Basic Controller

```php
<?php

namespace App\Views\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Contracts\TemplateControllerInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

#[AsTemplateController('single')]
final class SingleController implements TemplateControllerInterface
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function handle(): ?string
    {
        $post = \Timber\Timber::get_post();

        return $this->view->render('single', [
            'post' => $post,
        ]);
    }
}
```

### Return Null to Pass Through

Return `null` to let WordPress handle the template:

```php
#[AsTemplateController('single')]
final class SingleController implements TemplateControllerInterface
{
    public function handle(): ?string
    {
        $post = \Timber\Timber::get_post();

        // Only handle products
        if ($post->post_type !== 'product') {
            return null;
        }

        return $this->view->render('single-product', [
            'post' => $post,
        ]);
    }
}
```

### Multiple Templates

```php
#[AsTemplateController(['home', 'front-page'])]
final class HomeController implements TemplateControllerInterface
{
    public function handle(): ?string
    {
        return $this->view->render('pages/home', [
            'featured' => $this->getFeaturedPosts(),
        ]);
    }
}
```

### Wildcard Pattern

```php
#[AsTemplateController('archive-*')]
final class ArchiveController implements TemplateControllerInterface
{
    public function handle(): ?string
    {
        return $this->view->render('archive', [
            'posts' => \Timber\Timber::get_posts(),
            'pagination' => \Timber\Timber::get_pagination(),
        ]);
    }
}
```

## Required Interface

Classes must implement `TemplateControllerInterface`:

```php
interface TemplateControllerInterface
{
    public function handle(): ?string;
}
```

## Related

- [Guide: Template Controllers](/guide/template-controllers)
- [`TemplateControllerInterface`](./template-controller-interface)
- [`#[AsViewComposer]`](./as-view-composer)
