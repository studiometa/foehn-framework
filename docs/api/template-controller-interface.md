# TemplateControllerInterface

Interface for template controllers that handle full template rendering.

## Signature

```php
<?php

namespace Studiometa\WPTempest\Contracts;

interface TemplateControllerInterface
{
    /**
     * Handle the template request.
     *
     * This method is called when WordPress would render a matching template.
     * It should return the rendered HTML or null to let WordPress handle it.
     *
     * @return string|null Rendered HTML or null to pass through
     */
    public function handle(): ?string;
}
```

## Methods

### handle()

Handle the template request. Return rendered HTML or `null` to let WordPress handle it normally.

```php
public function handle(): ?string
{
    $post = \Timber\Timber::get_post();

    // Return null to pass through to WordPress
    if (!$post) {
        return null;
    }

    return $this->view->render('single', ['post' => $post]);
}
```

## Usage

```php
<?php

namespace App\Views\Controllers;

use Studiometa\WPTempest\Attributes\AsTemplateController;
use Studiometa\WPTempest\Contracts\TemplateControllerInterface;
use Studiometa\WPTempest\Contracts\ViewEngineInterface;

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
            'related' => $this->getRelatedPosts($post),
        ]);
    }

    private function getRelatedPosts($post): array
    {
        return \Timber\Timber::get_posts([
            'post_type' => $post->post_type,
            'posts_per_page' => 3,
            'post__not_in' => [$post->ID],
        ]);
    }
}
```

### Conditional Handling

```php
#[AsTemplateController('single')]
final class SingleController implements TemplateControllerInterface
{
    public function handle(): ?string
    {
        $post = \Timber\Timber::get_post();

        // Only handle products, let WordPress handle other post types
        if ($post->post_type !== 'product') {
            return null;
        }

        return $this->view->render('single-product', [
            'product' => $post,
            'related' => $post->relatedProducts(4),
        ]);
    }
}
```

## Related

- [Guide: Template Controllers](/guide/template-controllers)
- [`#[AsTemplateController]`](./as-template-controller)
