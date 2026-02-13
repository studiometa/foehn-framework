# ViewEngineInterface

Interface for view rendering engines. Abstracts the template engine (Timber/Twig) behind a consistent API.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

interface ViewEngineInterface
{
    /**
     * Render a template with the given context.
     *
     * @param string $template Template name/path (without extension)
     * @param array<string, mixed>|object $context Variables to pass to the template
     * @return string Rendered HTML
     */
    public function render(string $template, array|object $context = []): string;

    /**
     * Render the first existing template from a list.
     *
     * @param string[] $templates List of template names to try
     * @param array<string, mixed>|object $context Variables to pass to the template
     * @return string Rendered HTML
     * @throws RuntimeException If no template is found
     */
    public function renderFirst(array $templates, array|object $context = []): string;

    /**
     * Check if a template exists.
     */
    public function exists(string $template): bool;

    /**
     * Share data with all templates.
     */
    public function share(string $key, mixed $value): void;

    /**
     * Get all shared data.
     *
     * @return array<string, mixed>
     */
    public function getShared(): array;
}
```

## Default Implementation

Føhn ships with `TimberViewEngine`, which wraps Timber/Twig rendering with context provider support.

### Context Resolution Order

When rendering a template, contexts are merged in this order (later wins):

1. **Timber global context** — site, theme, user, etc. (`Timber::context_global()`)
2. **Shared data** — registered via `share()`
3. **Context providers** — matched by template name
4. **Explicit context** — passed to `render()`

## Usage

### Injecting the View Engine

```php
use Studiometa\Foehn\Contracts\ViewEngineInterface;

final readonly class MyService
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function renderCard(array $data): string
    {
        return $this->view->render('components/card', $data);
    }
}
```

### Template Hierarchy Fallbacks

Use `renderFirst()` for WordPress-style template hierarchy:

```php
$html = $view->renderFirst([
    "pages/single-{$postType}-{$slug}",
    "pages/single-{$postType}",
    'pages/single',
], $context);
```

### Sharing Global Data

Share data available in all templates:

```php
$view->share('currentYear', date('Y'));
$view->share('navigation', $navItems);
```

### Checking Template Existence

```php
if ($view->exists('blocks/hero')) {
    $html = $view->render('blocks/hero', $context);
}
```

## Template Resolution

The `.twig` extension is added automatically if not present:

```php
$view->render('pages/home');       // → pages/home.twig
$view->render('pages/home.twig');  // → pages/home.twig
```

Templates are searched in the directories configured by `TimberConfig::$templatesDir`.

## Related

- [TimberConfig](./timber-config)
- [`#[AsContextProvider]`](./as-context-provider)
- [`#[AsTemplateController]`](./as-template-controller)
- [Guide: Template Controllers](/guide/template-controllers)
