<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Images;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Glide\Server;
use League\Glide\ServerFactory;

/**
 * Where Glide reads originals, where it writes results, and with what.
 *
 * The two filesystems follow the uploads. On a site whose media sits on local
 * disk, both are directories. On a site using `humanmade/s3-uploads`, both are
 * prefixes of the same bucket — reading the prefix the plugin writes to, and
 * caching beside it.
 *
 * Caching into the bucket rather than onto local disk is deliberate: a container
 * loses its disk on every release, which is the reason the uploads left it in the
 * first place. A cache that has to be rebuilt after each deploy is a cache that
 * costs a few hundred milliseconds per image to the first visitor.
 *
 * The S3 client is built from the same `S3_UPLOADS_*` variables `wp-config.php`
 * already defines. One place configures the bucket; this reads it.
 */
final class GlideConfig
{
    /** Where the cache lives, under the uploads root or the bucket. */
    private const string CACHE_PREFIX = 'cache/glide';

    private ?Server $server = null;

    private ?FilesystemOperator $cache = null;

    public function __construct(
        /**
         * `gd` or `imagick`.
         *
         * `gd` by default, and not for lack of ambition: measured on a 2777x1973
         * photograph it is about twice as fast as Imagick *and* produces smaller
         * files. It is also the extension that is always present.
         */
        private readonly string $driver = 'gd',
        /** Quality for lossy formats, when a transform does not say. */
        private readonly int $quality = 82,
    ) {}

    /**
     * The Glide server, built once.
     */
    public function server(): Server
    {
        return $this->server ??= ServerFactory::create([
            'source' => $this->sourceFilesystem(),
            'cache' => $this->cacheFilesystem(),
            'driver' => $this->driver,
            'defaults' => ['q' => $this->quality],
            // A response is built by the route, which needs the path rather than a
            // stream: it hands the file to the webserver and lets it do the rest.
            'base_url' => '/' . GlideTransformer::ROUTE,
            // A closure literal, and neither `$this->cachePath(...)` nor a static
            // closure. Glide rebinds this callable onto the Server, and
            // `Closure::bind()` hands back null for both of those — a closure made
            // from a method cannot move to another class, and a static one cannot
            // be given a `$this` at all. Glide turns the null into "Invalid cache
            // path callable", so every image 404s while the unit suite stays green.
            //
            // @mago-expect lint:prefer-static-closure
            // @mago-expect lint:prefer-arrow-function
            'cache_path_callable' => function (string $path, array $params): string {
                return GlideConfig::cachePath($path, $params);
            },
        ]);
    }

    /**
     * Where a result is cached: the source path, then the URL's own signature.
     *
     * Glide keys a result under `xxh3(path + params)` by default, which is
     * deterministic but not *reproducible by a webserver* — nginx cannot hash. So
     * every cache hit would still boot WordPress to work out which file to send,
     * and the caching would save the transform while paying for the boot.
     *
     * The signature is already a keyed hash over exactly the path and the
     * parameters, and it is already in the query string. Keying on it makes the
     * cache path something a webserver can assemble from the request alone:
     *
     *     /_image/2016/06/photo.jpg?w=400&s=<sig>  →  <cache>/2016/06/photo.jpg/<sig>
     *
     * A forged `s` cannot reach a file: nothing is ever written under a signature
     * the site did not produce, so a wrong one misses and falls through to PHP,
     * where the signature is checked and refused.
     *
     * @param array<string, mixed> $params
     */
    public static function cachePath(string $path, array $params): string
    {
        $signature = (string) ($params['s'] ?? '');

        if (preg_match('/^[a-f0-9]{32}$/', $signature) !== 1) {
            // Nothing to key on. `GlideRoute` validates a signature before it ever
            // gets here, so this is a direct call to the server — hash the
            // parameters instead, the way Glide would have.
            unset($params['s'], $params['p']);
            ksort($params);
            $signature = md5(http_build_query($params));
        }

        return $path . '/' . $signature;
    }

    /**
     * Originals, wherever the uploads are.
     */
    public function sourceFilesystem(): FilesystemOperator
    {
        if ($this->usesObjectStorage()) {
            return new Filesystem(new AwsS3V3Adapter($this->s3(), $this->bucket(), 'uploads'));
        }

        return new Filesystem(new LocalFilesystemAdapter($this->uploadsBaseDir()));
    }

    /**
     * Results, beside the originals.
     */
    public function cacheFilesystem(): FilesystemOperator
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if ($this->usesObjectStorage()) {
            return $this->cache = new Filesystem(new AwsS3V3Adapter($this->s3(), $this->bucket(), self::CACHE_PREFIX));
        }

        return $this->cache = new Filesystem(
            new LocalFilesystemAdapter($this->uploadsBaseDir() . '/' . self::CACHE_PREFIX),
        );
    }

    /**
     * The path of an image relative to the uploads root, or null when the URL
     * does not belong to this site's media.
     *
     * A URL from anywhere else — another domain, a remote avatar, a theme asset —
     * is not something this transformer can read, so it says so rather than
     * building a URL that would 404.
     */
    public function relativePath(string $src): ?string
    {
        $base = (string) (wp_get_upload_dir()['baseurl'] ?? '');

        if ($base === '' || !str_starts_with($src, $base)) {
            return null;
        }

        $chemin = ltrim(substr($src, strlen($base)), '/');
        // A query string belongs to the caller, not to the object key.
        $chemin = (string) preg_replace('/[?#].*$/', '', $chemin);

        // `..` in a path that reaches the filesystem is how a transformer becomes
        // a way to read files it was never pointed at.
        if ($chemin === '' || str_contains($chemin, '..')) {
            return null;
        }

        return $chemin;
    }

    /**
     * The key URLs are signed with.
     *
     * `NONCE_SALT` is already required, already secret, and already specific to
     * the install, so a signature does not survive being copied elsewhere.
     */
    public function signingKey(): string
    {
        return defined('NONCE_SALT') ? (string) constant('NONCE_SALT') : 'foehn-glide';
    }

    /**
     * Whether the uploads live in a bucket rather than on disk.
     */
    private function usesObjectStorage(): bool
    {
        return defined('S3_UPLOADS_BUCKET') && (string) constant('S3_UPLOADS_BUCKET') !== '';
    }

    private function bucket(): string
    {
        return (string) constant('S3_UPLOADS_BUCKET');
    }

    /**
     * The uploads root on disk, without the date subdirectory.
     */
    private function uploadsBaseDir(): string
    {
        return (string) (wp_get_upload_dir()['basedir'] ?? '');
    }

    /**
     * A client for the same bucket `s3-uploads` writes to.
     *
     * Flysystem needs an S3 client; the plugin uses a stream wrapper. Both sit on
     * `aws/aws-sdk-php`, so this adds configuration rather than a second SDK — and
     * it reads the configuration the installer already wrote.
     */
    private function s3(): S3Client
    {
        $config = [
            'version' => 'latest',
            'region' => defined('S3_UPLOADS_REGION') ? (string) constant('S3_UPLOADS_REGION') : 'us-east-1',
        ];

        if (defined('S3_UPLOADS_KEY') && defined('S3_UPLOADS_SECRET')) {
            $config['credentials'] = [
                'key' => (string) constant('S3_UPLOADS_KEY'),
                'secret' => (string) constant('S3_UPLOADS_SECRET'),
            ];
        }

        // Anything that is not AWS — MinIO, R2, Scaleway — needs its endpoint, and
        // path-style addressing because a bucket is not a subdomain there.
        if (defined('S3_UPLOADS_ENDPOINT') && (string) constant('S3_UPLOADS_ENDPOINT') !== '') {
            $config['endpoint'] = (string) constant('S3_UPLOADS_ENDPOINT');
            $config['use_path_style_endpoint'] = true;
        }

        return new S3Client($config);
    }
}
