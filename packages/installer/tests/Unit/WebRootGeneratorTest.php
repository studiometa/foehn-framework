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
