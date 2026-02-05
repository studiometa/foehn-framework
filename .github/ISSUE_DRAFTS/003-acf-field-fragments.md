# Document and standardize ACF Field Fragments pattern

## Problem

All analyzed WordPress projects have a `ACF/Fields/` directory containing reusable field definitions:

```
app/ACF/Fields/
├── AbstractField.php
├── FieldInterface.php
├── ButtonLink.php
├── Image.php
├── Wysiwyg.php
├── Repeater.php
├── Video.php
├── Text.php
└── Section.php
```

These "field fragments" are used to ensure consistency across blocks and field groups:

```php
// Current pattern (static method)
class ButtonLink extends AbstractField {
    public static function get_field($name = 'cta', $label = 'Call to action') {
        $cta = new FieldsBuilder($name);
        $cta->addLink($name, [
            'label' => $label,
            'return_format' => 'array',
        ]);
        return $cta;
    }
}

// Usage
$builder->addFields(ButtonLink::get_field('hero_cta', 'Hero Button'));
```

This is NOT something Foehn should auto-discover, but we should:

1. Document the recommended pattern
2. Provide a cleaner approach using class inheritance
3. Include in CLI scaffolding

## Proposed pattern: Extend FieldsBuilder

Instead of static methods, extend `FieldsBuilder` directly. This provides:

- Consistency with ACF Builder API
- Ability to chain additional fields
- Better IDE auto-completion
- Easy extensibility via inheritance

### Base pattern

```php
// app/Fields/Fragments/ButtonLinkBuilder.php
namespace App\Fields\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

class ButtonLinkBuilder extends FieldsBuilder
{
    public function __construct(
        string $name = 'cta',
        string $label = 'Call to action',
        bool $required = false,
    ) {
        parent::__construct($name);

        $this->addLink($name, [
            'label' => $label,
            'required' => $required,
            'return_format' => 'array',
        ]);
    }
}
```

### Usage in blocks/field groups

```php
use App\Fields\Fragments\ButtonLinkBuilder;
use App\Fields\Fragments\ResponsiveImageBuilder;

#[AsAcfBlock(name: 'hero', title: 'Hero Banner')]
final readonly class HeroBlock implements AcfBlockInterface
{
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('hero'))
            ->addTab('Content')
            ->addWysiwyg('content')
            ->addFields(new ButtonLinkBuilder('cta', 'Primary Button', required: true))
            ->addFields(new ButtonLinkBuilder('cta_secondary', 'Secondary Button'))

            ->addTab('Media')
            ->addFields(new ResponsiveImageBuilder('background', 'Background'));
    }
}
```

### Chainable extensions

The real power: you can continue chaining after the constructor!

```php
// Add extra fields to a fragment
$builder->addFields(
    (new ButtonLinkBuilder('cta'))
        ->addTrueFalse('open_new_tab', [
            'label' => 'Open in new tab',
            'default_value' => false,
        ])
);
```

### Creating variants via inheritance

```php
// app/Fields/Fragments/ButtonLinkWithIconBuilder.php
namespace App\Fields\Fragments;

class ButtonLinkWithIconBuilder extends ButtonLinkBuilder
{
    public function __construct(
        string $name = 'cta',
        string $label = 'Call to action',
        bool $required = false,
    ) {
        parent::__construct($name, $label, $required);

        $this->addImage($name . '_icon', [
            'label' => 'Icon',
            'return_format' => 'id',
            'preview_size' => 'thumbnail',
        ]);
    }
}
```

## Common fragment examples

### ButtonLinkBuilder

```php
namespace App\Fields\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

class ButtonLinkBuilder extends FieldsBuilder
{
    public function __construct(
        string $name = 'cta',
        string $label = 'Call to action',
        bool $required = false,
    ) {
        parent::__construct($name);

        $this->addLink($name, [
            'label' => $label,
            'required' => $required,
            'return_format' => 'array',
        ]);
    }
}
```

### ResponsiveImageBuilder

```php
namespace App\Fields\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

class ResponsiveImageBuilder extends FieldsBuilder
{
    public function __construct(
        string $name = 'image',
        string $label = 'Image',
        ?string $instructions = null,
        bool $required = false,
        bool $withMobile = true,
    ) {
        parent::__construct($name);

        $this->addImage($name, [
            'label' => $label,
            'instructions' => $instructions ?? 'Recommended: JPG or WebP, max 500KB',
            'required' => $required,
            'return_format' => 'id',
            'preview_size' => 'medium',
            'mime_types' => 'jpg,jpeg,png,webp',
        ]);

        if ($withMobile) {
            $this->addImage($name . '_mobile', [
                'label' => $label . ' (Mobile)',
                'instructions' => 'Optional mobile-specific image',
                'return_format' => 'id',
                'preview_size' => 'thumbnail',
            ]);
        }
    }
}
```

### VideoBuilder

```php
namespace App\Fields\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

class VideoBuilder extends FieldsBuilder
{
    public function __construct(
        string $name = 'video',
        string $label = 'Video',
        bool $allowUpload = true,
        bool $allowEmbed = true,
    ) {
        parent::__construct($name);

        if ($allowUpload && $allowEmbed) {
            $this->addRadio($name . '_type', [
                'label' => 'Video type',
                'choices' => [
                    'upload' => 'Upload',
                    'embed' => 'YouTube/Vimeo URL',
                ],
                'default_value' => 'embed',
                'layout' => 'horizontal',
            ]);
        }

        if ($allowUpload) {
            $this->addFile($name . '_file', [
                'label' => $label . ' (File)',
                'return_format' => 'url',
                'mime_types' => 'mp4,webm',
            ])
            ->conditional($name . '_type', '==', 'upload');
        }

        if ($allowEmbed) {
            $this->addUrl($name . '_url', [
                'label' => $label . ' (URL)',
                'instructions' => 'YouTube or Vimeo URL',
            ])
            ->conditional($name . '_type', '==', 'embed');
        }

        $this->addImage($name . '_poster', [
            'label' => 'Poster image',
            'instructions' => 'Thumbnail shown before video plays',
            'return_format' => 'id',
        ]);
    }
}
```

### SectionHeaderBuilder

```php
namespace App\Fields\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

class SectionHeaderBuilder extends FieldsBuilder
{
    public function __construct(
        string $name = 'section',
        string $label = 'Section Header',
        bool $withSubtitle = true,
    ) {
        parent::__construct($name);

        $this->addText($name . '_title', [
            'label' => $label . ' - Title',
        ]);

        if ($withSubtitle) {
            $this->addTextarea($name . '_subtitle', [
                'label' => $label . ' - Subtitle',
                'rows' => 2,
            ]);
        }
    }
}
```

## Directory structure convention

```
app/
├── Fields/
│   ├── Fragments/              # Reusable field builders
│   │   ├── ButtonLinkBuilder.php
│   │   ├── ResponsiveImageBuilder.php
│   │   ├── VideoBuilder.php
│   │   ├── SectionHeaderBuilder.php
│   │   └── IconTextBuilder.php
│   │
│   ├── PostType/               # Field groups for CPTs
│   │   └── ProductFields.php
│   │
│   ├── Page/                   # Field groups for pages
│   │   └── FrontPageFields.php
│   │
│   └── Options/                # Options pages
│       └── ThemeSettings.php
```

## Naming convention

- Fragment classes: `{Name}Builder` (e.g., `ButtonLinkBuilder`)
- Extends `FieldsBuilder`
- Use constructor with named parameters for clarity

## CLI command

```bash
php foehn make:field-fragment ButtonLink
php foehn make:field-fragment ResponsiveImage --with-mobile

# Output: app/Fields/Fragments/ButtonLinkBuilder.php
```

## Documentation sections needed

1. **Why use field fragments?**
   - Consistency across fields
   - DRY principle
   - Easier maintenance (change once, apply everywhere)
   - Standard configurations (return formats, preview sizes)

2. **Naming conventions**
   - Use `{Name}Builder` class naming
   - Extend `FieldsBuilder` for chainability
   - Use constructor with named parameters

3. **Best practices**
   - Keep fragments focused (single responsibility)
   - Use sensible defaults
   - Document expected return formats
   - Consider Timber/Twig usage when choosing return formats
   - Create variants via inheritance, not duplication

## Comparison with old pattern

| Aspect      | Old (`static get_field()`) | New (`extends FieldsBuilder`) |
| ----------- | -------------------------- | ----------------------------- |
| Syntax      | `ButtonLink::get_field()`  | `new ButtonLinkBuilder()`     |
| Chainable   | ❌ No                      | ✅ Yes                        |
| Extensible  | Copy-paste                 | Inheritance                   |
| IDE support | Limited                    | Full (inherited methods)      |
| Consistency | Custom pattern             | Same as ACF Builder           |

## Tasks

- [ ] Document field fragment pattern with `extends FieldsBuilder`
- [ ] Add common fragment examples to docs
- [ ] Add `make:field-fragment` CLI command
- [ ] Update theme_example.md with new pattern
- [ ] Consider providing base fragments in Foehn package (optional)

## Labels

`documentation`, `acf`, `priority-medium`
