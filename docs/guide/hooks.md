# Hooks

WP Tempest provides `#[AsAction]` and `#[AsFilter]` attributes to register WordPress hooks declaratively.

## Actions

Use `#[AsAction]` to register action hooks:

```php
<?php

namespace App\Hooks;

use Studiometa\WPTempest\Attributes\AsAction;

final class ThemeHooks
{
    #[AsAction('after_setup_theme')]
    public function setupTheme(): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
        add_theme_support('html5', ['search-form', 'gallery', 'caption']);
    }

    #[AsAction('wp_enqueue_scripts')]
    public function enqueueAssets(): void
    {
        wp_enqueue_style('theme-style', get_stylesheet_uri());
        wp_enqueue_script('theme-script', get_template_directory_uri() . '/dist/main.js');
    }
}
```

### Priority and Arguments

You can specify priority and the number of accepted arguments:

```php
#[AsAction('save_post', priority: 20, acceptedArgs: 3)]
public function onSavePost(int $postId, WP_Post $post, bool $update): void
{
    if ($post->post_type !== 'product') {
        return;
    }

    // Handle product save
}
```

### Multiple Actions

A method can respond to multiple actions:

```php
#[AsAction('admin_init')]
#[AsAction('init')]
public function initialize(): void
{
    // Runs on both hooks
}
```

## Filters

Use `#[AsFilter]` to register filter hooks:

```php
<?php

namespace App\Hooks;

use Studiometa\WPTempest\Attributes\AsFilter;

final class ContentFilters
{
    #[AsFilter('the_content')]
    public function filterContent(string $content): string
    {
        // Add wrapper div around content
        return '<div class="content-wrapper">' . $content . '</div>';
    }

    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }

    #[AsFilter('excerpt_more')]
    public function excerptMore(): string
    {
        return '...';
    }
}
```

### Filter with Multiple Arguments

```php
#[AsFilter('wp_nav_menu_items', priority: 10, acceptedArgs: 2)]
public function addSearchToMenu(string $items, object $args): string
{
    if ($args->theme_location === 'primary') {
        $items .= '<li>' . get_search_form(false) . '</li>';
    }

    return $items;
}
```

## Dependency Injection

Hook handlers support constructor dependency injection:

```php
<?php

namespace App\Hooks;

use App\Services\AnalyticsService;
use Studiometa\WPTempest\Attributes\AsAction;

final class AnalyticsHooks
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    #[AsAction('wp_footer')]
    public function trackPageView(): void
    {
        $this->analytics->trackPageView(get_the_ID());
    }
}
```

## Organizing Hooks

Group related hooks in dedicated classes:

```
app/Hooks/
├── ThemeHooks.php      # Theme setup, supports
├── AssetHooks.php      # Scripts, styles
├── ContentHooks.php    # Content filters
├── AdminHooks.php      # Admin-only hooks
└── SeoHooks.php        # SEO-related hooks
```

## Common Hooks Reference

### Theme Setup

```php
#[AsAction('after_setup_theme')]
public function setup(): void
{
    // Theme supports
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('custom-logo');
    add_theme_support('editor-styles');

    // Register menus
    register_nav_menus([
        'primary' => 'Primary Menu',
        'footer' => 'Footer Menu',
    ]);

    // Add image sizes
    add_image_size('hero', 1920, 800, true);
}
```

### Admin Customizations

```php
#[AsAction('admin_menu')]
public function customizeAdminMenu(): void
{
    remove_menu_page('edit-comments.php');
}

#[AsAction('admin_bar_menu', priority: 999)]
public function customizeAdminBar(\WP_Admin_Bar $adminBar): void
{
    $adminBar->remove_node('comments');
}
```

### Login Customizations

```php
#[AsFilter('login_headerurl')]
public function loginLogoUrl(): string
{
    return home_url();
}

#[AsAction('login_enqueue_scripts')]
public function loginStyles(): void
{
    wp_enqueue_style('login-style', get_template_directory_uri() . '/dist/login.css');
}
```

## See Also

- [API Reference: #[AsAction]](/api/as-action)
- [API Reference: #[AsFilter]](/api/as-filter)
