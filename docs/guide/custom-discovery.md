# Custom Discovery

Føhn's attribute scanning is not a closed set. A discovery of your own — in the theme's `app/` directory or in a Composer package — is found the same way the framework's own are, and applies at the WordPress hook it asks for.

## What a discovery is

A discovery is a class implementing `Tempest\Discovery\Discovery`:

- `discover()` is called once per scanned class. It inspects the class and records what it cares about.
- `apply()` is called once, at the phase the class declares, and registers what was recorded with WordPress.

Between the two, the recorded items may be written to the discovery cache and read back on a later request — so `apply()` must work from data alone, without re-reflecting.

## Writing one

### 1. Define the attribute

```php
<?php
// app/Attributes/AsWidget.php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsWidget
{
    public function __construct(
        public string $name,
        public string $title,
        public string $description = '',
    ) {}
}
```

### 2. Write the discovery

```php
<?php
// app/Discovery/WidgetDiscovery.php

namespace App\Discovery;

use App\Attributes\AsWidget;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use WP_Widget;

#[AsDiscovery(phase: DiscoveryPhase::Early)]
final class WidgetDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        // A base widget class carries the attribute its children inherit, and
        // registering it would hand WordPress a class it cannot instantiate.
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsWidget::class);

        if ($attribute === null) {
            return;
        }

        if (!$class->getReflection()->isSubclassOf(WP_Widget::class)) {
            return;
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    public function apply(): void
    {
        add_action('widgets_init', function (): void {
            foreach ($this->getItems() as $item) {
                register_widget($item['className']);
            }
        });
    }
}
```

### 3. There is no step three

Nothing registers the discovery. It is found because it implements `Discovery` and sits in a scanned location — the theme's `app/` directory is one, and so is any package that opts in.

## Choosing a phase

`#[AsDiscovery]` names the moment `apply()` runs:

| Phase                   | WordPress hook      | Register here                                                    |
| ----------------------- | ------------------- | ---------------------------------------------------------------- |
| `DiscoveryPhase::Early` | `after_setup_theme` | Theme support, hooks, image sizes, Twig extensions, CLI commands |
| `DiscoveryPhase::Main`  | `init`              | Post types, taxonomies, blocks, meta, menus, cron                |
| `DiscoveryPhase::Late`  | `wp_loaded`         | REST routes, template controllers, context providers             |

The attribute is optional: without it a discovery applies in the `Main` phase, which is what most registration wants.

If the API you are registering with wants a hook none of the three matches — `admin_menu`, say, or `widgets_init` above — call `add_action()` from inside `apply()`. A discovery wanting a different hook is not a reason for a fourth phase.

Within a phase, discoveries apply in class name order. Scan order is filesystem order and a restored cache replays whatever order it was written in, so neither is something to register against.

## Items have to survive the cache

Whatever `addItem()` receives is written to the discovery cache through `var_export()` and read back on the next request. Attribute instances, strings, numbers and arrays of those survive that round trip. Closures, resources and objects holding either do not — and because the cache is off in development and on in production, a closure in an item works everywhere except where it matters.

This is why every Føhn attribute that takes a callback takes a **method name** rather than a callable, resolved when `apply()` runs. Do the same in your own.

## Making a package discoverable

A Composer package is scanned when it either requires a `tempest/*` package or opts in explicitly:

```json
{
  "extra": {
    "tempest": {
      "can-discover": true
    }
  }
}
```

Without one of those, `DiscoveryLocations` never lists the package, its discoveries are never found, and everything fails quietly. A package requiring `studiometa/foehn` and nothing from Tempest needs the `extra` block.

## Guards worth copying

- **`isConcrete()`**, from `IsWpDiscovery`, excludes abstract classes, traits and enums. Instantiability is deliberately not the test: `Timber\Post` and `Timber\Term` declare protected constructors, so every post type and taxonomy model would fail it.
- **`#[SkipDiscovery]`**, from Tempest, excludes a class from discovery entirely. Command stubs carrying real attributes use it — and so must an abstract class that implements `Discovery` itself, or the runner will try to resolve it.
- **A missing platform function is a skip, not a fatal.** If your discovery targets a plugin or a newer WordPress, guard `apply()` with `function_exists()` and say nothing unless `FoehnConfig::debug` is on.

## Seeing what was found

```bash
wp foehn discovery:list --discovery=Widget
```

Your discovery appears there with no work on your part: the renderer reflects whatever attribute the item holds, so a third-party one prints like a built-in. If it reports zero items, the output also says whether each location was scanned or restored from the cache — an entry written before your class existed does not contain it, and that is the usual answer. Clear it with `wp foehn discovery:clear`.

See [Listing what was found](/guide/discovery-cache#listing-what-was-found).

## Related

- [#[AsDiscovery]](/api/as-discovery)
- [DiscoveryRunner](/api/discovery-runner)
- [Guide: Discovery Cache](/guide/discovery-cache)
