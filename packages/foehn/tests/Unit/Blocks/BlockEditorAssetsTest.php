<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\BlockEditorAssets;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\BlockDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\BlockFixture;

beforeEach(function () {
    wp_stub_reset();

    $this->scriptPath = WP_CONTENT_DIR . '/foehn/editor.js';

    // BlockDiscovery is not a container singleton, so the definitions must come
    // from the discovery instance the DiscoveryRunner holds.
    $this->discovery = new BlockDiscovery();
    $this->discovery->discover(
        testDiscoveryLocation('Tests\\Fixtures\\', __DIR__),
        new \Tempest\Reflection\ClassReflector(BlockFixture::class),
    );

    $this->makeRunner = function (array $discoveries): DiscoveryRunner {
        $runner = testDiscoveryRunner(new GenericContainer());

        new ReflectionProperty(DiscoveryRunner::class, 'discoveries')->setValue($runner, $discoveries);

        // Stand in for a runner that has already scanned, so that reading the
        // discoveries back does not replace them with a fresh scan.
        new ReflectionProperty(DiscoveryRunner::class, 'discovered')->setValue($runner, true);

        return $runner;
    };

    // The registrar is copied into the web root by the installer.
    if (!is_dir(dirname($this->scriptPath))) {
        mkdir(dirname($this->scriptPath), 0o777, true);
    }

    file_put_contents($this->scriptPath, '// generated registrar');
});

afterEach(function () {
    if (is_file($this->scriptPath)) {
        unlink($this->scriptPath);
    }
});

describe('BlockEditorAssets', function () {
    it('enqueues the registrar with the static dependency array', function () {
        $runner = ($this->makeRunner)([BlockDiscovery::class => $this->discovery]);
        $assets = new BlockEditorAssets($runner, new FoehnConfig());

        $assets->enqueue();

        $scripts = wp_stub_get_calls('wp_enqueue_script');

        expect($scripts)->toHaveCount(1);
        expect($scripts[0]['args']['handle'])->toBe('foehn-editor');
        expect($scripts[0]['args']['src'])->toBe('http://example.com/wp-content/foehn/editor.js');
        expect($scripts[0]['args']['deps'])->toBe([
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-server-side-render',
        ]);
        expect($scripts[0]['args']['in_footer'])->toBeTrue();
    });

    it('versions the registrar with its file modification time', function () {
        $runner = ($this->makeRunner)([BlockDiscovery::class => $this->discovery]);
        $assets = new BlockEditorAssets($runner, new FoehnConfig());

        $assets->enqueue();

        $scripts = wp_stub_get_calls('wp_enqueue_script');

        expect($scripts[0]['args']['ver'])->toBe((string) filemtime($this->scriptPath));
    });

    it('inlines the block definitions before the registrar', function () {
        $runner = ($this->makeRunner)([BlockDiscovery::class => $this->discovery]);
        $assets = new BlockEditorAssets($runner, new FoehnConfig());

        $assets->enqueue();

        $inline = wp_stub_get_calls('wp_add_inline_script');

        expect($inline)->toHaveCount(1);
        expect($inline[0]['args']['handle'])->toBe('foehn-editor');
        expect($inline[0]['args']['position'])->toBe('before');
        expect($inline[0]['args']['data'])->toStartWith('window.foehnBlocks = ');
        expect($inline[0]['args']['data'])->toEndWith(';');

        $json = substr($inline[0]['args']['data'], strlen('window.foehnBlocks = '), -1);

        expect(json_decode($json, true))->toBe($this->discovery->getEditorDefinitions());
    });

    it('does nothing when the runner has no block discovery', function () {
        $runner = ($this->makeRunner)([]);
        $assets = new BlockEditorAssets($runner, new FoehnConfig());

        $assets->enqueue();

        expect(wp_stub_get_calls('wp_enqueue_script'))->toBeEmpty();
        expect(wp_stub_get_calls('wp_add_inline_script'))->toBeEmpty();
    });

    it('does nothing when no block was discovered', function () {
        $runner = ($this->makeRunner)([BlockDiscovery::class => new BlockDiscovery()]);
        $assets = new BlockEditorAssets($runner, new FoehnConfig());

        $assets->enqueue();

        expect(wp_stub_get_calls('wp_enqueue_script'))->toBeEmpty();
        expect(wp_stub_get_calls('wp_add_inline_script'))->toBeEmpty();
    });

    it('enqueues no registrar when it has not been generated', function () {
        unlink($this->scriptPath);

        $runner = ($this->makeRunner)([BlockDiscovery::class => $this->discovery]);
        $assets = new BlockEditorAssets($runner, new FoehnConfig());

        $assets->enqueue();

        expect(wp_stub_get_calls('wp_enqueue_script'))->toBeEmpty();

        $inline = wp_stub_get_calls('wp_add_inline_script');

        // No definitions are inlined, only the diagnostic.
        expect($inline)->toHaveCount(1);
        expect($inline[0]['args']['data'])->not->toContain('window.foehnBlocks');
    });

    it('reports the missing registrar in the editor console whatever the debug mode', function () {
        unlink($this->scriptPath);

        $runner = ($this->makeRunner)([BlockDiscovery::class => $this->discovery]);
        $assets = new BlockEditorAssets($runner, new FoehnConfig(debug: false));

        $assets->enqueue();

        $inline = wp_stub_get_calls('wp_add_inline_script');

        // Without the registrar every Foehn block disappears from the inserter, so the
        // cause must be visible on the screen where the symptom shows up — not only in
        // a debug-gated PHP warning.
        expect($inline)->toHaveCount(1);
        expect($inline[0]['args']['handle'])->toBe('wp-blocks');
        expect($inline[0]['args']['data'])->toContain('window.console.error');
        expect($inline[0]['args']['data'])->toContain('Block editor registrar not found');
        expect($inline[0]['args']['data'])->toContain('no Foehn block is available in the editor');
    });

    it('warns about the missing registrar when debug is enabled', function () {
        unlink($this->scriptPath);

        $runner = ($this->makeRunner)([BlockDiscovery::class => $this->discovery]);
        $assets = new BlockEditorAssets($runner, new FoehnConfig(debug: true));

        $warningMessage = '';

        set_error_handler(function ($errno, $errstr) use (&$warningMessage) {
            if ($errno === E_USER_WARNING) {
                $warningMessage = $errstr;
            }

            return true;
        });

        try {
            $assets->enqueue();
        } finally {
            restore_error_handler();
        }

        expect($warningMessage)->toContain('[Foehn] Block editor registrar not found');
        expect($warningMessage)->toContain($this->scriptPath);
        expect($warningMessage)->toContain('composer install');
    });

    it('stays silent about the missing registrar when debug is disabled', function () {
        unlink($this->scriptPath);

        $runner = ($this->makeRunner)([BlockDiscovery::class => $this->discovery]);
        $assets = new BlockEditorAssets($runner, new FoehnConfig(debug: false));

        $warningTriggered = false;

        set_error_handler(function ($errno) use (&$warningTriggered) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
            }

            return true;
        });

        try {
            $assets->enqueue();
        } finally {
            restore_error_handler();
        }

        expect($warningTriggered)->toBeFalse();
    });
});
