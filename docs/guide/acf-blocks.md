# ACF Blocks

WP Tempest provides `#[AsAcfBlock]` for creating ACF blocks with type-safe fields using `stoutlogic/acf-builder`.

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

use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;
use Studiometa\WPTempest\Contracts\ViewEngineInterface;
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

use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;
use Studiometa\WPTempest\Contracts\ViewEngineInterface;
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
