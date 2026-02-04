# ACF Blocks

Foehn provides `#[AsAcfBlock]` for creating ACF blocks with type-safe fields using `stoutlogic/acf-builder`.

## Requirements

- [ACF Pro](https://www.advancedcustomfields.com/pro/) installed and active
- `stoutlogic/acf-builder` package

```bash
composer require stoutlogic/acf-builder
```

## Basic ACF Block

```php
<?php
// app/Blocks/Hero/HeroBlock.php

namespace App\Blocks\Hero;

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfBlock(
    name: 'hero',
    title: 'Hero Banner',
    category: 'layout',
    icon: 'cover-image',
)]
final readonly class HeroBlock implements AcfBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('hero'))
            ->addText('title', ['label' => 'Title'])
            ->addWysiwyg('content', ['label' => 'Content'])
            ->addImage('background', ['label' => 'Background Image']);
    }

    public function compose(array $block, array $fields): array
    {
        return [
            'title' => $fields['title'] ?? '',
            'content' => $fields['content'] ?? '',
            'background' => $fields['background'] ?? null,
            'block_id' => $block['id'] ?? '',
        ];
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return $this->view->render('blocks/hero', $context);
    }
}
```

## Template

```twig
{# views/blocks/hero.twig #}
<section class="hero" id="{{ block_id }}">
    {% if background %}
        <img
            class="hero__background"
            src="{{ background.src('full') }}"
            alt="{{ background.alt }}"
        >
    {% endif %}

    <div class="hero__content">
        {% if title %}
            <h1 class="hero__title">{{ title }}</h1>
        {% endif %}

        {% if content %}
            <div class="hero__text">{{ content }}</div>
        {% endif %}
    </div>
</section>
```

## Automatic Field Transformation

By default, Foehn automatically transforms ACF field values into Timber objects. This means you don't need to manually convert image IDs to `Timber\Image`, post IDs to `Timber\Post`, etc.

### Enabled by Default

Field transformation is enabled by default. To disable it:

```php
Kernel::boot(__DIR__, [
    'acf_transform_fields' => false,
]);
```

### Transformed Field Types

| ACF Field Type     | Timber Type                                |
| ------------------ | ------------------------------------------ |
| `image`            | `Timber\Image`                             |
| `gallery`          | `Timber\PostQuery` (array of Images)       |
| `file`             | `Timber\Attachment`                        |
| `post_object`      | `Timber\Post` (or `PostQuery` if multiple) |
| `relationship`     | `Timber\PostQuery`                         |
| `taxonomy`         | `Timber\Term` (or array of Terms)          |
| `user`             | `Timber\User` (or array of Users)          |
| `date_picker`      | `DateTimeImmutable`                        |
| `date_time_picker` | `DateTimeImmutable`                        |

### Nested Fields Support

Transformation works recursively for nested field types:

- **Repeater**: Each row's sub-fields are transformed
- **Flexible Content**: Each layout's sub-fields are transformed
- **Group**: All sub-fields are transformed

### Example: Before and After

**Without transformation** (manual conversion required):

```php
public function compose(array $block, array $fields): array
{
    $context = $fields;

    // Manual transformation for every image field
    if (!empty($fields['image'])) {
        $context['image'] = Timber::get_image($fields['image']);
    }

    // Manual transformation for relationships
    if (!empty($fields['related_posts'])) {
        $context['related_posts'] = Timber::get_posts($fields['related_posts']);
    }

    return $context;
}
```

**With transformation** (automatic):

```php
public function compose(array $block, array $fields): array
{
    // $fields['image'] is already a Timber\Image
    // $fields['related_posts'] is already a Timber\PostQuery
    return $fields;
}
```

### In Twig Templates

With automatic transformation, you can use Timber's full API directly:

```twig
{# Image fields #}
<img
    src="{{ image.src('large') }}"
    alt="{{ image.alt }}"
    srcset="{{ image.srcset }}"
    width="{{ image.width }}"
    height="{{ image.height }}"
>

{# Gallery fields #}
{% for item in gallery %}
    <img src="{{ item.src('thumbnail') }}" alt="{{ item.alt }}">
{% endfor %}

{# Relationship fields #}
{% for post in related_posts %}
    <a href="{{ post.link }}">{{ post.title }}</a>
{% endfor %}

{# Date fields #}
<time datetime="{{ date|date('Y-m-d') }}">
    {{ date|date('F j, Y') }}
</time>
```

## Full Configuration

```php
#[AsAcfBlock(
    name: 'testimonial',
    title: 'Testimonial',
    category: 'common',
    icon: 'format-quote',
    description: 'Display a customer testimonial',
    keywords: ['quote', 'review', 'customer'],
    mode: 'preview',
    supports: [
        'align' => true,
        'mode' => true,
        'jsx' => true,
    ],
    postTypes: ['page', 'post'],
)]
final readonly class TestimonialBlock implements AcfBlockInterface {}
```

## Complex Fields Example

```php
<?php

namespace App\Blocks\Features;

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfBlock(
    name: 'features',
    title: 'Features Grid',
    category: 'layout',
    icon: 'grid-view',
)]
final readonly class FeaturesBlock implements AcfBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('features');

        $builder
            ->addText('title', ['label' => 'Section Title'])
            ->addTextarea('description', ['label' => 'Section Description'])
            ->addRepeater('features', ['label' => 'Features', 'layout' => 'block'])
                ->addImage('icon', ['label' => 'Icon'])
                ->addText('title', ['label' => 'Feature Title'])
                ->addTextarea('description', ['label' => 'Feature Description'])
                ->addLink('link', ['label' => 'Link'])
            ->endRepeater()
            ->addSelect('columns', [
                'label' => 'Columns',
                'choices' => [
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                ],
                'default_value' => '3',
            ]);

        return $builder;
    }

    public function compose(array $block, array $fields): array
    {
        return [
            'title' => $fields['title'] ?? '',
            'description' => $fields['description'] ?? '',
            'features' => $fields['features'] ?? [],
            'columns' => $fields['columns'] ?? '3',
        ];
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return $this->view->render('blocks/features', $context);
    }
}
```

## Conditional Fields

```php
public static function fields(): FieldsBuilder
{
    $builder = new FieldsBuilder('cta');

    $builder
        ->addText('title')
        ->addSelect('button_type', [
            'choices' => [
                'link' => 'Link',
                'download' => 'Download',
                'modal' => 'Modal',
            ],
        ])
        ->addLink('link')
            ->conditional('button_type', '==', 'link')
        ->addFile('file')
            ->conditional('button_type', '==', 'download')
        ->addText('modal_id')
            ->conditional('button_type', '==', 'modal');

    return $builder;
}
```

## Tabs and Groups

```php
public static function fields(): FieldsBuilder
{
    $builder = new FieldsBuilder('card');

    $builder
        ->addTab('Content')
            ->addText('title')
            ->addWysiwyg('content')
            ->addImage('image')

        ->addTab('Settings')
            ->addSelect('style', [
                'choices' => ['default', 'featured', 'minimal'],
            ])
            ->addColorPicker('background_color')
            ->addTrueFalse('show_shadow');

        ->addTab('Link')
            ->addLink('link');

    return $builder;
}
```

## Field Validation

Foehn provides a `ValidatesFields` trait for optional field validation and sanitization in your `compose()` method.

### Using the Trait

```php
<?php

namespace App\Blocks\Hero;

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Blocks\Concerns\ValidatesFields;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfBlock(name: 'hero', title: 'Hero Banner')]
final readonly class HeroBlock implements AcfBlockInterface
{
    use ValidatesFields;

    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('hero'))
            ->addText('title')
            ->addWysiwyg('content')
            ->addNumber('count');
    }

    public function compose(array $block, array $fields): array
    {
        // Validate required fields (throws InvalidArgumentException if missing)
        $this->validateRequired($fields, ['title']);

        // Sanitize individual fields
        return [
            'title' => $this->sanitizeField($fields['title'], 'string'),
            'content' => $this->sanitizeField($fields['content'] ?? '', 'html'),
            'count' => $this->sanitizeField($fields['count'] ?? 0, 'int'),
        ];
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return $this->view->render('blocks/hero', $context);
    }
}
```

### Schema-Based Validation

For more complex validation, use `validateFields()` with a schema:

```php
public function compose(array $block, array $fields): array
{
    return $this->validateFields($fields, [
        'title' => ['type' => 'string', 'required' => true],
        'content' => ['type' => 'html', 'default' => ''],
        'count' => ['type' => 'int', 'default' => 0],
        'email' => ['type' => 'email'],
        'link' => ['type' => 'url'],
        'items' => ['type' => 'array', 'default' => []],
    ]);
}
```

### Available Methods

| Method | Description |
| ------ | ----------- |
| `validateRequired(array $fields, array $required)` | Throws if required fields are missing or empty |
| `validateType(mixed $value, string $type)` | Returns `true` if value matches expected type |
| `sanitizeField(mixed $value, string $type)` | Coerces value to expected type |
| `validateFields(array $fields, array $schema)` | Validates and sanitizes fields against a schema |

### Supported Types

| Type | Description |
| ---- | ----------- |
| `string` | Trimmed string |
| `int` | Integer (coerced from numeric strings) |
| `float` | Float (coerced from numeric values) |
| `bool` | Boolean (handles `'true'`, `'yes'`, `'1'`, `'on'`) |
| `array` | Array |
| `html` | HTML content (sanitized via `wp_kses_post`) |
| `email` | Email address (sanitized) |
| `url` | URL (sanitized via `esc_url_raw`) |

### Advanced Validation

For more advanced validation needs, consider using:

- [`webmozart/assert`](https://github.com/webmozarts/assert) - Simple assertions
- [`respect/validation`](https://github.com/Respect/Validation) - Fluent validation API (Zod-like)

```php
use Webmozart\Assert\Assert;

public function compose(array $block, array $fields): array
{
    Assert::stringNotEmpty($fields['title'] ?? '');
    Assert::nullOrInteger($fields['count'] ?? null);

    return $fields;
}
```

## Preview Mode

Handle preview mode differently:

```php
public function render(array $context, bool $isPreview = false): string
{
    if ($isPreview && empty($context['title'])) {
        return '<div class="acf-placeholder">Please add content</div>';
    }

    return $this->view->render('blocks/hero', $context);
}
```

## File Structure

Organize blocks with their templates:

```
app/Blocks/
├── Hero/
│   └── HeroBlock.php
├── Features/
│   └── FeaturesBlock.php
├── Testimonial/
│   └── TestimonialBlock.php
└── Cta/
    └── CtaBlock.php

views/blocks/
├── hero.twig
├── features.twig
├── testimonial.twig
└── cta.twig
```

## Attribute Parameters

| Parameter     | Type       | Default     | Description                        |
| ------------- | ---------- | ----------- | ---------------------------------- |
| `name`        | `string`   | _required_  | Block name (without `acf/` prefix) |
| `title`       | `string`   | _required_  | Display title                      |
| `category`    | `string`   | `'common'`  | Block category                     |
| `icon`        | `?string`  | `null`      | Dashicon or SVG                    |
| `description` | `?string`  | `null`      | Block description                  |
| `keywords`    | `string[]` | `[]`        | Search keywords                    |
| `mode`        | `string`   | `'preview'` | `'preview'`, `'edit'`, or `'auto'` |
| `supports`    | `array`    | `[]`        | Block supports                     |
| `template`    | `?string`  | `null`      | Custom template path               |
| `postTypes`   | `string[]` | `[]`        | Allowed post types                 |
| `parent`      | `?string`  | `null`      | Parent block name                  |

## See Also

- [Native Blocks](./native-blocks)
- [Block Patterns](./block-patterns)
- [API Reference: #[AsAcfBlock]](/api/as-acf-block)
- [API Reference: AcfBlockInterface](/api/acf-block-interface)
