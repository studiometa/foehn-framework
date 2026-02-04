# #[AsShortcode]

Register a method as a WordPress shortcode handler.

## Signature

```php
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class AsShortcode
{
    public function __construct(
        public string $tag,
    ) {}
}
```

## Parameters

| Parameter | Type     | Default | Description                   |
| --------- | -------- | ------- | ----------------------------- |
| `tag`     | `string` | —       | Shortcode tag name (required) |

## Usage

### Basic Shortcode

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsShortcode;

final class Shortcodes
{
    #[AsShortcode('year')]
    public function year(): string
    {
        return date('Y');
    }
}
```

**Usage:** `[year]` → `2024`

### With Attributes

```php
#[AsShortcode('button')]
public function button(array $atts, ?string $content = null): string
{
    $atts = shortcode_atts([
        'url' => '#',
        'class' => 'btn',
    ], $atts);

    return sprintf(
        '<a href="%s" class="%s">%s</a>',
        esc_url($atts['url']),
        esc_attr($atts['class']),
        esc_html($content ?? 'Click')
    );
}
```

**Usage:** `[button url="https://example.com"]Learn More[/button]`

### Enclosing Shortcode

```php
#[AsShortcode('spoiler')]
public function spoiler(array $atts, ?string $content = null): string
{
    $atts = shortcode_atts(['title' => 'Spoiler'], $atts);

    return sprintf(
        '<details><summary>%s</summary>%s</details>',
        esc_html($atts['title']),
        do_shortcode($content ?? '')
    );
}
```

**Usage:**

```
[spoiler title="Click to reveal"]
Hidden content here.
[/spoiler]
```

### With Template

```php
public function __construct(
    private readonly ViewEngineInterface $view,
) {}

#[AsShortcode('testimonial')]
public function testimonial(array $atts): string
{
    $atts = shortcode_atts(['id' => 0], $atts);

    $post = \Timber\Timber::get_post($atts['id']);
    if (!$post) {
        return '';
    }

    return $this->view->render('shortcodes/testimonial', [
        'testimonial' => $post,
    ]);
}
```

## Related

- [Guide: Shortcodes](/guide/shortcodes)
