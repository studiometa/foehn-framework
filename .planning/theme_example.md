# Theme Example with foehn

## Complete Theme Structure

```
starter-theme/
├── app/
│   ├── Acf/
│   │   └── Fragments/                  # Reusable ACF field groups
│   │       ├── BackgroundBuilder.php
│   │       ├── ButtonLinkBuilder.php
│   │       ├── ResponsiveImageBuilder.php
│   │       └── SpacingBuilder.php
│   │
│   ├── Blocks/
│   │   ├── Acf/
│   │   │   ├── Hero/
│   │   │   │   └── HeroBlock.php
│   │   │   ├── Testimonials/
│   │   │   │   └── TestimonialsBlock.php
│   │   │   └── Gallery/
│   │   │       └── GalleryBlock.php
│   │   │
│   │   └── Native/
│   │       ├── Accordion/
│   │       │   ├── AccordionBlock.php
│   │       │   ├── edit.js
│   │       │   ├── view.js
│   │       │   └── style.scss
│   │       └── Tabs/
│   │           ├── TabsBlock.php
│   │           ├── edit.js
│   │           ├── view.js
│   │           └── style.scss
│   │
│   ├── Models/
│   │   ├── Post.php
│   │   ├── Page.php
│   │   ├── Product.php
│   │   └── Testimonial.php
│   │
│   ├── Taxonomies/
│   │   ├── ProductCategory.php
│   │   └── ProductTag.php
│   │
│   ├── Http/
│   │   └── Controllers/
│   │       ├── SingleController.php
│   │       ├── ArchiveController.php
│   │       ├── SearchController.php
│   │       └── Error404Controller.php
│   │
│   ├── Views/
│   │   └── Composers/
│   │       ├── GlobalComposer.php
│   │       ├── SingleComposer.php
│   │       ├── ArchiveComposer.php
│   │       └── ProductComposer.php
│   │
│   ├── Patterns/
│   │   ├── HeroFullWidth.php
│   │   ├── TeamGrid.php
│   │   ├── CtaSection.php
│   │   └── FaqAccordion.php
│   │
│   ├── Services/
│   │   ├── MenuService.php
│   │   ├── ImageService.php
│   │   ├── SeoService.php
│   │   └── OptionsService.php
│   │
│   ├── Hooks/
│   │   ├── ThemeHooks.php
│   │   ├── AdminHooks.php
│   │   ├── SecurityHooks.php
│   │   └── PerformanceHooks.php
│   │
│   └── Theme/
│       └── ThemeConfig.php
│
├── templates/
│   ├── layouts/
│   │   ├── base.twig
│   │   └── blank.twig
│   │
│   ├── pages/
│   │   ├── single.twig
│   │   ├── single-product.twig
│   │   ├── archive.twig
│   │   ├── archive-product.twig
│   │   ├── search.twig
│   │   ├── 404.twig
│   │   └── front-page.twig
│   │
│   ├── blocks/
│   │   ├── hero.twig
│   │   ├── testimonials.twig
│   │   ├── gallery.twig
│   │   ├── accordion.twig
│   │   └── tabs.twig
│   │
│   ├── patterns/
│   │   ├── hero-full-width.twig
│   │   ├── team-grid.twig
│   │   ├── cta-section.twig
│   │   └── faq-accordion.twig
│   │
│   ├── components/
│   │   ├── header.twig
│   │   ├── footer.twig
│   │   ├── navigation.twig
│   │   ├── card-post.twig
│   │   ├── card-product.twig
│   │   ├── pagination.twig
│   │   ├── breadcrumb.twig
│   │   └── social-share.twig
│   │
│   └── partials/
│       ├── meta-post.twig
│       └── meta-product.twig
│
├── parts/                          # FSE Template Parts (HTML)
│   ├── header.html
│   └── footer.html
│
├── assets/
│   ├── css/
│   │   ├── app.scss
│   │   ├── editor.scss
│   │   └── admin.scss
│   ├── js/
│   │   └── app.js
│   └── images/
│
├── config/
│   └── assets.yml
│
├── dist/                           # Compiled assets
│
├── functions.php                   # 1 ligne !
├── style.css                       # Theme header
├── theme.json                      # Généré depuis ThemeConfig
├── screenshot.png
├── composer.json
├── package.json
└── README.md
```

---

## Theme Files

### functions.php

```php
<?php
/**
 * Theme: Starter Theme
 *
 * @package StarterTheme
 */

declare(strict_types=1);

use Studiometa\Foehn\Kernel;

// Boot the application
Kernel::boot(__DIR__ . '/app');
```

### composer.json

```json
{
  "name": "starter/theme",
  "description": "Starter theme with foehn",
  "type": "wordpress-theme",
  "require": {
    "php": "^8.4",
    "studiometa/foehn": "^1.0",
    "stoutlogic/acf-builder": "^1.12"
  },
  "require-dev": {
    "phpstan/phpstan": "^2.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  },
  "config": {
    "optimize-autoloader": true
  }
}
```

### style.css

```css
/*
Theme Name: Starter Theme
Theme URI: https://github.com/studiometa/starter-theme
Author: Studio Meta
Author URI: https://www.studiometa.fr
Description: A modern WordPress theme powered by foehn
Version: 1.0.0
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.4
License: MIT
Text Domain: starter-theme
*/
```

---

## Models

### app/Models/Post.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post as TimberPost;

/**
 * Default Post model.
 * No #[AsPostType] needed - 'post' already exists in WordPress.
 * This just extends Timber\Post with custom methods.
 */
final class Post extends TimberPost
{
    /**
     * Get estimated reading time in minutes.
     */
    public function readingTime(): int
    {
        $words = str_word_count(strip_tags($this->content()));
        return max(1, (int) ceil($words / 200));
    }

    /**
     * Get post excerpt with fallback.
     */
    public function safeExcerpt(int $length = 160): string
    {
        if ($this->excerpt()) {
            return $this->excerpt();
        }

        $content = strip_tags($this->content());
        if (strlen($content) <= $length) {
            return $content;
        }

        return substr($content, 0, $length) . '…';
    }

    /**
     * Check if post has a featured image.
     */
    public function hasThumbnail(): bool
    {
        return $this->thumbnail() !== null;
    }
}
```

### app/Models/Product.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;
use Timber\Post as TimberPost;

#[AsPostType(
    name: 'product',
    singular: 'Produit',
    plural: 'Produits',
    public: true,
    hasArchive: true,
    showInRest: true,
    menuIcon: 'dashicons-cart',
    supports: ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
    taxonomies: ['product_category', 'product_tag'],
)]
final class Product extends TimberPost implements ConfiguresPostType
{
    public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder
    {
        return $builder
            ->setRewrite(['slug' => 'boutique', 'with_front' => false])
            ->setMenuPosition(5);
    }

    /**
     * Get product price.
     */
    public function price(): ?float
    {
        $price = $this->meta('price');
        return $price ? (float) $price : null;
    }

    /**
     * Get formatted price.
     */
    public function formattedPrice(): string
    {
        $price = $this->price();

        if ($price === null) {
            return __('Prix sur demande', 'starter-theme');
        }

        return number_format($price, 2, ',', ' ') . ' €';
    }

    /**
     * Check if product is on sale.
     */
    public function isOnSale(): bool
    {
        $salePrice = $this->meta('sale_price');
        return $salePrice && (float) $salePrice < $this->price();
    }

    /**
     * Get sale price if exists.
     */
    public function salePrice(): ?float
    {
        $salePrice = $this->meta('sale_price');
        return $salePrice ? (float) $salePrice : null;
    }

    /**
     * Get product categories.
     */
    public function productCategories(): array
    {
        return $this->terms('product_category');
    }

    /**
     * Get product gallery images.
     */
    public function galleryImages(): array
    {
        $gallery = $this->meta('gallery');

        if (!$gallery || !is_array($gallery)) {
            return [];
        }

        return array_map(
            fn($id) => \Timber\Timber::get_image($id),
            $gallery
        );
    }
}
```

### app/Models/Testimonial.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post as TimberPost;

#[AsPostType(
    name: 'testimonial',
    singular: 'Témoignage',
    plural: 'Témoignages',
    public: false,
    showInRest: true,
    menuIcon: 'dashicons-format-quote',
    supports: ['title', 'editor', 'thumbnail'],
)]
final class Testimonial extends TimberPost
{
    public function authorName(): string
    {
        return $this->meta('author_name') ?: $this->title();
    }

    public function authorRole(): ?string
    {
        return $this->meta('author_role');
    }

    public function company(): ?string
    {
        return $this->meta('company');
    }

    public function rating(): ?int
    {
        $rating = $this->meta('rating');
        return $rating ? (int) $rating : null;
    }
}
```

---

## Taxonomies

### app/Taxonomies/ProductCategory.php

```php
<?php

declare(strict_types=1);

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Contracts\ConfiguresTaxonomy;
use Studiometa\Foehn\PostTypes\TaxonomyBuilder;

#[AsTaxonomy(
    name: 'product_category',
    singular: 'Catégorie',
    plural: 'Catégories',
    postTypes: ['product'],
    hierarchical: true,
    showInRest: true,
    showAdminColumn: true,
)]
final class ProductCategory implements ConfiguresTaxonomy
{
    public static function configureTaxonomy(TaxonomyBuilder $builder): TaxonomyBuilder
    {
        return $builder
            ->setRewrite(['slug' => 'boutique/categorie', 'with_front' => false]);
    }
}
```

### app/Taxonomies/ProductTag.php

```php
<?php

declare(strict_types=1);

namespace App\Taxonomies;

use Studiometa\Foehn\Attributes\AsTaxonomy;

#[AsTaxonomy(
    name: 'product_tag',
    singular: 'Étiquette',
    plural: 'Étiquettes',
    postTypes: ['product'],
    hierarchical: false,
    showInRest: true,
)]
final class ProductTag
{
    // No additional configuration needed
}
```

---

## Services

### app/Services/MenuService.php

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Timber\Menu;
use Timber\Timber;

final class MenuService
{
    private array $cache = [];

    /**
     * Get a menu by location.
     */
    public function get(string $location): ?Menu
    {
        if (!isset($this->cache[$location])) {
            $this->cache[$location] = Timber::get_menu($location);
        }

        return $this->cache[$location];
    }

    /**
     * Get all registered menus.
     */
    public function all(): array
    {
        return [
            'header' => $this->get('header'),
            'footer' => $this->get('footer'),
            'legal' => $this->get('legal'),
        ];
    }

    /**
     * Check if a menu location has items.
     */
    public function has(string $location): bool
    {
        $menu = $this->get($location);
        return $menu && !empty($menu->items);
    }
}
```

### app/Services/ImageService.php

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Timber\Image;
use Timber\Timber;

final class ImageService
{
    /**
     * Get responsive image data.
     */
    public function responsive(int|string|null $imageId, array $sizes = []): ?array
    {
        if (!$imageId) {
            return null;
        }

        $image = Timber::get_image($imageId);

        if (!$image) {
            return null;
        }

        $defaultSizes = [
            'thumbnail' => 150,
            'medium' => 300,
            'medium_large' => 768,
            'large' => 1024,
            'full' => null,
        ];

        $sizes = array_merge($defaultSizes, $sizes);

        return [
            'id' => $image->id,
            'src' => $image->src(),
            'alt' => $image->alt() ?: '',
            'width' => $image->width(),
            'height' => $image->height(),
            'srcset' => $this->buildSrcset($image, $sizes),
            'sizes' => $this->buildSizes($sizes),
        ];
    }

    /**
     * Build srcset attribute.
     */
    private function buildSrcset(Image $image, array $sizes): string
    {
        $srcset = [];

        foreach ($sizes as $size => $width) {
            $src = $image->src($size);
            if ($src && $width) {
                $srcset[] = "{$src} {$width}w";
            }
        }

        return implode(', ', $srcset);
    }

    /**
     * Build sizes attribute.
     */
    private function buildSizes(array $sizes): string
    {
        return '(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw';
    }

    /**
     * Get placeholder image.
     */
    public function placeholder(int $width = 800, int $height = 600): array
    {
        return [
            'src' => "https://via.placeholder.com/{$width}x{$height}",
            'alt' => '',
            'width' => $width,
            'height' => $height,
        ];
    }
}
```

### app/Services/OptionsService.php

```php
<?php

declare(strict_types=1);

namespace App\Services;

final class OptionsService
{
    private ?array $cache = null;

    /**
     * Get all theme options.
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = get_fields('options') ?: [];
        }

        return $this->cache;
    }

    /**
     * Get a specific option.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $options = $this->all();
        return $options[$key] ?? $default;
    }

    /**
     * Get social links.
     */
    public function socialLinks(): array
    {
        return $this->get('social_links', []);
    }

    /**
     * Get contact info.
     */
    public function contact(): array
    {
        return [
            'email' => $this->get('contact_email'),
            'phone' => $this->get('contact_phone'),
            'address' => $this->get('contact_address'),
        ];
    }

    /**
     * Get footer text.
     */
    public function footerText(): ?string
    {
        return $this->get('footer_text');
    }
}
```

---

## Hooks

### app/Hooks/ThemeHooks.php

```php
<?php

declare(strict_types=1);

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use App\Services\MenuService;

final readonly class ThemeHooks
{
    public function __construct(
        private MenuService $menus,
    ) {}

    #[AsAction('after_setup_theme')]
    public function setupTheme(): void
    {
        // Theme supports
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
        add_theme_support('html5', [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ]);
        add_theme_support('responsive-embeds');
        add_theme_support('wp-block-styles');
        add_theme_support('editor-styles');

        // Image sizes
        add_image_size('card', 400, 300, true);
        add_image_size('hero', 1920, 1080, true);

        // Register menus
        register_nav_menus([
            'header' => __('Menu principal', 'starter-theme'),
            'footer' => __('Menu footer', 'starter-theme'),
            'legal' => __('Mentions légales', 'starter-theme'),
        ]);
    }

    #[AsAction('init')]
    public function registerTaxonomies(): void
    {
        // Additional taxonomy configuration if needed
    }

    #[AsFilter('timber/twig')]
    public function extendTwig(\Twig\Environment $twig): \Twig\Environment
    {
        // Add custom Twig functions
        $twig->addFunction(new \Twig\TwigFunction('icon', [$this, 'renderIcon'], [
            'is_safe' => ['html'],
        ]));

        return $twig;
    }

    public function renderIcon(string $name, array $attrs = []): string
    {
        $class = $attrs['class'] ?? '';
        $path = get_template_directory() . "/assets/icons/{$name}.svg";

        if (!file_exists($path)) {
            return '';
        }

        $svg = file_get_contents($path);

        if ($class) {
            $svg = str_replace('<svg', "<svg class=\"{$class}\"", $svg);
        }

        return $svg;
    }

    #[AsFilter('the_content')]
    #[AsFilter('acf_the_content')]
    public function convertYoutubeToNocookie(string $content): string
    {
        if (str_contains($content, 'youtube.com')) {
            $content = str_replace(
                'youtube.com/embed/',
                'youtube-nocookie.com/embed/',
                $content
            );
        }

        return $content;
    }

    #[AsFilter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }

    #[AsFilter('excerpt_more')]
    public function excerptMore(): string
    {
        return '…';
    }
}
```

### app/Hooks/AdminHooks.php

```php
<?php

declare(strict_types=1);

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Views\ViewEngineInterface;

final readonly class AdminHooks
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    #[AsAction('admin_enqueue_scripts')]
    public function enqueueAdminAssets(): void
    {
        wp_enqueue_style(
            'theme-admin',
            get_template_directory_uri() . '/dist/css/admin.css',
            [],
            filemtime(get_template_directory() . '/dist/css/admin.css')
        );
    }

    #[AsAction('login_enqueue_scripts')]
    public function enqueueLoginAssets(): void
    {
        wp_enqueue_style(
            'theme-login',
            get_template_directory_uri() . '/dist/css/login.css',
            [],
            filemtime(get_template_directory() . '/dist/css/login.css')
        );
    }

    #[AsFilter('login_headerurl')]
    public function loginHeaderUrl(): string
    {
        return home_url('/');
    }

    #[AsFilter('login_headertext')]
    public function loginHeaderText(): string
    {
        return get_bloginfo('name');
    }

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

    #[AsFilter('admin_footer_text')]
    public function adminFooterText(): string
    {
        return $this->view->render('admin/footer-text', [
            'year' => date('Y'),
        ]);
    }
}
```

### app/Hooks/SecurityHooks.php

```php
<?php

declare(strict_types=1);

namespace App\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

final class SecurityHooks
{
    #[AsAction('init')]
    public function cleanupHead(): void
    {
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
    }

    #[AsFilter('wp_headers')]
    public function removeXPingback(array $headers): array
    {
        unset($headers['X-Pingback']);
        return $headers;
    }

    #[AsFilter('xmlrpc_enabled')]
    public function disableXmlRpc(): bool
    {
        return false;
    }

    #[AsFilter('login_errors')]
    public function genericLoginError(): string
    {
        return __('Identifiants incorrects.', 'starter-theme');
    }

    #[AsFilter('style_loader_src')]
    #[AsFilter('script_loader_src')]
    public function removeVersionFromAssets(string $src): string
    {
        if (strpos($src, 'ver=') && !strpos($src, content_url('themes'))) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }
}
```

---

## View Composers

### app/Views/Composers/GlobalComposer.php

```php
<?php

declare(strict_types=1);

namespace App\Views\Composers;

use Studiometa\Foehn\Attributes\AsViewComposer;
use Studiometa\Foehn\Contracts\ViewComposerInterface;
use App\Services\MenuService;
use App\Services\OptionsService;
use Timber\Site;

#[AsViewComposer('*')]
final readonly class GlobalComposer implements ViewComposerInterface
{
    public function __construct(
        private MenuService $menus,
        private OptionsService $options,
    ) {}

    public function compose(array $context): array
    {
        return array_merge($context, [
            'site' => new Site(),
            'menus' => $this->menus->all(),
            'options' => $this->options->all(),
            'social_links' => $this->options->socialLinks(),
            'contact' => $this->options->contact(),
            'current_year' => date('Y'),
            'is_home' => is_front_page(),
            'is_single' => is_single(),
            'is_archive' => is_archive(),
            'env' => wp_get_environment_type(),
        ]);
    }
}
```

### app/Views/Composers/SingleComposer.php

```php
<?php

declare(strict_types=1);

namespace App\Views\Composers;

use Studiometa\Foehn\Attributes\AsViewComposer;
use Studiometa\Foehn\Contracts\ViewComposerInterface;
use Timber\Timber;

#[AsViewComposer(['single', 'single-post'])]
final readonly class SingleComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        $post = $context['post'] ?? null;

        if (!$post) {
            return $context;
        }

        // Get related posts
        $relatedPosts = Timber::get_posts([
            'post_type' => 'post',
            'posts_per_page' => 3,
            'post__not_in' => [$post->ID],
            'category__in' => wp_get_post_categories($post->ID),
        ]);

        return array_merge($context, [
            'related_posts' => $relatedPosts,
            'reading_time' => $post->readingTime(),
            'categories' => $post->terms('category'),
            'tags' => $post->terms('post_tag'),
            'author' => $post->author(),
            'share_urls' => $this->getShareUrls($post),
        ]);
    }

    private function getShareUrls($post): array
    {
        $url = urlencode($post->link());
        $title = urlencode($post->title());

        return [
            'twitter' => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$url}",
            'linkedin' => "https://www.linkedin.com/shareArticle?mini=true&url={$url}",
            'email' => "mailto:?subject={$title}&body={$url}",
        ];
    }
}
```

### app/Views/Composers/ProductComposer.php

```php
<?php

declare(strict_types=1);

namespace App\Views\Composers;

use Studiometa\Foehn\Attributes\AsViewComposer;
use Studiometa\Foehn\Contracts\ViewComposerInterface;
use Timber\Timber;

#[AsViewComposer(['single-product', 'archive-product'])]
final readonly class ProductComposer implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        // Single product
        if (is_singular('product') && isset($context['post'])) {
            $product = $context['post'];

            $context['gallery'] = $product->galleryImages();
            $context['categories'] = $product->productCategories();

            // Related products
            $context['related_products'] = Timber::get_posts([
                'post_type' => 'product',
                'posts_per_page' => 4,
                'post__not_in' => [$product->ID],
                'tax_query' => [
                    [
                        'taxonomy' => 'product_category',
                        'terms' => wp_get_post_terms($product->ID, 'product_category', ['fields' => 'ids']),
                    ],
                ],
            ]);
        }

        // Archive
        if (is_post_type_archive('product') || is_tax('product_category')) {
            $context['product_categories'] = Timber::get_terms([
                'taxonomy' => 'product_category',
                'hide_empty' => true,
            ]);

            $context['current_category'] = is_tax('product_category')
                ? Timber::get_term(get_queried_object())
                : null;
        }

        return $context;
    }
}
```

---

## Template Controllers

### app/Http/Controllers/SingleController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Views\ViewEngineInterface;
use Timber\Timber;

#[AsTemplateController('single', 'single-*')]
final readonly class SingleController
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function __invoke(): string
    {
        $context = Timber::context();
        $post = $context['post'];

        // Password protected
        if (post_password_required($post->ID)) {
            return $this->view->render('pages/password', $context);
        }

        // Find the right template
        $templates = [
            "pages/single-{$post->post_type}-{$post->slug}",
            "pages/single-{$post->post_type}",
            'pages/single',
        ];

        return $this->view->renderFirst($templates, $context);
    }
}
```

### app/Http/Controllers/ArchiveController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Views\ViewEngineInterface;
use Timber\Timber;

#[AsTemplateController('archive', 'archive-*', 'home', 'category', 'tag', 'tax-*')]
final readonly class ArchiveController
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function __invoke(): string
    {
        $context = Timber::context();

        // Posts are already in context via Timber::context()
        // Add pagination
        if (isset($context['posts']) && method_exists($context['posts'], 'pagination')) {
            $context['pagination'] = $context['posts']->pagination([
                'mid_size' => 2,
                'end_size' => 1,
            ]);
        }

        // Archive title
        $context['archive_title'] = $this->getArchiveTitle();
        $context['archive_description'] = get_the_archive_description();

        // Find template
        $templates = $this->getTemplates();

        return $this->view->renderFirst($templates, $context);
    }

    private function getArchiveTitle(): string
    {
        if (is_category()) {
            return single_cat_title('', false);
        }

        if (is_tag()) {
            return single_tag_title('', false);
        }

        if (is_tax()) {
            return single_term_title('', false);
        }

        if (is_post_type_archive()) {
            return post_type_archive_title('', false);
        }

        if (is_home()) {
            return __('Blog', 'starter-theme');
        }

        return get_the_archive_title();
    }

    private function getTemplates(): array
    {
        $templates = [];

        if (is_post_type_archive()) {
            $postType = get_query_var('post_type');
            $templates[] = "pages/archive-{$postType}";
        }

        if (is_tax()) {
            $term = get_queried_object();
            $templates[] = "pages/taxonomy-{$term->taxonomy}-{$term->slug}";
            $templates[] = "pages/taxonomy-{$term->taxonomy}";
        }

        if (is_category()) {
            $templates[] = 'pages/category';
        }

        if (is_tag()) {
            $templates[] = 'pages/tag';
        }

        $templates[] = 'pages/archive';

        return $templates;
    }
}
```

### app/Http/Controllers/SearchController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Views\ViewEngineInterface;
use Timber\Timber;

#[AsTemplateController('search')]
final readonly class SearchController
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public function __invoke(): string
    {
        $context = Timber::context();

        $context['search_query'] = get_search_query();
        $context['found_posts'] = $GLOBALS['wp_query']->found_posts;

        if (isset($context['posts'])) {
            $context['pagination'] = $context['posts']->pagination();
        }

        return $this->view->render('pages/search', $context);
    }
}
```

---

## ACF Blocks

### app/Acf/Fragments/ButtonLinkBuilder.php

```php
<?php

declare(strict_types=1);

namespace App\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Reusable button/link field fragment.
 *
 * Creates the following fields:
 * - {$name}_link (link)
 * - {$name}_style (select)
 */
final class ButtonLinkBuilder extends FieldsBuilder
{
    public function __construct(
        string $name = 'button',
        string $label = 'Button',
        bool $required = false,
    ) {
        parent::__construct($name, ['label' => $label]);

        $this
            ->addLink('link', [
                'label' => 'Lien',
                'required' => $required,
                'return_format' => 'array',
            ])
            ->addSelect('style', [
                'label' => 'Style',
                'choices' => [
                    'primary' => 'Principal',
                    'secondary' => 'Secondaire',
                    'outline' => 'Contour',
                ],
                'default_value' => 'primary',
            ]);
    }
}
```

### app/Acf/Fragments/ResponsiveImageBuilder.php

```php
<?php

declare(strict_types=1);

namespace App\Acf\Fragments;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Responsive image fragment with desktop/mobile variants.
 *
 * Creates the following fields:
 * - {$name}_desktop (image)
 * - {$name}_mobile (image)
 */
final class ResponsiveImageBuilder extends FieldsBuilder
{
    public function __construct(
        string $name = 'image',
        string $label = 'Image',
        bool $required = false,
    ) {
        parent::__construct($name, ['label' => $label]);

        $this
            ->addImage('desktop', [
                'label' => 'Desktop',
                'instructions' => 'Recommandé : 1920×1080px',
                'required' => $required,
                'return_format' => 'id',
                'preview_size' => 'medium',
            ])
            ->addImage('mobile', [
                'label' => 'Mobile',
                'instructions' => 'Recommandé : 768×1024px. Laisser vide pour utiliser l\'image desktop.',
                'return_format' => 'id',
                'preview_size' => 'medium',
            ]);
    }
}
```

### app/Blocks/Acf/Hero/HeroBlock.php

```php
<?php

declare(strict_types=1);

namespace App\Blocks\Acf\Hero;

use App\Acf\Fragments\ButtonLinkBuilder;
use App\Acf\Fragments\ResponsiveImageBuilder;
use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Views\ViewEngineInterface;
use App\Services\ImageService;
use StoutLogic\AcfBuilder\FieldsBuilder;

#[AsAcfBlock(
    name: 'hero',
    title: 'Hero',
    category: 'starter-theme',
    icon: 'cover-image',
    keywords: ['hero', 'banner', 'header'],
    mode: 'preview',
)]
final readonly class HeroBlock implements AcfBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
        private ImageService $images,
    ) {}

    public static function fields(): FieldsBuilder
    {
        $builder = new FieldsBuilder('hero', [
            'title' => 'Hero',
        ]);

        $builder
            ->addTab('content', ['label' => 'Contenu'])
            ->addWysiwyg('content', [
                'label' => 'Contenu',
                'tabs' => 'all',
                'toolbar' => 'full',
                'media_upload' => false,
            ])
            // Use the reusable ButtonLinkBuilder fragment
            ->appendFields(new ButtonLinkBuilder('cta', 'Call to action'))

            ->addTab('media', ['label' => 'Média'])
            // Use the reusable ResponsiveImageBuilder fragment
            ->appendFields(new ResponsiveImageBuilder('background', 'Image de fond'))
            ->addTrueFalse('has_overlay', [
                'label' => 'Ajouter un overlay',
                'default_value' => true,
            ])

            ->addTab('settings', ['label' => 'Paramètres'])
            ->addSelect('height', [
                'label' => 'Hauteur',
                'choices' => [
                    'auto' => 'Automatique',
                    'full' => 'Plein écran',
                    'large' => 'Grande (80vh)',
                    'medium' => 'Moyenne (60vh)',
                ],
                'default_value' => 'large',
            ])
            ->addSelect('text_align', [
                'label' => 'Alignement du texte',
                'choices' => [
                    'left' => 'Gauche',
                    'center' => 'Centré',
                    'right' => 'Droite',
                ],
                'default_value' => 'center',
            ]);

        return $builder;
    }

    public function compose(array $block, array $fields): array
    {
        return [
            'block_id' => $block['id'],
            'content' => $fields['content'] ?? '',
            'cta' => $fields['cta'] ?? null,
            'background' => $this->images->responsive($fields['background_image'] ?? null),
            'has_overlay' => $fields['has_overlay'] ?? true,
            'height' => $fields['height'] ?? 'large',
            'text_align' => $fields['text_align'] ?? 'center',
            'class' => $this->buildClasses($block, $fields),
            'is_preview' => $block['is_preview'] ?? false,
        ];
    }

    public function render(array $context): string
    {
        return $this->view->render('blocks/hero', $context);
    }

    private function buildClasses(array $block, array $fields): string
    {
        $classes = ['block-hero'];
        $classes[] = 'block-hero--' . ($fields['height'] ?? 'large');
        $classes[] = 'block-hero--align-' . ($fields['text_align'] ?? 'center');

        if ($fields['has_overlay'] ?? true) {
            $classes[] = 'block-hero--has-overlay';
        }

        if (!empty($block['className'])) {
            $classes[] = $block['className'];
        }

        if (!empty($block['align'])) {
            $classes[] = 'align' . $block['align'];
        }

        return implode(' ', $classes);
    }
}
```

---

## Native Blocks (Interactivity)

### app/Blocks/Native/Accordion/AccordionBlock.php

```php
<?php

declare(strict_types=1);

namespace App\Blocks\Native\Accordion;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
use Studiometa\Foehn\Views\ViewEngineInterface;
use WP_Block;

#[AsBlock(
    name: 'starter-theme/accordion',
    title: 'Accordion',
    category: 'starter-theme',
    icon: 'list-view',
    keywords: ['accordion', 'faq', 'collapse'],
    interactivity: true,
    supports: [
        'align' => ['wide', 'full'],
        'html' => false,
        'color' => [
            'background' => true,
            'text' => true,
        ],
        'spacing' => [
            'margin' => true,
            'padding' => true,
        ],
    ],
)]
final readonly class AccordionBlock implements InteractiveBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
    ) {}

    public static function attributes(): array
    {
        return [
            'items' => [
                'type' => 'array',
                'default' => [],
            ],
            'allowMultiple' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'defaultOpen' => [
                'type' => 'number',
                'default' => -1,
            ],
        ];
    }

    public static function supports(): array
    {
        return [
            'interactivity' => true,
        ];
    }

    public static function initialState(): array
    {
        return [];
    }

    public function initialContext(array $attributes): array
    {
        $defaultOpen = $attributes['defaultOpen'] ?? -1;
        $items = $attributes['items'] ?? [];

        $openItems = [];
        if ($defaultOpen >= 0 && isset($items[$defaultOpen])) {
            $openItems[] = $defaultOpen;
        }

        return [
            'openItems' => $openItems,
            'allowMultiple' => $attributes['allowMultiple'] ?? false,
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        $items = $attributes['items'] ?? [];

        // Add unique IDs to items
        foreach ($items as $index => &$item) {
            $item['id'] = $item['id'] ?? "item-{$index}";
            $item['index'] = $index;
        }

        return [
            'wrapper_attributes' => get_block_wrapper_attributes([
                'class' => 'accordion-block',
            ]),
            'namespace' => 'starter-theme/accordion',
            'context' => $this->initialContext($attributes),
            'items' => $items,
            'allow_multiple' => $attributes['allowMultiple'] ?? false,
        ];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return $this->view->render('blocks/accordion',
            $this->compose($attributes, $content, $block)
        );
    }
}
```

---

## Block Patterns

### app/Patterns/HeroFullWidth.php

```php
<?php

declare(strict_types=1);

namespace App\Patterns;

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Contracts\BlockPatternInterface;

#[AsBlockPattern(
    name: 'starter-theme/hero-full-width',
    title: 'Hero pleine largeur',
    categories: ['starter-theme-heroes'],
    keywords: ['hero', 'banner', 'full-width'],
    blockTypes: ['core/cover', 'acf/hero'],
)]
final readonly class HeroFullWidth implements BlockPatternInterface
{
    public function compose(): array
    {
        return [
            'default_heading' => __('Bienvenue sur notre site', 'starter-theme'),
            'default_text' => __('Découvrez nos services et nos produits exceptionnels.', 'starter-theme'),
            'default_cta_text' => __('En savoir plus', 'starter-theme'),
            'default_cta_url' => '#',
            'overlay_opacity' => 50,
            'min_height' => '80vh',
        ];
    }
}
```

### templates/patterns/hero-full-width.twig

```twig
{# Hero Full Width Pattern #}

<!-- wp:cover {"overlayColor":"primary","minHeight":80,"minHeightUnit":"vh","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}}} -->
<div class="wp-block-cover alignfull" style="min-height:{{ min_height }};padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)">
    <span aria-hidden="true" class="wp-block-cover__background has-primary-background-color" style="opacity:0.{{ overlay_opacity }}"></span>
    <div class="wp-block-cover__inner-container">

        <!-- wp:group {"layout":{"type":"constrained","contentSize":"800px"}} -->
        <div class="wp-block-group">

            <!-- wp:heading {"textAlign":"center","level":1,"textColor":"white"} -->
            <h1 class="wp-block-heading has-text-align-center has-white-color has-text-color">{{ default_heading }}</h1>
            <!-- /wp:heading -->

            <!-- wp:paragraph {"align":"center","textColor":"white","fontSize":"large"} -->
            <p class="has-text-align-center has-white-color has-text-color has-large-font-size">{{ default_text }}</p>
            <!-- /wp:paragraph -->

            <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|medium"}}}} -->
            <div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--medium)">
                <!-- wp:button {"backgroundColor":"white","textColor":"primary"} -->
                <div class="wp-block-button">
                    <a class="wp-block-button__link has-primary-color has-white-background-color has-text-color has-background" href="{{ default_cta_url }}">{{ default_cta_text }}</a>
                </div>
                <!-- /wp:button -->
            </div>
            <!-- /wp:buttons -->

        </div>
        <!-- /wp:group -->

    </div>
</div>
<!-- /wp:cover -->
```

---

## Theme Configuration (FSE)

### app/Theme/ThemeConfig.php

```php
<?php

declare(strict_types=1);

namespace App\Theme;

use Studiometa\Foehn\Attributes\AsThemeConfig;
use Studiometa\Foehn\Contracts\ThemeConfigInterface;

#[AsThemeConfig]
final class ThemeConfig implements ThemeConfigInterface
{
    public static function settings(): array
    {
        return [
            'appearanceTools' => true,
            'useRootPaddingAwareAlignments' => true,

            'color' => [
                'palette' => [
                    ['name' => 'Primary', 'slug' => 'primary', 'color' => '#1a1a2e'],
                    ['name' => 'Secondary', 'slug' => 'secondary', 'color' => '#16213e'],
                    ['name' => 'Accent', 'slug' => 'accent', 'color' => '#e94560'],
                    ['name' => 'Light', 'slug' => 'light', 'color' => '#f8f9fa'],
                    ['name' => 'Dark', 'slug' => 'dark', 'color' => '#212529'],
                    ['name' => 'White', 'slug' => 'white', 'color' => '#ffffff'],
                    ['name' => 'Black', 'slug' => 'black', 'color' => '#000000'],
                ],
                'gradients' => [
                    [
                        'name' => 'Primary to Secondary',
                        'slug' => 'primary-to-secondary',
                        'gradient' => 'linear-gradient(135deg, var(--wp--preset--color--primary) 0%, var(--wp--preset--color--secondary) 100%)',
                    ],
                ],
            ],

            'typography' => [
                'fontFamilies' => [
                    [
                        'name' => 'System',
                        'slug' => 'system',
                        'fontFamily' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
                    ],
                    [
                        'name' => 'Serif',
                        'slug' => 'serif',
                        'fontFamily' => 'Georgia, "Times New Roman", Times, serif',
                    ],
                ],
                'fontSizes' => [
                    ['name' => 'Small', 'slug' => 'small', 'size' => '0.875rem'],
                    ['name' => 'Medium', 'slug' => 'medium', 'size' => '1rem'],
                    ['name' => 'Large', 'slug' => 'large', 'size' => '1.25rem'],
                    ['name' => 'X-Large', 'slug' => 'x-large', 'size' => '1.5rem'],
                    ['name' => '2X-Large', 'slug' => '2x-large', 'size' => '2rem'],
                    ['name' => '3X-Large', 'slug' => '3x-large', 'size' => '3rem'],
                ],
            ],

            'spacing' => [
                'units' => ['px', 'em', 'rem', '%', 'vw', 'vh'],
                'spacingSizes' => [
                    ['name' => 'XS', 'slug' => 'xs', 'size' => '0.5rem'],
                    ['name' => 'Small', 'slug' => 'small', 'size' => '1rem'],
                    ['name' => 'Medium', 'slug' => 'medium', 'size' => '2rem'],
                    ['name' => 'Large', 'slug' => 'large', 'size' => '4rem'],
                    ['name' => 'XL', 'slug' => 'xl', 'size' => '6rem'],
                ],
            ],

            'layout' => [
                'contentSize' => '800px',
                'wideSize' => '1200px',
            ],

            'custom' => [
                'lineHeight' => [
                    'small' => 1.2,
                    'medium' => 1.5,
                    'large' => 1.8,
                ],
            ],
        ];
    }

    public static function styles(): array
    {
        return [
            'color' => [
                'background' => 'var(--wp--preset--color--white)',
                'text' => 'var(--wp--preset--color--dark)',
            ],
            'typography' => [
                'fontFamily' => 'var(--wp--preset--font-family--system)',
                'fontSize' => 'var(--wp--preset--font-size--medium)',
                'lineHeight' => '1.6',
            ],
            'spacing' => [
                'blockGap' => 'var(--wp--preset--spacing--medium)',
            ],
            'elements' => [
                'link' => [
                    'color' => [
                        'text' => 'var(--wp--preset--color--accent)',
                    ],
                    ':hover' => [
                        'color' => [
                            'text' => 'var(--wp--preset--color--primary)',
                        ],
                    ],
                ],
                'heading' => [
                    'color' => [
                        'text' => 'var(--wp--preset--color--primary)',
                    ],
                    'typography' => [
                        'fontWeight' => '700',
                        'lineHeight' => '1.2',
                    ],
                ],
                'button' => [
                    'color' => [
                        'background' => 'var(--wp--preset--color--accent)',
                        'text' => 'var(--wp--preset--color--white)',
                    ],
                    'border' => [
                        'radius' => '4px',
                    ],
                    ':hover' => [
                        'color' => [
                            'background' => 'var(--wp--preset--color--primary)',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function customTemplates(): array
    {
        return [
            [
                'name' => 'blank',
                'title' => 'Blank',
                'postTypes' => ['page', 'post'],
            ],
            [
                'name' => 'full-width',
                'title' => 'Full Width',
                'postTypes' => ['page'],
            ],
            [
                'name' => 'sidebar-left',
                'title' => 'Sidebar Left',
                'postTypes' => ['page', 'post'],
            ],
        ];
    }

    public static function templateParts(): array
    {
        return [
            ['name' => 'header', 'title' => 'Header', 'area' => 'header'],
            ['name' => 'header-minimal', 'title' => 'Header Minimal', 'area' => 'header'],
            ['name' => 'footer', 'title' => 'Footer', 'area' => 'footer'],
            ['name' => 'sidebar', 'title' => 'Sidebar', 'area' => 'uncategorized'],
        ];
    }

    public static function blockCategories(): array
    {
        return [
            [
                'slug' => 'starter-theme',
                'title' => 'Starter Theme',
                'icon' => 'star-filled',
            ],
            [
                'slug' => 'starter-theme-heroes',
                'title' => 'Starter Theme - Heroes',
                'icon' => 'cover-image',
            ],
        ];
    }
}
```

---

## Templates Twig

### templates/layouts/base.twig

```twig
<!DOCTYPE html>
<html {{ site.language_attributes }}>
<head>
    <meta charset="{{ site.charset }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{ function('wp_head') }}
</head>
<body class="{{ body_class }}">
    {{ function('wp_body_open') }}

    <a class="skip-link screen-reader-text" href="#main">
        {{ 'Aller au contenu'|trans }}
    </a>

    {% block header %}
        {% include 'components/header.twig' %}
    {% endblock %}

    <main id="main" class="site-main">
        {% block content %}{% endblock %}
    </main>

    {% block footer %}
        {% include 'components/footer.twig' %}
    {% endblock %}

    {{ function('wp_footer') }}
</body>
</html>
```

### templates/pages/single.twig

```twig
{% extends 'layouts/base.twig' %}

{% block content %}
<article class="post post--single" id="post-{{ post.ID }}">

    <header class="post__header">
        <div class="container">
            {% include 'components/breadcrumb.twig' %}

            <h1 class="post__title">{{ post.title }}</h1>

            {% include 'partials/meta-post.twig' %}
        </div>
    </header>

    {% if post.thumbnail %}
    <figure class="post__thumbnail">
        <img
            src="{{ post.thumbnail.src('large') }}"
            alt="{{ post.thumbnail.alt }}"
            width="{{ post.thumbnail.width }}"
            height="{{ post.thumbnail.height }}"
            loading="eager"
        >
    </figure>
    {% endif %}

    <div class="post__content container">
        <div class="post__body prose">
            {{ post.content }}
        </div>

        {% if tags %}
        <footer class="post__footer">
            <div class="post__tags">
                {% for tag in tags %}
                <a href="{{ tag.link }}" class="tag">{{ tag.name }}</a>
                {% endfor %}
            </div>

            {% include 'components/social-share.twig' %}
        </footer>
        {% endif %}
    </div>

</article>

{% if related_posts %}
<section class="related-posts">
    <div class="container">
        <h2>{{ 'Articles similaires'|trans }}</h2>

        <div class="grid grid--3">
            {% for related in related_posts %}
                {% include 'components/card-post.twig' with { post: related } %}
            {% endfor %}
        </div>
    </div>
</section>
{% endif %}
{% endblock %}
```

### templates/blocks/hero.twig

```twig
{#
  Hero Block

  Variables:
    - block_id: string
    - content: string (WYSIWYG)
    - cta: array|null (link field)
    - background: array|null (responsive image)
    - has_overlay: bool
    - height: string (full|large|medium|auto)
    - text_align: string (left|center|right)
    - class: string
    - is_preview: bool
#}

<section
    class="{{ class }}"
    id="{{ block_id }}"
    {% if background %}
    style="--hero-bg: url('{{ background.src }}');"
    {% endif %}
>
    {% if background %}
    <picture class="block-hero__background">
        <img
            src="{{ background.src }}"
            srcset="{{ background.srcset }}"
            sizes="100vw"
            alt="{{ background.alt }}"
            loading="eager"
        >
    </picture>
    {% endif %}

    {% if has_overlay %}
    <div class="block-hero__overlay"></div>
    {% endif %}

    <div class="block-hero__container container">
        <div class="block-hero__content">
            {% if content %}
            <div class="block-hero__text prose">
                {{ content|raw }}
            </div>
            {% endif %}

            {% if cta %}
            <div class="block-hero__actions">
                <a
                    href="{{ cta.url }}"
                    class="button button--primary"
                    {% if cta.target %}target="{{ cta.target }}"{% endif %}
                >
                    {{ cta.title }}
                </a>
            </div>
            {% endif %}
        </div>
    </div>
</section>

{% if is_preview and not content and not background %}
<div class="block-preview-placeholder">
    <p>{{ 'Ajoutez du contenu et/ou une image de fond'|trans }}</p>
</div>
{% endif %}
```

### templates/blocks/accordion.twig

```twig
{#
  Interactive Accordion Block

  Variables:
    - wrapper_attributes: string
    - namespace: string
    - context: array (Interactivity context)
    - items: array
    - allow_multiple: bool
#}

<div
    {{ wrapper_attributes|raw }}
    data-wp-interactive="{{ namespace }}"
    data-wp-context='{{ context|json_encode }}'
>
    {% for item in items %}
    <div
        class="accordion-block__item"
        data-wp-class--is-open="context.openItems.includes({{ item.index }})"
    >
        <button
            type="button"
            class="accordion-block__trigger"
            id="accordion-trigger-{{ item.id }}"
            aria-controls="accordion-panel-{{ item.id }}"
            data-wp-on--click="actions.toggle"
            data-wp-bind--aria-expanded="context.openItems.includes({{ item.index }})"
            data-item-index="{{ item.index }}"
        >
            <span class="accordion-block__title">{{ item.title }}</span>
            <span class="accordion-block__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="24" height="24">
                    <path d="M7 10l5 5 5-5z" fill="currentColor"/>
                </svg>
            </span>
        </button>

        <div
            class="accordion-block__panel"
            id="accordion-panel-{{ item.id }}"
            aria-labelledby="accordion-trigger-{{ item.id }}"
            data-wp-bind--hidden="!context.openItems.includes({{ item.index }})"
            role="region"
        >
            <div class="accordion-block__content prose">
                {{ item.content|raw }}
            </div>
        </div>
    </div>
    {% endfor %}
</div>
```

### templates/components/card-post.twig

```twig
{#
  Post Card Component

  Variables:
    - post: Timber\Post
    - show_excerpt: bool (default: true)
    - show_date: bool (default: true)
    - show_category: bool (default: true)
#}

{% set show_excerpt = show_excerpt ?? true %}
{% set show_date = show_date ?? true %}
{% set show_category = show_category ?? true %}

<article class="card-post">
    <a href="{{ post.link }}" class="card-post__link">
        {% if post.thumbnail %}
        <figure class="card-post__thumbnail">
            <img
                src="{{ post.thumbnail.src('card') }}"
                alt="{{ post.thumbnail.alt }}"
                loading="lazy"
                width="400"
                height="300"
            >
        </figure>
        {% else %}
        <figure class="card-post__thumbnail card-post__thumbnail--placeholder">
            {{ icon('image') }}
        </figure>
        {% endif %}

        <div class="card-post__body">
            {% if show_category and post.category %}
            <span class="card-post__category">
                {{ post.category.name }}
            </span>
            {% endif %}

            <h3 class="card-post__title">{{ post.title }}</h3>

            {% if show_excerpt %}
            <p class="card-post__excerpt">
                {{ post.safeExcerpt(120) }}
            </p>
            {% endif %}

            {% if show_date %}
            <time class="card-post__date" datetime="{{ post.date('c') }}">
                {{ post.date('j F Y') }}
            </time>
            {% endif %}
        </div>
    </a>
</article>
```

---

## Summary

Ce thème exemple démontre :

| Feature                           | Fichier(s)                                  |
| --------------------------------- | ------------------------------------------- |
| **Bootstrap minimal**             | `functions.php` (1 ligne)                   |
| **Post Types**                    | `app/Models/Product.php`, `Testimonial.php` |
| **Taxonomies**                    | `app/Taxonomies/ProductCategory.php`        |
| **Hooks déclaratifs**             | `app/Hooks/ThemeHooks.php`, etc.            |
| **View Composers**                | `app/Views/Composers/*.php`                 |
| **Template Controllers**          | `app/Http/Controllers/*.php`                |
| **ACF Field Fragments**           | `app/Acf/Fragments/ButtonLinkBuilder.php`   |
| **ACF Blocks**                    | `app/Blocks/Acf/Hero/HeroBlock.php`         |
| **Native Blocks + Interactivity** | `app/Blocks/Native/Accordion/`              |
| **Block Patterns**                | `app/Patterns/HeroFullWidth.php`            |
| **FSE theme.json**                | `app/Theme/ThemeConfig.php`                 |
| **Services avec DI**              | `app/Services/*.php`                        |
| **Templates Twig**                | `templates/**/*.twig`                       |
