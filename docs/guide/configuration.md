# Configuration

Føhn uses Tempest's auto-discovery for configuration. Each config class can be customized by creating a `*.config.php` file in your app directory.

## How Config Files Work

Tempest automatically discovers files matching the `*.config.php` pattern in your app directory. Each file must return a config class instance:

```php
<?php
// app/timber.config.php

use Studiometa\Foehn\Config\TimberConfig;

return new TimberConfig(
    templatesDir: ['views', 'templates'],
);
```

Føhn registers the returned instance in the DI container. If no config file is found, sensible defaults are used.

## Available Config Classes

### FoehnConfig — Core Settings

Controls the bootstrap process: discovery caching, debug mode, and opt-in hooks.

```php
<?php
// app/foehn.config.php

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Tempest\Core\DiscoveryCacheStrategy;

return new FoehnConfig(
    discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
    hooks: [CleanHeadTags::class],
    debug: false,
);
```

| Property                  | Type                     | Default  | Description                     |
| ------------------------- | ------------------------ | -------- | ------------------------------- |
| `discoveryCacheStrategy`  | `DiscoveryCacheStrategy` | `NONE`   | Discovery cache strategy        |
| `discoveryCachePath`      | `?string`                | `null`   | Custom cache path               |
| `hooks`                   | `class-string[]`         | `[]`     | Opt-in hook classes             |
| `debug`                   | `bool`                   | `false`  | Enable discovery debug logging  |

See [FoehnConfig API](/api/foehn-config) for details.

### TimberConfig — Templates

Controls where Timber looks for `.twig` template files.

```php
<?php
// app/timber.config.php

use Studiometa\Foehn\Config\TimberConfig;

return new TimberConfig(
    templatesDir: ['views'],
);
```

| Property       | Type       | Default          | Description                  |
| -------------- | ---------- | ---------------- | ---------------------------- |
| `templatesDir` | `string[]` | `['templates']`  | Template directory names     |

See [TimberConfig API](/api/timber-config) for details.

### AcfConfig — ACF Integration

Controls how ACF fields are handled in blocks.

```php
<?php
// app/acf.config.php

use Studiometa\Foehn\Config\AcfConfig;

return new AcfConfig(
    transformFields: true,
);
```

| Property          | Type   | Default | Description                              |
| ----------------- | ------ | ------- | ---------------------------------------- |
| `transformFields` | `bool` | `true`  | Auto-convert ACF values to Timber objects |

See [AcfConfig API](/api/acf-config) for details.

### RestConfig — REST API

Controls default permissions for `#[AsRestRoute]` endpoints.

```php
<?php
// app/rest.config.php

use Studiometa\Foehn\Config\RestConfig;

return new RestConfig(
    defaultCapability: 'edit_posts',
);
```

| Property            | Type      | Default        | Description                     |
| ------------------- | --------- | -------------- | ------------------------------- |
| `defaultCapability` | `?string` | `'edit_posts'` | Default capability for routes   |

See [RestConfig API](/api/rest-config) for details.

### RenderApiConfig — Server-side Rendering

Controls the REST endpoint for server-side template rendering.

```php
<?php
// app/render-api.config.php

use Studiometa\Foehn\Config\RenderApiConfig;

return new RenderApiConfig(
    templates: ['partials/*', 'components/*'],
    cacheMaxAge: 3600,
);
```

| Property      | Type       | Default | Description                          |
| ------------- | ---------- | ------- | ------------------------------------ |
| `templates`   | `string[]` | `[]`    | Allowed template patterns            |
| `cacheMaxAge` | `int`      | `0`     | Cache-Control max-age in seconds     |
| `debug`       | `bool`     | `false` | Include exception details in errors  |

See [RenderApiConfig API](/api/render-api-config) for details.

### QueryFiltersConfig — URL Query Filtering

Controls which custom taxonomies and query vars are exposed via URL parameters.

```php
<?php
// app/query-filters.config.php

use Studiometa\Foehn\Config\QueryFiltersConfig;

return new QueryFiltersConfig(
    taxonomies: [
        'genre' => ['in', 'not_in'],
        'product_cat' => ['in'],
    ],
    publicVars: [
        'posts_per_page' => [12, 24, 48],
    ],
);
```

See [Guide: Query Filters](/guide/query-filters) for details.

## Override Priority

When multiple sources provide the same config:

1. **Config file** (`app/*.config.php`) — highest priority, auto-discovered by Tempest
2. **Kernel::boot() array** — legacy fallback (only for `FoehnConfig`)
3. **Defaults** — built-in defaults from the config class constructor

## Environment-Specific Configuration

Use PHP logic in your config files for environment-specific settings:

```php
<?php
// app/foehn.config.php

use Studiometa\Foehn\Config\FoehnConfig;
use Tempest\Core\DiscoveryCacheStrategy;

$isDev = defined('WP_DEBUG') && WP_DEBUG;

return new FoehnConfig(
    discoveryCacheStrategy: $isDev
        ? DiscoveryCacheStrategy::NONE
        : DiscoveryCacheStrategy::FULL,
    debug: $isDev,
);
```

## File Structure Example

```
theme/
├── app/
│   ├── foehn.config.php         # Core configuration
│   ├── timber.config.php        # Template directories
│   ├── acf.config.php           # ACF settings
│   ├── rest.config.php          # REST API defaults
│   ├── render-api.config.php    # Render API allowlist
│   ├── query-filters.config.php # Query filter rules
│   ├── Hooks/
│   ├── Models/
│   └── ...
└── functions.php
```

## Related

- [API: FoehnConfig](/api/foehn-config)
- [API: TimberConfig](/api/timber-config)
- [API: AcfConfig](/api/acf-config)
- [API: RestConfig](/api/rest-config)
- [API: RenderApiConfig](/api/render-api-config)
- [Guide: Theme Conventions](/guide/theme-conventions)
