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

| Property          | Type   | Default | Description                               |
| ----------------- | ------ | ------- | ----------------------------------------- |
| `transformFields` | `bool` | `true`  | Auto-convert ACF values to Timber objects |

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

When `transformFields` is enabled (default), Timber's ACF transforms replace ACF's formatting for some field types while Føhn reads a block's fields. The stored value becomes a Timber object:

| ACF Field Type | Stored Value  | Timber Object                     |
| -------------- | ------------- | --------------------------------- |
| Image          | Attachment ID | `Timber\Image`                    |
| Post Object    | Post ID       | `Timber\Post`                     |
| Relationship   | Array of IDs  | `Timber\PostArrayObject` of posts |
| Taxonomy       | Term ID       | `Timber\Term`                     |
| Date Picker    | `Ymd` string  | `DateTimeImmutable`               |

See the [ACF blocks guide](/guide/acf-blocks#transformed-field-types) for every type.

### Disabling Transformation

For performance, or when you want ACF's own formatting:

```php
return new AcfConfig(
    transformFields: false,
);
```

With transformation disabled, block fields have ACF's formatting, as `get_fields()` returns them: an image is what its `return_format` gives (an array, a URL or an ID), and you must resolve Timber objects manually.

## Related

- [Guide: ACF Blocks](/guide/acf-blocks)
- [Guide: ACF Options Pages](/guide/acf-options-pages)
- [AcfBlockInterface](./acf-block-interface)
- [Guide: Configuration](/guide/configuration)
