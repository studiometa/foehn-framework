# Add `#[AsMenu]` attribute for WordPress menu registration

## Problem

Every WordPress theme needs to register navigation menus. Currently this is done manually in ThemeManager:

```php
// Current pattern
class ThemeManager implements ManagerInterface {
    public function run() {
        add_action('admin_init', [$this, 'register_menus']);
        add_filter('timber/context', [$this, 'add_menus_to_context']);
    }

    public function register_menus() {
        register_nav_menus([
            'header_menu' => 'Navigation Header Menu',
            'footer_menu' => 'Navigation Footer Menu',
            'legal_menu' => 'Legals Footer Menu',
        ]);
    }

    public function add_menus_to_context(array $context) {
        $context['header_menu'] = new Menu('header_menu');
        $context['footer_menu'] = new Menu('footer_menu');
        $context['legals_menu'] = new Menu('legal_menu');
        return $context;
    }
}
```

Problems:

- Menus are scattered in ThemeManager
- No single place to see all registered menus
- Manual context registration

## Proposed solution: One class per menu

Following Foehn's discovery pattern, each menu gets its own class with `#[AsMenu]`:

```php
// app/Menus/HeaderMenu.php
namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(
    location: 'header',
    title: 'Header Menu',
)]
final class HeaderMenu {}
```

```php
// app/Menus/FooterMenu.php
namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(
    location: 'footer',
    title: 'Footer Menu',
)]
final class FooterMenu {}
```

```php
// app/Menus/LegalMenu.php
namespace App\Menus;

use Studiometa\Foehn\Attributes\AsMenu;

#[AsMenu(
    location: 'legal',
    title: 'Legal Menu',
)]
final class LegalMenu {}
```

## Benefits

1. **Discoverability**: All menus are in `app/Menus/`, easy to find
2. **Consistency**: Same pattern as `#[AsPostType]`, `#[AsTaxonomy]`, etc.
3. **Auto-registration**: No manual `register_nav_menus()` call
4. **Auto-context**: Menus automatically added to Timber context

## Attribute definition

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMenu
{
    /**
     * @param string $location Menu location slug (used in Timber::get_menu())
     * @param string $title Menu title displayed in admin (Appearance > Menus)
     * @param bool $addToContext Auto-add to Timber context (default: true)
     * @param string|null $contextKey Context key (defaults to location)
     */
    public function __construct(
        public string $location,
        public string $title,
        public bool $addToContext = true,
        public ?string $contextKey = null,
    ) {}
}
```

## Discovery

```php
final class MenuDiscovery implements Discovery
{
    use IsDiscovery;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsMenu::class);

        if ($attribute) {
            $this->discoveryItems->add($location, $attribute);
        }
    }

    public function apply(): void
    {
        $menus = [];

        foreach ($this->discoveryItems as $item) {
            $menus[$item->location] = $item->title;
        }

        // Register all menus
        add_action('after_setup_theme', function () use ($menus) {
            register_nav_menus($menus);
        });

        // Add to Timber context
        add_filter('timber/context', function (array $context) {
            foreach ($this->discoveryItems as $item) {
                if ($item->addToContext) {
                    $key = $item->contextKey ?? $item->location;
                    $context['menus'][$key] = Timber::get_menu($item->location);
                }
            }
            return $context;
        });
    }
}
```

## Usage in Twig

```twig
{# Menus are automatically available #}
<nav>
    {% for item in menus.header.items %}
        <a href="{{ item.link }}" class="{{ item.current ? 'active' : '' }}">
            {{ item.title }}
        </a>

        {# Submenu #}
        {% if item.children %}
            <ul>
                {% for child in item.children %}
                    <a href="{{ child.link }}">{{ child.title }}</a>
                {% endfor %}
            </ul>
        {% endif %}
    {% endfor %}
</nav>
```

## ACF custom fields on menu items

ACF allows adding custom fields to menu items. Access them via `meta()`:

```twig
{% for item in menus.header.items %}
    <a href="{{ item.link }}">
        {# ACF icon field #}
        {% set icon = item.meta('icon') %}
        {% if icon %}
            <img src="{{ icon.url }}" alt="" class="menu-icon">
        {% endif %}

        {{ item.title }}

        {# ACF description field #}
        {% if item.meta('description') %}
            <span class="menu-description">{{ item.meta('description') }}</span>
        {% endif %}
    </a>
{% endfor %}
```

## Directory structure

```
app/
├── Menus/
│   ├── HeaderMenu.php
│   ├── FooterMenu.php
│   └── LegalMenu.php
```

## CLI command

```bash
php foehn make:menu HeaderMenu --location=header --title="Header Menu"

# Output: app/Menus/HeaderMenu.php
```

## Advanced: Menu without auto-context

For menus that shouldn't be loaded on every page:

```php
#[AsMenu(
    location: 'mobile',
    title: 'Mobile Menu',
    addToContext: false,  // Don't auto-add to context
)]
final class MobileMenu {}
```

Then load manually when needed:

```php
// In a specific ContextProvider
$context['mobile_menu'] = Timber::get_menu('mobile');
```

## Tasks

- [ ] Create `AsMenu` attribute
- [ ] Create `MenuDiscovery` class
- [ ] Auto-register menus via `register_nav_menus()`
- [ ] Auto-add to Timber context under `menus.*`
- [ ] Add `make:menu` CLI command
- [ ] Document ACF custom fields on menu items
- [ ] Add tests

## Labels

`enhancement`, `priority-medium`
