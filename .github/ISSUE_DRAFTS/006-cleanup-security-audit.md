# Audit and complete cleanup/security hooks

## Current state

Foehn already has comprehensive cleanup and security hooks in `src/Hooks/`:

### Cleanup hooks

- `CleanContent.php` - Remove empty paragraphs, clean archive title prefix
- `CleanHeadTags.php` - Remove wlwmanifest, RSD, shortlink, REST discovery
- `CleanImageSizes.php` - Remove medium_large, 1536x1536, 2048x2048 sizes
- `DisableEmoji.php` - Full emoji removal (scripts, styles, DNS prefetch, TinyMCE)
- `DisableFeeds.php` - Disable RSS/Atom feeds
- `DisableGlobalStyles.php` - Disable global styles inline CSS and SVG duotone
- `DisableOembed.php` - Disable oEmbed

### Security hooks

- `DisableFileEditor.php` - Disable theme/plugin editor
- `DisableVersionDisclosure.php` - Hide WP version from meta, assets, RSS
- `DisableXmlRpc.php` - Disable XML-RPC
- `RestApiAuth.php` - REST API authentication
- `SecurityHeaders.php` - Security HTTP headers

### Other hooks

- `YouTubeNoCookieHooks.php` - Convert YouTube to youtube-nocookie.com

---

## Audit results

### ✅ `DisableVersionDisclosure` - VERIFIED

Correctly removes version from:

- `wp_generator` meta tag
- `script_loader_src` (removes `?ver=`)
- `style_loader_src` (removes `?ver=`)
- RSS feed generator

### ❌ `DisableGlobalStyles` - INCOMPLETE

Currently only removes:

- `wp_enqueue_global_styles` (inline global styles)
- `wp_global_styles_render_svg_filters` (SVG duotone)
- `wp_enqueue_global_styles_custom_css` (custom CSS)

**Missing** (found in every real project):

```php
wp_dequeue_style('wp-block-library');
wp_dequeue_style('wp-block-library-theme');
wp_dequeue_style('classic-theme-styles');
```

---

## What's missing

### 1. Block library styles removal (NEEDED)

All analyzed projects remove Gutenberg block styles on frontend. This should be a separate hook:

```php
// src/Hooks/Cleanup/DisableBlockStyles.php
namespace Studiometa\Foehn\Hooks\Cleanup;

use Studiometa\Foehn\Attributes\AsAction;

/**
 * Disable WordPress block library styles on the frontend.
 *
 * WordPress enqueues ~30KB of block styles on every page, even if
 * your theme doesn't use Gutenberg blocks on the frontend.
 *
 * This removes:
 * - wp-block-library (core block styles)
 * - wp-block-library-theme (theme-specific block styles)
 * - classic-theme-styles (WP 6.1+ classic theme compatibility)
 *
 * ⚠️  Only use if you don't use native Gutenberg blocks in your theme,
 * or if you provide your own block styling.
 */
final class DisableBlockStyles
{
    #[AsAction('wp_enqueue_scripts', priority: 100)]
    public function dequeueBlockStyles(): void
    {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('classic-theme-styles');
    }
}
```

### 2. Admin cleanup (DOCUMENT ONLY)

These are project-specific, don't include but document the pattern:

```php
// Example in theme's app/Hooks/AdminHooks.php
#[AsAction('wp_dashboard_setup')]
public function removeDashboardWidgets(): void
{
    remove_meta_box('dashboard_primary', 'dashboard', 'side');
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
}

#[AsAction('admin_menu')]
public function removeMenuItems(): void
{
    remove_menu_page('edit-comments.php');
}
```

### 3. Login customization (DOCUMENT ONLY)

Project-specific, document the pattern:

```php
// Example in theme's app/Hooks/LoginHooks.php
#[AsFilter('login_errors')]
public function genericLoginError(): string
{
    return __('Invalid credentials.', 'theme');
}

#[AsFilter('login_headerurl')]
public function loginLogoUrl(): string
{
    return home_url('/');
}

#[AsFilter('login_headertext')]
public function loginLogoText(): string
{
    return get_bloginfo('name');
}
```

### 4. Plugin-specific dequeue (DOCUMENT ONLY)

Project-specific, document the pattern:

```php
// Example: Remove Contact Form 7 assets on pages without forms
#[AsAction('wp_enqueue_scripts', priority: 100)]
public function conditionallyRemoveCF7(): void
{
    if (!is_page('contact')) {
        wp_dequeue_script('contact-form-7');
        wp_dequeue_style('contact-form-7');
    }
}

// Example: Remove WooCommerce styles on non-shop pages
#[AsAction('wp_enqueue_scripts', priority: 100)]
public function conditionallyRemoveWooStyles(): void
{
    if (!is_woocommerce() && !is_cart() && !is_checkout()) {
        wp_dequeue_style('woocommerce-general');
        wp_dequeue_style('woocommerce-layout');
    }
}
```

---

## Hooks activation strategy

**Current approach**: Hooks exist in Foehn but are NOT auto-enabled. Theme must use them explicitly or create their own.

**Question**: Should some hooks be opt-out instead of opt-in?

### Recommendation: Keep as library, document well

Don't auto-enable any hooks. Instead:

1. Provide well-documented hook classes in Foehn
2. Theme developers explicitly use the ones they need
3. Document common patterns for project-specific cleanup

This avoids surprises and gives full control to theme developers.

### Usage in theme

```php
// Theme can use Foehn's built-in hooks via inheritance or composition
// Or simply copy the patterns into their own hooks

// app/Hooks/CleanupHooks.php
namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;

final class CleanupHooks
{
    #[AsAction('wp_enqueue_scripts', priority: 100)]
    public function removeBlockStyles(): void
    {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('classic-theme-styles');
    }
}
```

---

## Tasks

### Code changes

- [ ] Create `DisableBlockStyles.php` hook class (separate from DisableGlobalStyles)

### Documentation

- [ ] Document all available cleanup hooks with use cases
- [ ] Document all available security hooks with use cases
- [ ] Add examples for admin cleanup patterns
- [ ] Add examples for login customization patterns
- [ ] Add examples for plugin-specific dequeue patterns
- [ ] Clarify that hooks are opt-in (not auto-enabled)

## Labels

`enhancement`, `documentation`, `priority-low`
