# Page cache in object storage

The static page cache stores pages on local disk. On a site with more than one instance behind a load balancer, each instance has its own cache, and a purge runs on one instance only. The other instances serve stale pages until the TTL sweep removes them.

This spec adds a bucket mode: the page cache stores pages in the bucket that `humanmade/s3-uploads` already uses, and nginx serves hits from the bucket. It follows the pattern the image transforms already use (`GlideConfig`, `docker/wordpress/nginx/templates/image-cache.conf.template`). Usage is documented in [`docs/guide/page-cache.md`](../docs/guide/page-cache.md).

## Decisions

| Question    | Decision                                                                                                                                                                                   |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Activation  | Explicit opt-in: `new PageCacheConfig(storage: PageCacheStorage::Bucket)`. Not automatic when `S3_UPLOADS_BUCKET` is set: hits are slower, and a single-instance site gets nothing for it. |
| Location    | The uploads bucket, under the `cache/pages/` prefix, as Glide uses `cache/glide/`. No second bucket and no new variables.                                                                  |
| Public read | Accepted. nginx reads the bucket anonymously, so cache objects are `public-read`, like uploads.                                                                                            |
| Readers     | nginx only. The drop-in does not read in bucket mode, and Apache is not supported.                                                                                                         |

## Design

1. **Storage seam.** `PageStore` is the interface that the recorder, the invalidator, the sweep, the commands and the dashboard use. `Store` (with `CacheDirectory`) is the disk implementation. `BucketStore` is the bucket implementation, on Flysystem `AwsS3V3Adapter` with the s3-uploads S3 client, the client Glide uses.
2. **Object keys.** `cache/pages/{host}/{path}/index….html`, the same relative path as on disk. Each object gets `Content-Type: text/html; charset=UTF-8` and the `public-read` ACL. An S3 PUT is atomic, so no temporary object is necessary.
3. **Write after the response.** In bucket mode the recorder writes on shutdown, after `fastcgi_finish_request()`, so a miss does not wait for the PUT.
4. **nginx.** `wp foehn cache:config --server=nginx` renders a bucket variant. The bypass rules and the key assembly do not change. The variant does not test a file: it rewrites to the object key and proxies to the bucket, and `error_page 403 404` sends a miss to PHP. Scheme, host, path style and resolver come from the `S3_UPLOADS_*` variables that the uploads proxy and `/_image/` use, and they are in the policy hash.
5. **Drop-in.** In bucket mode the drop-in does not read. Reading the bucket before WordPress loads means loading the AWS SDK and an HTTP round trip, which gives almost nothing. A miss goes to WordPress.
6. **Invalidation and sweep.** Clearing a URL deletes the `index*` objects of its prefix, and the `page/**` subtree when asked. A flush deletes the prefix. The sweep reads `lastModified` from a listing, one request for each 1,000 objects.
7. **No headers sidecar.** Only the drop-in replays recorded headers, and nginx cannot. The `X-Robots-Tag` of a section response is derived in the snippet, as on disk.
8. **No stored 404.** nginx never serves the `--404` file, and the drop-in does not read, so bucket mode does not write it.

## Trade-offs

- **Slower hits.** A hit is an nginx to bucket round trip, about 10–50 ms in the same region, against about 1 ms from disk. A local nginx `proxy_cache` would make hits fast again, but it brings back the per-instance staleness this mode removes. There is no micro-cache in this version.
- **Deploys must clear the cache.** On disk, a deploy removes the cache. In the bucket, pages rendered by the old templates survive the deploy, so the release step must run `wp foehn cache:clear`.
- **Cache objects can be read from the bucket host.** This is the HTML the site serves to anonymous visitors, but it is duplicate content, and a section fragment read from the bucket host has no `noindex`.
- **A miss also does a PUT.** It runs after the response is sent.
- **Errors are not fatal.** Like the disk store, the bucket store never throws: a failed write is a miss on the next request, and a failed delete counts nothing.

## Status

**Done.** Issue #217.
