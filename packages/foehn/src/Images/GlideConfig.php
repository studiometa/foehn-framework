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

    /**
     * The only parameters a request may carry, and the only ones in a cache key.
     *
     * A list rather than a filter of known-bad keys, because Glide merges request
     * parameters *over* everything configured server-side — `getAllParams()` ends
     * with `array_merge($all, $params)`. Any key that reaches it is an override,
     * so an unknown one is not ignored, it wins.
     *
     * `q` is deliberately absent: quality is a decision about the site, not about
     * one image, and every value it could take multiplies the cache.
     */
    public const array PARAMS = ['w', 'h', 'fit', 'fm'];

    /** What `fit` may be. Glide accepts more; these are the ones worth caching. */
    public const array FITS = ['crop', 'contain', 'max', 'fill'];

    /** What `fm` may be. */
    public const array FORMATS = ['webp', 'avif', 'jpg', 'png'];

    public function __construct(
        /**
         * `gd` or `imagick`.
         *
         * `gd` by default, and not for lack of ambition: measured on a 2777x1973
         * photograph it is about twice as fast as Imagick *and* produces smaller
         * files. It is also the extension that is always present.
         */
        private readonly string $driver = 'gd',
        /** Quality for lossy formats. Not accepted from a URL — see PARAMS. */
        private readonly int $quality = 82,
        /**
         * The grid widths and heights snap to.
         *
         * This is what keeps the cache finite. Without it `?w=601` is a distinct
         * transform from `?w=600`, and the number of them an image can have is the
         * number of integers — so the parameters are quantised on the way out and
         * refused on the way in if they are off the grid.
         *
         * 100 to match what a client is likely to send: `normalizeSize()` in
         * studiometa/ui rounds a measured element up to a step, so both ends land
         * on the same grid without being told about each other.
         */
        private readonly int $step = 100,
        /** The largest dimension worth producing. A multiple of `step`. */
        private readonly int $maxSize = 2600,
    ) {}

    public function step(): int
    {
        return $this->step;
    }

    public function maxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * A transform as it will be asked for, or null when it cannot be.
     *
     * Used on both sides and that is the point: `GlideTransformer` runs a
     * template's request through it to build a URL that is valid by construction,
     * and `GlideRoute` runs an incoming request through the same code to decide
     * whether to honour it. One definition of what a transform may be.
     *
     * @param array<string, mixed> $params
     * @return array<string, string>|null
     */
    public function normalise(array $params): ?array
    {
        $clean = [];

        foreach (self::PARAMS as $key) {
            $value = $params[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'w' || $key === 'h') {
                // Numeric rather than int: `{{ image_url(image, { h: (600 * ratio)|round }) }}`
                // hands over a float, because that is what Twig's `round` returns.
                // Refusing it made the transformer fall back to the source URL on
                // every image in the demo — correct behaviour for an impossible
                // transform, and silent, which is how it survived a page that
                // rendered perfectly with no transforms in it at all.
                $size = is_numeric($value) ? $this->snap((float) $value) : null;

                if ($size === null) {
                    return null;
                }

                $clean[$key] = (string) $size;

                continue;
            }

            if (!is_string($value)) {
                return null;
            }

            if ($key === 'fit' && !in_array($value, self::FITS, true)) {
                return null;
            }

            if ($key === 'fm' && !in_array($value, self::FORMATS, true)) {
                return null;
            }

            $clean[$key] = $value;
        }

        // A request that asks for no dimension is a request for the original, which
        // is a URL the site already has.
        $sansTaille = ($clean['w'] ?? null) === null && ($clean['h'] ?? null) === null;

        return $sansTaille ? null : $clean;
    }

    /**
     * A dimension on the grid, or null when it is not a dimension.
     *
     * Rounds up rather than rejecting, so a template may write `w: 601` and get a
     * URL that works. An incoming request is checked against the same function, so
     * `?w=601` and `?w=700` name the same cached file rather than two.
     */
    private function snap(float $value): ?int
    {
        // NAN and INF are numeric, and `(int)` on either is meaningless rather
        // than large.
        if (!is_finite($value) || $value < 1 || $value > 100_000) {
            return null;
        }

        $size = (int) $value;

        // Snapped before the ceiling is applied, so `maxSize` bounds what is
        // actually produced rather than what was asked for.
        $size = (int) (ceil($size / $this->step) * $this->step);

        return $size > $this->maxSize ? null : $size;
    }

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
     * Where a result is cached: the source path, then the transform, spelled out.
     *
     * Glide keys a result under `xxh3(path + params)` by default, which is
     * deterministic but not *reproducible by a webserver* — nginx cannot hash. So
     * every cache hit would still boot WordPress to work out which file to send,
     * and the caching would save the transform while paying for the boot.
     *
     * Spelling the parameters into the name makes the path something nginx can
     * assemble out of the request, from named arguments:
     *
     *     /_image/2016/06/photo.jpg?w=600&h=400&fit=crop&fm=webp
     *       →  <cache>/2016/06/photo.jpg/600x400-crop-webp
     *
     * Named arguments and not the raw query string, so the same transform written
     * in a different order is the same file rather than a second copy of it.
     *
     * An absent parameter leaves its slot empty, so a width-only resize reads as
     * 600x-max- and the shape stays fixed however few parameters are given. Which
     * is what keeps the two ends agreeing.
     *
     * @param array<string, mixed> $params
     */
    public static function cachePath(string $path, array $params): string
    {
        $slot = static function (string $key) use ($params): string {
            $value = $params[$key] ?? '';

            // Never a separator, and never a way out of the directory.
            return preg_replace('/[^a-z0-9]/i', '', is_scalar($value) ? (string) $value : '') ?? '';
        };

        return sprintf('%s/%sx%s-%s-%s', $path, $slot('w'), $slot('h'), $slot('fit'), $slot('fm'));
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
     * Its own, rather than a second one built from constants. The bucket and the
     * region are constants, but everything that makes a non-AWS bucket reachable
     * is not: the plugin takes its endpoint, its path-style addressing and its
     * checksum settings from the `s3_uploads_s3_client_params` filter, which is
     * where Føhn's own `S3UploadsEndpoint` supplies them, and where a site adds
     * whatever R2 or Scaleway needs.
     *
     * Reading only the constants produced a client pointed at AWS while the
     * uploads went to MinIO — and the symptom was every transform 404ing with the
     * originals plainly present in the media library.
     */
    private function s3(): S3Client
    {
        // `class_exists` and not a `use`: the plugin is a dependency of the site,
        // never of the framework.
        if (class_exists('S3_Uploads\Plugin')) {
            /** @var S3Client */
            return call_user_func(['S3_Uploads\Plugin', 'get_instance'])->s3();
        }

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

        // The plugin's own extension point, applied to the client Glide will use,
        // so one filter configures both.
        if (function_exists('apply_filters')) {
            /** @var array<string, mixed> $config */
            $config = apply_filters('s3_uploads_s3_client_params', $config);
        }

        return new S3Client($config);
    }
}
