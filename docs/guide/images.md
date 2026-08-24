# Images

WordPress generates the sizes a theme registers, at upload time. `#[AsImageSize]` is the right tool when the sizes are known up front, and the wrong one when they are not: an art-directed crop at an arbitrary size, WebP or AVIF, or a size added after five hundred images were already uploaded.

An `ImageTransformer` covers that second case. A template asks for a size and never learns who produced it:

```twig
<img src="{{ image_url(post.thumbnail, { w: 400, h: 267, fit: 'crop' }) }}" alt="" />
<img src="{{ image_url(post.thumbnail, { w: 800, fm: 'webp' }) }}" alt="" />
```

Keys drivers agree on: `w`, `h`, `fit` (`crop`, `contain`, `max`, `fill`) and `fm` (`webp`, `avif`, `jpg`, `png`). Quality is not one of them — it is a decision about the site rather than about one image, and every value it could take multiplies the cache.

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

The S3 client is the plugin's own, not a second one built from constants. The bucket and the region are constants, but what makes a non-AWS bucket reachable is not: `humanmade/s3-uploads` takes its endpoint, its path-style addressing and its checksum settings from the `s3_uploads_s3_client_params` filter, which is where [`S3UploadsEndpoint`](/guide/uploads) supplies them. Reading only the constants gives you a client pointed at AWS while the uploads go to MinIO — every transform 404s with the originals plainly present in the media library.

Cached objects are written at the bucket's own visibility, not forced public: a bucket with Block Public Access on rejects a `public-read` write outright, and a private bucket behind a CDN is a setup that has to keep working.

### Serve the cache from the webserver

This is the part worth doing. Booting WordPress costs more than the transform saves, so every cache hit should be answered without PHP.

That requires the cache path to be something the webserver can assemble from the request. Glide's own key is `xxh3(path + params)`, which nginx cannot compute — so Føhn spells the transform into the path instead:

```
/_image/2016/06/photo.jpg?w=600&h=400&fit=crop&fm=webp
  →  <cache>/2016/06/photo.jpg/600x400-crop-webp
```

Which makes the rule a rewrite over named arguments:

```nginx
# Cached transforms, straight from disk. PHP only ever sees a miss.
location ^~ /_image/ {
    limit_req zone=foehn_image burst=100 nodelay;
    rewrite ^/_image/(.+)$ /wp-content/uploads/cache/glide/$1/${arg_w}x${arg_h}-${arg_fit}-${arg_fm} break;
    try_files $uri @foehn;
}

location @foehn {
    rewrite ^ /index.php last;
}
```

Named arguments and not `$args`, so the same transform written in another order finds the same file instead of building a second copy of it. An absent parameter leaves its slot empty, so a width-only resize is `600x-max-` and the shape stays fixed.

With uploads in a bucket, point the same rewrite at the bucket — the shape is the one an uploads proxy already has; `packages/demo/.ddev/nginx/image-cache.conf` is a working example against MinIO.

`^~` and not a regex location: a regex would lose to the `\.(jpg|png|webp)$` static-file rule most WordPress configurations already carry, and every transform would 404.

Keep the query string in the rewrite, and use a **named** location for the fallback. A URI internal redirect drops `$args`, and PHP is then handed one of its own URLs with no parameters on it.

Without the rule the site still works. It just pays a WordPress boot per image.

### There is no signature. There is a bound

A cold transform costs real CPU, so `?w=9999` must not be an instruction the site will follow. There are two ways to stop that: sign the URL so only transforms the site asked for can exist, or bound what may be asked for at all. Føhn does the second, and that is what lets a browser build these URLs — a signature needs a secret, so a client could only ever replay sizes the server chose in advance, which is the thing a responsive image is trying not to do.

`GlideConfig::normalise()` is the bound, and the same code runs on both ends: `image_url()` uses it to produce a URL that is valid by construction, and the route uses it to refuse one that is not.

| Parameter     | Rule                                                                 |
| ------------- | -------------------------------------------------------------------- |
| `w`, `h`      | a multiple of `step` (default 100), at most `maxSize` (default 2600) |
| `fit`         | `crop`, `contain`, `max`, `fill`                                     |
| `fm`          | `webp`, `avif`, `jpg`, `png`                                         |
| anything else | dropped                                                              |

Sizes are rounded **up** onto the grid rather than refused, so a template may write `w: 601` and get a URL that works — and `?w=601` and `?w=700` name the same cached file rather than two.

The allowlist is on the parameter **keys**, not only their values, and that is not fussiness. `Server::getAllParams()` finishes with `array_merge($all, $params)`, so any key that reaches Glide overrides what the server configured. An unknown parameter is not ignored; it wins.

### Rate limiting, and where it can actually go

The bound makes the number of transforms an image can have finite. It does not bound how fast someone walks that space — measured on the demo, a cold transform is ~55ms and eight FPM workers manage about 18 per second.

The obvious answer is to throttle only cache misses, since a hit costs nginx a couple of milliseconds. **That is not expressible in nginx.** `limit_req` runs in the preaccess phase, and a request arriving at the miss handler by internal redirect — `@named` or URI, it makes no difference — never has that phase applied again. Measured on the demo, the same limit allowed 15 of 15 on the miss location and 3 of 15 on the outer one.

So the limit covers hits as well, and a large burst is what makes that harmless:

```nginx
# http context — sites-enabled, not a server-level include
limit_req_zone $binary_remote_addr zone=foehn_image:10m rate=5r/s;
```

Real traffic is bursty and short — a gallery asks for thirty images at once and then stops. A walk is sustained. On the demo, `burst=100` lets a full page through untouched while a 260-request walk is cut to 136 served and 124 refused.

It is a throttle, not a shield: it is per-IP, so a distributed effort routes around it.

### What is left exposed

Without a signature, `/_image/<any path under uploads>` is an unauthenticated read path. On a default WordPress that is no new exposure — `/wp-content/uploads/` is already public. It matters for a site that puts access rules on its uploads directory, where `/_image/` bypasses them and serves a resized copy. `maxSize` caps what comes back, so the original bytes are never reachable, but the content is.

If that describes your site, do not enable the transformer.

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
