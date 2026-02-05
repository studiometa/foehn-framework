<?php

declare(strict_types=1);

use Studiometa\Foehn\Assets\WebpackManifest;

describe('WebpackManifest', function () {
    beforeEach(function () {
        wp_stub_reset();

        $this->fixturesDir = __DIR__ . '/fixtures';
        $this->manifestDir = $this->fixturesDir . '/dist';
        $this->manifestPath = $this->manifestDir . '/assets-manifest.json';

        if (!is_dir($this->manifestDir)) {
            mkdir($this->manifestDir, 0o755, true);
        }

        // Realistic manifest schema matching @studiometa/webpack-config output
        $this->validManifest = [
            'css/app.css' => 'css/app.abc123.css',
            'js/app.js' => 'js/app.def456.js',
            'entrypoints' => [
                'css/app' => [
                    'assets' => [
                        'js' => ['manifest.abc123.js'],
                        'css' => ['css/app.abc123.css'],
                    ],
                ],
                'js/app' => [
                    'assets' => [
                        'js' => ['manifest.abc123.js', 'vendors.xyz789.js', 'js/app.def456.js'],
                    ],
                ],
                'mixed' => [
                    'assets' => [
                        'css' => ['mixed.abc123.css'],
                        'js' => ['mixed.def456.js'],
                    ],
                ],
            ],
        ];

        // Set up theme directory stubs to point to fixtures
        $GLOBALS['wp_stub_template_directory'] = $this->fixturesDir;
        $GLOBALS['wp_stub_template_directory_uri'] = 'http://example.com/themes/theme';
        $GLOBALS['wp_stub_stylesheet_directory'] = $this->fixturesDir;
        $GLOBALS['wp_stub_stylesheet_directory_uri'] = 'http://example.com/themes/child-theme';
    });

    afterEach(function () {
        // Cleanup created files recursively
        $cleanup = function ($dir) use (&$cleanup) {
            if (!is_dir($dir)) {
                return;
            }
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $cleanup($path) : unlink($path);
            }
            rmdir($dir);
        };

        $cleanup($this->fixturesDir);
    });

    describe('constructor', function () {
        it('fails gracefully when manifest file not found', function () {
            $manifest = new WebpackManifest(
                '/non/existent/manifest.json',
                'dist/',
                'http://example.com/dist/',
                '/var/www/dist/',
            );

            expect($manifest->exists())->toBeFalse();
            expect($manifest->getManifest())->toBeNull();
        });

        it('creates instance with valid manifest', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/dist/',
                $this->fixturesDir . '/dist/',
            );

            expect($manifest)->toBeInstanceOf(WebpackManifest::class);
            expect($manifest->exists())->toBeTrue();
            expect($manifest->getManifest())->toBeInstanceOf(\Studiometa\WebpackConfig\Manifest::class);
        });

        it('uses WordPress functions when baseUri and basePath not provided', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = new WebpackManifest($this->manifestPath, 'dist/');

            expect($manifest->exists())->toBeTrue();
        });
    });

    describe('fromTheme', function () {
        it('creates instance from theme directory', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = WebpackManifest::fromTheme('/dist/assets-manifest.json', 'dist/');

            expect($manifest)->toBeInstanceOf(WebpackManifest::class);
            expect($manifest->exists())->toBeTrue();
        });

        it('fails gracefully when theme manifest not found', function () {
            $manifest = WebpackManifest::fromTheme('/non/existent.json');

            expect($manifest->exists())->toBeFalse();
        });
    });

    describe('fromChildTheme', function () {
        it('creates instance from child theme directory', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = WebpackManifest::fromChildTheme('/dist/assets-manifest.json', 'dist/');

            expect($manifest)->toBeInstanceOf(WebpackManifest::class);
            expect($manifest->exists())->toBeTrue();
        });

        it('fails gracefully when child theme manifest not found', function () {
            $manifest = WebpackManifest::fromChildTheme('/non/existent.json');

            expect($manifest->exists())->toBeFalse();
        });
    });

    describe('enqueueEntry', function () {
        it('returns self when manifest not found', function () {
            $manifest = new WebpackManifest(
                '/non/existent/manifest.json',
                'dist/',
                'http://example.com/dist/',
                '/var/www/dist/',
            );

            $result = $manifest->enqueueEntry('css/app');

            expect($result)->toBe($manifest);
            expect(wp_stub_get_calls('wp_enqueue_style'))->toBeEmpty();
        });

        it('returns self when entry not found', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/dist/',
                $this->fixturesDir . '/dist/',
            );
            $result = $manifest->enqueueEntry('non-existent');

            expect($result)->toBe($manifest);
            expect(wp_stub_get_calls('wp_enqueue_style'))->toBeEmpty();
        });

        it('enqueues styles from entry', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            // Create the CSS file for version hashing (in dist/ since Manifest prepends distPath)
            mkdir($this->manifestDir . '/css', 0o755, true);
            file_put_contents($this->manifestDir . '/css/app.abc123.css', 'body {}');

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/',
                $this->fixturesDir . '/',
            );
            $manifest->enqueueEntry('css/app', prefix: 'theme', media: 'screen');

            $calls = wp_stub_get_calls('wp_enqueue_style');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['handle'])->toStartWith('theme-');
            expect($calls[0]['args']['src'])->toContain('dist/css/app.abc123.css');
            expect($calls[0]['args']['media'])->toBe('screen');
            expect($calls[0]['args']['ver'])->not->toBeNull(); // Has version hash
        });

        it('enqueues scripts from entry', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            // Create the JS files for version hashing (in dist/ since Manifest prepends distPath)
            mkdir($this->manifestDir . '/js', 0o755, true);
            file_put_contents($this->manifestDir . '/manifest.abc123.js', '// manifest');
            file_put_contents($this->manifestDir . '/vendors.xyz789.js', '// vendors');
            file_put_contents($this->manifestDir . '/js/app.def456.js', 'console.log("hello");');

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/',
                $this->fixturesDir . '/',
            );
            $manifest->enqueueEntry('js/app', prefix: 'app', inFooter: true);

            $calls = wp_stub_get_calls('wp_enqueue_script');
            expect($calls)->toHaveCount(3); // manifest, vendors, app
            expect($calls[0]['args']['in_footer'])->toBeTrue();
            expect($calls[2]['args']['src'])->toContain('dist/js/app.def456.js');
        });

        it('enqueues both styles and scripts from mixed entry', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            // Create files (in dist/ since Manifest prepends distPath)
            file_put_contents($this->manifestDir . '/mixed.abc123.css', 'body {}');
            file_put_contents($this->manifestDir . '/mixed.def456.js', 'console.log("mixed");');

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/',
                $this->fixturesDir . '/',
            );
            $manifest->enqueueEntry('mixed', prefix: 'theme', deps: ['jquery']);

            expect(wp_stub_get_calls('wp_enqueue_style'))->toHaveCount(1);
            expect(wp_stub_get_calls('wp_enqueue_script'))->toHaveCount(1);

            // Check deps are passed
            $styleCalls = wp_stub_get_calls('wp_enqueue_style');
            expect($styleCalls[0]['args']['deps'])->toBe(['jquery']);
        });

        it('handles missing asset files gracefully (null version)', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            // Don't create the actual CSS file
            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/',
                $this->fixturesDir . '/',
            );
            $manifest->enqueueEntry('css/app');

            $calls = wp_stub_get_calls('wp_enqueue_style');
            expect($calls)->toHaveCount(1);
            expect($calls[0]['args']['ver'])->toBeNull(); // No version when file doesn't exist
        });

        it('returns self for fluent interface', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/',
                $this->fixturesDir . '/',
            );

            $result = $manifest->enqueueEntry('css/app')->enqueueEntry('js/app', inFooter: true);

            expect($result)->toBe($manifest);
        });
    });

    describe('enqueueEntries', function () {
        it('enqueues multiple entries at once', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/',
                $this->fixturesDir . '/',
            );
            $manifest->enqueueEntries(['css/app', 'mixed'], prefix: 'theme', inFooter: true);

            // css/app has 1 css + 1 js (manifest), mixed has 1 css + 1 js
            expect(wp_stub_get_calls('wp_enqueue_style'))->toHaveCount(2);
            expect(wp_stub_get_calls('wp_enqueue_script'))->toHaveCount(2);
        });

        it('returns self for fluent interface', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/',
                $this->fixturesDir . '/',
            );
            $result = $manifest->enqueueEntries(['css/app', 'js/app']);

            expect($result)->toBe($manifest);
        });
    });

    describe('exists', function () {
        it('returns true when manifest is loaded', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/',
                $this->fixturesDir . '/',
            );

            expect($manifest->exists())->toBeTrue();
        });

        it('returns false when manifest is not found', function () {
            $manifest = new WebpackManifest(
                '/non/existent.json',
                'dist/',
                'http://example.com/dist/',
                '/var/www/dist/',
            );

            expect($manifest->exists())->toBeFalse();
        });
    });

    describe('getManifest', function () {
        it('returns Manifest instance when loaded', function () {
            file_put_contents($this->manifestPath, json_encode($this->validManifest));

            $manifest = new WebpackManifest(
                $this->manifestPath,
                'dist/',
                'http://example.com/',
                $this->fixturesDir . '/',
            );

            expect($manifest->getManifest())->toBeInstanceOf(\Studiometa\WebpackConfig\Manifest::class);
        });

        it('returns null when manifest not found', function () {
            $manifest = new WebpackManifest(
                '/non/existent.json',
                'dist/',
                'http://example.com/dist/',
                '/var/www/dist/',
            );

            expect($manifest->getManifest())->toBeNull();
        });
    });
});
