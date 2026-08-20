<?php

declare(strict_types=1);

use Studiometa\Foehn\Assets\ViteManifest;

/**
 * The class reads files, so the tests write them: a manifest and a hot file are the
 * two inputs, and which one exists is the whole behaviour.
 */
describe('ViteManifest', function () {
    beforeEach(function () {
        wp_stub_reset();

        $this->dist = sys_get_temp_dir() . '/foehn-vite-' . uniqid();
        $this->uri = 'https://example.test/wp-content/themes/theme/dist';

        mkdir($this->dist . '/.vite', 0o777, true);

        $this->writeManifest = function (array $manifest): void {
            file_put_contents($this->dist . '/.vite/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        };

        // The shape Vite actually emits: a stylesheet entry, and a script entry that
        // carries the CSS it imported.
        ($this->writeManifest)([
            'assets/css/app.css' => [
                'file' => 'assets/app-abc123.css',
                'src' => 'assets/css/app.css',
                'isEntry' => true,
            ],
            'assets/js/app.js' => [
                'file' => 'assets/app-def456.js',
                'src' => 'assets/js/app.js',
                'isEntry' => true,
                'css' => ['assets/app-imported-789.css'],
            ],
        ]);

        $this->manifest = fn(): ViteManifest => new ViteManifest($this->dist, $this->uri);
        $this->calls = fn(string $name): array => array_values(array_filter(
            $GLOBALS['wp_stub_calls'] ?? [],
            fn(array $call): bool => $call['function'] === $name,
        ));
    });

    afterEach(function () {
        array_map('unlink', glob($this->dist . '/.vite/*') ?: []);
        array_map('unlink', glob($this->dist . '/*') ?: []);
        @rmdir($this->dist . '/.vite');
        @rmdir($this->dist);
    });

    it('enqueues a stylesheet entry', function () {
        ($this->manifest)()->enqueue('assets/css/app.css', handle: 'theme-styles');

        $styles = ($this->calls)('wp_enqueue_style');

        expect($styles)->toHaveCount(1);
        expect($styles[0]['args']['src'])->toBe($this->uri . '/assets/app-abc123.css');
        expect(($this->calls)('wp_enqueue_script'))->toHaveCount(0);
    });

    it('enqueues a script entry', function () {
        ($this->manifest)()->enqueue('assets/js/app.js', handle: 'theme-app', inFooter: true);

        $scripts = ($this->calls)('wp_enqueue_script');

        expect($scripts)->toHaveCount(1);
        expect($scripts[0]['args']['handle'])->toBe('theme-app');
        expect($scripts[0]['args']['src'])->toBe($this->uri . '/assets/app-def456.js');
        expect($scripts[0]['args']['in_footer'])->toBeTrue();
    });

    it('enqueues the CSS a script entry imported', function () {
        ($this->manifest)()->enqueue('assets/js/app.js', handle: 'theme-app');

        $styles = ($this->calls)('wp_enqueue_style');

        // The failure this exists to stop: enqueue the JS, miss the `css` array, and
        // the page loads with no styles and no error anywhere.
        expect($styles)->toHaveCount(1);
        expect($styles[0]['args']['src'])->toBe($this->uri . '/assets/app-imported-789.css');
    });

    it('serves an ES module tag, which core will not do on its own', function () {
        $manifest = ($this->manifest)();
        $manifest->enqueue('assets/js/app.js', handle: 'theme-app');

        // WP_Scripts reads `strategy`, `before`, `after` and `data` — never `type` —
        // so wp_script_add_data($handle, 'type', 'module') is silently ignored and
        // the browser refuses the file as a classic script.
        $tag = $manifest->moduleTag('<script src="app.js" id="theme-app"></script>', 'theme-app', 'app.js');

        expect($tag)->toContain('type="module"');
    });

    it('leaves other handles alone', function () {
        $manifest = ($this->manifest)();
        $manifest->enqueue('assets/js/app.js', handle: 'theme-app');

        $original = '<script src="jquery.js" id="jquery-js"></script>';

        expect($manifest->moduleTag($original, 'jquery', 'jquery.js'))->toBe($original);
    });

    it('does nothing for an entry the build does not have', function () {
        ($this->manifest)()->enqueue('assets/js/missing.js', handle: 'nope');

        expect(($this->calls)('wp_enqueue_script'))->toHaveCount(0);
        expect(($this->calls)('wp_enqueue_style'))->toHaveCount(0);
    });

    it('does nothing at all without a build', function () {
        $manifest = new ViteManifest($this->dist . '/absent', $this->uri);

        $manifest->enqueue('assets/js/app.js', handle: 'theme-app');

        expect($manifest->exists())->toBeFalse();
        expect(($this->calls)('wp_enqueue_script'))->toHaveCount(0);
    });

    describe('with the dev server running', function () {
        beforeEach(function () {
            file_put_contents($this->dist . '/hot', "http://localhost:5173\n");
        });

        it('prefers the dev server over a build that also exists', function () {
            $manifest = ($this->manifest)();

            expect($manifest->isDevServer())->toBeTrue();

            $manifest->enqueue('assets/js/app.js', handle: 'theme-app');

            $scripts = ($this->calls)('wp_enqueue_script');
            $sources = array_column(array_column($scripts, 'args'), 'src');

            // The source path, not the hashed build output: the dev server resolves
            // it, and serving the build instead is what makes HMR look broken.
            expect($sources)->toContain('http://localhost:5173/assets/js/app.js');
            expect($sources)->not->toContain($this->uri . '/assets/app-def456.js');
        });

        it('loads the Vite client once, however many entries are enqueued', function () {
            ($this->manifest)()
                ->enqueue('assets/js/app.js', handle: 'theme-app')
                ->enqueue('assets/css/app.css', handle: 'theme-styles');

            $handles = array_column(array_column(($this->calls)('wp_enqueue_script'), 'args'), 'handle');

            expect(array_count_values($handles)['vite-client'])->toBe(1);
        });

        it('enqueues a stylesheet as a module rather than as a link', function () {
            ($this->manifest)()->enqueue('assets/css/app.css', handle: 'theme-styles');

            // The dev server hands CSS over as a module that injects itself; a <link>
            // to the source path would 404.
            expect(($this->calls)('wp_enqueue_style'))->toHaveCount(0);
            expect(($this->calls)('wp_enqueue_script'))->toHaveCount(2);
        });

        it('ignores an empty hot file', function () {
            file_put_contents($this->dist . '/hot', "  \n");

            expect(($this->manifest)()->isDevServer())->toBeFalse();
        });
    });
});
