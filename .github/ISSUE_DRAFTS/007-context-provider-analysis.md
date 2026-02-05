# Analyze ContextProvider benefits vs current timber/context pattern

## Current pattern in WordPress/Timber projects

All projects use multiple `timber/context` filters in ThemeManager:

```php
class ThemeManager implements ManagerInterface {
    public function run() {
        add_filter('timber/context', [$this, 'add_app_env_to_context']);
        add_filter('timber/context', [$this, 'add_site_to_context']);
        add_filter('timber/context', [$this, 'add_menus_to_context']);
        add_filter('timber/context', [$this, 'add_options_to_context']);
        add_filter('timber/context', [$this, 'add_global_to_context']);
    }

    public function add_app_env_to_context(array $context) {
        $context['APP_ENV'] = getenv('APP_ENV');
        $context['twicpics_domain'] = getenv('TWICPICS_DOMAIN');
        return $context;
    }

    public function add_menus_to_context(array $context) {
        $context['header_menu'] = new Menu('header_menu');
        $context['footer_menu'] = new Menu('footer_menu');
        return $context;
    }

    public function add_options_to_context(array $context) {
        $context['global'] = [
            'reassurance' => get_field('global_reassurance_list', 'options'),
            'footer' => get_field('footer', 'options'),
            // ... 20+ more fields
        ];
        return $context;
    }

    // ... more methods
}
```

## Foehn's ContextProvider approach

```php
#[AsContextProvider('*')]
final readonly class GlobalContext implements ContextProviderInterface
{
    public function __construct(
        private MenuService $menus,
        private AcfOptionsService $options,
    ) {}

    public function provide(array $context): array
    {
        return array_merge($context, [
            'APP_ENV' => getenv('APP_ENV'),
            'menus' => $this->menus->all(),
            'options' => $this->options->all(),
            'site' => new Site(),
        ]);
    }
}

#[AsContextProvider(['single', 'single-*'])]
final readonly class SingleContext implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $post = $context['post'] ?? null;
        if (!$post) return $context;

        return array_merge($context, [
            'related_posts' => $this->getRelatedPosts($post),
            'reading_time' => $this->calculateReadingTime($post),
        ]);
    }
}
```

## Pros of ContextProvider

### 1. **Separation of concerns**

- Each provider has a single responsibility
- GlobalContext for site-wide data
- SingleContext for single post pages
- ProductContext for product-related pages

### 2. **Template-specific data**

```php
#[AsContextProvider('archive-product')]
class ProductArchiveContext {
    // Only runs on product archives
    // Data computed only when needed
}
```

vs current approach where ALL context filters run on EVERY page.

### 3. **Dependency injection**

```php
public function __construct(
    private MenuService $menus,
    private AcfOptionsService $options,
    private RelatedPostsService $related,
) {}
```

Services are injected, testable, mockable.

### 4. **Testability**

```php
test('SingleContext adds related posts', function () {
    $provider = new SingleContext($mockRelatedService);
    $context = $provider->provide(['post' => $mockPost]);

    expect($context)->toHaveKey('related_posts');
});
```

### 5. **Clear file organization**

```
app/Context/
├── GlobalContext.php       # Site-wide data (menus, options, env)
├── SingleContext.php       # Single posts (related, reading time)
├── ArchiveContext.php      # Archives (pagination, title)
├── ProductContext.php      # Products (gallery, categories)
└── SearchContext.php       # Search (query, count)
```

### 6. **Wildcard matching**

```php
#[AsContextProvider('single-*')]     // All single-{post_type} templates
#[AsContextProvider('archive-*')]    // All archives
#[AsContextProvider(['page', 'page-*'])]  // Pages
```

## Cons of ContextProvider

### 1. **Learning curve**

- New pattern to learn
- More files to create
- Need to understand wildcard matching

### 2. **Migration effort**

- Existing ThemeManager must be refactored
- Multiple filter callbacks → multiple Provider classes

### 3. **Potential performance overhead**

- Pattern matching on every request
- Multiple provider instantiation
- (Though likely negligible with caching)

### 4. **Over-engineering for simple sites**

- Small sites might just need one `timber/context` filter
- Adding 5 files for what was 1 file

### 5. **Debugging complexity**

- "Where does this context variable come from?"
- Need to check multiple providers
- (Mitigated by clear naming conventions)

## Comparison table

| Aspect          | Current (timber/context) | ContextProvider             |
| --------------- | ------------------------ | --------------------------- |
| Files           | 1 (ThemeManager)         | Multiple (1 per concern)    |
| Performance     | All filters run always   | Selective based on template |
| Testability     | Hard (global state)      | Easy (DI)                   |
| Discoverability | Grep for `add_filter`    | Check `Context/` directory  |
| Reusability     | Copy-paste               | Extend/compose              |
| Learning curve  | Low (familiar)           | Medium (new pattern)        |
| Type safety     | None                     | Interfaces                  |

## Recommendation

ContextProvider is objectively better architecture, but the migration path needs to be clear:

### 1. Document the "migration map"

```
BEFORE (ThemeManager)                    AFTER (Context Providers)
───────────────────                      ─────────────────────────
add_app_env_to_context()         →       GlobalContext
add_site_to_context()            →       GlobalContext
add_menus_to_context()           →       GlobalContext (with MenuService)
add_options_to_context()         →       GlobalContext (with AcfOptionsService)
add_global_to_context()          →       GlobalContext

add_single_context()             →       SingleContext
add_archive_context()            →       ArchiveContext
add_product_context()            →       ProductContext
```

### 2. Provide a "quick start" GlobalContext

```php
// Default GlobalContext that covers 80% of use cases
#[AsContextProvider('*')]
final readonly class GlobalContext implements ContextProviderInterface
{
    public function __construct(
        private MenuService $menus,
        private AcfOptionsService $options,
    ) {}

    public function provide(array $context): array
    {
        return array_merge($context, [
            'site' => new Site(),
            'menus' => $this->menus->all(),
            'options' => $this->options->all(),
            'env' => wp_get_environment_type(),
            'is_home' => is_front_page(),
            'is_single' => is_single(),
            'is_archive' => is_archive(),
            'current_year' => date('Y'),
        ]);
    }
}
```

### 3. CLI command to scaffold

```bash
php foehn make:context GlobalContext --global
php foehn make:context SingleContext --template=single
php foehn make:context ProductContext --template=single-product,archive-product
```

### 4. Document when to use what

| Scenario                        | Recommendation                               |
| ------------------------------- | -------------------------------------------- |
| Site-wide data (menus, options) | `GlobalContext` with `*`                     |
| Single post data                | `SingleContext` with `single`, `single-*`    |
| Archive-specific                | `ArchiveContext` with `archive`, `archive-*` |
| Custom post type                | Dedicated context provider                   |
| One-off page                    | Inline in TemplateController                 |

## Naming rationale

**Why `ContextProvider` instead of `ViewComposer`?**

- Directly maps to Timber's `$context` concept
- WordPress/Timber developers immediately understand "I'm providing context"
- `add_*_to_context()` methods → `*Context` classes (intuitive migration)
- Avoids Laravel-specific terminology that may confuse WP developers

## Tasks

- [ ] Rename `AsViewComposer` → `AsContextProvider`
- [ ] Rename `ViewComposerInterface` → `ContextProviderInterface`
- [ ] Rename `compose()` method → `provide()` method
- [ ] Update directory convention: `app/Views/Composers/` → `app/Context/`
- [ ] Document ContextProvider benefits in README
- [ ] Create "migration guide" from timber/context to ContextProvider
- [ ] Provide default GlobalContext example
- [ ] Add `make:context` CLI command
- [ ] Document template matching patterns
- [ ] Add performance benchmarks (optional)

## Labels

`documentation`, `enhancement`, `priority-medium`, `breaking-change`
