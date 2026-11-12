<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache\ServerConfig;

use Studiometa\Foehn\Helpers\UploadsBucket;

/**
 * Where nginx reads the uploads bucket: scheme, host, path prefix and resolver.
 *
 * Derived exactly as the Docker image derives the upstream of `/wp-content/uploads/` and
 * `/_image/` (`docker/wordpress/entrypoint.d/60-nginx-uploads-proxy.sh`), from the same
 * variables, so a CDN in front of one is a CDN in front of all three:
 *
 * - `S3_UPLOADS_PROXY_HOST` and `S3_UPLOADS_PROXY_SCHEME`, when a CDN or an endpoint with
 *   neither addressing style is in front of the bucket;
 * - else `S3_UPLOADS_ENDPOINT`, with the bucket in the path when `S3_UPLOADS_PATH_STYLE`
 *   is true, and in the hostname when it is not;
 * - `S3_UPLOADS_PROXY_RESOLVER` for the resolver, `1.1.1.1 8.8.8.8` by default.
 */
final readonly class BucketOrigin
{
    public function __construct(
        public string $scheme,
        public string $host,
        /** The bucket as the first path segment, with its slash, or empty. */
        public string $prefix,
        public string $resolver,
    ) {}

    /**
     * The origin of this site's bucket, or null when there is not enough to reach it.
     */
    public static function fromEnvironment(): ?self
    {
        $bucket = UploadsBucket::name() ?? self::env('S3_UPLOADS_BUCKET');

        if ($bucket === null) {
            return null;
        }

        $resolver = self::env('S3_UPLOADS_PROXY_RESOLVER') ?? '1.1.1.1 8.8.8.8';
        $proxy = self::env('S3_UPLOADS_PROXY_HOST');

        if ($proxy !== null) {
            return self::valid(new self(self::env('S3_UPLOADS_PROXY_SCHEME') ?? 'https', $proxy, '', $resolver));
        }

        $endpoint = self::env('S3_UPLOADS_ENDPOINT');

        if ($endpoint === null) {
            return null;
        }

        $match = [];
        $scheme = preg_match('#^([a-z][a-z0-9+.-]*)://#', $endpoint, $match) === 1 ? $match[1] : 'https';
        $host = (string) preg_replace(['#^[a-z][a-z0-9+.-]*://#', '#/.*$#'], '', $endpoint);

        if (self::env('S3_UPLOADS_PATH_STYLE') === 'true') {
            return self::valid(new self($scheme, $host, '/' . $bucket, $resolver));
        }

        return self::valid(new self($scheme, $bucket . '.' . $host, '', $resolver));
    }

    /**
     * The origin, if every value can go into an nginx directive as it is.
     *
     * The values come from the environment and are written into the snippet without
     * quotes, so a space, a `;` or a `$` would start another directive or a variable.
     */
    private static function valid(self $origin): ?self
    {
        $valid =
            preg_match('/^https?$/D', $origin->scheme) === 1
            && preg_match('/^[A-Za-z0-9.:-]+$/D', $origin->host) === 1
            && preg_match('#^(?:/[A-Za-z0-9._-]+)?$#D', $origin->prefix) === 1
            && preg_match('/^[A-Za-z0-9.:\[\] =-]+$/D', $origin->resolver) === 1;

        return $valid ? $origin : null;
    }

    /**
     * The values a snippet bakes in, for its policy hash.
     *
     * @return array{scheme: string, host: string, prefix: string, resolver: string}
     */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'host' => $this->host,
            'prefix' => $this->prefix,
            'resolver' => $this->resolver,
        ];
    }

    /**
     * A variable as the generated `wp-config.php` reads it, with an empty value read as absent.
     */
    private static function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
