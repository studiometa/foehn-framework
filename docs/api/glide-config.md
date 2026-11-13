# GlideConfig

Configuration of the Glide image driver: the image library, the quality and the size grid. See [Images](/guide/images#the-glide-driver) for the driver.

## Signature

```php
<?php

namespace Studiometa\Foehn\Images;

final class GlideConfig
{
    public const array PARAMS = ['w', 'h', 'fit', 'fm'];
    public const array FITS = ['crop', 'contain', 'max', 'fill'];
    public const array FORMATS = ['webp', 'avif', 'jpg', 'png'];

    public function __construct(
        string $driver = 'gd',
        int $quality = 82,
        int $step = 100,
        int $maxSize = 2600,
    );
}
```

## Properties

| Property  | Type     | Default | Description                                                                         |
| --------- | -------- | ------- | ----------------------------------------------------------------------------------- |
| `driver`  | `string` | `'gd'`  | `gd` or `imagick`. GD is faster and gives smaller files on the measured photographs |
| `quality` | `int`    | `82`    | Quality of lossy formats. A request cannot change it                                |
| `step`    | `int`    | `100`   | Widths and heights round up to a multiple of this value                             |
| `maxSize` | `int`    | `2600`  | The largest width or height. Use a multiple of `step`. A larger request is refused  |

## Usage

Create a config file in your app directory. Føhn registers every object that an `app/*.config.php` file returns:

```php
<?php
// app/glide.config.php

use Studiometa\Foehn\Images\GlideConfig;

return new GlideConfig(
    driver: 'imagick',
    maxSize: 3000,
);
```

Without this file, Føhn uses the defaults.

## Request parameters

A transform accepts only the keys in `PARAMS`:

| Key   | Values                             |
| ----- | ---------------------------------- |
| `w`   | A number. It rounds up to `step`   |
| `h`   | A number. It rounds up to `step`   |
| `fit` | `crop`, `contain`, `max` or `fill` |
| `fm`  | `webp`, `avif`, `jpg` or `png`     |

A request with no `w` and no `h`, a size larger than `maxSize`, or an unknown `fit` or `fm` gives no transform: the transformer returns the source URL.

## Related

- [Guide: Images](/guide/images)
- [FoehnConfig](/api/foehn-config)
