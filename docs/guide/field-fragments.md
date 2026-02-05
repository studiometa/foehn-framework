# ACF Field Fragments

Field Fragments are reusable ACF field groups that can be shared across multiple blocks. By extending `FieldsBuilder`, you create self-contained field definitions that can be appended to any block's fields.

## Why Use Field Fragments?

When building ACF blocks, you often repeat the same field patterns:

- Button/link with text, URL, target, and style
- Responsive images with mobile/desktop variants
- Spacing controls (margin, padding)
- Background settings (color, image, overlay)

Instead of duplicating these fields in every block, create a **Field Fragment** once and reuse it everywhere.

## Creating a Field Fragment

A Field Fragment extends `FieldsBuilder` and configures its fields in the constructor:

```php
<?php
// app/Acf/Fragments/ButtonLinkBuilder.php

namespace App\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

final class ButtonLinkBuilder extends FieldsBuilder
{
    public function __construct(string $name = 'button', string $label = 'Button')
    {
        parent::__construct($name, ['label' => $label]);

        $this
            ->addLink('link', [
                'label' => 'Link',
                'return_format' => 'array',
            ])
            ->addSelect('style', [
                'label' => 'Style',
                'choices' => [
                    'primary' => 'Primary',
                    'secondary' => 'Secondary',
                    'outline' => 'Outline',
                    'ghost' => 'Ghost',
                ],
                'default_value' => 'primary',
            ])
            ->addSelect('size', [
                'label' => 'Size',
                'choices' => [
                    'small' => 'Small',
                    'medium' => 'Medium',
                    'large' => 'Large',
                ],
                'default_value' => 'medium',
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

use App\Acf\Fragments\ButtonLinkBuilder;
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
            ->addImage('background', ['label' => 'Background Image'])

            // Append the button fragment
            ->appendFields(new ButtonLinkBuilder('cta', 'Call to Action'));

        return $builder;
    }

    // ...
}
```

The fragment's fields are added inline, producing:
- `content` (wysiwyg)
- `background` (image)
- `cta_link` (link)
- `cta_style` (select)
- `cta_size` (select)

## Common Fragment Examples

### ResponsiveImageBuilder

For images that need different sources on mobile and desktop:

```php
<?php
// app/Acf/Fragments/ResponsiveImageBuilder.php

namespace App\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

final class ResponsiveImageBuilder extends FieldsBuilder
{
    public function __construct(
        string $name = 'image',
        string $label = 'Image',
        bool $required = false,
    ) {
        parent::__construct($name, ['label' => $label]);

        $this
            ->addImage('desktop', [
                'label' => 'Desktop',
                'instructions' => 'Recommended: 1920×1080px',
                'required' => $required,
                'return_format' => 'id',
                'preview_size' => 'medium',
            ])
            ->addImage('mobile', [
                'label' => 'Mobile',
                'instructions' => 'Recommended: 768×1024px. Leave empty to use desktop image.',
                'return_format' => 'id',
                'preview_size' => 'medium',
            ]);
    }
}
```

### SpacingBuilder

For consistent spacing controls:

```php
<?php
// app/Acf/Fragments/SpacingBuilder.php

namespace App\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

final class SpacingBuilder extends FieldsBuilder
{
    private const SIZES = [
        'none' => 'None',
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
        'xlarge' => 'Extra Large',
    ];

    public function __construct(string $name = 'spacing')
    {
        parent::__construct($name, ['label' => 'Spacing']);

        $this
            ->addSelect('padding_top', [
                'label' => 'Padding Top',
                'choices' => self::SIZES,
                'default_value' => 'medium',
            ])
            ->addSelect('padding_bottom', [
                'label' => 'Padding Bottom',
                'choices' => self::SIZES,
                'default_value' => 'medium',
            ]);
    }
}
```

### BackgroundBuilder

For background settings with color, image, and overlay:

```php
<?php
// app/Acf/Fragments/BackgroundBuilder.php

namespace App\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

final class BackgroundBuilder extends FieldsBuilder
{
    public function __construct(string $name = 'background')
    {
        parent::__construct($name, ['label' => 'Background']);

        $this
            ->addButtonGroup('type', [
                'label' => 'Background Type',
                'choices' => [
                    'none' => 'None',
                    'color' => 'Color',
                    'image' => 'Image',
                ],
                'default_value' => 'none',
            ])
            ->addColorPicker('color', [
                'label' => 'Background Color',
            ])
                ->conditional('type', '==', 'color')
            ->addImage('image', [
                'label' => 'Background Image',
                'return_format' => 'id',
            ])
                ->conditional('type', '==', 'image')
            ->addTrueFalse('overlay', [
                'label' => 'Add Overlay',
                'default_value' => true,
            ])
                ->conditional('type', '==', 'image')
            ->addRange('overlay_opacity', [
                'label' => 'Overlay Opacity',
                'min' => 0,
                'max' => 100,
                'step' => 5,
                'default_value' => 50,
            ])
                ->conditional('type', '==', 'image')
                ->and('overlay', '==', 1);
    }
}
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
