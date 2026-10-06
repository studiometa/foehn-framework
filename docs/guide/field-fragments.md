# ACF Field Fragments

::: tip
Field fragments ship in `studiometa/foehn-acf`, the optional ACF package. See [ACF Blocks](/guide/acf-blocks#requirements).
:::

Field Fragments are reusable ACF field groups that can be shared across multiple blocks. By extending `FieldsBuilder`, you create self-contained field definitions that you add to any block's fields with acf-builder's `addFields()`.

## Why Use Field Fragments?

When building ACF blocks, you often repeat the same field patterns:

- Button/link with text, URL, target, and style
- Responsive images with mobile/desktop variants
- Spacing controls (margin, padding)
- Background settings (color, image, overlay)

Instead of duplicating these fields in every block, create a **Field Fragment** once and reuse it everywhere.

## Built-in Fragments

Føhn provides common fragments out of the box:

| Fragment                 | Description                          | Field names                                            |
| ------------------------ | ------------------------------------ | ------------------------------------------------------ |
| `ButtonLinkBuilder`      | Link with style and size options     | `link`, `style`, `size`                                |
| `ResponsiveImageBuilder` | Desktop/mobile image variants        | `desktop`, `mobile`                                    |
| `SpacingBuilder`         | Padding top/bottom controls          | `top`, `bottom`                                        |
| `BackgroundBuilder`      | Color, image, and overlay background | `type`, `color`, `image`, `overlay`, `overlay_opacity` |

```php
use Studiometa\Foehn\Acf\Fragments\ButtonLinkBuilder;
use Studiometa\Foehn\Acf\Fragments\ResponsiveImageBuilder;
use Studiometa\Foehn\Acf\Fragments\SpacingBuilder;
use Studiometa\Foehn\Acf\Fragments\BackgroundBuilder;
```

## Creating Custom Fragments

A Field Fragment extends `FieldsBuilder` and configures its fields in the constructor:

```php
<?php
// app/Acf/Fragments/VideoEmbedBuilder.php

namespace App\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

final class VideoEmbedBuilder extends FieldsBuilder
{
    public function __construct(string $name = 'video', string $label = 'Video')
    {
        parent::__construct($name, ['label' => $label]);

        $this
            ->addOembed('url', [
                'label' => 'Video URL',
                'instructions' => 'YouTube or Vimeo URL',
            ])
            ->addImage('poster', [
                'label' => 'Poster Image',
                'instructions' => 'Custom thumbnail (optional)',
                'return_format' => 'id',
            ])
            ->addTrueFalse('autoplay', [
                'label' => 'Autoplay',
                'default_value' => false,
            ]);
    }
}
```

## Using Fragments in Blocks

Use acf-builder's `addFields()` to add a fragment to your block's field configuration. `addFields()` copies the fragment's fields into the builder, with the field names that the fragment gives them.

The first constructor argument of a fragment (`'cta'` in `new ButtonLinkBuilder('cta')`) names the fragment's own builder. It does **not** prefix the field names: every `ButtonLinkBuilder` creates `link`, `style` and `size`. Wrap each fragment in `addGroup()` so that its fields have their own namespace:

```php
<?php
// app/Blocks/Hero/HeroBlock.php

namespace App\Blocks\Hero;

use Studiometa\Foehn\Acf\Fragments\ButtonLinkBuilder;
use Studiometa\Foehn\Acf\Fragments\BackgroundBuilder;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfBlock(
    name: 'hero',
    title: 'Hero Banner',
    category: 'layout',
)]
final readonly class HeroBlock implements AcfBlockInterface
{
    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('hero');

        $builder
            ->addWysiwyg('content', ['label' => 'Content'])

            // Add the built-in fragments, each one in its own group
            ->addGroup('cta', ['label' => 'Call to Action'])
                ->addFields(new ButtonLinkBuilder())
            ->endGroup()
            ->addGroup('background', ['label' => 'Background'])
                ->addFields(new BackgroundBuilder())
            ->endGroup();

        return $builder;
    }

    // ...
}
```

This produces:

- `content` (wysiwyg)
- `cta` (group)
  - `link` (link)
  - `style` (select)
  - `size` (select)
- `background` (group)
  - `type` (button_group)
  - `color` (color_picker)
  - `image` (image)
  - `overlay` (true_false)
  - `overlay_opacity` (range)

The block values are nested in the same way: `$fields['cta']['link']`, `$fields['background']['type']`. The conditional logic inside a fragment keeps working in a group, because acf-builder resolves it against the field keys of the group.

Without the groups, two fragments that share a field name throw a `FieldNameCollisionException` when you add the second one. For example, two `ButtonLinkBuilder` fragments in the same builder both create `link`.

## Customizing Built-in Fragments

All built-in fragments accept constructor parameters for customization. The examples below continue a `$builder` chain like the one in `HeroBlock`.

### ButtonLinkBuilder

```php
use Studiometa\Foehn\Acf\Fragments\ButtonLinkBuilder;

// Default usage
->addGroup('button')
    ->addFields(new ButtonLinkBuilder())
->endGroup()

// Custom styles, no size field
->addGroup('cta', ['label' => 'Call to Action'])
    ->addFields(new ButtonLinkBuilder(
        name: 'cta',
        label: 'Call to Action',
        styles: ['primary' => 'Primary', 'ghost' => 'Ghost'],
        sizes: null, // Disable size field
        required: true,
    ))
->endGroup()
```

### ResponsiveImageBuilder

```php
use Studiometa\Foehn\Acf\Fragments\ResponsiveImageBuilder;

// Default usage
->addGroup('image')
    ->addFields(new ResponsiveImageBuilder())
->endGroup()

// With custom instructions
->addGroup('hero_image', ['label' => 'Hero Image'])
    ->addFields(new ResponsiveImageBuilder(
        name: 'hero_image',
        label: 'Hero Image',
        required: true,
        desktopInstructions: 'Recommended: 2560×1440px',
        mobileInstructions: 'Recommended: 750×1334px',
    ))
->endGroup()
```

### SpacingBuilder

```php
use Studiometa\Foehn\Acf\Fragments\SpacingBuilder;

// Default usage
->addGroup('spacing')
    ->addFields(new SpacingBuilder())
->endGroup()

// Custom sizes and labels
->addGroup('margin', ['label' => 'Margins'])
    ->addFields(new SpacingBuilder(
        name: 'margin',
        label: 'Margins',
        sizes: ['0' => 'None', '1' => 'Small', '2' => 'Medium', '3' => 'Large'],
        default: '1',
        topLabel: 'Margin Top',
        bottomLabel: 'Margin Bottom',
    ))
->endGroup()
```

### BackgroundBuilder

```php
use Studiometa\Foehn\Acf\Fragments\BackgroundBuilder;

// Default usage
->addGroup('background')
    ->addFields(new BackgroundBuilder())
->endGroup()

// Image-only background (no color option)
->addGroup('bg', ['label' => 'Background'])
    ->addFields(new BackgroundBuilder(
        name: 'bg',
        label: 'Background',
        types: ['none' => 'None', 'image' => 'Image'],
        default: 'none',
        defaultOpacity: 70,
    ))
->endGroup()
```

## Organizing Fragments with Tabs

Fragments work well inside tabs for better editor UX:

```php
public static function fields(): FieldsBuilder
{
    $builder = new FieldsBuilder('hero');

    $builder
        ->addTab('Content')
            ->addText('title')
            ->addWysiwyg('content')
            ->addGroup('cta', ['label' => 'Call to Action'])
                ->addFields(new ButtonLinkBuilder())
            ->endGroup()

        ->addTab('Media')
            ->addGroup('hero_image', ['label' => 'Hero Image'])
                ->addFields(new ResponsiveImageBuilder(required: true))
            ->endGroup()

        ->addTab('Settings')
            ->addGroup('spacing', ['label' => 'Spacing'])
                ->addFields(new SpacingBuilder())
            ->endGroup()
            ->addGroup('background', ['label' => 'Background'])
                ->addFields(new BackgroundBuilder())
            ->endGroup();

    return $builder;
}
```

`addTab()` returns the new tab field, not `$builder`. Keep the chain on a `$builder` variable and return the variable.

## File Structure

Organize fragments in a dedicated directory:

```
app/
├── Acf/
│   └── Fragments/
│       ├── BackgroundBuilder.php
│       ├── ButtonLinkBuilder.php
│       ├── ResponsiveImageBuilder.php
│       └── SpacingBuilder.php
│
├── Blocks/
│   ├── Hero/
│   │   └── HeroBlock.php
│   └── Features/
│       └── FeaturesBlock.php
```

## Best Practices

### 1. Use Constructor Parameters for Customization

Allow fragments to be configured when instantiated:

```php
final class ButtonLinkBuilder extends FieldsBuilder
{
    public function __construct(
        string $name = 'button',
        string $label = 'Button',
        array $styles = ['primary', 'secondary'],
        bool $required = false,
    ) {
        parent::__construct($name, ['label' => $label]);

        $this
            ->addLink('link', [
                'label' => 'Link',
                'required' => $required,
            ])
            ->addSelect('style', [
                'label' => 'Style',
                'choices' => array_combine($styles, array_map('ucfirst', $styles)),
            ]);
    }
}
```

### 2. Keep Fragments Focused

Each fragment should handle one concern. Prefer multiple small fragments over one large one:

```php
// ✅ Good: focused fragments
->addGroup('cta')
    ->addFields(new ButtonLinkBuilder())
->endGroup()
->addGroup('spacing')
    ->addFields(new SpacingBuilder())
->endGroup()

// ❌ Avoid: kitchen-sink fragment
->addFields(new ButtonWithSpacingAndBackgroundBuilder())
```

### 3. Document Field Names

Fragments do not prefix field names, so document the names that a fragment creates:

```php
/**
 * Creates the following fields:
 * - link (link)
 * - style (select)
 * - size (select) - optional
 */
final class ButtonLinkBuilder extends FieldsBuilder
```

### 4. Use Static Factory Methods for Presets

For common configurations, add static factory methods to your own fragment:

```php
final class ButtonLinkBuilder extends FieldsBuilder
{
    // Constructor from practice 1...

    public static function primary(string $name = 'cta'): self
    {
        return new self($name, 'Call to Action', ['primary', 'secondary']);
    }
}

// Usage
->addGroup('cta')
    ->addFields(ButtonLinkBuilder::primary())
->endGroup()
```

## See Also

- [ACF Blocks](./acf-blocks) — Creating ACF blocks with `#[AsAcfBlock]`
- [acf-builder documentation](https://github.com/StoutLogic/acf-builder) — Full FieldsBuilder API
