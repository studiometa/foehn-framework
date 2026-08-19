<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Smoke\Support;

use RuntimeException;

/**
 * The running ddev site, as the smoke suite needs to poke at it.
 *
 * Three kinds of access, and the split matters:
 *
 * - **HTTP**, through {@see Client}, which is the only way to observe which reader
 *   answered a request.
 * - **The filesystem, directly.** ddev mounts the project, so the cache directory is a
 *   real path on the host and a file assertion is a plain `is_file()`. No `ddev exec`.
 * - **WP-CLI, through `ddev exec`.** Only for the things that genuinely need WordPress:
 *   creating a post, changing an option, running `wp foehn cache:clear`.
 */
final readonly class Site
{
    /** Where the page-cache config for this environment is written, relative to the starter. */
    public const LOCAL_CONFIG = 'theme/app/page-cache.local.config.php';

    /** The generated nginx include ddev picks up from `.ddev/nginx/*.conf`. */
    public const NGINX_INCLUDE = '.ddev/nginx/foehn-page-cache.conf';

    /** Where `wp foehn cache:config --server=nginx --write` puts the snippet. */
    public const GENERATED_NGINX = 'config/nginx/foehn-page-cache.conf';

    private const CACHE_ROOT = 'web/wp-content/cache/foehn/pages';

    /**
     * The starter's directory on the host.
     */
    public static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public static function path(string $relative): string
    {
        return self::root() . '/' . ltrim($relative, '/');
    }

    /**
     * The site URL WordPress itself reports, or null when nothing is running.
     */
    public static function url(): ?string
    {
        static $url = null;

        if ($url === null) {
            $reported = self::wp('wp option get home');
            $url = $reported === null ? false : rtrim($reported, '/');
        }

        return $url === false ? null : $url;
    }

    /**
     * The host as it appears in a cache path.
     */
    public static function host(): string
    {
        $url = self::url();

        if ($url === null) {
            throw new RuntimeException('The site is not running.');
        }

        return (string) parse_url($url, PHP_URL_HOST);
    }

    /**
     * Whether there is a site to test at all.
     *
     * A developer on a cold machine should get skips, not a wall of red.
     */
    public static function isRunning(): bool
    {
        $url = self::url();

        return $url !== null && Client::reachable($url . '/');
    }

    /**
     * Run a WP-CLI command in the container. Null when it could not run.
     */
    public static function wp(string $command): ?string
    {
        $script = sprintf('cd /var/www/html && %s', $command);
        $output = [];
        $status = 0;

        exec(
            sprintf('cd %s && ddev exec %s 2>/dev/null', escapeshellarg(self::root()), escapeshellarg($script)),
            $output,
            $status,
        );

        if ($status !== 0) {
            return null;
        }

        // WP-CLI in this image prints PHP deprecations from its own vendor directory
        // before the value, so the output is filtered rather than taken whole.
        $lines = array_values(array_filter(
            array_map('trim', $output),
            static fn(string $line): bool => $line !== '' && !str_contains($line, 'Deprecated'),
        ));

        return $lines === [] ? '' : end($lines);
    }

    /**
     * Enable the page cache for this environment, by writing the file the config loader
     * reads in preference to the plain one.
     *
     * The starter ships the cache production-only on purpose — caching while somebody
     * edits a template is nobody's idea of a good local setup — so switching it on for a
     * test run also exercises the loader's environment precedence.
     *
     * @param array<string, string> $arguments Constructor arguments to override, rendered as PHP
     */
    public static function enableCache(array $arguments = []): void
    {
        // Merged by name, not appended: a repeated named argument is a PHP fatal, and a
        // config file that throws takes the whole site down rather than the cache.
        $named = [
            'enabled' => 'true',
            'ttl' => '0',
            'environments' => "['local']",
            'debugHeaders' => 'true',
            ...$arguments,
        ];

        $rendered = [];

        foreach ($named as $name => $value) {
            $rendered[] = $name . ': ' . $value;
        }

        $path = self::path(self::LOCAL_CONFIG);

        file_put_contents($path, sprintf("<?php\n\ndeclare(strict_types=1);\n\n"
        . "// Written by the smoke suite, removed when it finishes.\n"
        . "return new Studiometa\\Foehn\\Config\\PageCacheConfig(\n    %s,\n);\n", implode(",\n    ", $rendered)));

        self::awaitOpcache($path);
    }

    /**
     * Wait until PHP-FPM will actually read a file that has just been written.
     *
     * `opcache.file_update_protection` is 2 seconds by default: opcache deliberately
     * ignores a file younger than that, so it never caches one caught half-written. The
     * consequence here is that a config file rewritten between two test cases is invisible
     * to every worker for two seconds, and a request in that window is answered under the
     * *previous* configuration — which reads as the feature ignoring its own settings.
     *
     * No deploy rewrites a config file and serves a request in the same second, so this is
     * the suite's problem to solve rather than the framework's. Backdating the mtime would
     * be faster but would make two writes in one second indistinguishable, which is the
     * other half of the same trap.
     */
    private static function awaitOpcache(string $path): void
    {
        $protection = 2;

        while (true) {
            clearstatcache(true, $path);
            $age = time() - (int) filemtime($path);

            if ($age > $protection) {
                return;
            }

            usleep(100_000);
        }
    }

    public static function disableCache(): void
    {
        $path = self::path(self::LOCAL_CONFIG);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Install or remove the generated nginx include, and wait for nginx to mean it.
     *
     * `nginx -s reload` returns as soon as the signal is sent: the old workers keep
     * serving until they drain, so the next request can still be answered by the config
     * that was just replaced. This polls until the reader actually changes.
     */
    public static function useNginx(bool $enabled): void
    {
        $include = self::path(self::NGINX_INCLUDE);
        $parked = $include . '.off';

        if ($enabled && is_file($parked)) {
            rename($parked, $include);
        }

        if (!$enabled && is_file($include)) {
            rename($include, $parked);
        }

        self::reloadNginx();
        self::awaitVia($enabled ? 'nginx' : 'php');
    }

    /**
     * Empty the cache, and prove it is empty.
     */
    public static function clearCache(): void
    {
        self::wp('wp foehn cache:clear');
    }

    /**
     * The absolute path of the file a URL path is cached at.
     *
     * The filename is a parameter because keyed query args change it: `?page=2` stores as
     * `index__page=2&.html` beside the plain `index.html`.
     */
    public static function cacheFile(string $path, string $filename = 'index.html'): string
    {
        $trimmed = trim($path, '/');

        return (
            self::path(self::CACHE_ROOT)
            . '/'
            . self::host()
            . ($trimmed === '' ? '' : '/' . $trimmed)
            . '/'
            . $filename
        );
    }

    /**
     * Regenerate the nginx include from the configuration that is loaded right now.
     *
     * The committed include is generated from the starter's own config, which keys no
     * query args. A test that changes the policy has to regenerate the snippet as a deploy
     * would, or it is asserting against rules nginx was never given.
     *
     * @return string The previous contents, for {@see Site::restoreNginxInclude()}.
     */
    public static function generateNginxInclude(): string
    {
        $include = self::path(self::NGINX_INCLUDE);
        $previous = (string) file_get_contents($include);

        self::wp('wp foehn cache:config --server=nginx --write');
        copy(self::path(self::GENERATED_NGINX), $include);
        self::reloadNginx();
        self::awaitVia('nginx');

        return $previous;
    }

    /**
     * Put the committed include back, and remove what `--write` left behind.
     */
    public static function restoreNginxInclude(string $previous): void
    {
        file_put_contents(self::path(self::NGINX_INCLUDE), $previous);

        if (is_file(self::path(self::GENERATED_NGINX))) {
            unlink(self::path(self::GENERATED_NGINX));
        }

        self::reloadNginx();
    }

    /**
     * Every file currently in the cache, relative to the cache root.
     *
     * @return list<string>
     */
    public static function cachedFiles(): array
    {
        $root = self::path(self::CACHE_ROOT);

        if (!is_dir($root)) {
            return [];
        }

        $found = [];
        $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $root,
            \FilesystemIterator::SKIP_DOTS,
        ));

        /** @var \SplFileInfo $entry */
        foreach ($entries as $entry) {
            if ($entry->isFile()) {
                $found[] = substr($entry->getPathname(), strlen($root) + 1);
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Wait until the homepage is answered by a given reader.
     */
    public static function awaitVia(string $via, int $attempts = 40): bool
    {
        $url = self::url();

        if ($url === null) {
            return false;
        }

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            // Two requests: the first fills the cache if the reload emptied nothing, the
            // second is the one that can be answered by the fast path.
            Client::get($url . '/');

            if (Client::get($url . '/')->via() === $via) {
                return true;
            }

            usleep(250_000);
        }

        return false;
    }

    private static function reloadNginx(): void
    {
        exec(sprintf(
            'cd %s && ddev exec %s >/dev/null 2>&1',
            escapeshellarg(self::root()),
            escapeshellarg('sudo nginx -t && sudo nginx -s reload'),
        ));
    }
}
