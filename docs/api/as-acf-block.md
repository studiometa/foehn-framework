# #[AsAcfBlock]

Register a class as an ACF (Advanced Custom Fields) block.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAcfBlock
{
    public function __construct(
        public string $name,
        public string $title,
        public string $category = 'common',
        public ?string $icon = null,
        public ?string $description = null,
        public array $keywords = [],
        public string $mode = 'preview',
        public array $supports = [],
        public ?string $template = null,
        public array $postTypes = [],
        public ?string $parent = null,
    ) {}

    public function getFullName(): string {}
}
```

## Parameters

| Parameter     | Type       | Default     | Description                             |
| ------------- | ---------- | ----------- | --------------------------------------- |
| `name`        | `string`   | —           | Block name without `acf/` (required)    |
| `title`       | `string`   | —           | Display title (required)                |
| `category`    | `string`   | `'common'`  | Block category                          |
| `icon`        | `?string`  | `null`      | Dashicon name or SVG                    |
| `description` | `?string`  | `null`      | Block description                       |
| `keywords`    | `string[]` | `[]`        | Search keywords                         |
| `mode`        | `string`   | `'preview'` | Display mode: `preview`, `edit`, `auto` |
| `supports`    | `array`    | `[]`        | Block supports configuration            |
| `template`    | `?string`  | `null`      | Custom template path                    |
| `postTypes`   | `string[]` | `[]`        | Allowed post types (empty = all)        |
| `parent`      | `?string`  | `null`      | Parent block name                       |

## Usage

### Basic ACF Block

```php
<?php

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
            ->addText('title')
            ->addWysiwyg('content')
            ->addImage('background');
    }

    public function compose(array $block, array $fields): array
    {
        return [
            'title' => $fields['title'] ?? '',
            'content' => $fields['content'] ?? '',
            'background' => $fields['background'] ?? null,
        ];
    }

    public function render(array $context, bool $isPreview = false): string
    {
        return $this->view->render('blocks/hero', $context);
    }
}
```

### Full Configuration

```php
#[AsAcfBlock(
    name: 'testimonial',
    title: 'Testimonial',
    category: 'common',
    icon: 'format-quote',
    description: 'Display a customer testimonial',
    keywords: ['quote', 'review'],
    mode: 'preview',
    supports: [
        'align' => true,
        'mode' => true,
        'jsx' => true,
    ],
    postTypes: ['page', 'post'],
)]
```

### With Complex Fields

```php
public static function fields(): FieldsBuilder
{
    return (new FieldsBuilder('features'))
        ->addText('title')
        ->addRepeater('items', ['layout' => 'block'])
            ->addImage('icon')
            ->addText('title')
            ->addTextarea('description')
        ->endRepeater()
        ->addSelect('columns', [
            'choices' => ['2' => '2 Columns', '3' => '3 Columns'],
        ]);
}
```

## Required Interface

Classes must implement `AcfBlockInterface`:

```php
interface AcfBlockInterface
{
    public static function fields(): FieldsBuilder;
    public function compose(array $block, array $fields): array;
    public function render(array $context, bool $isPreview = false): string;
}
```

## Related

- [Guide: ACF Blocks](/guide/acf-blocks)
- [`AcfBlockInterface`](./acf-block-interface)
- [`#[AsBlock]`](./as-block)
