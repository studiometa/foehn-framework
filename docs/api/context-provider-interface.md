# ContextProviderInterface

Interface for context providers that add data to templates.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

interface ContextProviderInterface
{
    /**
     * Provide additional data for the view context.
     *
     * @param array<string, mixed> $context Current template context
     * @return array<string, mixed> Modified context with additional data
     */
    public function provide(array $context): array;
}
```

## Methods

### provide()

Receives the current template context and returns the modified context with additional data.

```php
public function provide(array $context): array
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

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;

#[AsContextProvider('*')]
final class NavigationContextProvider implements ContextProviderInterface
{
    public function provide(array $context): array
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

namespace App\ContextProviders;

use App\Services\CartService;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;

#[AsContextProvider('*')]
final class CartContextProvider implements ContextProviderInterface
{
    public function __construct(
        private readonly CartService $cart,
    ) {}

    public function provide(array $context): array
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

- [Guide: Context Providers](/guide/context-providers)
- [`#[AsContextProvider]`](./as-context-provider)
