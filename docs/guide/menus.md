# Menus

Foehn uses `#[AsMenu]` to register WordPress navigation menu locations with automatic Timber context integration.

## Basic Menu

```php
<?php
// app/Menus/PrimaryMenu.php

namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(
    location: 'primary',
    description: 'Primary Navigation',
)]
final class PrimaryMenu {}
```

This registers a menu location and automatically adds it to Timber's context when a menu is assigned.

## Multiple Menus

Create separate classes for each menu location:

```php
<?php
// app/Menus/PrimaryMenu.php

namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(location: 'primary', description: 'Primary Navigation')]
final class PrimaryMenu {}
```

```php
<?php
// app/Menus/FooterMenu.php

namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(location: 'footer', description: 'Footer Navigation')]
final class FooterMenu {}
```

```php
<?php
// app/Menus/MobileMenu.php

namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(location: 'mobile', description: 'Mobile Navigation')]
final class MobileMenu {}
```

## Using in Templates

Menus are automatically available in Timber context under `menus.<location>`:

```twig
{# views/partials/header.twig #}
<header>
    <nav class="primary-nav">
        {% if menus.primary %}
            <ul>
                {% for item in menus.primary.items %}
                    <li class="{{ item.classes|join(' ') }}">
                        <a 
                            href="{{ item.link }}"
                            {% if item.current %}aria-current="page"{% endif %}
                        >
                            {{ item.title }}
                        </a>
                        
                        {# Nested menu items #}
                        {% if item.children %}
                            <ul class="submenu">
                                {% for child in item.children %}
                                    <li>
                                        <a href="{{ child.link }}">{{ child.title }}</a>
                                    </li>
                                {% endfor %}
                            </ul>
                        {% endif %}
                    </li>
                {% endfor %}
            </ul>
        {% endif %}
    </nav>
</header>
```

```twig
{# views/partials/footer.twig #}
<footer>
    {% if menus.footer %}
        <nav class="footer-nav">
            <ul>
                {% for item in menus.footer.items %}
                    <li>
                        <a href="{{ item.link }}">{{ item.title }}</a>
                    </li>
                {% endfor %}
            </ul>
        </nav>
    {% endif %}
</footer>
```

## Menu Item Properties

Timber's `MenuItem` class provides these useful properties:

| Property      | Type          | Description                           |
| ------------- | ------------- | ------------------------------------- |
| `title`       | `string`      | Menu item title                       |
| `link`        | `string`      | URL                                   |
| `current`     | `bool`        | Is current page                       |
| `current_item_parent` | `bool` | Is parent of current page           |
| `current_item_ancestor` | `bool` | Is ancestor of current page       |
| `children`    | `MenuItem[]`  | Child menu items                      |
| `classes`     | `string[]`    | CSS classes                           |
| `target`      | `string`      | Link target (`_blank`, etc.)          |
| `description` | `string`      | Menu item description                 |

## Checking Menu Assignment

Menus only appear in context when assigned in WordPress admin (Appearance → Menus):

```twig
{% if menus.primary %}
    {# Menu is assigned, render it #}
{% else %}
    {# Fallback or nothing #}
{% endif %}
```

## File Structure

Organize menus in a dedicated directory:

```
app/
├── Menus/
│   ├── PrimaryMenu.php
│   ├── FooterMenu.php
│   └── MobileMenu.php
├── Models/
│   └── ...
└── Hooks/
    └── ...
```

## Attribute Parameters

| Parameter     | Type     | Default    | Description                        |
| ------------- | -------- | ---------- | ---------------------------------- |
| `location`    | `string` | _required_ | Menu location slug                 |
| `description` | `string` | _required_ | Label shown in WordPress admin     |

## See Also

- [API Reference: #[AsMenu]](/api/as-menu)
- [Timber Menu Documentation](https://timber.github.io/docs/v2/guides/menus/)
