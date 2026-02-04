# Block Patterns

WP Tempest provides `#[AsBlockPattern]` for registering block patterns with Twig template support.

## Basic Block Pattern

```php
<?php
// app/Patterns/HeroWithCta.php

namespace App\Patterns;

use Studiometa\WPTempest\Attributes\AsBlockPattern;

#[AsBlockPattern(
    name: 'theme/hero-with-cta',
    title: 'Hero with CTA',
    categories: ['featured'],
)]
final class HeroWithCta {}
```

With the corresponding Twig template:

```twig
{# patterns/hero-with-cta.twig #}
<!-- wp:cover {"dimRatio":50} -->
<div class="wp-block-cover">
    <div class="wp-block-cover__inner-container">
        <!-- wp:heading {"level":1} -->
        <h1 class="wp-block-heading">Welcome to our site</h1>
        <!-- /wp:heading -->

        <!-- wp:paragraph -->
        <p>Discover amazing content and services.</p>
        <!-- /wp:paragraph -->

        <!-- wp:buttons -->
        <div class="wp-block-buttons">
            <!-- wp:button -->
            <div class="wp-block-button">
                <a class="wp-block-button__link wp-element-button">Get Started</a>
            </div>
            <!-- /wp:button -->
        </div>
        <!-- /wp:buttons -->
    </div>
</div>
<!-- /wp:cover -->
```

## Full Configuration

```php
#[AsBlockPattern(
    name: 'theme/pricing-table',
    title: 'Pricing Table',
    categories: ['featured', 'pricing'],
    keywords: ['price', 'plans', 'subscription'],
    blockTypes: ['core/group'],
    description: 'A three-column pricing comparison table',
    viewportWidth: 1400,
    inserter: true,
)]
final class PricingTable {}
```

## Dynamic Patterns

For patterns with dynamic content, implement `BlockPatternInterface`:

```php
<?php
// app/Patterns/LatestPosts.php

namespace App\Patterns;

use Studiometa\WPTempest\Attributes\AsBlockPattern;
use Studiometa\WPTempest\Contracts\BlockPatternInterface;

#[AsBlockPattern(
    name: 'theme/latest-posts',
    title: 'Latest Posts Grid',
    categories: ['posts'],
)]
final class LatestPosts implements BlockPatternInterface
{
    public function context(): array
    {
        $posts = \Timber\Timber::get_posts([
            'post_type' => 'post',
            'posts_per_page' => 3,
        ]);

        return [
            'posts' => $posts,
        ];
    }
}
```

```twig
{# patterns/latest-posts.twig #}
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
    <!-- wp:heading -->
    <h2 class="wp-block-heading">Latest Posts</h2>
    <!-- /wp:heading -->

    <!-- wp:columns -->
    <div class="wp-block-columns">
        {% for post in posts %}
        <!-- wp:column -->
        <div class="wp-block-column">
            <!-- wp:heading {"level":3} -->
            <h3 class="wp-block-heading">{{ post.title }}</h3>
            <!-- /wp:heading -->

            <!-- wp:paragraph -->
            <p>{{ post.excerpt }}</p>
            <!-- /wp:paragraph -->

            <!-- wp:buttons -->
            <div class="wp-block-buttons">
                <!-- wp:button {"className":"is-style-outline"} -->
                <div class="wp-block-button is-style-outline">
                    <a class="wp-block-button__link wp-element-button" href="{{ post.link }}">Read More</a>
                </div>
                <!-- /wp:button -->
            </div>
            <!-- /wp:buttons -->
        </div>
        <!-- /wp:column -->
        {% endfor %}
    </div>
    <!-- /wp:columns -->
</div>
<!-- /wp:group -->
```

## Pattern Categories

Patterns use standard WordPress categories or custom ones:

### Standard Categories

- `featured` - Featured patterns
- `posts` - Post-related patterns
- `text` - Text patterns
- `gallery` - Gallery patterns
- `call-to-action` - CTA patterns
- `banner` - Banner patterns
- `header` - Header patterns
- `footer` - Footer patterns
- `columns` - Column layouts

### Custom Categories

Register custom categories using filters:

```php
<?php

namespace App\Hooks;

use Studiometa\WPTempest\Attributes\AsFilter;

final class PatternHooks
{
    #[AsFilter('block_pattern_categories_all')]
    public function registerCategories(array $categories): array
    {
        $categories['theme-layouts'] = [
            'label' => 'Theme Layouts',
            'description' => 'Custom layouts for this theme',
        ];

        return $categories;
    }
}
```

## Template Path Resolution

By default, patterns look for templates in `patterns/`:

- `theme/hero-with-cta` → `patterns/hero-with-cta.twig`
- `theme/pricing-table` → `patterns/pricing-table.twig`

Custom path:

```php
#[AsBlockPattern(
    name: 'theme/hero',
    title: 'Hero',
    template: 'my-patterns/hero-banner',
)]
```

## Examples

### Two Column Text

```twig
{# patterns/two-column-text.twig #}
<!-- wp:columns -->
<div class="wp-block-columns">
    <!-- wp:column -->
    <div class="wp-block-column">
        <!-- wp:heading -->
        <h2 class="wp-block-heading">Column One</h2>
        <!-- /wp:heading -->

        <!-- wp:paragraph -->
        <p>Add your content here. This pattern creates a two-column layout.</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column -->
    <div class="wp-block-column">
        <!-- wp:heading -->
        <h2 class="wp-block-heading">Column Two</h2>
        <!-- /wp:heading -->

        <!-- wp:paragraph -->
        <p>Add your content here. Edit this pattern to match your needs.</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
</div>
<!-- /wp:columns -->
```

### Call to Action

```twig
{# patterns/call-to-action.twig #}
<!-- wp:group {"backgroundColor":"primary","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-white-color has-primary-background-color has-text-color has-background">
    <!-- wp:heading {"textAlign":"center"} -->
    <h2 class="wp-block-heading has-text-align-center">Ready to get started?</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"align":"center"} -->
    <p class="has-text-align-center">Join thousands of satisfied customers today.</p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
    <div class="wp-block-buttons">
        <!-- wp:button {"backgroundColor":"white","textColor":"primary"} -->
        <div class="wp-block-button">
            <a class="wp-block-button__link has-primary-color has-white-background-color has-text-color has-background wp-element-button">Sign Up Now</a>
        </div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
</div>
<!-- /wp:group -->
```

## File Structure

```
app/Patterns/
├── HeroWithCta.php
├── PricingTable.php
├── LatestPosts.php
└── CallToAction.php

patterns/
├── hero-with-cta.twig
├── pricing-table.twig
├── latest-posts.twig
└── call-to-action.twig
```

## Attribute Parameters

| Parameter       | Type       | Default       | Description                 |
| --------------- | ---------- | ------------- | --------------------------- |
| `name`          | `string`   | _required_    | Pattern name with namespace |
| `title`         | `string`   | _required_    | Display title               |
| `categories`    | `string[]` | `[]`          | Pattern categories          |
| `keywords`      | `string[]` | `[]`          | Search keywords             |
| `blockTypes`    | `string[]` | `[]`          | Associated block types      |
| `description`   | `?string`  | `null`        | Pattern description         |
| `template`      | `?string`  | Auto-resolved | Template path               |
| `viewportWidth` | `int`      | `1200`        | Preview viewport width      |
| `inserter`      | `bool`     | `true`        | Show in inserter            |

## See Also

- [ACF Blocks](./acf-blocks)
- [Native Blocks](./native-blocks)
- [API Reference: #[AsBlockPattern]](/api/as-block-pattern)
- [API Reference: BlockPatternInterface](/api/block-pattern-interface)
