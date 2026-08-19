<?php

declare(strict_types=1);

use Studiometa\FoehnInstaller\InstallerConfig;
use Studiometa\FoehnInstaller\WebRootGenerator;

beforeEach(function () {
    $this->io = recordingIo();
    $this->root = makeProjectRoot('theme', 'mu-plugins');
    $this->config = InstallerConfig::fromArray([], $this->root);

    $this->generate = function (?string $foehnPackagePath = null, ?InstallerConfig $config = null): void {
        new WebRootGenerator($this->io, $this->root, $config ?? $this->config, $foehnPackagePath)->generate();
    };
});

afterEach(fn() => removeDirectory($this->root));

describe('WebRootGenerator', function () {
    it('creates the web root WordPress expects', function () {
        ($this->generate)();

        expect($this->root . '/web')->toBeDirectory();
        expect($this->root . '/web/wp-content/themes')->toBeDirectory();
        expect($this->root . '/web/wp-content/plugins')->toBeDirectory();
        expect($this->root . '/web/wp-content/mu-plugins')->toBeDirectory();
        expect($this->root . '/web/wp-content/uploads')->toBeDirectory();
        expect($this->root . '/web/wp-content/foehn')->toBeDirectory();
    });

    it('writes a front controller that loads WordPress', function () {
        ($this->generate)();

        expect($this->root . '/web/index.php')->toBeFile();
        expect(file_get_contents($this->root . '/web/index.php'))
            ->toContain("require __DIR__ . '/wp/wp-blog-header.php';")
            ->toContain("define('WP_USE_THEMES', true);");
    });

    it('writes the front controller against the configured WordPress directory', function () {
        $config = InstallerConfig::fromArray(['wp-dir' => 'wordpress'], $this->root);

        ($this->generate)(null, $config);

        expect(file_get_contents($this->root . '/web/index.php'))
            ->toContain("require __DIR__ . '/wordpress/wp-blog-header.php';");
    });

    it('writes a wp-config.php', function () {
        ($this->generate)();

        expect($this->root . '/web/wp-config.php')->toBeFile();

        $contents = file_get_contents($this->root . '/web/wp-config.php');

        expect($contents)->toContain('DB_NAME');
        expect($contents)->toContain('ABSPATH');
    });

    it('writes the page cache drop-in, holding no policy of its own', function () {
        ($this->generate)();

        expect($this->root . '/web/wp-content/advanced-cache.php')->toBeFile();

        $contents = file_get_contents($this->root . '/web/wp-content/advanced-cache.php');

        expect($contents)
            ->toContain('Studiometa\Foehn\PageCache\Server::boot')
            ->toContain("dirname(__DIR__, 2) . '/theme/app'")
            // Whether caching happens at all is the config file's decision, not this
            // file's: a drop-in that carried its own switch would be a second source of
            // truth for exactly the rules this feature keeps in one place.
            ->not->toContain('enabled');
    });

    it('points the drop-in at the configured theme directory', function () {
        $config = InstallerConfig::fromArray(['theme-dir' => 'src'], $this->root);

        ($this->generate)(null, $config);

        expect(file_get_contents($this->root . '/web/wp-content/advanced-cache.php'))
            ->toContain("dirname(__DIR__, 2) . '/src/app'");
    });

    it('defines WP_CACHE, without which WordPress never loads the drop-in', function () {
        ($this->generate)();

        // Inert on its own: with no drop-in WordPress skips it, and with a drop-in whose
        // config leaves the cache off it returns immediately.
        expect(file_get_contents($this->root . '/web/wp-config.php'))->toContain("define('WP_CACHE', true);");
    });

    it('defines WP_CACHE before it hands over to WordPress', function () {
        // wp-settings.php reads WP_CACHE as it loads; defined after the require it would
        // never be seen, and the drop-in would silently never run.
        ($this->generate)();

        $contents = (string) file_get_contents($this->root . '/web/wp-config.php');

        expect(strpos($contents, "define('WP_CACHE', true);"))
            ->toBeLessThan(strpos($contents, "require_once ABSPATH . 'wp-settings.php';"));
    });

    it('produces a web root whose PHP parses', function () {
        ($this->generate)();

        // These three files are written as strings and never executed by any other
        // test; a syntax error in them would only show up on a real install.
        foreach (['/web/index.php', '/web/wp-config.php', '/web/wp-content/advanced-cache.php'] as $file) {
            $output = [];
            $status = 0;
            exec('php -l ' . escapeshellarg($this->root . $file) . ' 2>&1', $output, $status);

            expect($status)->toBe(0, implode("\n", $output));
        }
    });

    it('symlinks the theme into wp-content', function () {
        ($this->generate)();

        $link = $this->root . '/web/wp-content/themes/theme';

        expect(is_link($link))->toBeTrue();
        expect(realpath($link))->toBe(realpath($this->root . '/theme'));
    });

    it('symlinks the theme under the name the project chose', function () {
        $config = InstallerConfig::fromArray(['theme-name' => 'starter-theme'], $this->root);

        ($this->generate)(null, $config);

        expect(is_link($this->root . '/web/wp-content/themes/starter-theme'))->toBeTrue();
    });

    it('reports a theme directory it cannot find instead of failing silently', function () {
        $config = InstallerConfig::fromArray(['theme-dir' => 'missing'], $this->root);

        ($this->generate)(null, $config);

        expect($this->io->getOutput())->toContain('Skipped theme symlink');
    });

    it('symlinks mu-plugins and writes their loader', function () {
        ($this->generate)();

        expect(is_link($this->root . '/web/wp-content/mu-plugins/_custom'))->toBeTrue();
        expect($this->root . '/web/wp-content/mu-plugins/00-loader.php')->toBeFile();
        expect(file_get_contents($this->root . '/web/wp-content/mu-plugins/00-loader.php'))
            ->toContain("__DIR__ . '/_custom'");
    });

    it('leaves mu-plugins alone when the project has none', function () {
        $root = makeProjectRoot('theme');

        try {
            new WebRootGenerator($this->io, $root, InstallerConfig::fromArray([], $root), null)->generate();

            expect(file_exists($root . '/web/wp-content/mu-plugins/00-loader.php'))->toBeFalse();
        } finally {
            removeDirectory($root);
        }
    });

    it('copies .env.example to .env on a first install', function () {
        file_put_contents($this->root . '/.env.example', "WP_HOME=https://example.test\n");

        ($this->generate)();

        expect($this->root . '/.env')->toBeFile();
        expect(file_get_contents($this->root . '/.env'))->toContain('WP_HOME=https://example.test');
    });

    it('never overwrites an .env that already exists', function () {
        file_put_contents($this->root . '/.env.example', "WP_HOME=https://example.test\n");
        file_put_contents($this->root . '/.env', "WP_HOME=https://production.example\n");

        ($this->generate)();

        // The .env holds a real site's database credentials.
        expect(file_get_contents($this->root . '/.env'))->toContain('production.example');
    });

    it('copies the block editor registrar out of the framework package', function () {
        $package = makeProjectRoot('resources/js');
        file_put_contents($package . '/resources/js/editor.js', "console.log('registrar');\n");

        try {
            ($this->generate)($package);

            $target = $this->root . '/web/wp-content/foehn/editor.js';

            expect($target)->toBeFile();
            expect(file_get_contents($target))->toContain("console.log('registrar');")->toContain('DO NOT EDIT');
        } finally {
            removeDirectory($package);
        }
    });

    it('reports a registrar it cannot resolve', function () {
        ($this->generate)();

        expect($this->io->getOutput())->toContain('Skipped editor script');
        expect(file_exists($this->root . '/web/wp-content/foehn/editor.js'))->toBeFalse();
    });

    it('can run twice, as composer install does', function () {
        ($this->generate)();
        ($this->generate)();

        expect(is_link($this->root . '/web/wp-content/themes/theme'))->toBeTrue();
        expect($this->root . '/web/index.php')->toBeFile();
    });

    it('generates the security keys into .env on a first install', function () {
        file_put_contents($this->root . '/.env.example', "DB_NAME=db\nAUTH_KEY=\n");

        ($this->generate)();

        $env = file_get_contents($this->root . '/.env');

        foreach ([
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        ] as $name) {
            expect($env)->toMatch('/^' . $name . '="[^"]{64,}"$/m');
        }

        // What the project already had is still there, and the name it listed empty
        // was filled in rather than added a second time.
        expect($env)->toContain('DB_NAME=db');
        expect(preg_match_all('/^AUTH_KEY=/m', $env))->toBe(1);
        expect($env)->not->toContain('change-me-');
    });

    it('generates keys with no .env at all', function () {
        ($this->generate)();

        expect($this->root . '/.env')->toBeFile();
        expect(file_get_contents($this->root . '/.env'))->toMatch('/^AUTH_KEY="/m');
    });

    it('generates a different set for every install', function () {
        ($this->generate)();
        $first = file_get_contents($this->root . '/.env');

        $second = makeProjectRoot('theme');

        try {
            new WebRootGenerator($this->io, $second, InstallerConfig::fromArray([], $second), null)->generate();

            // Two installs of the same project must not share keys, which is what the
            // old md5(__DIR__) fallback did.
            expect(file_get_contents($second . '/.env'))->not->toBe($first);
        } finally {
            removeDirectory($second);
        }
    });

    it('never replaces keys .env already sets', function () {
        file_put_contents($this->root . '/.env', "AUTH_KEY=\"mine\"\n");

        ($this->generate)();

        $env = file_get_contents($this->root . '/.env');

        // Rewriting on every composer install would log every user out every deploy.
        expect($env)->toContain('AUTH_KEY="mine"');
        // The seven it did not set are filled in, because a partial set still fails.
        expect($env)->toMatch('/^NONCE_SALT="/m');
    });

    it('leaves the keys alone when the environment supplies them', function () {
        $names = [
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        ];

        foreach ($names as $name) {
            putenv("{$name}=from-the-environment");
        }

        try {
            ($this->generate)();

            // A vault or a container already provides them; .env should stay untouched.
            expect(file_exists($this->root . '/.env'))->toBeFalse();
        } finally {
            foreach ($names as $name) {
                putenv($name);
            }
        }
    });

    it('leaves the keys alone when the project uses the PHP file', function () {
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/wordpress-salts.config.php', "<?php // mine\n");

        ($this->generate)();

        // wp-config.php reads that file first, so writing .env keys would be writing
        // keys nothing uses.
        expect(file_exists($this->root . '/.env'))->toBeFalse();
    });

    it('refuses to serve production without the keys', function () {
        ($this->generate)();

        $config = file_get_contents($this->root . '/web/wp-config.php');

        expect($config)->toContain('wp foehn salts:generate');
        expect($config)->toContain("!defined('WP_CLI')");
        // The old build defined guessable keys from md5(__DIR__) instead of stopping.
        expect($config)->not->toContain("'change-me-' . \$salt");
    });

    it('stops a production request that has no keys', function () {
        // The generated wp-config.php is only ever executed by a booting WordPress,
        // so the guard is run here for real rather than matched as a string.
        mkdir($this->root . '/vendor', 0o777, true);
        file_put_contents($this->root . '/vendor/autoload.php', "<?php\n");

        ($this->generate)();

        unlink($this->root . '/.env');

        $output = [];
        $status = 0;
        exec(
            'WP_ENVIRONMENT_TYPE=production php ' . escapeshellarg($this->root . '/web/wp-config.php') . ' 2>&1',
            $output,
            $status,
        );

        expect($status)->not->toBe(0);
        expect(implode("\n", $output))->toContain('wp foehn salts:generate');
    });

    it('serves a development request that has no keys', function () {
        mkdir($this->root . '/vendor', 0o777, true);
        file_put_contents($this->root . '/vendor/autoload.php', "<?php\n");

        ($this->generate)();

        unlink($this->root . '/.env');

        $output = [];
        $status = 0;
        exec(
            'WP_ENVIRONMENT_TYPE=development php ' . escapeshellarg($this->root . '/web/wp-config.php') . ' 2>&1',
            $output,
            $status,
        );

        // It gets far enough to fail on WordPress itself being absent, which is proof
        // it did not stop at the keys.
        expect(implode("\n", $output))->not->toContain('wp foehn salts:generate');
    });

    it('loads .env before the project configuration', function () {
        linkRealAutoloader($this->root);

        mkdir($this->root . '/config', 0o777, true);
        file_put_contents(
            $this->root . '/config/wordpress.config.php',
            "<?php echo 'BASE:' . \$env('FOEHN_PROBE', 'nothing') . PHP_EOL;\n",
        );

        ($this->generate)();

        file_put_contents($this->root . '/.env', "FOEHN_PROBE=from-dotenv\n", FILE_APPEND);

        // The config files used to run before .env was loaded, so a project could not
        // read its own environment from them.
        expect(runWpConfig($this->root))->toContain('BASE:from-dotenv');
    });

    it('selects the environment-specific config from .env', function () {
        linkRealAutoloader($this->root);

        mkdir($this->root . '/config', 0o777, true);
        file_put_contents(
            $this->root . '/config/wordpress.development.config.php',
            "<?php echo 'DEVELOPMENT' . PHP_EOL;\n",
        );
        file_put_contents(
            $this->root . '/config/wordpress.production.config.php',
            "<?php echo 'PRODUCTION' . PHP_EOL;\n",
        );

        ($this->generate)();

        file_put_contents($this->root . '/.env', "WP_ENVIRONMENT_TYPE=development\n", FILE_APPEND);

        // This used to read getenv(), which .env does not populate, so a project that
        // set WP_ENVIRONMENT_TYPE there silently got the production config while the
        // security-keys guard below read the same variable and saw development.
        $output = runWpConfig($this->root);

        expect($output)->toContain('DEVELOPMENT');
        expect($output)->not->toContain('PRODUCTION');
    });

    it('still defines the constants after the project configuration has run', function () {
        linkRealAutoloader($this->root);

        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/wordpress.config.php', "<?php // nothing\n");

        ($this->generate)();

        // $env is a closure the defines below depend on. The config block used to
        // reuse the name for the environment string, which would now overwrite it.
        $output = runWpConfig($this->root, ['WP_ENVIRONMENT_TYPE' => 'development']);

        expect($output)->not->toContain('not callable');
        expect($output)->toContain('wp-settings.php');
    });

    it('clears a discovery cache left by the previous release', function () {
        $cache = $this->root . '/web/wp-content/cache/foehn/discovery';
        mkdir($cache, 0o777, true);
        file_put_contents($cache . '/entry.php', '<?php return [];');

        ($this->generate)();

        // Foehn refills it on the first request; what must not survive is the entry
        // describing the code this install just replaced.
        expect(file_exists($cache . '/entry.php'))->toBeFalse();
        expect(is_dir($this->root . '/web/wp-content/cache/foehn'))->toBeFalse();
        expect($this->io->getOutput())->toContain('Cleared:');
    });

    it('clears a page cache left by the previous release', function () {
        // The stored HTML was rendered by templates this install has just replaced.
        // Nothing has to boot WordPress to invalidate it, and the next request refills.
        $pages = $this->root . '/web/wp-content/cache/foehn/pages/example.com/blog';
        mkdir($pages, 0o777, true);
        file_put_contents($pages . '/index.html', '<html>old</html>');

        ($this->generate)();

        expect(file_exists($pages . '/index.html'))->toBeFalse();
        expect(is_dir($this->root . '/web/wp-content/cache/foehn'))->toBeFalse();
    });

    it('leaves other caches in wp-content alone', function () {
        $other = $this->root . '/web/wp-content/cache/some-plugin';
        mkdir($other, 0o777, true);
        file_put_contents($other . '/keep.txt', 'keep me');

        ($this->generate)();

        expect(file_exists($other . '/keep.txt'))->toBeTrue();
    });

    it('says nothing about a cache that was never written', function () {
        ($this->generate)();

        expect($this->io->getOutput())->not->toContain('Cleared:');
    });

    it('refuses to replace a real directory with a symlink', function () {
        mkdir($this->root . '/web/wp-content/themes/theme', 0o777, true);

        ($this->generate)();

        expect(is_link($this->root . '/web/wp-content/themes/theme'))->toBeFalse();
        expect($this->io->getOutput())->toContain('already exists as directory');
    });
});
