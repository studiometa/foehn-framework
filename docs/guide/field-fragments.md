# ACF Field Fragments

Field Fragments are reusable ACF field groups that can be shared across multiple blocks. By extending `FieldsBuilder`, you create self-contained field definitions that can be appended to any block's fields.

## Why Use Field Fragments?

When building ACF blocks, you often repeat the same field patterns:

- Button/link with text, URL, target, and style
- Responsive images with mobile/desktop variants
- Spacing controls (margin, padding)
- Background settings (color, image, overlay)

Instead of duplicating these fields in every block, create a **Field Fragment** once and reuse it everywhere.

## Built-in Fragments

Føhn provides common fragments out of the box:

| Fragment                  | Description                                |
| ------------------------- | ------------------------------------------ |
| `ButtonLinkBuilder`       | Link with style and size options           |
| `ResponsiveImageBuilder`  | Desktop/mobile image variants              |
| `SpacingBuilder`          | Padding top/bottom controls                |
| `BackgroundBuilder`       | Color, image, and overlay background       |

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

Use `appendFields()` to add a fragment to your block's field configuration:

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

            // Append the built-in fragments
            ->appendFields(new ButtonLinkBuilder('cta', 'Call to Action'))
            ->appendFields(new BackgroundBuilder());

        return $builder;
    }

    // ...
}
```

The fragment's fields are added inline, producing:
- `content` (wysiwyg)
- `cta_link` (link)
- `cta_style` (select)
- `cta_size` (select)
- `background_type` (button_group)
- `background_color` (color_picker)
- `background_image` (image)
- `background_overlay` (true_false)
- `background_overlay_opacity` (range)

## Customizing Built-in Fragments

All built-in fragments accept constructor parameters for customization:

### ButtonLinkBuilder

```php
use Studiometa\Foehn\Acf\Fragments\ButtonLinkBuilder;

// Default usage
->appendFields(new ButtonLinkBuilder())

// Custom styles, no size field
->appendFields(new ButtonLinkBuilder(
    name: 'cta',
    label: 'Call to Action',
    styles: ['primary' => 'Primary', 'ghost' => 'Ghost'],
    sizes: null, // Disable size field
    required: true,
))
```

### ResponsiveImageBuilder

```php
use Studiometa\Foehn\Acf\Fragments\ResponsiveImageBuilder;

// Default usage
->appendFields(new ResponsiveImageBuilder())

// With custom instructions
->appendFields(new ResponsiveImageBuilder(
    name: 'hero_image',
    label: 'Hero Image',
    required: true,
    desktopInstructions: 'Recommended: 2560×1440px',
    mobileInstructions: 'Recommended: 750×1334px',
))
```

### SpacingBuilder

```php
use Studiometa\Foehn\Acf\Fragments\SpacingBuilder;

// Default usage
->appendFields(new SpacingBuilder())

// Custom sizes and labels
->appendFields(new SpacingBuilder(
    name: 'margin',
    label: 'Margins',
    sizes: ['0' => 'None', '1' => 'Small', '2' => 'Medium', '3' => 'Large'],
    default: '1',
    topLabel: 'Margin Top',
    bottomLabel: 'Margin Bottom',
))
```

### BackgroundBuilder

```php
use Studiometa\Foehn\Acf\Fragments\BackgroundBuilder;

// Default usage
->appendFields(new BackgroundBuilder())

// Image-only background (no color option)
->appendFields(new BackgroundBuilder(
    name: 'bg',
    label: 'Background',
    types: ['none' => 'None', 'image' => 'Image'],
    default: 'none',
    defaultOpacity: 70,
))
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
            ->appendFields(new ButtonLinkBuilder('cta', 'Call to Action'))

        ->addTab('Media')
            ->appendFields(new ResponsiveImageBuilder('hero_image', 'Hero Image', true))

        ->addTab('Settings')
            ->appendFields(new SpacingBuilder())
            ->appendFields(new BackgroundBuilder());

    return $builder;
}
```

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
->appendFields(new ButtonLinkBuilder('cta'))
->appendFields(new SpacingBuilder())

// ❌ Avoid: kitchen-sink fragment
->appendFields(new ButtonWithSpacingAndBackgroundBuilder())
```

### 3. Document Field Names

Since fragments prefix field names, document what fields are created:

```php
/**
 * Creates the following fields:
 * - {$name}_link (link)
 * - {$name}_style (select)
 * - {$name}_size (select)
 */
final class ButtonLinkBuilder extends FieldsBuilder
```

### 4. Use Static Factory Methods for Presets

For common configurations, add static factory methods:

```php
final class ButtonLinkBuilder extends FieldsBuilder
{
    // Default constructor...

    public static function primary(string $name = 'cta'): self
    {
        return new self($name, 'Call to Action', ['primary', 'secondary']);
    }

    public static function simple(string $name = 'link'): self
    {
        $builder = new self($name, 'Link', ['primary']);
        // Remove size field for simpler variant
        return $builder;
    }
}

// Usage
->appendFields(ButtonLinkBuilder::primary())
```

## See Also

- [ACF Blocks](./acf-blocks) — Creating ACF blocks with `#[AsAcfBlock]`
- [acf-builder documentation](https://github.com/StoutLogic/acf-builder) — Full FieldsBuilder API
