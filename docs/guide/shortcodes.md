# Shortcodes

Føhn provides `#[AsShortcode]` for registering shortcode handlers.

## Basic Shortcode

```php
<?php
// app/Hooks/Shortcodes.php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsShortcode;

final class Shortcodes
{
    #[AsShortcode('button')]
    public function button(array $atts, ?string $content = null): string
    {
        $atts = shortcode_atts([
            'url' => '#',
            'target' => '_self',
            'class' => 'btn',
        ], $atts);

        return sprintf(
            '<a href="%s" target="%s" class="%s">%s</a>',
            esc_url($atts['url']),
            esc_attr($atts['target']),
            esc_attr($atts['class']),
            esc_html($content ?? 'Click here')
        );
    }
}
```

**Usage:**

```
[button url="https://example.com" class="btn btn-primary"]Learn More[/button]
```

## Shortcode with Templates

For complex shortcodes, use templates:

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsShortcode;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

final class Shortcodes
{
    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    #[AsShortcode('testimonial')]
    public function testimonial(array $atts): string
    {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $testimonial = \Timber\Timber::get_post($atts['id']);

        if (!$testimonial || $testimonial->post_type !== 'testimonial') {
            return '';
        }

        return $this->view->render('shortcodes/testimonial', [
            'testimonial' => $testimonial,
        ]);
    }
}
```

```twig
{# templates/shortcodes/testimonial.twig #}
<blockquote class="testimonial">
    <p class="testimonial__content">{{ testimonial.content }}</p>
    <footer class="testimonial__author">
        {% if testimonial.thumbnail %}
            <img src="{{ testimonial.thumbnail.src('thumbnail') }}" alt="{{ testimonial.meta('author_name') }}">
        {% endif %}
        <cite>{{ testimonial.meta('author_name') }}</cite>
    </footer>
</blockquote>
```

## Multiple Shortcodes

```php
<?php

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsShortcode;

final class Shortcodes
{
    #[AsShortcode('alert')]
    public function alert(array $atts, ?string $content = null): string
    {
        $atts = shortcode_atts([
            'type' => 'info',
        ], $atts);

        return sprintf(
            '<div class="alert alert--%s">%s</div>',
            esc_attr($atts['type']),
            wp_kses_post($content)
        );
    }

    #[AsShortcode('icon')]
    public function icon(array $atts): string
    {
        $atts = shortcode_atts([
            'name' => 'star',
            'size' => '24',
        ], $atts);

        return sprintf(
            '<svg class="icon icon--%s" width="%s" height="%s">
                <use href="#icon-%s"></use>
            </svg>',
            esc_attr($atts['name']),
            esc_attr($atts['size']),
            esc_attr($atts['size']),
            esc_attr($atts['name'])
        );
    }

    #[AsShortcode('year')]
    public function year(): string
    {
        return date('Y');
    }
}
```

## Dynamic Content Shortcodes

```php
#[AsShortcode('recent_posts')]
public function recentPosts(array $atts): string
{
    $atts = shortcode_atts([
        'count' => 5,
        'category' => '',
    ], $atts);

    $args = [
        'post_type' => 'post',
        'posts_per_page' => (int) $atts['count'],
    ];

    if ($atts['category']) {
        $args['category_name'] = $atts['category'];
    }

    $posts = \Timber\Timber::get_posts($args);

    return $this->view->render('shortcodes/recent-posts', [
        'posts' => $posts,
    ]);
}
```

**Usage:**

```
[recent_posts count="3" category="news"]
```

## Contact Form Shortcode

```php
#[AsShortcode('contact_form')]
public function contactForm(array $atts): string
{
    $atts = shortcode_atts([
        'recipient' => get_option('admin_email'),
        'subject' => 'Contact Form Submission',
    ], $atts);

    return $this->view->render('shortcodes/contact-form', [
        'form_id' => 'contact-' . wp_unique_id(),
        'recipient' => $atts['recipient'],
        'subject' => $atts['subject'],
    ]);
}
```

## Enclosing Shortcodes

Handle content between opening and closing tags:

```php
#[AsShortcode('spoiler')]
public function spoiler(array $atts, ?string $content = null): string
{
    $atts = shortcode_atts([
        'title' => 'Spoiler',
    ], $atts);

    return sprintf(
        '<details class="spoiler">
            <summary>%s</summary>
            <div class="spoiler__content">%s</div>
        </details>',
        esc_html($atts['title']),
        do_shortcode($content ?? '')
    );
}
```

**Usage:**

```
[spoiler title="Click to reveal"]
This content is hidden by default.
[/spoiler]
```

## Nested Shortcodes

Support nested shortcode processing:

```php
#[AsShortcode('tabs')]
public function tabs(array $atts, ?string $content = null): string
{
    // Process nested [tab] shortcodes
    $content = do_shortcode($content ?? '');

    return sprintf(
        '<div class="tabs">%s</div>',
        $content
    );
}

#[AsShortcode('tab')]
public function tab(array $atts, ?string $content = null): string
{
    $atts = shortcode_atts([
        'title' => 'Tab',
    ], $atts);

    return sprintf(
        '<div class="tab" data-title="%s">%s</div>',
        esc_attr($atts['title']),
        wp_kses_post($content)
    );
}
```

**Usage:**

```
[tabs]
[tab title="First"]Content for first tab[/tab]
[tab title="Second"]Content for second tab[/tab]
[/tabs]
```

## Organizing Shortcodes

```
app/Hooks/
├── Shortcodes/
│   ├── ButtonShortcode.php
│   ├── AlertShortcode.php
│   ├── FormShortcodes.php
│   └── ContentShortcodes.php
```

Or group related shortcodes:

```php
// app/Hooks/UIShortcodes.php
final class UIShortcodes
{
    #[AsShortcode('button')]
    public function button() {}

    #[AsShortcode('alert')]
    public function alert() {}

    #[AsShortcode('card')]
    public function card() {}
}

// app/Hooks/ContentShortcodes.php
final class ContentShortcodes
{
    #[AsShortcode('recent_posts')]
    public function recentPosts() {}

    #[AsShortcode('testimonial')]
    public function testimonial() {}
}
```

## Security Best Practices

Shortcode output is rendered directly in page content, making proper escaping critical to prevent XSS vulnerabilities.

### Always Escape Output

Every dynamic value in your shortcode output must be escaped using the appropriate function:

```php
#[AsShortcode('profile')]
public function profile(array $atts, ?string $content = null): string
{
    $atts = shortcode_atts([
        'name' => '',
        'url' => '#',
        'class' => 'profile',
    ], $atts);

    return sprintf(
        '<div class="%s">
            <a href="%s">%s</a>
            <p>%s</p>
        </div>',
        esc_attr($atts['class']),      // HTML attribute
        esc_url($atts['url']),         // URL
        esc_html($atts['name']),       // Plain text
        wp_kses_post($content)         // Rich HTML content
    );
}
```

### Escaping Function Reference

| Function         | Use For                     | Example Context                    |
| ---------------- | --------------------------- | ---------------------------------- |
| `esc_html()`     | Plain text                  | Text inside tags                   |
| `esc_attr()`     | Attribute values            | `class=""`, `data-*=""`            |
| `esc_url()`      | URLs                        | `href=""`, `src=""`                |
| `wp_kses_post()` | HTML allowing safe tags     | User-provided rich content         |
| `wp_kses()`      | HTML with custom whitelist  | Restricted formatting              |

### Common Mistakes

```php
// ❌ DANGEROUS: Unescaped attributes allow XSS
#[AsShortcode('link')]
public function unsafeLink(array $atts): string
{
    return '<a href="' . $atts['url'] . '" class="' . $atts['class'] . '">Click</a>';
}

// ✅ SAFE: All output properly escaped
#[AsShortcode('link')]
public function safeLink(array $atts): string
{
    $atts = shortcode_atts([
        'url' => '#',
        'class' => 'link',
        'text' => 'Click here',
    ], $atts);

    return sprintf(
        '<a href="%s" class="%s">%s</a>',
        esc_url($atts['url']),
        esc_attr($atts['class']),
        esc_html($atts['text'])
    );
}
```

### Template-Based Shortcodes

When using Twig templates, rely on Twig's auto-escaping but be careful with `|raw`:

```twig
{# templates/shortcodes/card.twig #}
<div class="{{ class }}"> {# Auto-escaped #}
    <h3>{{ title }}</h3>   {# Auto-escaped #}
    <a href="{{ url|e('url') }}">{{ link_text }}</a>
    <div class="content">{{ content|raw }}</div> {# Only for trusted HTML #}
</div>
```

For detailed security guidance, see the [Security Guide](/guide/security).

## See Also

- [API Reference: #[AsShortcode]](/api/as-shortcode)
- [Security Guide](/guide/security)
