# RenderApiConfig

Configuration class for the Render API (server-side template rendering via REST).

## Signature

```php
<?php

namespace Studiometa\Foehn\Config;

final readonly class RenderApiConfig
{
    /**
     * @param list<string> $templates Allowed template patterns (supports * wildcard)
     * @param int $cacheMaxAge Cache-Control max-age in seconds (0 to disable)
     * @param bool $debug When true, error messages include exception details
     */
    public function __construct(
        public array $templates = [],
        public int $cacheMaxAge = 0,
        public bool $debug = false,
    );

    /**
     * Check if a template path is allowed.
     */
    public function isTemplateAllowed(string $template): bool;
}
```

## Properties

| Property      | Type       | Default | Description                                    |
| ------------- | ---------- | ------- | ---------------------------------------------- |
| `templates`   | `string[]` | `[]`    | Allowed template patterns (supports `*`)       |
| `cacheMaxAge` | `int`      | `0`     | Cache-Control max-age in seconds               |
| `debug`       | `bool`     | `false` | Include exception details in error responses   |

## Usage

Create a config file in your app directory:

```php
<?php
// app/render-api.config.php

use Studiometa\Foehn\Config\RenderApiConfig;

return new RenderApiConfig(
    templates: ['partials/*', 'components/*'],
    cacheMaxAge: 3600,
    debug: false,
);
```

### Template Allowlisting

The `templates` array defines which templates can be rendered via the REST endpoint. This is a security feature — only explicitly allowed patterns are renderable.

```php
return new RenderApiConfig(
    templates: [
        'partials/*',       // All templates in partials/
        'components/*',     // All templates in components/
        'blocks/hero',      // Specific template
    ],
);
```

An empty `templates` array (the default) disables the Render API entirely.

### Caching

Set `cacheMaxAge` to enable `Cache-Control` headers on responses:

```php
return new RenderApiConfig(
    templates: ['partials/*'],
    cacheMaxAge: 3600, // 1 hour
);
```

### Debug Mode

When `debug` is `true`, error responses include full exception messages. Only enable in development:

```php
return new RenderApiConfig(
    templates: ['partials/*'],
    debug: WP_DEBUG,
);
```

## Related

- [Guide: Render API](/guide/render-api)
- [Guide: Configuration](/guide/configuration)
