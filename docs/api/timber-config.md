# TimberConfig

Configuration class for Timber/Twig integration.

## Signature

```php
<?php

namespace Studiometa\Foehn\Config;

final readonly class TimberConfig
{
    /**
     * @param string[] $templatesDir Timber templates directory names
     */
    public function __construct(
        public array $templatesDir = ['templates'],
    );
}
```

## Properties

| Property       | Type       | Default          | Description                          |
| -------------- | ---------- | ---------------- | ------------------------------------ |
| `templatesDir` | `string[]` | `['templates']`  | Timber templates directory names     |

## Usage

Create a config file in your app directory:

```php
<?php
// app/timber.config.php

use Studiometa\Foehn\Config\TimberConfig;

return new TimberConfig(
    templatesDir: ['views', 'templates'],
);
```

Tempest's auto-discovery will find this file and register it in the container. Føhn will use it during Timber initialization.

### Multiple Template Directories

You can specify multiple directories where Timber will look for templates:

```php
return new TimberConfig(
    templatesDir: ['views', 'templates', 'blocks'],
);
```

Timber will search directories in order and use the first matching template.

### Default Behavior

If no `timber.config.php` file is present, Føhn uses the default `['templates']` directory. This means Timber will look for `.twig` files in `theme/templates/`.

## Related

- [Guide: Theme Conventions](/guide/theme-conventions)
- [Guide: Configuration](/guide/configuration)
- [Kernel](./kernel)
