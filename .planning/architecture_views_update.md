# Architecture Update: Views for Patterns & Interactivity

## Problem

Les Block Patterns et l'Interactivity API utilisent des heredocs HTML :

```php
// ❌ Difficile à maintenir
public static function content(): string
{
    return <<<'BLOCKS'
<!-- wp:cover {"overlayColor":"primary"} -->
<div class="wp-block-cover">...</div>
<!-- /wp:cover -->
BLOCKS;
}

// ❌ Pas de templating
public function render(): string
{
    return <<<HTML
    <div data-wp-interactive="theme/counter">
        <span data-wp-text="context.count"></span>
    </div>
    HTML;
}
```

## Solution: ViewEngine Everywhere

Utiliser le moteur de rendu (Twig/Blade/Tempest View) pour tout :

```
┌─────────────────────────────────────────────────────────────────┐
│                        ViewEngine                                │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐              │
│  │    Twig     │ │    Blade    │ │ Tempest View│              │
│  └─────────────┘ └─────────────┘ └─────────────┘              │
├─────────────────────────────────────────────────────────────────┤
│  Used by:                                                        │
│  • Page templates      • Block Patterns                         │
│  • ACF Blocks          • Interactivity Blocks                   │
│  • Native Blocks       • Shortcodes                             │
│  • View Composers      • Email templates                        │
└─────────────────────────────────────────────────────────────────┘
```

---

## 1. Block Patterns with ViewEngine

### Updated Attribute

```php
<?php
// src/Attributes/AsBlockPattern.php

namespace Studiometa\WPTempest\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBlockPattern
{
    public function __construct(
        public string $name,
        public string $title,
        public array $categories = [],
        public array $keywords = [],
        public array $blockTypes = [],
        public ?string $description = null,
        public ?string $template = null,  // Template path (auto-resolved if null)
        public int $viewportWidth = 1200,
        public bool $inserter = true,
    ) {}
}
```

### Interface

```php
<?php
// src/Contracts/BlockPatternInterface.php

namespace Studiometa\WPTempest\Contracts;

interface BlockPatternInterface
{
    /**
     * Compose data for the pattern template.
     *
     * @return array Context variables for the template
     */
    public function compose(): array;

    /**
     * Optional: Override the rendered content.
     * If not implemented, uses template file.
     */
    // public function content(): string;
}
```

### Pattern Implementation

```php
<?php
// app/Patterns/HeroWithCta.php

namespace App\Patterns;

use Studiometa\WPTempest\Attributes\AsBlockPattern;
use Studiometa\WPTempest\Contracts\BlockPatternInterface;

#[AsBlockPattern(
    name: 'theme/hero-with-cta',
    title: 'Hero avec CTA',
    categories: ['hero', 'call-to-action'],
    keywords: ['banner', 'header', 'landing'],
    blockTypes: ['core/cover'],
    // template auto-resolved to: templates/patterns/hero-with-cta.twig
)]
final readonly class HeroWithCta implements BlockPatternInterface
{
    public function compose(): array
    {
        return [
            'colors' => [
                'primary' => 'var(--wp--preset--color--primary)',
                'secondary' => 'var(--wp--preset--color--secondary)',
            ],
            'default_heading' => __('Your Amazing Headline', 'theme'),
            'default_text' => __('A compelling description that converts visitors.', 'theme'),
            'default_cta' => __('Get Started', 'theme'),
            'min_height' => '80vh',
        ];
    }
}
```

### Twig Template for Pattern

```twig
{# templates/patterns/hero-with-cta.twig #}

{#
  Block Pattern Template
  Variables: colors, default_heading, default_text, default_cta, min_height
#}

<!-- wp:cover {"overlayColor":"primary","minHeight":{{ min_height | json_encode }},"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:{{ min_height }}">
    <span class="wp-block-cover__background has-primary-background-color"></span>
    <div class="wp-block-cover__inner-container">

        <!-- wp:heading {"textAlign":"center","level":1} -->
        <h1 class="wp-block-heading has-text-align-center">{{ default_heading }}</h1>
        <!-- /wp:heading -->

        <!-- wp:paragraph {"align":"center"} -->
        <p class="has-text-align-center">{{ default_text }}</p>
        <!-- /wp:paragraph -->

        <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
        <div class="wp-block-buttons">
            <!-- wp:button {"backgroundColor":"secondary","textColor":"primary"} -->
            <div class="wp-block-button">
                <a class="wp-block-button__link has-primary-color has-secondary-background-color">
                    {{ default_cta }}
                </a>
            </div>
            <!-- /wp:button -->
        </div>
        <!-- /wp:buttons -->

    </div>
</div>
<!-- /wp:cover -->
```

### Updated Discovery

```php
<?php
// src/Discovery/BlockPatternDiscovery.php

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsBlockPattern;
use Studiometa\WPTempest\Contracts\BlockPatternInterface;
use Studiometa\WPTempest\Views\ViewEngineInterface;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

use function Tempest\get;

final class BlockPatternDiscovery implements Discovery
{
    use IsDiscovery;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsBlockPattern::class);

        if (!$attribute) {
            return;
        }

        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'class' => $class,
        ]);
    }

    public function apply(): void
    {
        add_action('init', function() {
            foreach ($this->discoveryItems as $item) {
                $this->registerPattern($item['attribute'], $item['class']);
            }
        });
    }

    private function registerPattern(AsBlockPattern $attribute, ClassReflector $class): void
    {
        $className = $class->getName();

        // Resolve template path
        $template = $attribute->template
            ?? $this->resolveTemplatePath($attribute->name);

        // Get pattern content via ViewEngine
        $content = $this->renderPatternContent($className, $template);

        register_block_pattern($attribute->name, [
            'title' => $attribute->title,
            'description' => $attribute->description,
            'categories' => $attribute->categories,
            'keywords' => $attribute->keywords,
            'blockTypes' => $attribute->blockTypes,
            'viewportWidth' => $attribute->viewportWidth,
            'inserter' => $attribute->inserter,
            'content' => $content,
        ]);
    }

    private function resolveTemplatePath(string $name): string
    {
        // 'theme/hero-with-cta' → 'patterns/hero-with-cta'
        $slug = str_replace(['theme/', 'starter/'], '', $name);
        return "patterns/{$slug}";
    }

    private function renderPatternContent(string $className, string $template): string
    {
        /** @var ViewEngineInterface $view */
        $view = get(ViewEngineInterface::class);

        // Get composed data if class implements interface
        $context = [];
        if (is_subclass_of($className, BlockPatternInterface::class)) {
            /** @var BlockPatternInterface $instance */
            $instance = get($className);
            $context = $instance->compose();
        }

        return $view->render($template, $context);
    }
}
```

---

## 2. Interactivity API with ViewEngine

### Updated Attribute

```php
<?php
// src/Attributes/AsBlock.php (updated)

namespace Studiometa\WPTempest\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBlock
{
    public function __construct(
        public string $name,
        public string $title,
        public string $category = 'widgets',
        public ?string $icon = null,
        public ?string $description = null,
        public array $keywords = [],
        public array $supports = [],
        public ?string $parent = null,
        public array $ancestor = [],
        // Interactivity
        public bool $interactivity = false,
        public ?string $interactivityNamespace = null, // defaults to block name
        // Template
        public ?string $template = null, // auto-resolved if null
    ) {}
}
```

### Interface for Interactive Blocks

```php
<?php
// src/Contracts/InteractiveBlockInterface.php

namespace Studiometa\WPTempest\Contracts;

interface InteractiveBlockInterface extends BlockInterface
{
    /**
     * Initial state for the Interactivity API store.
     *
     * @return array State data
     */
    public static function initialState(): array;

    /**
     * Initial context for this block instance.
     * Can use $attributes to customize per-instance.
     *
     * @param array $attributes Block attributes
     * @return array Context data for data-wp-context
     */
    public function initialContext(array $attributes): array;
}
```

### Interactive Block Implementation

```php
<?php
// app/Blocks/Counter/CounterBlock.php

namespace App\Blocks\Counter;

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Contracts\InteractiveBlockInterface;
use Studiometa\WPTempest\Views\ViewEngineInterface;
use WP_Block;

#[AsBlock(
    name: 'theme/counter',
    title: 'Interactive Counter',
    category: 'widgets',
    icon: 'plus-alt',
    interactivity: true,
    // interactivityNamespace defaults to 'theme/counter'
    supports: [
        'color' => ['background' => true, 'text' => true],
        'spacing' => ['margin' => true, 'padding' => true],
    ],
)]
final readonly class CounterBlock implements InteractiveBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function attributes(): array
    {
        return [
            'initialValue' => [
                'type' => 'number',
                'default' => 0,
            ],
            'step' => [
                'type' => 'number',
                'default' => 1,
            ],
            'min' => [
                'type' => 'number',
                'default' => null,
            ],
            'max' => [
                'type' => 'number',
                'default' => null,
            ],
        ];
    }

    public static function supports(): array
    {
        return [
            'interactivity' => true,
            'color' => [
                'background' => true,
                'text' => true,
            ],
        ];
    }

    /**
     * Global state (shared across all block instances)
     */
    public static function initialState(): array
    {
        return [
            'totalClicks' => 0,
        ];
    }

    /**
     * Per-instance context
     */
    public function initialContext(array $attributes): array
    {
        return [
            'count' => $attributes['initialValue'] ?? 0,
            'step' => $attributes['step'] ?? 1,
            'min' => $attributes['min'] ?? null,
            'max' => $attributes['max'] ?? null,
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [
            'wrapper_attributes' => get_block_wrapper_attributes([
                'class' => 'counter-block',
            ]),
            'namespace' => 'theme/counter',
            'context' => $this->initialContext($attributes),
            'initial_value' => $attributes['initialValue'] ?? 0,
            'step' => $attributes['step'] ?? 1,
            'show_reset' => ($attributes['initialValue'] ?? 0) !== 0,
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        // Register initial state (once per page)
        wp_interactivity_state('theme/counter', self::initialState());

        return $this->view->render('blocks/counter',
            $this->compose($attributes, $content, $block)
        );
    }
}
```

### Twig Template for Interactive Block

```twig
{# templates/blocks/counter.twig #}

{#
  Interactive Counter Block

  Variables:
    - wrapper_attributes: string (HTML attributes)
    - namespace: string (Interactivity namespace)
    - context: array (Initial context for this instance)
    - initial_value: int
    - step: int
    - show_reset: bool

  Interactivity directives:
    - data-wp-interactive: Namespace for this block
    - data-wp-context: Per-instance reactive state
    - data-wp-text: Reactive text binding
    - data-wp-on--click: Event handler
    - data-wp-bind--disabled: Reactive attribute binding
    - data-wp-class--is-zero: Conditional class
#}

<div
    {{ wrapper_attributes | raw }}
    data-wp-interactive="{{ namespace }}"
    data-wp-context='{{ context | json_encode }}'
>
    <div class="counter-block__display">
        <span
            class="counter-block__value"
            data-wp-text="context.count"
            data-wp-class--is-zero="context.count === 0"
        >
            {{ context.count }}
        </span>
    </div>

    <div class="counter-block__controls">
        <button
            type="button"
            class="counter-block__button counter-block__button--decrement"
            data-wp-on--click="actions.decrement"
            data-wp-bind--disabled="context.min !== null && context.count <= context.min"
            aria-label="{{ 'Decrease' | trans }}"
        >
            <span aria-hidden="true">−</span>
        </button>

        <button
            type="button"
            class="counter-block__button counter-block__button--increment"
            data-wp-on--click="actions.increment"
            data-wp-bind--disabled="context.max !== null && context.count >= context.max"
            aria-label="{{ 'Increase' | trans }}"
        >
            <span aria-hidden="true">+</span>
        </button>

        {% if show_reset %}
        <button
            type="button"
            class="counter-block__button counter-block__button--reset"
            data-wp-on--click="actions.reset"
            data-wp-bind--disabled="context.count === {{ initial_value }}"
            aria-label="{{ 'Reset' | trans }}"
        >
            <span aria-hidden="true">↺</span>
        </button>
        {% endif %}
    </div>

    {# Debug info in preview mode #}
    {% if is_preview | default(false) %}
    <details class="counter-block__debug">
        <summary>Debug</summary>
        <pre>{{ context | json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
    </details>
    {% endif %}
</div>
```

### JavaScript Interactivity (view.js)

```javascript
// app/Blocks/Counter/view.js

import { store, getContext } from "@wordpress/interactivity";

const { state, actions } = store("theme/counter", {
  state: {
    get totalClicks() {
      return state.totalClicks;
    },
  },
  actions: {
    increment() {
      const ctx = getContext();
      if (ctx.max === null || ctx.count < ctx.max) {
        ctx.count += ctx.step;
        state.totalClicks++;
      }
    },
    decrement() {
      const ctx = getContext();
      if (ctx.min === null || ctx.count > ctx.min) {
        ctx.count -= ctx.step;
        state.totalClicks++;
      }
    },
    reset() {
      const ctx = getContext();
      ctx.count = ctx.initialValue ?? 0;
    },
  },
});
```

---

## 3. Advanced Example: Interactive Tabs

### Block Class

```php
<?php
// app/Blocks/Tabs/TabsBlock.php

namespace App\Blocks\Tabs;

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Contracts\InteractiveBlockInterface;
use Studiometa\WPTempest\Views\ViewEngineInterface;
use WP_Block;

#[AsBlock(
    name: 'theme/tabs',
    title: 'Tabs',
    category: 'layout',
    icon: 'table-row-after',
    interactivity: true,
    supports: [
        'align' => ['wide', 'full'],
        'html' => false,
    ],
)]
final readonly class TabsBlock implements InteractiveBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function attributes(): array
    {
        return [
            'tabs' => [
                'type' => 'array',
                'default' => [],
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                    ],
                ],
            ],
            'defaultTab' => [
                'type' => 'string',
                'default' => '',
            ],
        ];
    }

    public static function initialState(): array
    {
        return [];
    }

    public function initialContext(array $attributes): array
    {
        $tabs = $attributes['tabs'] ?? [];
        $defaultTab = $attributes['defaultTab'] ?? ($tabs[0]['id'] ?? '');

        return [
            'activeTab' => $defaultTab,
            'tabs' => array_map(fn($tab) => $tab['id'], $tabs),
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        $tabs = $attributes['tabs'] ?? [];

        return [
            'wrapper_attributes' => get_block_wrapper_attributes(['class' => 'tabs-block']),
            'namespace' => 'theme/tabs',
            'context' => $this->initialContext($attributes),
            'tabs' => $tabs,
            'block_id' => $block->parsed_block['attrs']['anchor'] ?? uniqid('tabs-'),
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/tabs',
            $this->compose($attributes, $content, $block)
        );
    }
}
```

### Template Twig

```twig
{# templates/blocks/tabs.twig #}

<div
    {{ wrapper_attributes | raw }}
    data-wp-interactive="{{ namespace }}"
    data-wp-context='{{ context | json_encode }}'
    id="{{ block_id }}"
>
    {# Tab navigation #}
    <div class="tabs-block__nav" role="tablist">
        {% for tab in tabs %}
        <button
            type="button"
            role="tab"
            class="tabs-block__tab"
            id="{{ block_id }}-tab-{{ tab.id }}"
            aria-controls="{{ block_id }}-panel-{{ tab.id }}"
            data-wp-on--click="actions.selectTab"
            data-wp-bind--aria-selected="context.activeTab === '{{ tab.id }}'"
            data-wp-class--is-active="context.activeTab === '{{ tab.id }}'"
            data-tab-id="{{ tab.id }}"
        >
            {{ tab.label }}
        </button>
        {% endfor %}
    </div>

    {# Tab panels #}
    <div class="tabs-block__panels">
        {% for tab in tabs %}
        <div
            role="tabpanel"
            class="tabs-block__panel"
            id="{{ block_id }}-panel-{{ tab.id }}"
            aria-labelledby="{{ block_id }}-tab-{{ tab.id }}"
            data-wp-bind--hidden="context.activeTab !== '{{ tab.id }}'"
            data-wp-class--is-active="context.activeTab === '{{ tab.id }}'"
        >
            {{ tab.content | raw }}
        </div>
        {% endfor %}
    </div>
</div>
```

---

## 4. ViewEngine : Helpers pour Interactivity

### Twig Extension

```php
<?php
// src/Views/Twig/InteractivityExtension.php

namespace Studiometa\WPTempest\Views\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

final class InteractivityExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('wp_context', [$this, 'wpContext'], ['is_safe' => ['html']]),
            new TwigFunction('wp_interactive', [$this, 'wpInteractive'], ['is_safe' => ['html']]),
            new TwigFunction('wp_directive', [$this, 'wpDirective'], ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('wp_context', [$this, 'filterWpContext'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Generate data-wp-context attribute
     */
    public function wpContext(array $context): string
    {
        return sprintf("data-wp-context='%s'", json_encode($context, JSON_HEX_APOS));
    }

    /**
     * Generate data-wp-interactive attribute
     */
    public function wpInteractive(string $namespace): string
    {
        return sprintf('data-wp-interactive="%s"', esc_attr($namespace));
    }

    /**
     * Generate any wp directive
     *
     * Usage: {{ wp_directive('on--click', 'actions.toggle') }}
     */
    public function wpDirective(string $directive, string $value): string
    {
        return sprintf('data-wp-%s="%s"', esc_attr($directive), esc_attr($value));
    }

    /**
     * Filter to add context to existing attributes
     */
    public function filterWpContext(array $context): string
    {
        return json_encode($context, JSON_HEX_APOS);
    }
}
```

### Usage in Templates

```twig
{# Avec helpers #}
<div
    {{ wp_interactive('theme/counter') }}
    {{ wp_context({ count: 0, step: 1 }) }}
>
    <span {{ wp_directive('text', 'context.count') }}>0</span>
    <button {{ wp_directive('on--click', 'actions.increment') }}>+</button>
</div>

{# Ou avec filtre #}
<div
    data-wp-interactive="theme/counter"
    data-wp-context='{{ { count: 0, step: 1 } | wp_context }}'
>
    ...
</div>
```

---

## 5. Updated File Structure

```
theme/
├── app/
│   ├── Blocks/
│   │   ├── Counter/
│   │   │   ├── CounterBlock.php      # Block class
│   │   │   ├── edit.js               # Editor component (React)
│   │   │   ├── view.js               # Interactivity store
│   │   │   └── style.css             # Styles
│   │   └── Tabs/
│   │       ├── TabsBlock.php
│   │       ├── edit.js
│   │       ├── view.js
│   │       └── style.css
│   │
│   └── Patterns/
│       ├── HeroWithCta.php           # Pattern class (compose())
│       ├── TeamGrid.php
│       └── FeatureList.php
│
├── templates/
│   ├── blocks/
│   │   ├── counter.twig              # Block template
│   │   └── tabs.twig
│   │
│   └── patterns/
│       ├── hero-with-cta.twig        # Pattern template (Gutenberg markup)
│       ├── team-grid.twig
│       └── feature-list.twig
│
└── functions.php
```

---

## 6. Benefits of This Approach

| Aspect                   | Heredoc HTML | ViewEngine           |
| ------------------------ | ------------ | -------------------- |
| **Syntax highlighting**  | ❌ Aucun     | ✅ Complet           |
| **IDE autocomplete**     | ❌ Non       | ✅ Oui               |
| **Réutilisation**        | ❌ Difficile | ✅ Includes, extends |
| **Variables dynamiques** | ❌ Limité    | ✅ Complet           |
| **Conditionnels**        | ❌ Complexe  | ✅ Simple            |
| **Loops**                | ❌ Manuel    | ✅ Natif             |
| **Escaping**             | ❌ Manuel    | ✅ Automatique       |
| **Traductions**          | ❌ Complexe  | ✅ Filtres           |
| **Maintenabilité**       | ❌ Faible    | ✅ Élevée            |
| **Testabilité**          | ❌ Difficile | ✅ Simple            |

---

## 7. task_plan.md Update

Ajouter dans Phase 5 et 6 :

```markdown
### Phase 5: Blocks - Native Gutenberg (mise à jour)

- [ ] 5.1 Attribut `#[AsBlock]` avec option interactivity
- [ ] 5.2 Génération block.json automatique
- [ ] 5.3 Génération render.php (appelle ViewEngine)
- [ ] 5.4 InteractiveBlockInterface
- [ ] 5.5 Twig InteractivityExtension
- [ ] 5.6 Assets management (CSS/JS/view.js)
- [ ] 5.7 Tests

### Phase 6: FSE Support (mise à jour)

- [ ] 6.1 ThemeConfig → theme.json generator
- [ ] 6.2 Attribut `#[AsBlockPattern]` avec template support
- [ ] 6.3 BlockPatternDiscovery avec ViewEngine
- [ ] 6.4 Templates Twig pour patterns
- [ ] 6.5 Template parts support
- [ ] 6.6 Tests
```
