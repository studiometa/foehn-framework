# ViewComposerInterface

Interface for view composers that add data to templates.

## Signature

```php
<?php

namespace Studiometa\WPTempest\Contracts;

interface ViewComposerInterface
{
    /**
     * Compose additional data for the view.
     *
     * @param array<string, mixed> $context Current template context
     * @return array<string, mixed> Modified context with additional data
     */
    public function compose(array $context): array;
}
```

## Methods

### compose()

Receives the current template context and returns the modified context with additional data.

```php
public function compose(array $context): array
{
    // Add new data
    $context['site_name'] = get_bloginfo('name');

    // Modify existing data
    if (isset($context['post'])) {
        $context['related_posts'] = $this->getRelatedPosts($context['post']);
    }

    return $context;
}
```

## Usage

```php
<?php

namespace App\Views\Composers;

use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;

#[AsViewComposer('*')]
final class NavigationComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $context['menus'] = [
            'primary' => \Timber\Timber::get_menu('primary'),
            'footer' => \Timber\Timber::get_menu('footer'),
        ];

        return $context;
    }
}
```

### With Dependency Injection

```php
<?php

namespace App\Views\Composers;

use App\Services\CartService;
use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;

#[AsViewComposer('*')]
final class CartComposer implements ViewComposerInterface
{
    public function __construct(
        private readonly CartService $cart,
    ) {}

    public function compose(array $context): array
    {
        $context['cart'] = [
            'count' => $this->cart->getItemCount(),
            'total' => $this->cart->getTotal(),
        ];

        return $context;
    }
}
```

## Related

- [Guide: View Composers](/guide/view-composers)
- [`#[AsViewComposer]`](./as-view-composer)
