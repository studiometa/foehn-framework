# Enhance CLI commands for scaffolding

## Current state

Foehn has some CLI commands in `src/Console/Commands/`:

- `MakeHooksCommand.php` - Scaffolds hooks classes

## Missing commands (based on conventions)

Based on the theme structure conventions, we need comprehensive scaffolding commands.

## Commands to implement

### 1. `make:model` - Create Timber post type model

```bash
php foehn make:model Product
php foehn make:model Product --post-type  # With #[AsPostType] attribute

# Output: app/Models/Product.php
```

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(
    name: 'product',
    singular: 'Product',
    plural: 'Products',
    public: true,
    hasArchive: true,
    menuIcon: 'dashicons-admin-post',
    supports: ['title', 'editor', 'thumbnail'],
)]
final class Product extends Post
{
    // Add custom methods here
}
```

### 2. `make:taxonomy` - Create taxonomy

```bash
php foehn make:taxonomy ProductCategory --post-type=product
php foehn make:taxonomy ProductCategory --post-type=product --hierarchical

# Output: app/Taxonomies/ProductCategory.php
```

### 3. `make:block` - Create block (ACF or native)

```bash
php foehn make:block Hero --acf
php foehn make:block Hero --acf --with-template
php foehn make:block Accordion --native --interactivity

# Output (ACF): app/Blocks/Acf/Hero/HeroBlock.php
# Output (Native): app/Blocks/Native/Accordion/AccordionBlock.php
# With template: templates/blocks/hero.twig
```

### 4. `make:field-group` - Create ACF field group

```bash
php foehn make:field-group ProductFields --post-type=product
php foehn make:field-group FrontPageFields --page-template=front-page
php foehn make:field-group CategoryFields --taxonomy=category

# Output:
# app/Fields/PostType/ProductFields.php
# app/Fields/Page/FrontPageFields.php
# app/Fields/Taxonomy/CategoryFields.php
```

### 5. `make:options-page` - Create ACF options page

```bash
php foehn make:options-page ThemeSettings
php foehn make:options-page FooterSettings --parent=theme-settings

# Output: app/Fields/Options/ThemeSettings.php
```

### 6. `make:context` - Create context provider

```bash
php foehn make:context GlobalContext --template="*"
php foehn make:context SingleContext --template="single,single-*"
php foehn make:context ProductContext --template="single-product,archive-product"

# Output: app/Context/GlobalContext.php
```

### 7. `make:controller` - Create template controller

```bash
php foehn make:controller SingleController --template="single,single-*"
php foehn make:controller SearchController --template="search"

# Output: app/Http/Controllers/SingleController.php
```

### 8. `make:service` - Create service class

```bash
php foehn make:service ImageService
php foehn make:service RelatedPostsService

# Output: app/Services/ImageService.php
```

### 9. `make:hooks` - Create hooks class (existing, enhance)

```bash
php foehn make:hooks ThemeHooks
php foehn make:hooks AdminHooks --admin

# Output: app/Hooks/ThemeHooks.php
```

### 10. `make:menu` - Create navigation menu

```bash
php foehn make:menu HeaderMenu --location=header --title="Header Menu"
php foehn make:menu FooterMenu --location=footer --title="Footer Menu"

# Output: app/Menus/HeaderMenu.php
```

### 11. `make:image-size` - Create image size

```bash
php foehn make:image-size Card --width=400 --height=300 --crop
php foehn make:image-size Hero --width=1920 --height=1080 --crop
php foehn make:image-size Logo --width=200 --height=0

# Output: app/ImageSizes/Card.php
```

### 12. `make:pattern` - Create block pattern

```bash
php foehn make:pattern HeroFullWidth --category=heroes
php foehn make:pattern HeroFullWidth --with-template

# Output: app/Patterns/HeroFullWidth.php
# With template: templates/patterns/hero-full-width.twig
```

## Meta commands

### `init` - Initialize a new Foehn theme

```bash
php foehn init
php foehn init --name="My Theme" --namespace="MyTheme"

# Creates:
# - app/ directory structure
# - functions.php with Kernel::boot()
# - style.css with theme header
# - composer.json with autoload
# - Basic GlobalContext
# - Basic ThemeHooks
```

### `list` - List discovered items

```bash
php foehn list:models
php foehn list:blocks
php foehn list:context
php foehn list:hooks

# Shows all discovered items with their attributes
```

### `validate` - Validate theme structure

```bash
php foehn validate

# Checks:
# - Missing templates for blocks
# - Invalid attribute configurations
# - Namespace mismatches
# - Missing interfaces
```

### `cache:clear` - Clear discovery cache

```bash
php foehn cache:clear
```

## Command options

### Global options

```bash
--dry-run     # Show what would be created without creating
--force       # Overwrite existing files
--no-tests    # Skip test file generation
```

### Example with dry-run

```bash
$ php foehn make:model Product --post-type --dry-run

Would create:
  → app/Models/Product.php

With content:
  <?php

  declare(strict_types=1);

  namespace App\Models;
  ...
```

## Stubs location

```
src/
├── Console/
│   ├── Commands/
│   │   ├── MakeModelCommand.php
│   │   ├── MakeBlockCommand.php
│   │   └── ...
│   │
│   └── stubs/
│       ├── model.php.stub
│       ├── model-post-type.php.stub
│       ├── acf-block.php.stub
│       ├── native-block.php.stub
│       ├── field-group.php.stub
│       ├── options-page.php.stub
│       ├── context-provider.php.stub
│       ├── controller.php.stub
│       ├── service.php.stub
│       ├── hooks.php.stub
│       ├── menu.php.stub
│       ├── image-size.php.stub
│       └── pattern.php.stub
```

## Stub example

```php
// stubs/model-post-type.php.stub
<?php

declare(strict_types=1);

namespace {{ namespace }};

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(
    name: '{{ slug }}',
    singular: '{{ singular }}',
    plural: '{{ plural }}',
    public: true,
    hasArchive: {{ hasArchive }},
    menuIcon: '{{ menuIcon }}',
    supports: ['title', 'editor', 'thumbnail'],
)]
final class {{ class }} extends Post
{
    // Add custom methods here
}
```

## Integration with Tempest Console

Use Tempest's console component for:

- Command registration
- Argument/option parsing
- Output formatting
- Interactive prompts

```php
use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;

final class MakeModelCommand
{
    use HasConsole;

    #[ConsoleCommand('make:model')]
    public function __invoke(string $name, bool $postType = false): void
    {
        $path = $this->resolvePath($name);
        $content = $this->renderStub($postType ? 'model-post-type' : 'model', [
            'namespace' => 'App\\Models',
            'class' => $name,
            'slug' => Str::snake($name),
        ]);

        $this->writeFile($path, $content);
        $this->success("Model created: {$path}");
    }
}
```

## Tasks

- [ ] Implement `make:model` command
- [ ] Implement `make:taxonomy` command
- [ ] Implement `make:block` command (ACF + Native)
- [ ] Implement `make:field-group` command
- [ ] Implement `make:options-page` command
- [ ] Implement `make:context` command
- [ ] Implement `make:controller` command
- [ ] Implement `make:service` command
- [ ] Enhance `make:hooks` command
- [ ] Implement `make:menu` command
- [ ] Implement `make:image-size` command
- [ ] Implement `make:pattern` command
- [ ] Implement `init` command
- [ ] Implement `list:*` commands
- [ ] Implement `validate` command
- [ ] Add `--dry-run` support to all commands
- [ ] Create all stub files
- [ ] Add tests for all commands

## Labels

`enhancement`, `cli`, `priority-high`
