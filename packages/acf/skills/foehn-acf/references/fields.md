# Fields in detail

Detail for [SKILL.md](../SKILL.md): field group locations, field fragments, custom fragments, and the core helpers that work on ACF values. The full `FieldsBuilder` API is in the [acf-builder documentation](https://github.com/StoutLogic/acf-builder/wiki).

## Locations

`#[AsAcfFieldGroup]` takes `location` in one of two forms.

Flat form: each `key => value` pair is a `==` rule, and all rules are joined with AND. Values are cast to strings.

```php
#[AsAcfFieldGroup(name: 'product_fields', title: 'Product', location: ['post_type' => 'product'])]
#[AsAcfFieldGroup(name: 'faq_fields', title: 'FAQ', location: ['page_template' => 'page-faq.php'])]
#[AsAcfFieldGroup(name: 'category_fields', title: 'Category', location: ['taxonomy' => 'category'])]
#[AsAcfFieldGroup(name: 'social_fields', title: 'Social', location: ['options_page' => 'theme-settings'])]
#[AsAcfFieldGroup(name: 'contact_fields', title: 'Contact', location: [
    'post_type' => 'page',
    'page_template' => 'page-contact.php',
])]
```

Full ACF form: a list of OR groups, each a list of AND rules. Use it for OR conditions or for operators other than `==`. The discovery detects this form when the first rule is an array with a `param` key.

```php
#[AsAcfFieldGroup(
    name: 'promo_fields',
    title: 'Promotion',
    location: [
        [
            ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
            ['param' => 'post_status', 'operator' => '!=', 'value' => 'draft'],
        ],
        [
            ['param' => 'post_type', 'operator' => '==', 'value' => 'page'],
        ],
    ],
)]
```

`make:field-group` writes the flat form: `--post-type=product` gives `['post_type' => 'product']`, `--page-template=front-page` gives `['page_template' => 'front-page.php']`, `--taxonomy=category` gives `['taxonomy' => 'category']`. With no option, the location is `['post_type' => 'post']`.

Blocks and options pages do not take a location. The discovery sets `block == acf/<name>` or `options_page == <menuSlug>` on the builder that `fields()` returns.

## Builder patterns

Repeater, conditional fields and tabs:

```php
public static function fields(): FieldsBuilder
{
    $builder = new FieldsBuilder('features');

    $builder
        ->addTab('content', ['label' => 'Content'])
            ->addText('title', ['label' => 'Title'])
            ->addRepeater('items', ['label' => 'Items', 'layout' => 'block', 'min' => 1])
                ->addImage('icon', ['label' => 'Icon', 'return_format' => 'id'])
                ->addText('title', ['label' => 'Title'])
                ->addTextarea('text', ['label' => 'Text'])
            ->endRepeater()
        ->addTab('settings', ['label' => 'Settings'])
            ->addSelect('columns', [
                'label' => 'Columns',
                'choices' => ['2' => '2', '3' => '3', '4' => '4'],
                'default_value' => '3',
            ])
            ->addTrueFalse('has_link', ['label' => 'Add a link'])
            ->addLink('link', ['label' => 'Link'])
                ->conditional('has_link', '==', 1);

    // The chain ends on a ConditionalBuilder, so return the root builder.
    return $builder;
}
```

Flexible content:

```php
->addFlexibleContent('sections', ['label' => 'Sections'])
    ->addLayout('text')
        ->addWysiwyg('content')
    ->addLayout('quote')
        ->addTextarea('quote')
        ->addText('author')
->endFlexibleContent()
```

Each row has an `acf_fc_layout` key with the layout name. In an ACF block, the field transformation also runs on the sub-fields of each layout.

## Built-in fragments

All four are in `Studiometa\Foehn\Acf\Fragments`. Add them with `addFields()`, inside `addGroup()` when two of them could share a field name (see [SKILL.md](../SKILL.md#field-fragments)).

### ButtonLinkBuilder

```php
new ButtonLinkBuilder(
    name: 'button',
    label: 'Button',
    styles: ['primary' => 'Primary', 'secondary' => 'Secondary', 'outline' => 'Outline'],
    sizes: ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'], // null removes the size field
    required: false, // makes the link required
);
```

Fields: `link` (link, `return_format: array`), `style` (select, default is the first style), `size` (select, default `medium`).

### ResponsiveImageBuilder

```php
new ResponsiveImageBuilder(
    name: 'image',
    label: 'Image',
    required: false, // makes the desktop image required
    desktopInstructions: 'Recommended: 1920×1080px',
    mobileInstructions: 'Leave empty to use desktop image.',
);
```

Fields: `desktop`, `mobile` (images, `return_format: id`). In a block with the field transformation on, both become `Timber\Image`. Use `mobile ?: desktop` in the template.

### SpacingBuilder

```php
new SpacingBuilder(
    name: 'spacing',
    label: 'Spacing',
    sizes: ['none' => 'None', 'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large', 'xlarge' => 'Extra Large'],
    default: 'medium',
    topLabel: 'Padding Top',
    bottomLabel: 'Padding Bottom',
);
```

Fields: `top`, `bottom` (selects).

### BackgroundBuilder

```php
new BackgroundBuilder(
    name: 'background',
    label: 'Background',
    types: ['none' => 'None', 'color' => 'Color', 'image' => 'Image'],
    default: 'none',
    defaultOpacity: 50,
);
```

Fields: `type` (button group), `color` (color picker, shown when `type` is `color`), `image` (image ID, shown when `type` is `image`), `overlay` (true/false, shown when `type` is `image`), `overlay_opacity` (range 0 to 100, shown when `overlay` is on).

## Custom fragments

A fragment is a `FieldsBuilder` subclass that adds its fields in the constructor. Put project fragments in `theme/app/Acf/Fragments/`. A class that has no discovery attribute is not registered, so a fragment is only a building block.

```php
<?php

declare(strict_types=1);

namespace App\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

final class VideoEmbedBuilder extends FieldsBuilder
{
    public function __construct(string $name = 'video', string $label = 'Video')
    {
        parent::__construct($name, ['label' => $label]);

        $this
            ->addOembed('url', ['label' => 'Video URL'])
            ->addImage('poster', ['label' => 'Poster', 'return_format' => 'id'])
            ->addTrueFalse('autoplay', ['label' => 'Autoplay']);
    }
}
```

The same fragment class can be used by a block, a field group and an options page, because each `fields()` creates a new builder and adds the fragment to it.

## Core helpers for ACF values

These are in `studiometa/foehn`, not in this package. See the `foehn` skill and the [Arrayable DTOs guide](https://studiometa.github.io/foehn-framework/guide/arrayable-dtos.md).

- `Studiometa\Foehn\Data\LinkData::fromAcf(?array $link): ?self` takes an ACF link value (`return_format: array`) and returns `null` when it has no URL. Properties: `url`, `title`, `target`.
- `Studiometa\Foehn\Data\SpacingData` has `top` and `bottom` (default `medium`). `SpacingData::fromAcf($fields, 'spacing')` reads the flat keys `spacing_top` and `spacing_bottom`. For a `SpacingBuilder` in a group, build it directly: `new SpacingData(top: $spacing['top'] ?? 'medium', bottom: $spacing['bottom'] ?? 'medium')`.
- `Studiometa\Foehn\Data\ImageData::fromAttachmentId(?int $id, string $size = 'large'): ?self` needs an attachment ID. It does not accept a `Timber\Image`, so use it only with `AcfConfig(transformFields: false)` and an image field with `return_format: id`.

`compose()` can return one of these DTOs, or your own `Arrayable` class. The renderer calls `toArray()`.

### Validation in compose()

The core trait `Studiometa\Foehn\Blocks\Concerns\ValidatesFields` adds protected methods: `validateRequired($fields, $required)` (throws `InvalidArgumentException`), `validateType($value, $type)`, `sanitizeField($value, $type)` and `validateFields($fields, $schema)`. `sanitizeField()` and `validateFields()` accept the types `string`, `int`, `float`, `bool`, `array`, `html`, `email` and `url`. `validateType()` returns a `bool` and accepts `string`, `int`, `float`, `bool`, `array`, `object`, `numeric`, `scalar`, `callable`, `iterable` or a class name. In a `validateFields()` schema, each entry takes `type`, and optionally `required` (throws when empty) and `default`.

```php
use Studiometa\Foehn\Blocks\Concerns\ValidatesFields;
use Studiometa\Foehn\Contracts\AcfBlockInterface;

final readonly class QuoteBlock implements AcfBlockInterface
{
    use ValidatesFields;

    public function compose(array $block, array $fields): array
    {
        return $this->validateFields($fields, [
            'quote' => ['type' => 'html', 'default' => ''],
            'author' => ['type' => 'string', 'default' => ''],
        ]);
    }

    // fields() and render() ...
}
```

A required field that throws in `compose()` breaks the block in the editor too. In preview, prefer defaults and a placeholder from `render()` when `$isPreview` is `true`.
