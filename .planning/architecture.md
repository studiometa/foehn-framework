# Architecture: studiometa/wp-tempest

## 1. Vue d'ensemble

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           Theme / Plugin                                │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                         app/                                     │   │
│  │   Blocks/  Models/  Http/  Views/  Services/  Patterns/         │   │
│  └─────────────────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────────────┤
│                       studiometa/wp-tempest                             │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐     │
│  │  Kernel  │ │Discovery │ │  Views   │ │  Blocks  │ │   FSE    │     │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘     │
├─────────────────────────────────────────────────────────────────────────┤
│                        tempest/framework                                │
│        container / discovery / reflection / console / view              │
├─────────────────────────────────────────────────────────────────────────┤
│                     timber/timber + WordPress                           │
└─────────────────────────────────────────────────────────────────────────┘
```

## 2. Structure du package

```
studiometa/wp-tempest/
├── src/
│   ├── Kernel.php
│   │
│   ├── Attributes/
│   │   ├── AsAction.php
│   │   ├── AsFilter.php
│   │   ├── AsShortcode.php
│   │   ├── AsPostType.php
│   │   ├── AsTaxonomy.php
│   │   ├── AsBlock.php
│   │   ├── AsAcfBlock.php
│   │   ├── AsBlockPattern.php
│   │   ├── AsViewComposer.php
│   │   ├── AsTemplateController.php
│   │   └── AsRestRoute.php
│   │
│   ├── Discovery/
│   │   ├── HookDiscovery.php
│   │   ├── PostTypeDiscovery.php
│   │   ├── TaxonomyDiscovery.php
│   │   ├── BlockDiscovery.php
│   │   ├── AcfBlockDiscovery.php
│   │   ├── BlockPatternDiscovery.php
│   │   ├── ViewComposerDiscovery.php
│   │   ├── TemplateControllerDiscovery.php
│   │   ├── ShortcodeDiscovery.php
│   │   └── RestRouteDiscovery.php
│   │
│   ├── Contracts/
│   │   ├── BlockInterface.php
│   │   ├── AcfBlockInterface.php
│   │   ├── ViewComposerInterface.php
│   │   ├── TemplateControllerInterface.php
│   │   └── ConfiguresPostType.php
│   │
│   ├── Blocks/
│   │   ├── BlockDefinition.php
│   │   ├── BlockRenderer.php
│   │   ├── BlockAssetManager.php
│   │   ├── AcfBlockRenderer.php
│   │   └── InteractivityBridge.php
│   │
│   ├── Views/
│   │   ├── ViewEngine.php
│   │   ├── TimberViewEngine.php
│   │   ├── ViewComposerRegistry.php
│   │   └── TemplateResolver.php
│   │
│   ├── PostTypes/
│   │   ├── PostTypeBuilder.php
│   │   ├── TaxonomyBuilder.php
│   │   └── TimberClassMap.php
│   │
│   ├── FSE/
│   │   ├── ThemeJsonGenerator.php
│   │   ├── BlockPatternRegistry.php
│   │   └── TemplatePartRegistry.php
│   │
│   ├── Console/
│   │   ├── MakeBlockCommand.php
│   │   ├── MakePostTypeCommand.php
│   │   ├── MakeComposerCommand.php
│   │   ├── BuildThemeJsonCommand.php
│   │   ├── DiscoveryCacheCommand.php
│   │   └── DiscoveryClearCommand.php
│   │
│   ├── Config/
│   │   └── WPTempestConfig.php
│   │
│   └── helpers.php
│
├── stubs/
│   ├── block.php.stub
│   ├── acf-block.php.stub
│   ├── post-type.php.stub
│   ├── taxonomy.php.stub
│   ├── view-composer.php.stub
│   └── template-controller.php.stub
│
├── config/
│   └── wp-tempest.php
│
├── tests/
│   ├── Unit/
│   └── Integration/
│
├── composer.json
├── phpunit.xml
├── phpstan.neon
└── README.md
```

## 3. Composants principaux

### 3.1 Kernel

```php
<?php

namespace Studiometa\WPTempest;

use Tempest\Core\Tempest;
use Tempest\Container\Container;

final class Kernel
{
    private static ?self $instance = null;
    private Container $container;
    private bool $booted = false;
    
    private function __construct(
        private readonly string $appPath,
        private readonly array $config = [],
    ) {}
    
    public static function boot(string $appPath, array $config = []): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        
        self::$instance = new self($appPath, $config);
        self::$instance->bootstrap();
        
        return self::$instance;
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Kernel not booted. Call Kernel::boot() first.');
        }
        return self::$instance;
    }
    
    public static function container(): Container
    {
        return self::getInstance()->container;
    }
    
    private function bootstrap(): void
    {
        // 1. Initialize Tempest
        $this->initializeTempest();
        
        // 2. Register core services
        $this->registerCoreServices();
        
        // 3. Hook into WordPress lifecycle
        add_action('after_setup_theme', [$this, 'onAfterSetupTheme'], 1);
        add_action('init', [$this, 'onInit'], 1);
        add_action('wp_loaded', [$this, 'onWpLoaded'], 1);
    }
    
    public function onAfterSetupTheme(): void
    {
        // Run discoveries that need early registration
        $this->runEarlyDiscoveries();
    }
    
    public function onInit(): void
    {
        // Run main discoveries (post types, taxonomies, blocks)
        $this->runMainDiscoveries();
        $this->booted = true;
    }
    
    public function onWpLoaded(): void
    {
        // Run late discoveries (template controllers, etc.)
        $this->runLateDiscoveries();
    }
}
```

### 3.2 Attributs

```php
<?php
// src/Attributes/AsAction.php

namespace Studiometa\WPTempest\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsAction
{
    public function __construct(
        public string $hook,
        public int $priority = 10,
        public int $acceptedArgs = 1,
    ) {}
}
```

```php
<?php
// src/Attributes/AsPostType.php

namespace Studiometa\WPTempest\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsPostType
{
    public function __construct(
        public string $name,
        public ?string $singular = null,
        public ?string $plural = null,
        public bool $public = true,
        public bool $hasArchive = false,
        public bool $showInRest = true,
        public ?string $menuIcon = null,
        public array $supports = ['title', 'editor', 'thumbnail'],
        public array $taxonomies = [],
    ) {}
}
```

```php
<?php
// src/Attributes/AsBlock.php

namespace Studiometa\WPTempest\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBlock
{
    public function __construct(
        public string $name,
        public string $title,
        public string $category = 'widgets',
        public ?string $icon = null,
        public ?string $description = null,
        public array $keywords = [],
        public array $supports = [],
        public ?string $parent = null,
        public array $ancestor = [],
        public bool $hasInteractivity = false,
    ) {}
}
```

```php
<?php
// src/Attributes/AsAcfBlock.php

namespace Studiometa\WPTempest\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAcfBlock
{
    public function __construct(
        public string $name,
        public string $title,
        public string $category = 'layout',
        public ?string $icon = null,
        public ?string $description = null,
        public array $keywords = [],
        public string $mode = 'preview',
        public array $supports = ['align' => false],
        public ?string $renderTemplate = null,
        public array $allowedPostTypes = [],
    ) {}
}
```

```php
<?php
// src/Attributes/AsViewComposer.php

namespace Studiometa\WPTempest\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsViewComposer
{
    /**
     * @param string|string[] $templates Template patterns (supports wildcards: 'single-*')
     */
    public function __construct(
        public string|array $templates,
        public int $priority = 10,
    ) {}
}
```

### 3.3 Discoveries

```php
<?php
// src/Discovery/PostTypeDiscovery.php

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsPostType;
use Studiometa\WPTempest\Contracts\ConfiguresPostType;
use Studiometa\WPTempest\PostTypes\PostTypeBuilder;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Timber\Post;

final class PostTypeDiscovery implements Discovery
{
    use IsDiscovery;
    
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsPostType::class);
        
        if (!$attribute) {
            return;
        }
        
        // Verify class extends Timber\Post
        if (!$class->isSubclassOf(Post::class)) {
            throw new \InvalidArgumentException(
                "Class {$class->getName()} must extend Timber\\Post to use #[AsPostType]"
            );
        }
        
        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'class' => $class,
        ]);
    }
    
    public function apply(): void
    {
        foreach ($this->discoveryItems as $item) {
            $attribute = $item['attribute'];
            $class = $item['class'];
            
            // Register post type
            add_action('init', function() use ($attribute, $class) {
                $builder = new PostTypeBuilder($attribute->name);
                
                // Apply attribute config
                $builder
                    ->setLabels(
                        $attribute->singular ?? ucfirst($attribute->name),
                        $attribute->plural ?? ucfirst($attribute->name) . 's'
                    )
                    ->setPublic($attribute->public)
                    ->setHasArchive($attribute->hasArchive)
                    ->setShowInRest($attribute->showInRest)
                    ->setSupports($attribute->supports);
                
                if ($attribute->menuIcon) {
                    $builder->setMenuIcon($attribute->menuIcon);
                }
                
                // Allow class to customize
                if ($class->implementsInterface(ConfiguresPostType::class)) {
                    $builder = $class->getName()::configurePostType($builder);
                }
                
                $builder->register();
            }, 5);
            
            // Register Timber classmap
            add_filter('timber/post/classmap', function(array $map) use ($attribute, $class) {
                $map[$attribute->name] = $class->getName();
                return $map;
            });
        }
    }
}
```

```php
<?php
// src/Discovery/AcfBlockDiscovery.php

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;
use Studiometa\WPTempest\Blocks\AcfBlockRenderer;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

use function Tempest\get;

final class AcfBlockDiscovery implements Discovery
{
    use IsDiscovery;
    
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(AsAcfBlock::class);
        
        if (!$attribute) {
            return;
        }
        
        if (!$class->implementsInterface(AcfBlockInterface::class)) {
            throw new \InvalidArgumentException(
                "Class {$class->getName()} must implement AcfBlockInterface to use #[AsAcfBlock]"
            );
        }
        
        $this->discoveryItems->add($location, [
            'attribute' => $attribute,
            'class' => $class,
        ]);
    }
    
    public function apply(): void
    {
        add_action('acf/init', function() {
            foreach ($this->discoveryItems as $item) {
                $this->registerBlock($item['attribute'], $item['class']);
            }
        });
    }
    
    private function registerBlock(AsAcfBlock $attribute, ClassReflector $class): void
    {
        $className = $class->getName();
        
        // Register block type
        acf_register_block_type([
            'name' => $attribute->name,
            'title' => $attribute->title,
            'description' => $attribute->description,
            'category' => $attribute->category,
            'icon' => $attribute->icon,
            'keywords' => $attribute->keywords,
            'mode' => $attribute->mode,
            'supports' => $attribute->supports,
            'render_callback' => function($block, $content, $is_preview, $post_id) use ($className) {
                /** @var AcfBlockInterface $instance */
                $instance = get($className);
                $renderer = get(AcfBlockRenderer::class);
                
                echo $renderer->render($instance, $block, $is_preview);
            },
        ]);
        
        // Register fields
        if (method_exists($className, 'fields')) {
            $fields = $className::fields();
            $fields->setLocation('block', '==', 'acf/' . $attribute->name);
            acf_add_local_field_group($fields->build());
        }
    }
}
```

### 3.4 Contracts/Interfaces

```php
<?php
// src/Contracts/AcfBlockInterface.php

namespace Studiometa\WPTempest\Contracts;

use StoutLogic\AcfBuilder\FieldsBuilder;

interface AcfBlockInterface
{
    /**
     * Define ACF fields for this block.
     */
    public static function fields(): FieldsBuilder;
    
    /**
     * Compose data for the view.
     * 
     * @param array $block Block data from ACF
     * @param array $fields Field values from get_fields()
     * @return array Context for the template
     */
    public function compose(array $block, array $fields): array;
    
    /**
     * Render the block.
     * 
     * @param array $context Composed context
     * @return string Rendered HTML
     */
    public function render(array $context): string;
}
```

```php
<?php
// src/Contracts/ViewComposerInterface.php

namespace Studiometa\WPTempest\Contracts;

interface ViewComposerInterface
{
    /**
     * Compose additional context for the view.
     * 
     * @param array $context Current Timber context
     * @return array Modified context
     */
    public function compose(array $context): array;
}
```

### 3.5 Views

```php
<?php
// src/Views/TimberViewEngine.php

namespace Studiometa\WPTempest\Views;

use Timber\Timber;

final class TimberViewEngine implements ViewEngineInterface
{
    public function __construct(
        private readonly ViewComposerRegistry $composers,
    ) {}
    
    public function render(string $template, array $context = []): string
    {
        // Apply composers
        $context = $this->composers->compose($template, $context);
        
        return Timber::compile($this->resolveTemplate($template), $context);
    }
    
    public function renderFirst(array $templates, array $context = []): string
    {
        foreach ($templates as $template) {
            if ($this->exists($template)) {
                return $this->render($template, $context);
            }
        }
        
        throw new \RuntimeException('No template found: ' . implode(', ', $templates));
    }
    
    public function exists(string $template): bool
    {
        $resolved = $this->resolveTemplate($template);
        $locations = Timber::$dirname;
        
        foreach ((array) $locations as $location) {
            $path = get_template_directory() . '/' . $location . '/' . $resolved;
            if (file_exists($path)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function resolveTemplate(string $template): string
    {
        if (str_ends_with($template, '.twig')) {
            return $template;
        }
        return $template . '.twig';
    }
}
```

### 3.6 FSE Support

```php
<?php
// src/FSE/ThemeJsonGenerator.php

namespace Studiometa\WPTempest\FSE;

final class ThemeJsonGenerator
{
    public function generate(ThemeConfigInterface $config): array
    {
        return [
            '$schema' => 'https://schemas.wp.org/trunk/theme.json',
            'version' => 3,
            'settings' => $config->settings(),
            'styles' => $config->styles(),
            'customTemplates' => $config->customTemplates(),
            'templateParts' => $config->templateParts(),
        ];
    }
    
    public function write(ThemeConfigInterface $config, string $path): void
    {
        $json = $this->generate($config);
        
        file_put_contents(
            $path,
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
```

## 4. Lifecycle & Hooks

```
WordPress Request Lifecycle
===========================

1. mu-plugins loaded
2. plugins loaded
3. after_setup_theme     ──┐
   │                       │
   └─► Kernel::boot()      │ EARLY PHASE
       └─► initTempest()   │ - Theme supports
       └─► earlyDiscover() ─┘ - Timber init
   
4. init                  ──┐
   │                       │
   └─► mainDiscoveries()   │ MAIN PHASE
       ├─► PostTypes       │ - Post types
       ├─► Taxonomies      │ - Taxonomies  
       ├─► Blocks          │ - Blocks
       └─► AcfBlocks      ─┘ - ACF Blocks

5. wp_loaded             ──┐
   │                       │
   └─► lateDiscoveries()   │ LATE PHASE
       ├─► ViewComposers   │ - View composers
       └─► TemplateCtrl   ─┘ - Template controllers

6. template_redirect
7. template_include      ──► TemplateControllerDiscovery intercepts
8. wp_head
9. the_content
10. wp_footer
```

## 5. Configuration

```php
<?php
// config/wp-tempest.php

return [
    /*
    |--------------------------------------------------------------------------
    | Discovery Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('WP_ENV') === 'production',
        'path' => get_template_directory() . '/storage/cache',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | View Engine
    |--------------------------------------------------------------------------
    */
    'views' => [
        'engine' => 'timber', // 'timber', 'blade', 'tempest'
        'paths' => ['templates'],
        'compiled' => get_template_directory() . '/storage/views',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Blocks
    |--------------------------------------------------------------------------
    */
    'blocks' => [
        'namespace' => 'theme',
        'generate_json' => true, // Auto-generate block.json
        'assets_path' => 'dist/blocks',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | FSE
    |--------------------------------------------------------------------------
    */
    'fse' => [
        'generate_theme_json' => true,
        'theme_json_path' => get_template_directory() . '/theme.json',
    ],
];
```

## 6. Usage Examples

### Theme functions.php

```php
<?php
// functions.php

use Studiometa\WPTempest\Kernel;

// That's it!
Kernel::boot(__DIR__ . '/app');
```

### Post Type

```php
<?php
// app/Models/Product.php

namespace App\Models;

use Studiometa\WPTempest\Attributes\AsPostType;
use Studiometa\WPTempest\Contracts\ConfiguresPostType;
use Studiometa\WPTempest\PostTypes\PostTypeBuilder;
use Timber\Post;

#[AsPostType(
    name: 'product',
    singular: 'Produit',
    plural: 'Produits',
    public: true,
    hasArchive: true,
    menuIcon: 'dashicons-cart',
    supports: ['title', 'editor', 'thumbnail', 'excerpt'],
)]
final class Product extends Post implements ConfiguresPostType
{
    public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder
    {
        return $builder
            ->setRewrite(['slug' => 'boutique'])
            ->setTaxonomies(['product_category']);
    }
    
    public function getPrice(): ?float
    {
        return $this->meta('price') ? (float) $this->meta('price') : null;
    }
    
    public function getFormattedPrice(): string
    {
        $price = $this->getPrice();
        return $price ? number_format($price, 2, ',', ' ') . ' €' : 'Prix sur demande';
    }
}
```

### ACF Block

```php
<?php
// app/Blocks/Hero/HeroBlock.php

namespace App\Blocks\Hero;

use Studiometa\WPTempest\Attributes\AsAcfBlock;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;
use Studiometa\WPTempest\Views\ViewEngineInterface;
use StoutLogic\AcfBuilder\FieldsBuilder;
use App\Services\ImageService;

#[AsAcfBlock(
    name: 'hero',
    title: 'Hero Banner',
    category: 'layout',
    icon: 'cover-image',
    keywords: ['banner', 'header', 'hero'],
)]
final readonly class HeroBlock implements AcfBlockInterface
{
    public function __construct(
        private ViewEngineInterface $view,
        private ImageService $images,
    ) {}
    
    public static function fields(): FieldsBuilder
    {
        return (new FieldsBuilder('hero'))
            ->addImage('background', [
                'label' => 'Image de fond',
                'return_format' => 'id',
            ])
            ->addWysiwyg('content', [
                'label' => 'Contenu',
                'tabs' => 'all',
                'toolbar' => 'full',
            ])
            ->addLink('cta', [
                'label' => 'Call to action',
            ])
            ->addSelect('height', [
                'label' => 'Hauteur',
                'choices' => [
                    'auto' => 'Automatique',
                    'full' => 'Plein écran',
                    'half' => 'Demi-écran',
                ],
                'default_value' => 'auto',
            ]);
    }
    
    public function compose(array $block, array $fields): array
    {
        return [
            'block_id' => $block['id'],
            'background' => $this->images->responsive($fields['background'] ?? null),
            'content' => $fields['content'] ?? '',
            'cta' => $fields['cta'] ?? null,
            'height' => $fields['height'] ?? 'auto',
            'class' => $this->buildClass($block, $fields),
        ];
    }
    
    public function render(array $context): string
    {
        return $this->view->render('blocks/hero', $context);
    }
    
    private function buildClass(array $block, array $fields): string
    {
        $classes = ['block-hero'];
        $classes[] = 'block-hero--' . ($fields['height'] ?? 'auto');
        
        if (!empty($block['className'])) {
            $classes[] = $block['className'];
        }
        
        return implode(' ', $classes);
    }
}
```

### View Composer

```php
<?php
// app/Views/Composers/SingleComposer.php

namespace App\Views\Composers;

use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;
use App\Services\RelatedPostsService;
use App\Services\SeoService;

#[AsViewComposer(['single', 'single-*'])]
final readonly class SingleComposer implements ViewComposerInterface
{
    public function __construct(
        private RelatedPostsService $related,
        private SeoService $seo,
    ) {}
    
    public function compose(array $context): array
    {
        $post = $context['post'] ?? null;
        
        if (!$post) {
            return $context;
        }
        
        return array_merge($context, [
            'related_posts' => $this->related->forPost($post, 3),
            'seo' => $this->seo->forPost($post),
            'reading_time' => $this->calculateReadingTime($post->content()),
            'share_urls' => $this->getShareUrls($post),
        ]);
    }
    
    private function calculateReadingTime(string $content): int
    {
        $words = str_word_count(strip_tags($content));
        return max(1, (int) ceil($words / 200));
    }
    
    private function getShareUrls($post): array
    {
        $url = urlencode($post->link());
        $title = urlencode($post->title());
        
        return [
            'twitter' => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$url}",
            'linkedin' => "https://www.linkedin.com/shareArticle?mini=true&url={$url}&title={$title}",
        ];
    }
}
```

## 7. Migration depuis wp-toolkit

### Avant (wp-toolkit)

```php
// functions.php
$assets = new AssetsManager();
$managers = [
    $assets,
    new ThemeManager(),
    new ACFManager($assets),
    new CustomPostTypesManager(),
];
ManagerFactory::init($managers);

// CustomPostTypesManager.php
class CustomPostTypesManager implements ManagerInterface {
    public function run() {
        add_action('init', [$this, 'registerProduct']);
        add_filter('timber/post/classmap', [$this, 'setClassmap']);
    }
    
    public function registerProduct() {
        $cpt = new PostTypeBuilder('product');
        $cpt->set_labels('Produit', 'Produits')
            ->set_public(true)
            ->register();
    }
    
    public function setClassmap($map) {
        $map['product'] = Product::class;
        return $map;
    }
}
```

### Après (wp-tempest)

```php
// functions.php
Kernel::boot(__DIR__ . '/app');

// app/Models/Product.php
#[AsPostType(name: 'product', singular: 'Produit', plural: 'Produits')]
final class Product extends Post {}

// C'est tout !
```

## 8. Testing Strategy

```php
<?php
// tests/Unit/Discovery/PostTypeDiscoveryTest.php

use PHPUnit\Framework\TestCase;
use Studiometa\WPTempest\Discovery\PostTypeDiscovery;
use Studiometa\WPTempest\Attributes\AsPostType;

class PostTypeDiscoveryTest extends TestCase
{
    public function test_discovers_post_type_attribute(): void
    {
        $discovery = new PostTypeDiscovery();
        
        // Create mock class reflector
        $class = $this->createMockClassWithAttribute(
            AsPostType::class,
            ['name' => 'product']
        );
        
        $discovery->discover($this->createLocation(), $class);
        
        $this->assertCount(1, $discovery->getDiscoveryItems());
    }
    
    public function test_throws_if_class_does_not_extend_timber_post(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $discovery = new PostTypeDiscovery();
        $class = $this->createMockClassWithAttribute(
            AsPostType::class,
            ['name' => 'product'],
            extendsTimberPost: false
        );
        
        $discovery->discover($this->createLocation(), $class);
    }
}
```
