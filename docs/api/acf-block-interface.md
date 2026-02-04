# AcfBlockInterface

Interface for ACF (Advanced Custom Fields) blocks.

## Signature

```php
<?php

namespace Studiometa\Foehn\Contracts;

use StoutLogic\AcfBuilder\FieldsBuilder;

interface AcfBlockInterface
{
    /**
     * Define ACF fields for this block.
     *
     * @return FieldsBuilder The configured fields builder
     */
    public static function fields(): FieldsBuilder;

    /**
     * Compose data for the view.
     *
     * @param array<string, mixed> $block Block data from ACF
     * @param array<string, mixed> $fields Field values from get_fields()
     * @return array<string, mixed> Context for the template
     */
    public function compose(array $block, array $fields): array;

    /**
     * Render the block.
     *
     * @param array<string, mixed> $context Composed context
     * @param bool $isPreview Whether rendering in editor preview
     * @return string Rendered HTML
     */
    public function render(array $context, bool $isPreview = false): string;
}
```

## Methods

### fields()

Define ACF fields using `stoutlogic/acf-builder`. This is a static method called during registration.

```php
public static function fields(): FieldsBuilder
{
    return (new FieldsBuilder('hero'))
        ->addText('title', ['label' => 'Title'])
        ->addWysiwyg('content', ['label' => 'Content'])
        ->addImage('background', ['label' => 'Background']);
}
```

### compose()

Transform ACF field values into template context.

```php
public function compose(array $block, array $fields): array
{
    return [
        'title' => $fields['title'] ?? '',
        'content' => $fields['content'] ?? '',
        'background' => $fields['background'] ?? null,
        'block_id' => $block['id'] ?? '',
        'block_classes' => $block['className'] ?? '',
    ];
}
```

### render()

Render the block HTML. Receives the composed context and preview flag.

```php
public function render(array $context, bool $isPreview = false): string
{
    // Handle empty state in preview
    if ($isPreview && empty($context['title'])) {
        return '<div class="acf-placeholder">Add content</div>';
    }

    return $this->view->render('blocks/hero', $context);
}
```

## Usage

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

## Complex Fields Example

```php
public static function fields(): FieldsBuilder
{
    return (new FieldsBuilder('features'))
        ->addTab('Content')
            ->addText('title')
            ->addTextarea('description')
            ->addRepeater('items', ['layout' => 'block'])
                ->addImage('icon')
                ->addText('title')
                ->addTextarea('text')
            ->endRepeater()

        ->addTab('Settings')
            ->addSelect('columns', [
                'choices' => [
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                ],
            ])
            ->addColorPicker('background_color');
}
```

## Validation

Use the `ValidatesFields` trait to add optional validation in your `compose()` method:

```php
use Studiometa\Foehn\Blocks\Concerns\ValidatesFields;

final readonly class HeroBlock implements AcfBlockInterface
{
    use ValidatesFields;

    public function compose(array $block, array $fields): array
    {
        // Simple: validate required and sanitize
        $this->validateRequired($fields, ['title']);

        return [
            'title' => $this->sanitizeField($fields['title'], 'string'),
            'count' => $this->sanitizeField($fields['count'] ?? 0, 'int'),
        ];

        // Or use schema-based validation
        return $this->validateFields($fields, [
            'title' => ['type' => 'string', 'required' => true],
            'count' => ['type' => 'int', 'default' => 0],
        ]);
    }
}
```

See [Field Validation](/guide/acf-blocks#field-validation) for full documentation.

## Related

- [Guide: ACF Blocks](/guide/acf-blocks)
- [`#[AsAcfBlock]`](./as-acf-block)
