<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\Server;

/**
 * `Server::serve()` ends in `exit`, so the served path is driven in a subprocess: the
 * only honest way to assert that a hit really does stop the request before WordPress
 * would have loaded. The bypass and miss paths return normally and are tested inline.
 */

/**
 * Run a page-cache drop-in scenario in its own process and return what it printed.
 *
 * @param array<string, string> $server Request superglobals to set up
 * @return array{status: int, output: string}
 */
function pageCacheServeInProcess(string $root, array $server, string $body = '', int $ttl = 0): array
{
    $bootstrap = var_export(dirname(__DIR__, 2) . '/bootstrap.php', true);
    $script = sprintf(
        'require %s;
        $_SERVER = array_merge($_SERVER, %s);
        $config = new Studiometa\Foehn\Config\PageCacheConfig(
            enabled: true, path: %s, ttl: %d, debugHeaders: true,
        );
        %s
        Studiometa\Foehn\PageCache\Server::serve($config);
        echo "BOOTED";',
        $bootstrap,
        var_export($server, true),
        var_export($root, true),
        $ttl,
        $body === ''
            ? ''
            : sprintf(
                '$store = new Studiometa\Foehn\PageCache\Store($config);
                $store->put(Studiometa\Foehn\PageCache\CacheKey::create("example.com", %s), %s);',
                var_export($server['REQUEST_URI'] ?? '/', true),
                var_export($body, true),
            ),
    );

    $output = [];
    $status = 0;
    exec('php -r ' . escapeshellarg($script) . ' 2>&1', $output, $status);

    return ['status' => $status, 'output' => implode("\n", $output)];
}

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_environment_type'] = 'production';
    $this->root = pageCacheRoot();
});

afterEach(function () {
    removeTestDirectory($this->root);
});

describe('Server: serving', function () {
    it('sends the stored page and stops before WordPress would have loaded', function () {
        $result = pageCacheServeInProcess(
            $this->root,
            ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/blog/'],
            '<html>stored</html>',
        );

        expect($result['status'])->toBe(0, $result['output']);
        expect($result['output'])->toBe('<html>stored</html>');
        expect($result['output'])->not->toContain('BOOTED');
    });

    it('lets WordPress boot when nothing is stored', function () {
        $result = pageCacheServeInProcess($this->root, [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/blog/',
        ]);

        expect($result['output'])->toBe('BOOTED');
    });

    it('lets WordPress boot for a page the TTL has expired', function () {
        // The one rule nginx cannot enforce: try_files cannot check a file's age. On
        // this path the TTL is exact.
        mkdir($this->root . '/example.com/blog', 0o777, true);
        file_put_contents($this->root . '/example.com/blog/index.html', '<html>old</html>');
        touch($this->root . '/example.com/blog/index.html', time() - 7200);

        $result = pageCacheServeInProcess(
            $this->root,
            ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/blog/'],
            ttl: 3600,
        );

        expect($result['output'])->toBe('BOOTED');
    });

    it('lets WordPress boot for a request the rules bypass', function (array $server) {
        $result = pageCacheServeInProcess(
            $this->root,
            ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/blog/', ...$server],
            '<html>stored</html>',
        );

        expect($result['output'])->toBe('BOOTED');
    })->with([
        'a POST' => [['REQUEST_METHOD' => 'POST']],
        'another host' => [['HTTP_HOST' => 'evil.test']],
        'a real query string' => [['REQUEST_URI' => '/blog/?foo=bar']],
    ]);

    it('serves a page whose query string is only tracking parameters', function () {
        // The file was written for /blog/, and `?utm_source=` must reach it: otherwise
        // every link out of a newsletter pays for a render.
        mkdir($this->root . '/example.com/blog', 0o777, true);
        file_put_contents($this->root . '/example.com/blog/index.html', '<html>stored</html>');

        $result = pageCacheServeInProcess($this->root, [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/blog/?utm_source=newsletter',
        ]);

        expect($result['output'])->toBe('<html>stored</html>');
    });

    it('reads one file for two ignored args in either order', function () {
        mkdir($this->root . '/example.com/blog', 0o777, true);
        file_put_contents($this->root . '/example.com/blog/index.html', '<html>stored</html>');

        foreach (['?utm_source=a&utm_medium=b', '?utm_medium=b&utm_source=a'] as $query) {
            $result = pageCacheServeInProcess($this->root, [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/blog/' . $query,
            ]);

            expect($result['output'])->toBe('<html>stored</html>');
        }
    });
});

describe('Server: resolving its config', function () {
    beforeEach(function () {
        $this->app = pageCacheRoot() . '/app';
        mkdir($this->app, 0o777, true);
    });

    afterEach(function () {
        removeTestDirectory(dirname($this->app));
    });

    it('reads the plain config file', function () {
        file_put_contents(
            $this->app . '/page-cache.config.php',
            '<?php return new Studiometa\Foehn\Config\PageCacheConfig(enabled: true, ttl: 60);',
        );

        expect(Server::config($this->app)?->ttl)->toBe(60);
    });

    it("prefers the environment's own file, as the config loader does", function () {
        $GLOBALS['wp_stub_environment_type'] = 'production';

        file_put_contents(
            $this->app . '/page-cache.config.php',
            '<?php return new Studiometa\Foehn\Config\PageCacheConfig(enabled: false);',
        );
        file_put_contents(
            $this->app . '/page-cache.production.config.php',
            '<?php return new Studiometa\Foehn\Config\PageCacheConfig(enabled: true, ttl: 99);',
        );

        expect(Server::config($this->app)?->enabled)->toBeTrue();
        expect(Server::config($this->app)?->ttl)->toBe(99);
    });

    it("ignores another environment's file", function () {
        $GLOBALS['wp_stub_environment_type'] = 'local';

        file_put_contents(
            $this->app . '/page-cache.config.php',
            '<?php return new Studiometa\Foehn\Config\PageCacheConfig(enabled: false, ttl: 1);',
        );
        file_put_contents(
            $this->app . '/page-cache.production.config.php',
            '<?php return new Studiometa\Foehn\Config\PageCacheConfig(enabled: true, ttl: 99);',
        );

        expect(Server::config($this->app)?->ttl)->toBe(1);
    });

    it('has no config when the project wrote none', function () {
        expect(Server::config($this->app))->toBeNull();
    });

    it('has no config when the file returns something else', function () {
        file_put_contents($this->app . '/page-cache.config.php', '<?php return ["enabled" => true];');

        expect(Server::config($this->app))->toBeNull();
    });

    it('lets WordPress boot rather than failing on a config file that throws', function () {
        // Nothing in a drop-in may ever be the reason a site does not come up.
        file_put_contents($this->app . '/page-cache.config.php', '<?php throw new RuntimeException("bad");');

        Server::boot($this->app);

        expect(true)->toBeTrue();
    });
});
