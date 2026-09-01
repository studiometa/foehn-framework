<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

use Studiometa\Foehn\Config\ConfigLoader;
use Studiometa\Foehn\Config\PageCacheConfig;
use Throwable;

/**
 * The portable read path: serve a stored page before WordPress loads.
 *
 * Reached from `web/wp-content/advanced-cache.php`, which `wp-settings.php` includes
 * when `WP_CACHE` is true. Two facts make it thin. The generated `wp-config.php` has
 * already required `vendor/autoload.php`, so the framework's classes are loadable; and
 * it has already defined `WP_HOME` and `WP_CONTENT_DIR`, so a request's `Host` header
 * can be validated and the cache root found without asking WordPress anything.
 *
 * Cost on a hit: the autoloader plus two `stat()`s. Slower than nginx by a millisecond
 * and faster than a render by two orders of magnitude — and it needs no server access,
 * which is what makes the feature useful on a host somebody else administers.
 *
 * Nothing here may ever be the reason a site fails to boot. Every path returns rather
 * than throwing, and the whole thing runs inside a `catch (Throwable)`: the worst
 * outcome of a bug in this file has to be a page that renders.
 */
final readonly class Server
{
    /**
     * Resolve the project's page-cache config and serve from it.
     *
     * @param string $appPath The theme's app directory, where `page-cache.config.php` lives
     */
    public static function boot(string $appPath): void
    {
        try {
            $config = self::config($appPath);

            if ($config === null) {
                return;
            }

            self::serve($config);
        } catch (Throwable $throwable) {
            // A page cache is an optimisation, never a reason for a site not to come up.
            // Reported to the log while the site is in debug, and swallowed otherwise:
            // there is no visitor who benefits from seeing this.
            if (defined('WP_DEBUG') && (bool) constant('WP_DEBUG')) {
                error_log('Foehn page cache: ' . $throwable->getMessage());
            }
        }
    }

    /**
     * The config file that applies, read the way `ConfigLoader` would read it.
     *
     * The environment's own file wins over the plain one beside it, which is what lets
     * a project keep `page-cache.production.config.php` next to a `page-cache.config.php`
     * that leaves the cache off everywhere else. The suffix list is `ConfigLoader`'s, so
     * the drop-in cannot come to a different conclusion than the loader.
     */
    public static function config(string $appPath): ?PageCacheConfig
    {
        $directory = rtrim($appPath, '/');
        $candidates = [];

        foreach (ConfigLoader::ENVIRONMENT_SUFFIXES[PageCacheConfig::environment()] ?? [] as $suffix) {
            $candidates[] = $directory . '/page-cache' . $suffix . '.config.php';
        }

        $candidates[] = $directory . '/page-cache.config.php';

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $config = require $candidate;

            if ($config instanceof PageCacheConfig) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Serve the stored page for this request, or return and let WordPress boot.
     */
    public static function serve(PageCacheConfig $config): void
    {
        $bypass = new Bypass($config);
        $reason = $bypass->forRequest($_SERVER, $_COOKIE, $_POST);

        if ($reason !== null) {
            DebugHeaders::send($config, DebugHeaders::STATE_BYPASS, $reason);

            return;
        }

        $key = $bypass->key($_SERVER);

        if ($key === null) {
            DebugHeaders::send($config, DebugHeaders::STATE_BYPASS, BypassReason::Path);

            return;
        }

        $store = new Store($config);
        $status = 200;
        $file = $store->file($key);

        // A stored 404 is looked for only when the project still wants them cached, and
        // only after the 200: turning `cacheNotFound` off must stop serving the ones
        // already on disk rather than wait for a purge.
        if (($file === null || !is_file($file)) && $config->cacheNotFound) {
            $notFound = $store->file($key, 404);

            if ($notFound !== null && is_file($notFound)) {
                $status = 404;
                $file = $notFound;
            }
        }

        if ($file === null || !is_file($file)) {
            DebugHeaders::send($config, DebugHeaders::STATE_MISS, BypassReason::NotCached);

            return;
        }

        // The one rule nginx and mod_rewrite cannot enforce: neither can check a file's
        // age. On this path the TTL is exact; on theirs the sweep is the bound.
        if ($store->isExpired($file)) {
            DebugHeaders::send($config, DebugHeaders::STATE_MISS, BypassReason::Expired);

            return;
        }

        self::send($config, $file, $status, $store->headers($key, $status));
    }

    /**
     * Send a stored file and stop.
     */
    /**
     * @param list<string> $stored Headers recorded with the entry, already validated.
     */
    private static function send(PageCacheConfig $config, string $file, int $status = 200, array $stored = []): void
    {
        $modified = (int) filemtime($file);
        $etag = '"' . md5($file . ':' . $modified) . '"';

        DebugHeaders::send($config, DebugHeaders::STATE_HIT);

        // What the response itself carried, before the headers this cache owns — so a
        // page's own `Link:` or `X-Robots-Tag` survives a hit, and none of them can
        // overwrite the freshness headers below. nginx cannot replay these; see
        // StoredHeaders.
        foreach ($stored as $header) {
            header($header, false);
        }

        if ($status !== 200) {
            http_response_code($status);
        }

        header('Content-Type: text/html; charset=UTF-8');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        header('ETag: ' . $etag);
        header(sprintf('Cache-Control: public, max-age=%d, must-revalidate', max(0, $config->browserMaxAge)));
        // Cookie, because the bypass rules turn on one. Without it a shared proxy could
        // hand a cached anonymous page to a logged-in visitor, which is the whole
        // failure mode this feature is built to avoid.
        header('Vary: Cookie, Accept-Encoding');

        if (self::isFresh($etag, $modified)) {
            http_response_code(304);

            exit();
        }

        readfile($file);

        exit();
    }

    /**
     * Whether the browser already holds this exact page.
     */
    private static function isFresh(string $etag, int $modified): bool
    {
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;

        if (is_string($ifNoneMatch) && str_contains($ifNoneMatch, trim($etag, '"'))) {
            return true;
        }

        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;

        if (!is_string($ifModifiedSince) || $ifModifiedSince === '') {
            return false;
        }

        $since = strtotime($ifModifiedSince);

        return $since !== false && $since >= $modified;
    }
}
