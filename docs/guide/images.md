# Images

WordPress generates the sizes a theme registers, at upload time. `#[AsImageSize]` is the right tool when the sizes are known up front, and the wrong one when they are not: an art-directed crop at an arbitrary size, WebP or AVIF, or a size added after five hundred images were already uploaded.

An `ImageTransformer` covers that second case. A template asks for a size and never learns who produced it:

```twig
<img src="{{ image_url(post.thumbnail, { w: 400, h: 267, fit: 'crop' }) }}" alt="" />
<img src="{{ image_url(post.thumbnail, { w: 800, fm: 'webp' }) }}" alt="" />
```

Keys drivers agree on: `w`, `h`, `fit` (`crop`, `contain`, `stretch`), `fm` (`webp`, `avif`, `jpg`, `png`), `q`.

## Choosing a driver

Nothing happens until a project asks. The default is `NullTransformer`, which returns the URL it was given — so the call above is safe to write before any driver exists, and a project that transforms nothing pays nothing.

```php
// theme/app/foehn.config.php
return new FoehnConfig(
    imageTransformer: GlideTransformer::class,
    // …
);
```

## The Glide driver

`GlideTransformer` serves transforms from this site, through `league/glide`. It is a `suggest`, not a dependency:

```bash
composer require league/glide
composer require league/flysystem-aws-s3-v3   # only if uploads live in a bucket
```

It follows the uploads. On local disk both the originals and the cache are directories under the uploads root; with `humanmade/s3-uploads` both are prefixes of the same bucket, reading where the plugin writes and caching beside it. Caching into the bucket is deliberate — a container loses its disk on every release, which is why the uploads left it.

The S3 client is built from the `S3_UPLOADS_*` constants `wp-config.php` already defines. One place configures the bucket.

### Serve the cache from the webserver

This is the part worth doing. Glide keys a result under a deterministic path, so every cache hit can be answered without PHP. Booting WordPress costs more than the transform saves.

```nginx
# Cached transforms, straight from disk. PHP only sees a miss.
location ^~ /_image/ {
    try_files /wp-content/uploads/cache/glide/$uri @foehn;
}
```

With uploads in a bucket, point the same rule at the bucket instead — the shape is the one an uploads proxy already has.

Without the rule the site still works. It just pays a WordPress boot per image.

### URLs are signed, and that is not optional

Every URL carries an HMAC of its path and parameters, keyed on `NONCE_SALT`. Without one, `?w=9999` is an instruction to spend CPU and disk on demand: a cold transform costs a few hundred milliseconds, so an ordinary crawler is enough to hurt and a deliberate one fills the cache. Unsigned requests are answered with 403 before anything is read or written.

Because the key is `NONCE_SALT`, signatures do not survive being copied to another install — which is the behaviour you want.

### GD or Imagick

GD by default, and not for lack of ambition. Measured on a 2777x1973 photograph:

| Transform           | GD    | Imagick |
| ------------------- | ----- | ------- |
| cover 400x267 jpg   | 195ms | 425ms   |
| cover 400x267 webp  | 197ms | 430ms   |
| cover 1300x600 webp | 335ms | 528ms   |
| cover 200x200 avif  | 253ms | 440ms   |

GD is about twice as fast _and_ produces smaller files here, and it is the extension that is always present. Pass `imagick` to `GlideConfig` if a format or a colour profile needs it.

### Invalidation

A cache key is built from the path and the transform, never from the content. Crop an image in the media library and every transform derived from it would keep serving the old pixels, indefinitely, because nothing else would invalidate them.

`ImageCacheHooks` forgets an image's transforms on `wp_update_attachment_metadata` and `delete_attachment`. Enable it alongside the driver:

```php
return new FoehnConfig(
    imageTransformer: GlideTransformer::class,
    hooks: [ImageCacheHooks::class],
);
```

## What a driver may not do

Return a broken URL. A driver that cannot produce a transform — an unreadable original, an image hosted elsewhere, a missing extension — returns the source URL. A missing size is a slower page; a broken URL is a missing image.
