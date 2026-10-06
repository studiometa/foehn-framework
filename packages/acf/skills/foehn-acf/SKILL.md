---
name: foehn-acf
description: 'Build ACF blocks, ACF field groups and ACF options pages in a Føhn theme with studiometa/foehn-acf. Load it when code uses #[AsAcfBlock], #[AsAcfFieldGroup], #[AsAcfOptionsPage], AcfBlockInterface, AcfFieldGroupInterface, AcfOptionsPageInterface, StoutLogic\AcfBuilder\FieldsBuilder, AcfOptionsService, AcfConfig, the field fragments (ButtonLinkBuilder, ResponsiveImageBuilder, SpacingBuilder, BackgroundBuilder), or the wp foehn make:acf-block, make:field-group and make:options-page commands.'
---

# Føhn ACF

`studiometa/foehn-acf` connects Advanced Custom Fields Pro to Føhn. It adds three attributes, each found by its own discovery. This skill builds on the `foehn` skill. Read that skill for discovery, the container, Twig templates, context providers and the discovery cache.

## Mental model

- **One class, one registration.** A class with `#[AsAcfBlock]`, `#[AsAcfFieldGroup]` or `#[AsAcfOptionsPage]` in a discovery location (the theme's `app/`, namespace `App\`) is registered on `acf/init`. You do not call `acf_register_block_type()`, `acf_add_local_field_group()` or `acf_add_options_page()` yourself.
- **Fields are code.** Each class declares its fields in a `public static function fields(): FieldsBuilder` (from `stoutlogic/acf-builder`). The discovery sets the field group location for you: the block itself, the options page itself, or the `location` of `#[AsAcfFieldGroup]`.
- **An ACF block is not a block.** In Føhn vocabulary, a _block_ is a native `#[AsBlock]`. An _ACF block_ is a separate concept with a separate registration path. Its full name is `acf/<name>`.
- **ACF is optional.** Each discovery checks for the ACF function it needs. When ACF Pro is not active, nothing registers and nothing fails. For a simple meta value that the block editor must read or bind, prefer `#[AsPostMeta]` from the core package. Use ACF when the editing UI matters: repeaters, flexible content, conditional logic, media pickers.
- **Namespaces stay in `Studiometa\Foehn\`.** Attributes are in `Studiometa\Foehn\Attributes`, interfaces in `Studiometa\Foehn\Contracts`, fragments in `Studiometa\Foehn\Acf\Fragments`.

Install:

```bash
composer require studiometa/foehn-acf
```

`studiometa/foehn-acf` requires the exact same version of `studiometa/foehn`. Upgrade the two packages together, for example `composer require studiometa/foehn:<version> studiometa/foehn-acf:<version>`.

ACF Pro is a WordPress plugin. It is not a Composer dependency of this package.

## ACF block

An ACF block implements `AcfBlockInterface`: `fields()` (static), `compose()` and `render()`. The class is built by the container, so you can inject services in the constructor.

```php
<?php

declare(strict_types=1);

namespace App\Blocks;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Acf\Fragments\SpacingBuilder;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

#[AsAcfBlock(
    name: 'hero',
    title: 'Hero Banner',
    category: 'layout',
    icon: 'cover-image',
    keywords: ['banner', 'header'],
    supports: ['align' => ['wide', 'full']],
)]
final readonly class HeroBlock implements AcfBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function fields(): FieldsBuilder
    {
        return new FieldsBuilder('hero')
            ->addText('title', ['label' => 'Title', 'required' => true])
            ->addWysiwyg('content', ['label' => 'Content', 'media_upload' => false])
            ->addImage('image', ['label' => 'Image', 'return_format' => 'id'])
            ->addLink('cta', ['label' => 'Call to action', 'return_format' => 'array'])
            ->addGroup('spacing', ['label' => 'Spacing'])
                ->addFields(new SpacingBuilder())
            ->endGroup();
    }

    public function compose(array $block, array $fields): array
    {
        return [
            'title' => $fields['title'] ?? '',
            'content' => $fields['content'] ?? '',
            'image' => $fields['image'] ?? null, // Timber\Image, see "Field transformation"
            'cta' => $fields['cta'] ?? null,
        ];
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return $this->view->render('blocks/hero', $context);
    }
}
```

```twig
{# theme/templates/blocks/hero.twig #}
<section id="{{ anchor ?: block_id }}" class="hero {{ block_class }}">
  {% if image %}
    <img src="{{ image.src('large') }}" alt="{{ image.alt }}">
  {% endif %}
  <h2>{{ title }}</h2>
  {{ content }}
  {% if cta %}
    <a href="{{ cta.url }}" target="{{ cta.target ?: '_self' }}" class="btn">{{ cta.title }}</a>
  {% endif %}
  {% if is_preview and not title %}<p>Add a title.</p>{% endif %}
</section>
```

What happens at render time (`AcfBlockRenderer`):

1. Field values are read with `get_fields($block['id'])`. ACF loads the block's data before the render callback, so the values are formatted and nested like the values of a post: a group is an array of its sub-fields, a repeater is a list of rows, a true/false is a `bool`. With the field transformation on, some types become Timber objects (see below).
2. `compose($block, $fields)` builds the context. It can return an array or an `Arrayable` DTO (see the `foehn` skill); a DTO is converted with `toArray()`.
3. These keys are merged into the context, after `compose()`: `block`, `block_id`, `block_name`, `block_class`, `is_preview`, `align`, `anchor`. `block_class` contains `wp-block-acf-<name>`, `align<value>` and the editor's custom class.
4. `render($context, $isPreview)` returns the HTML.

Attribute arguments: `name`, `title`, `category` (default `'common'`), `icon` (default `block-default`), `description`, `keywords`, `mode` (`'preview'`, `'edit'` or `'auto'`), `supports`, `postTypes` (empty means all), `parent`. `supports` is merged over the defaults `['align' => false, 'mode' => true, 'multiple' => true]`. Use `'jsx' => true` for InnerBlocks. A custom `category` must exist: declare it with `#[AsBlockCategory]` from the core package. Full reference: [#[AsAcfBlock]](https://studiometa.github.io/foehn-framework/api/as-acf-block.md), [AcfBlockInterface](https://studiometa.github.io/foehn-framework/api/acf-block-interface.md), [ACF blocks guide](https://studiometa.github.io/foehn-framework/guide/acf-blocks.md).

### Field transformation

With the default `AcfConfig`, Timber's ACF transforms replace ACF's formatting for these types, also inside repeater, group and flexible content rows. Other types keep ACF's formatting:

| ACF type                          | Value in `$fields`                            |
| --------------------------------- | --------------------------------------------- |
| `image`                           | `Timber\Image`                                |
| `gallery`, `relationship`         | collection of Timber posts or images          |
| `file`                            | `Timber\Attachment`                           |
| `post_object`                     | `Timber\Post` (or a collection when multiple) |
| `taxonomy`                        | `Timber\Term` (or an array)                   |
| `user`                            | `Timber\User` (or an array)                   |
| `date_picker`, `date_time_picker` | `DateTimeImmutable`                           |

To get ACF's own formatting for every type, turn it off in a config file. An image is then what its `return_format` gives: an array by default, an ID with `return_format: id` (which `ImageData::fromAttachmentId()` needs).

```php
<?php
// theme/app/acf.config.php

declare(strict_types=1);

use Studiometa\Foehn\Config\AcfConfig;

return new AcfConfig(transformFields: false);
```

The package ships the default config, and the theme's file overrides it. The transformation applies to ACF blocks only. Field groups and options pages return what `get_field()` returns. A `get_field()` call inside a block's own code also returns ACF's formatting, not the transformed value.

## Field group

A field group attaches fields to posts, terms, page templates or other ACF locations. It implements `AcfFieldGroupInterface`, which only has `fields()`.

```php
<?php

declare(strict_types=1);

namespace App\Fields\PostType;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsAcfFieldGroup;
use Studiometa\Foehn\Contracts\AcfFieldGroupInterface;

#[AsAcfFieldGroup(
    name: 'product_fields',
    title: 'Product details',
    location: ['post_type' => 'product'],
    position: 'acf_after_title',
    hideOnScreen: ['the_content'],
)]
final class ProductFields implements AcfFieldGroupInterface
{
    public static function fields(): FieldsBuilder
    {
        return new FieldsBuilder('product_fields')
            ->addNumber('price', ['label' => 'Price'])
            ->addRepeater('specs', ['label' => 'Specifications', 'layout' => 'table'])
                ->addText('label')
                ->addText('value')
            ->endRepeater();
    }
}
```

`location` has two forms. A flat array is a list of `==` rules joined with AND: `['post_type' => 'page', 'page_template' => 'page-contact.php']`. For OR groups or other operators, use the full ACF format, an array of groups of `['param' => ..., 'operator' => ..., 'value' => ...]` rules. See [references/fields.md](references/fields.md#locations).

The other arguments (`title`, `position`, `menuOrder`, `style`, `labelPlacement`, `instructionPlacement`, `hideOnScreen`) override the values the builder produces. Read the values in a Timber model with `$post->meta('price')` or with `get_field()`. Reference: [#[AsAcfFieldGroup]](https://studiometa.github.io/foehn-framework/api/as-acf-field-group.md).

## Options page

```php
<?php

declare(strict_types=1);

namespace App\Fields\Options;

use StoutLogic\AcfBuilder\FieldsBuilder;
use Studiometa\Foehn\Attributes\AsAcfOptionsPage;
use Studiometa\Foehn\Contracts\AcfOptionsPageInterface;

#[AsAcfOptionsPage(
    pageTitle: 'Theme Settings',
    menuTitle: 'Theme',
    menuSlug: 'theme-settings',
    capability: 'manage_options',
    iconUrl: 'dashicons-admin-generic',
)]
final class ThemeSettings implements AcfOptionsPageInterface
{
    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('theme_settings');

        $builder
            ->addTab('general', ['label' => 'General'])
            ->addTextarea('footer_text', ['label' => 'Footer text'])
            ->addTab('social', ['label' => 'Social'])
            ->addUrl('instagram_url', ['label' => 'Instagram']);

        // The chain ends on a field (FieldBuilder), so return the root builder.
        return $builder;
    }
}
```

- `menuSlug` defaults to `sanitize_title($pageTitle)`. `menuTitle` defaults to `pageTitle`.
- **The values are stored under `postId`, which defaults to the menu slug, not `'options'`.** Read them with `get_field('footer_text', 'theme-settings')`. Set `postId: 'options'` if you want ACF's usual `'option'`/`'options'` storage.
- A sub-page sets `parentSlug` to the parent's menu slug. `redirect` (default `true`) sends a parent page to its first child.
- `AcfOptionsPageInterface` is optional. Without it, the page registers but has no fields from the class. Use that when a field group with `location: ['options_page' => 'theme-settings']` supplies them.
- Other arguments: `position`, `autoload` (default `true`), `updateButton`, `updatedMessage`. Reference: [#[AsAcfOptionsPage]](https://studiometa.github.io/foehn-framework/api/as-acf-options-page.md), [options pages guide](https://studiometa.github.io/foehn-framework/guide/acf-options-pages.md).

Expose options to every template with a context provider and `AcfOptionsService` (injected by the container):

```php
<?php

declare(strict_types=1);

namespace App\ContextProviders;

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Services\AcfOptionsService;
use Studiometa\Foehn\Views\TemplateContext;

#[AsContextProvider('*')]
final readonly class OptionsContextProvider implements ContextProviderInterface
{
    public function __construct(
        private AcfOptionsService $options,
    ) {}

    public function provide(TemplateContext $context): TemplateContext
    {
        return $context->with('theme_settings', $this->options->all('theme-settings'));
    }
}
```

`AcfOptionsService` methods: `get($selector, $postId)`, `all($postId)`, `has($selector, $postId)`, `getObject($selector, $postId)`. Each `$postId` defaults to `'options'`, so always pass the page's post ID. Each method returns `null`, `[]` or `false` when ACF is absent.

## Field fragments

A fragment is a `FieldsBuilder` subclass in `Studiometa\Foehn\Acf\Fragments`. Add it to a builder with `->addFields(new ...)`.

| Fragment                 | Field names                                            |
| ------------------------ | ------------------------------------------------------ |
| `ButtonLinkBuilder`      | `link` (link, array), `style`, `size`                  |
| `ResponsiveImageBuilder` | `desktop`, `mobile` (images, `return_format: id`)      |
| `SpacingBuilder`         | `top`, `bottom`                                        |
| `BackgroundBuilder`      | `type`, `color`, `image`, `overlay`, `overlay_opacity` |

The first constructor argument (`'button'`, `'image'`, `'spacing'`, `'background'`) names the fragment's own builder. It does **not** prefix the field names. Two fragments with the same field names in one builder throw `FieldNameCollisionException`. Wrap each fragment in a group so its fields have their own namespace:

```php
->addGroup('primary_cta', ['label' => 'Primary button'])
    ->addFields(new ButtonLinkBuilder(styles: ['primary' => 'Primary', 'ghost' => 'Ghost'], sizes: null))
->endGroup()
->addGroup('background', ['label' => 'Background'])
    ->addFields(new BackgroundBuilder(types: ['none' => 'None', 'image' => 'Image']))
->endGroup()
```

Conditional logic inside a fragment keeps working in a group. Constructor options, custom fragments and the related core DTOs are in [references/fields.md](references/fields.md).

## Scaffolding commands

Run them with WP-CLI. Each accepts `--force` and `--dry-run`. Files go in the theme's `app/` directory, and the namespace comes from the theme's PSR-4 map.

```bash
wp foehn make:acf-block hero --title="Hero Banner" --fields=wysiwyg,image,cta   # app/Blocks/HeroBlock.php
wp foehn make:acf-block contact-form --mode=edit --category=layout --class=ContactBlock
wp foehn make:field-group ProductFields --post-type=product                     # app/Fields/PostType/
wp foehn make:field-group FrontPageFields --page-template=front-page             # app/Fields/Page/
wp foehn make:field-group CategoryFields --taxonomy=category                    # app/Fields/Taxonomy/
wp foehn make:field-group SharedFields                                          # app/Fields/, location post_type == post
wp foehn make:options-page ThemeSettings --menu-title=Theme --icon=dashicons-admin-generic
wp foehn make:options-page FooterSettings --parent=theme-settings              # app/Fields/Options/
```

`--fields` shortcuts for `make:acf-block`: `text`, `wysiwyg`, `image`, `gallery`, `url`, `cta`, `select`, `repeater`. The generated files need edits before they work; see the gotchas.

## Gotchas

- **`render()` must return the markup.** Nothing renders a template for you. The `template` argument of `#[AsAcfBlock]` is not used. The class that `make:acf-block` generates returns `''`, which shows an empty block. Inject `ViewEngineInterface` and return `$this->view->render('blocks/<name>', $context)`. The template lives in `theme/templates/blocks/<name>.twig`.
- **The generated options page has no fields.** `make:options-page` writes a class without `implements AcfOptionsPageInterface`, and the discovery registers `fields()` only for classes that implement it. Add the interface. The generated `get()` helper reads `get_field($key, 'option')`, which does not match the default post ID (the menu slug): change it, or set `postId: 'options'`.
- **`fields()` is static.** A non-static `fields()` breaks the interface and the registration.
- **`fields()` returns the root builder.** In acf-builder, every `addText()`, `addImage()`, `addTab()` and similar call returns the new field (a `FieldBuilder`), not the root `FieldsBuilder`. `addRepeater()`, `addGroup()`, `addFlexibleContent()` and `conditional()` also return nested builders. A `return new FieldsBuilder(...)->...` chain is correct only when its last call is `endRepeater()`, `endGroup()` or `endFlexibleContent()`. A chain that ends on `->addUrl(...)` or `->conditional(...)` causes a `TypeError`. Assign the builder to `$builder`, chain on it, and `return $builder;`.
- **The interfaces are required for blocks and field groups.** A class with `#[AsAcfBlock]` that does not implement `AcfBlockInterface`, or `#[AsAcfFieldGroup]` without `AcfFieldGroupInterface`, throws an `InvalidArgumentException` during discovery.
- **`location` must not be empty** on a field group. The first rule is required.
- **Reserved context keys.** `block`, `block_id`, `block_name`, `block_class`, `is_preview`, `align` and `anchor` replace keys with the same name from `compose()`. Use other names for your data.
- **Field keys are unique.** The `FieldsBuilder` name (`new FieldsBuilder('hero')`) gives the ACF group and field keys. Two classes with the same builder name collide. The `name` argument of `#[AsAcfFieldGroup]` does not change these keys.
- **Transformed values are objects.** With `transformFields` on, an image is a `Timber\Image`, not an ID. Do not pass it to `ImageData::fromAttachmentId()` or `wp_get_attachment_image()`.
- **Fragments do not prefix field names.** `new ButtonLinkBuilder('cta')` still creates `link`, `style` and `size`. Wrap fragments in `addGroup()`. acf-builder has no `appendFields()` method: use `addFields()`.
- **Read `$fields`, not `$block['data']`.** `$block['data']` is what ACF stores in the block comment: flat and unformatted (`items: 2`, `items_0_heading`, `meta_label`, an image ID, `"1"` for true), or keyed by field key in a block template. `$fields` is what `get_fields()` returns, keyed by field name: `$fields['meta']['label']`, `$fields['items'][0]['heading']`.
- **Discovery cache.** In an environment where the discovery cache is on, a new class is not found until you run `wp foehn discovery:generate`, or `wp foehn discovery:clear`. See the `foehn` skill.
- **Nothing registers without ACF Pro.** If a block or options page is missing, first make sure ACF Pro is active (`wp plugin list`), then run `wp foehn discovery:list` to see what discovery found.
