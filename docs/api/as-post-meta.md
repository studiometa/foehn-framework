# #[AsPostMeta]

Registers a meta key with WordPress through `register_meta()`, which is what puts a custom field in the REST API — and therefore in the block editor and in block bindings.

The attribute is repeatable and goes on the model that owns the field, because that model already declares its post type and already holds the accessors that read the key.

## Signature

```php
<?php

namespace Studiometa\Foehn\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsPostMeta
{
    public function __construct(
        public string $key,
        public string $type = 'string',
        public bool $single = true,
        public bool $showInRest = true,
        public string $description = '',
        public string|int|float|bool|array|null $default = null,
        public string $objectType = 'post',
        public ?string $objectSubtype = null,
        public string $capability = 'edit_posts',
        public ?string $sanitize = null,
        public array $schema = [],
    ) {}
}
```

## Parameters

| Parameter       | Type                | Default        | Description                                                                                   |
| --------------- | ------------------- | -------------- | --------------------------------------------------------------------------------------------- |
| `key`           | `string`            | _required_     | The meta key                                                                                  |
| `type`          | `string`            | `'string'`     | `string`, `boolean`, `integer`, `number`, `array` or `object`                                 |
| `single`        | `bool`              | `true`         | `false` registers a repeatable field                                                          |
| `showInRest`    | `bool`              | `true`         | On by default: without REST the field is invisible to the editor and to bindings              |
| `description`   | `string`            | `''`           | Surfaces in the REST schema                                                                   |
| `default`       | scalar/array/`null` | `null`         | Must survive `var_export()`. Omitted entirely when `null`                                     |
| `objectType`    | `string`            | `'post'`       | `post`, `term`, `user` or `comment`                                                           |
| `objectSubtype` | `?string`           | inferred       | The post type or taxonomy. Inferred from the class's own attributes; `''` means every subtype |
| `capability`    | `string`            | `'edit_posts'` | Becomes `auth_callback`                                                                       |
| `sanitize`      | `?string`           | `null`         | The name of a **public static** method on the declaring class, not a closure                  |
| `schema`        | `array`             | `[]`           | The REST schema. Required for `array` and `object`: WordPress cannot describe their contents  |

## Usage

```php
<?php

namespace App\Models;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;

#[AsPostType(name: 'product', singular: 'Product', plural: 'Products')]
#[AsPostMeta(key: 'price', type: 'number', description: 'Price in euros')]
#[AsPostMeta(key: 'sku', showInRest: false, sanitize: 'sanitizeSku')]
#[AsPostMeta(key: 'gallery', type: 'integer', single: false)]
final class Product extends Post
{
    public function price(): ?float
    {
        $price = $this->meta('price');

        return $price ? (float) $price : null;
    }

    public static function sanitizeSku(mixed $value): string
    {
        return strtoupper((string) $value);
    }
}
```

## Subtype inference

`register_meta('post', 'price', […])` without an `object_subtype` registers the key for **every** post type. A model declaring a key on itself never means that, so the subtype is read off the class:

| Declared on a class carrying     | Subtype              |
| -------------------------------- | -------------------- |
| `#[AsPostType(name: 'product')]` | `product`            |
| `#[AsTaxonomy(name: 'genre')]`   | `genre`              |
| `#[AsTimberModel('post')]`       | `post`               |
| none of the above                | `''` — every subtype |

`objectType: 'user'` and `objectType: 'comment'` have no subtypes in WordPress and are always registered globally. An explicit `objectSubtype` always wins.

## Sanitisers are method names, never closures

A discovery item reaches the cache through `var_export()`, so an attribute cannot hold a closure — it would work in development and fail only once caching is on, which is to say only in production. `sanitize` therefore names a method, resolved when `apply()` runs.

The method must be **public and static**: the declaring class is usually a Timber model, and `Timber\Post` declares a protected constructor, so there is nothing to call an instance method on.

## Arrays and objects need a schema

WordPress cannot build a REST schema for an array whose items it cannot describe, and says so only under `WP_DEBUG`. Føhn refuses the declaration instead:

```php
#[AsPostMeta(key: 'credits', type: 'array', schema: ['items' => ['type' => 'string']])]
```

Scalars need nothing: WordPress derives their schema from `type`, and wraps it in an array itself when `single` is `false`.

## It does not conflict with ACF

ACF stores its values in ordinary post meta. Declaring `#[AsPostMeta(key: 'price')]` for a key ACF also manages is legitimate and useful: ACF keeps the editing UI, and the declaration gives the key a REST schema and makes it bindable. They are not alternatives at the storage layer.

## Related

- [Guide: Post Types](/guide/post-types)
- [#[AsPostType]](./as-post-type)
- [#[AsTaxonomy]](./as-taxonomy)
