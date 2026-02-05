# #[AsMenu]

Register a WordPress navigation menu location.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMenu
{
    public function __construct(
        public string $location,
        public string $description,
    ) {}
}
```

## Parameters

| Parameter     | Type     | Default | Description                              |
| ------------- | -------- | ------- | ---------------------------------------- |
| `location`    | `string` | —       | Menu location slug (required)            |
| `description` | `string` | —       | Human-readable label shown in admin (required) |

## Usage

### Basic Menu

```php
<?php

namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(
    location: 'primary',
    description: 'Primary Navigation',
)]
final class PrimaryMenu {}
```

### Multiple Menus

```php
<?php

namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(location: 'primary', description: 'Primary Navigation')]
final class PrimaryMenu {}

#[AsMenu(location: 'footer', description: 'Footer Navigation')]
final class FooterMenu {}

#[AsMenu(location: 'mobile', description: 'Mobile Navigation')]
final class MobileMenu {}
```

## Timber Context Integration

Menus are automatically added to the Timber context under `menus.<location>` when assigned in WordPress admin:

```twig
{# In any Twig template #}
{% if menus.primary %}
    <nav>
        {% for item in menus.primary.items %}
            <a href="{{ item.link }}" {% if item.current %}aria-current="page"{% endif %}>
                {{ item.title }}
            </a>
        {% endfor %}
    </nav>
{% endif %}
```

## Related

- [Guide: Menus](/guide/menus)
