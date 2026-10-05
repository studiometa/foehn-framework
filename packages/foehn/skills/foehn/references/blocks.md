# Blocks

Native blocks, sidebar controls, containers, the Interactivity API, block patterns and block bindings. ACF blocks (`#[AsAcfBlock]`) are in the `foehn-acf` skill.

Docs: [Native blocks](https://studiometa.github.io/foehn-framework/guide/native-blocks.md), [Block editor](https://studiometa.github.io/foehn-framework/guide/block-editor.md), [Block patterns](https://studiometa.github.io/foehn-framework/guide/block-patterns.md), [Block bindings](https://studiometa.github.io/foehn-framework/guide/block-bindings.md).

## The model

- A block is a class with `#[AsBlock]` that implements `Studiometa\Foehn\Contracts\BlockInterface` (or `InteractiveBlockInterface`).
- `attributes()` is the schema. WordPress gets the standard keys (`type`, `default`, `enum`, `items`, …). The editor gets the control keys.
- Every block is dynamic: it renders through `render_callback` on the front end, and through `ServerSideRender` in the editor. There is no `save()` and no static markup in post content. You can change the Twig template at any time without a block validation error.
- One generic script, `wp-content/foehn/editor.js`, registers all Føhn blocks in the editor from their schemas. The `studiometa/foehn-installer` Composer plugin copies it there on `composer install`. You write no block JavaScript and no `block.json`.
- `supports.html` is `false` by default.

## `#[AsBlock]` arguments

`name` (required, `namespace/slug`), `title` (required), `category` (`'widgets'`), `icon` (dashicon name), `description`, `keywords`, `supports` (WordPress block supports, for example `['align' => ['wide', 'full']]`), `parent`, `ancestor`, `interactivity` (`false`), `interactivityNamespace` (defaults to `name`), `allowedBlocks`, `innerBlocksTemplate`, `innerBlocksTemplateLock`.

`BlockInterface`:

```php
public static function attributes(): array;
public function compose(array $attributes, string $content, WP_Block $block): array|Arrayable;
public function render(array $attributes, string $content, WP_Block $block): string;
```

`render()` chooses the template. The convention is `$this->view->render('blocks/<slug>', $this->compose(...))`.

## Sidebar controls

The control comes from `type`, or from an explicit `control`:

| Schema                                        | Control                                                   |
| --------------------------------------------- | --------------------------------------------------------- |
| `'type' => 'string'`                          | text field                                                |
| `'type' => 'string'` with `options` or `enum` | select                                                    |
| `'type' => 'boolean'`                         | toggle                                                    |
| `'type' => 'number'` or `'integer'`           | number field (an `integer` is rounded before it is saved) |
| `'type' => 'string', 'control' => 'textarea'` | textarea                                                  |
| `'type' => 'integer', 'control' => 'image'`   | media picker, stores the attachment ID                    |
| `'type' => 'array', 'control' => 'gallery'`   | multiple media picker                                     |
| `'type' => 'integer', 'control' => 'file'`    | any file                                                  |
| `'type' => 'array', 'control' => 'posts'`     | ordered post picker                                       |

Extra keys, removed before the schema goes to WordPress:

| Key            | Use                                                                                      |
| -------------- | ---------------------------------------------------------------------------------------- |
| `control`      | `text`, `textarea`, `toggle`, `number`, `select`, `image`, `gallery`, `file`, `posts`    |
| `label`        | Field label. Default: the humanized key (`ctaLabel` gives "Cta label").                  |
| `help`         | Help text under the field.                                                               |
| `options`      | Select choices: a list of values, a `value => label` map, or a list of `{label, value}`. |
| `allowedTypes` | MIME or top-level types for `file` and `gallery`, for example `['audio']`.               |
| `postTypes`    | Post types the `posts` control searches. Default: every viewable type.                   |

- `array`, `object` or untyped attributes without a `control` get no sidebar field. They still reach `compose()`.
- Declare `gallery` and `posts` as arrays: `['type' => 'array', 'items' => ['type' => 'integer'], 'default' => []]`. As a scalar, WordPress keeps only the first value.

## Containers and prose

Prose (headings, paragraphs) goes in inner blocks with core blocks, because a server-rendered preview cannot be edited in place. Structured data (image, variant, link) goes in sidebar controls.

```php
#[AsBlock(
    name: 'theme/section',
    title: 'Section',
    category: 'design',
    icon: 'layout',
    supports: ['align' => ['wide', 'full']],
    allowedBlocks: ['core/heading', 'core/paragraph', 'core/image', 'theme/callout'],
    innerBlocksTemplate: [
        ['core/heading', ['level' => 2, 'placeholder' => 'Section title']],
        ['core/paragraph', ['placeholder' => 'Introduce the section here.']],
    ],
    innerBlocksTemplateLock: false,
)]
final readonly class SectionBlock implements BlockInterface
{
    public function __construct(private ViewEngineInterface $view) {}

    public static function attributes(): array
    {
        return [
            'background' => ['type' => 'string', 'default' => 'none', 'options' => ['none' => 'None', 'light' => 'Light', 'dark' => 'Dark']],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return ['background' => $attributes['background'] ?? 'none', 'content' => $content];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/section', $this->compose($attributes, $content, $block));
    }
}
```

```twig
{# templates/blocks/section.twig #}
<section class="section section--{{ background }}">
  {{ content|raw }}
</section>
```

Any of `allowedBlocks`, `innerBlocksTemplate` or `innerBlocksTemplateLock` makes the block a container. `innerBlocksTemplateLock` takes WordPress `templateLock` values: `'all'`, `'insert'`, `'contentOnly'`, or `false`.

## Typed context

`compose()` can return an `Arrayable` DTO. Its keys reach Twig in snake_case. See [views.md](views.md#dtos).

```php
public function compose(array $attributes, string $content, WP_Block $block): HeroContext
{
    return new HeroContext(
        title: (string) ($attributes['title'] ?? ''),
        background: ImageData::fromAttachmentId($attributes['backgroundId'] ?? null),
        cta: ($attributes['ctaUrl'] ?? '') === '' ? null : new LinkData(
            url: (string) $attributes['ctaUrl'],
            title: (string) ($attributes['ctaLabel'] ?? ''),
        ),
    );
}
```

## Block assets

Named after the block slug, and loaded only on pages that render the block. Nothing declares them.

| File                           | Loaded                                                                               |
| ------------------------------ | ------------------------------------------------------------------------------------ |
| `assets/css/blocks/<slug>.css` | front end and editor                                                                 |
| `assets/js/blocks/<slug>.js`   | front end, as a script module (`import` works, including `@wordpress/interactivity`) |

These files are served as they are, without a build. Keep them plain CSS and plain JavaScript. Styles that need Tailwind or bundling go in the theme's main stylesheet.

## Interactive blocks

Set `interactivity: true` and implement `Studiometa\Foehn\Contracts\InteractiveBlockInterface`:

```php
#[AsBlock(name: 'theme/counter', title: 'Counter', icon: 'calculator', interactivity: true)]
final readonly class CounterBlock implements InteractiveBlockInterface
{
    public function __construct(private ViewEngineInterface $view) {}

    public static function attributes(): array
    {
        return ['initialCount' => ['type' => 'integer', 'default' => 0]];
    }

    // Global state for the namespace, passed to wp_interactivity_state().
    public static function initialState(): array
    {
        return [];
    }

    // Per-instance context.
    public function initialContext(array $attributes): array
    {
        return ['count' => $attributes['initialCount'] ?? 0];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return ['context' => $this->initialContext($attributes)];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/counter', $this->compose($attributes, $content, $block));
    }
}
```

```twig
{# templates/blocks/counter.twig #}
<div data-wp-interactive="theme/counter" {{ wp_context(context) }}>
  <button {{ wp_directive('on--click', 'actions.increment') }}>+</button>
  <span data-wp-text="context.count">{{ context.count }}</span>
</div>
```

```js
// assets/js/blocks/counter.js
import { store, getContext } from "@wordpress/interactivity";

store("theme/counter", {
  actions: {
    increment() {
      getContext().count += 1;
    },
  },
});
```

`wp foehn make:block counter --interactive` generates the class.

## Block patterns

```php
namespace App\Patterns;

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

#[AsBlockPattern(
    name: 'theme/hero-with-cta',
    title: 'Hero with CTA',
    categories: ['featured'],
    keywords: ['hero'],
)]
final readonly class HeroWithCta implements BlockPatternInterface
{
    // Optional. Without the interface, the template renders with an empty context.
    public function compose(): array
    {
        return ['heading' => __('Welcome', 'theme')];
    }
}
```

- Template: `templates/patterns/<slug>.twig` (the name without its namespace), or the `template` argument.
- Write block markup with `wp_block_start()`, `wp_block_end()` and `wp_block()`, or with literal `<!-- wp:… -->` comments.
- Other arguments: `blockTypes`, `description`, `viewportWidth` (`1200`), `inserter` (`true`).
- The pattern renders when it is registered, not when it is inserted. Do not depend on the current post.

## Block bindings

For a **stored** value, declare the key with `#[AsPostMeta]` and bind it through core's `core/post-meta`. See [content.md](content.md#aspostmeta).

For a **computed** value, declare a binding source:

```php
namespace App\Bindings;

use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Contracts\BlockBindingInterface;
use WP_Block;

#[AsBlockBinding(name: 'theme/reading-time', label: 'Reading time', usesContext: ['postId'])]
final readonly class ReadingTime implements BlockBindingInterface
{
    public function value(array $args, WP_Block $block, string $attribute): ?string
    {
        $postId = $block->context['postId'] ?? null;

        if ($postId === null) {
            return null;
        }

        $words = str_word_count(wp_strip_all_tags((string) get_post_field('post_content', (int) $postId)));

        return sprintf('%d min read', max(1, (int) ceil($words / 200)));
    }
}
```

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"theme/reading-time"}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

Return `null` to keep the block's own value.

## Block categories

Declare a category with `#[AsBlockCategory(slug: 'acme', title: 'Acme', icon: null)]` from `Studiometa\Foehn\Attributes` on any class. The attribute is repeatable. `BlockCategoryDiscovery` adds the categories through `block_categories_all`, before the existing ones, ordered by class name (attribute order within one class). It skips a slug that is already registered: a core slug keeps its core title, and between two classes the one whose name sorts first wins. Reference: [#[AsBlockCategory]](https://studiometa.github.io/foehn-framework/api/as-block-category.md).

To rename, remove or reorder existing categories, use the filter directly:

```php
#[AsFilter('block_categories_all')]
public function blockCategories(array $categories): array
{
    return array_map(
        static fn(array $category): array => $category['slug'] === 'theme' ? [...$category, 'title' => 'Site'] : $category,
        $categories,
    );
}
```

`wp foehn make:block` writes `category: 'theme'` (change it with `--category=`). WordPress has no `theme` category, so register it with this filter or pick a core category such as `widgets`, `design`, `text` or `media`.

## Troubleshooting

- A new block is not in the inserter: run `wp foehn discovery:clear` (discovery cache), and check that `wp-content/foehn/editor.js` exists (`composer install`). The browser console shows a message when the registrar is missing.
- A sidebar field is missing: the attribute has no derived control (`array`, `object`, no `type`). Add a `control`.
- An integer or array attribute resets to its default: its value does not match the declared `type`.
