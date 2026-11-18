<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\PrepublishChecks;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\BlockDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\BlockFixture;
use Tests\Fixtures\PrepublishBlockFixture;

beforeEach(function () {
    wp_stub_reset();

    $GLOBALS['wp_stub_current_screen'] = new WP_Screen('post', '', true);

    $this->scriptPath = WP_CONTENT_DIR . '/foehn/prepublish-checks.js';

    $this->discovery = new BlockDiscovery(new DiscoveryProblems());

    foreach ([BlockFixture::class, PrepublishBlockFixture::class] as $class) {
        $this->discovery->discover(
            testDiscoveryLocation('Tests\\Fixtures\\', __DIR__),
            new \Tempest\Reflection\ClassReflector($class),
        );
    }

    $this->makeChecks = function (
        FoehnConfig $config = new FoehnConfig(),
        ?BlockDiscovery $discovery = null,
    ): PrepublishChecks {
        $runner = testDiscoveryRunner(new GenericContainer());

        new ReflectionProperty(DiscoveryRunner::class, 'discoveries')->setValue($runner, [
            BlockDiscovery::class => $discovery ?? $this->discovery,
        ]);
        new ReflectionProperty(DiscoveryRunner::class, 'discovered')->setValue($runner, true);

        return new PrepublishChecks($runner, $config);
    };

    $this->payload = function (): array {
        $inline = wp_stub_get_calls('wp_add_inline_script');

        expect($inline)->toHaveCount(1);
        expect($inline[0]['args']['handle'])->toBe(PrepublishChecks::HANDLE);
        expect($inline[0]['args']['position'])->toBe('before');

        return json_decode(substr($inline[0]['args']['data'], strlen('window.foehnPrepublishChecks = '), -1), true);
    };

    if (!is_dir(dirname($this->scriptPath))) {
        mkdir(dirname($this->scriptPath), 0o777, true);
    }

    file_put_contents($this->scriptPath, '// generated checks');
});

afterEach(function () {
    if (is_file($this->scriptPath)) {
        unlink($this->scriptPath);
    }
});

describe('PrepublishChecks', function () {
    it('enqueues the checks script from the web root', function () {
        ($this->makeChecks)()->enqueue();

        $scripts = wp_stub_get_calls('wp_enqueue_script');

        expect($scripts)->toHaveCount(1);
        expect($scripts[0]['args']['handle'])->toBe('foehn-prepublish-checks');
        expect($scripts[0]['args']['src'])->toBe('http://example.com/wp-content/foehn/prepublish-checks.js');
        expect($scripts[0]['args']['deps'])->toContain('wp-plugins', 'wp-editor', 'wp-hooks', 'wp-data');
        expect($scripts[0]['args']['ver'])->toBe((string) filemtime($this->scriptPath));
        expect($scripts[0]['args']['in_footer'])->toBeTrue();
    });

    it('skips the screens that are not the block post editor', function (WP_Screen $screen) {
        $GLOBALS['wp_stub_current_screen'] = $screen;

        ($this->makeChecks)()->enqueue();

        expect(wp_stub_get_calls('wp_enqueue_script'))->toBeEmpty();
        expect(wp_stub_get_calls('wp_add_inline_script'))->toBeEmpty();
    })->with([
        'block widgets screen' => [new WP_Screen('widgets', '', true)],
        'classic post editor' => [new WP_Screen('post', '', false)],
    ]);

    it('skips a request with no current screen', function () {
        unset($GLOBALS['wp_stub_current_screen']);

        ($this->makeChecks)()->enqueue();

        expect(wp_stub_get_calls('wp_enqueue_script'))->toBeEmpty();
    });

    it('lists the image and heading level attributes of each Føhn block', function () {
        ($this->makeChecks)()->enqueue();

        expect(($this->payload)()['blocks'])->toBe([
            'test/teaser' => [
                'images' => ['imageId' => 'Background', 'images' => 'Images'],
                'headings' => ['titleLevel'],
            ],
        ]);
    });

    it('prints an empty block map as an object', function () {
        ($this->makeChecks)(new FoehnConfig(), new BlockDiscovery(new DiscoveryProblems()))->enqueue();

        $inline = wp_stub_get_calls('wp_add_inline_script');

        expect($inline[0]['args']['data'])->toContain('"blocks":{}');
    });

    it('only warns by default', function () {
        ($this->makeChecks)()->enqueue();

        expect(($this->payload)()['blockPublication'])->toBeFalse();
    });

    it('blocks publication when the config asks for it', function () {
        ($this->makeChecks)(new FoehnConfig(blockPublicationOnAccessibilityIssues: true))->enqueue();

        expect(($this->payload)()['blockPublication'])->toBeTrue();
    });

    it('skips a missing script, with a warning in debug mode', function () {
        unlink($this->scriptPath);

        $warning = '';

        set_error_handler(function ($errno, $errstr) use (&$warning) {
            $warning = $errstr;

            return true;
        });

        try {
            ($this->makeChecks)(new FoehnConfig(debug: true))->enqueue();
        } finally {
            restore_error_handler();
        }

        expect(wp_stub_get_calls('wp_enqueue_script'))->toBeEmpty();
        expect(wp_stub_get_calls('wp_add_inline_script'))->toBeEmpty();
        expect($warning)->toContain('[Foehn] Pre-publish checks script not found')->toContain('composer install');
    });

    it('skips a missing script silently out of debug mode', function () {
        unlink($this->scriptPath);

        $warned = false;

        set_error_handler(function () use (&$warned) {
            $warned = true;

            return true;
        });

        try {
            ($this->makeChecks)(new FoehnConfig(debug: false))->enqueue();
        } finally {
            restore_error_handler();
        }

        expect($warned)->toBeFalse();
        expect(wp_stub_get_calls('wp_enqueue_script'))->toBeEmpty();
    });
});
