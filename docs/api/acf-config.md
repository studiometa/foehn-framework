# AcfConfig

Configuration class for ACF (Advanced Custom Fields) integration.

## Signature

```php
<?php

namespace Studiometa\Foehn\Config;

final readonly class AcfConfig
{
    /**
     * @param bool $transformFields Transform ACF block fields via Timber's ACF integration.
     */
    public function __construct(
        public bool $transformFields = true,
    );
}
```

## Properties

| Property          | Type   | Default | Description                                  |
| ----------------- | ------ | ------- | -------------------------------------------- |
| `transformFields` | `bool` | `true`  | Auto-convert ACF values to Timber objects    |

## Usage

Create a config file in your app directory:

```php
<?php
// app/acf.config.php

use Studiometa\Foehn\Config\AcfConfig;

return new AcfConfig(
    transformFields: true,
);
```

### Field Transformation

When `transformFields` is enabled (default), raw ACF field values are automatically converted to Timber objects inside block rendering:

| ACF Field Type | Raw Value       | Timber Object         |
| -------------- | --------------- | --------------------- |
| Image          | Attachment ID   | `Timber\Image`        |
| Post Object    | Post ID         | `Timber\Post`         |
| Relationship   | Array of IDs    | Array of `Timber\Post`|
| Taxonomy       | Term ID         | `Timber\Term`         |

### Disabling Transformation

For performance or when you want raw values:

```php
return new AcfConfig(
    transformFields: false,
);
```

With transformation disabled, ACF fields return their raw values (IDs, arrays) and you must resolve objects manually.

## Related

- [Guide: ACF Blocks](/guide/acf-blocks)
- [Guide: ACF Options Pages](/guide/acf-options-pages)
- [AcfBlockInterface](./acf-block-interface)
- [Guide: Configuration](/guide/configuration)
