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

    it('produces a web root whose PHP parses', function () {
        ($this->generate)();

        // These two files are written as strings and never executed by any other
        // test; a syntax error in them would only show up on a real install.
        foreach (['/web/index.php', '/web/wp-config.php'] as $file) {
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

    it('generates the security keys on a first install', function () {
        ($this->generate)();

        $path = $this->root . '/config/wordpress-salts.config.php';

        expect($path)->toBeFile();

        $contents = file_get_contents($path);

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
            expect($contents)->toContain("define('{$name}',");
        }

        // Every key random, and none of them each other.
        preg_match_all("/define\('[A-Z_]+', '([^']+)'\);/", $contents, $matches);

        expect($matches[1])->toHaveCount(8);
        expect(array_unique($matches[1]))->toHaveCount(8);

        foreach ($matches[1] as $value) {
            expect(strlen($value))->toBeGreaterThanOrEqual(64);
            expect($value)->not->toContain('change-me-');
        }
    });

    it('writes the keys as parsable PHP', function () {
        ($this->generate)();

        $output = [];
        $status = 0;
        exec(
            'php -l ' . escapeshellarg($this->root . '/config/wordpress-salts.config.php') . ' 2>&1',
            $output,
            $status,
        );

        expect($status)->toBe(0, implode("\n", $output));
    });

    it('keeps the keys readable by their owner alone', function () {
        ($this->generate)();

        $mode = fileperms($this->root . '/config/wordpress-salts.config.php') & 0o777;

        expect(decoct($mode))->toBe('600');
    });

    it('leaves the keys to .env when .env defines them all', function () {
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

        file_put_contents(
            $this->root . '/.env',
            implode("\n", array_map(static fn(string $name): string => "{$name}=from-dotenv-{$name}", $names)) . "\n",
        );

        ($this->generate)();

        // wp-config.php requires the file before reading the environment, so writing
        // one here would silently replace the keys the project already set.
        expect(file_exists($this->root . '/config/wordpress-salts.config.php'))->toBeFalse();
        expect($this->io->getOutput())->toContain('.env already defines them');
    });

    it('generates keys when .env defines only some of them', function () {
        file_put_contents($this->root . '/.env', "AUTH_KEY=only-one-of-eight\n");

        ($this->generate)();

        // A partial set would leave the rest to the production refusal, so the file
        // is written and takes over.
        expect($this->root . '/config/wordpress-salts.config.php')->toBeFile();
    });

    it('never replaces keys that already exist', function () {
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/wordpress-salts.config.php', "<?php // mine\n");

        ($this->generate)();

        // Rewriting on every composer install would log every user out on every deploy.
        expect(file_get_contents($this->root . '/config/wordpress-salts.config.php'))->toContain('// mine');
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

        unlink($this->root . '/config/wordpress-salts.config.php');

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

        unlink($this->root . '/config/wordpress-salts.config.php');

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
