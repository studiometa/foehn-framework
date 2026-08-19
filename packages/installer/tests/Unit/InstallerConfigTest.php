<?php

declare(strict_types=1);

use Studiometa\FoehnInstaller\InstallerConfig;

beforeEach(function () {
    $this->root = makeProjectRoot();
});

afterEach(fn() => removeDirectory($this->root));

describe('InstallerConfig', function () {
    it('stays disabled for a project that is not a Foehn project', function () {
        // No theme directory and no extra.foehn: Composer runs this plugin in every
        // project that happens to require it, and it must generate nothing there.
        expect(InstallerConfig::fromArray([], $this->root)->enabled)->toBeFalse();
    });

    it('enables itself when the project has a theme directory', function () {
        mkdir($this->root . '/theme');

        expect(InstallerConfig::fromArray([], $this->root)->enabled)->toBeTrue();
    });

    it('enables itself when the project configures it explicitly', function () {
        expect(InstallerConfig::fromArray(['theme-name' => 'my-theme'], $this->root)->enabled)->toBeTrue();
    });

    it('looks for the theme directory the project named', function () {
        mkdir($this->root . '/sources');

        $config = InstallerConfig::fromArray(['theme-dir' => 'sources'], $this->root);

        expect($config->enabled)->toBeTrue();
        expect($config->themeDir)->toBe('sources');
    });

    it('falls back to its defaults', function () {
        $config = InstallerConfig::fromArray([], $this->root);

        expect($config->webDir)->toBe('web');
        expect($config->wpDir)->toBe('wp');
        expect($config->themeDir)->toBe('theme');
        expect($config->themeName)->toBe('theme');
        expect($config->muPluginsDir)->toBe('mu-plugins');
        expect($config->configDir)->toBe('config');
    });

    it('reads every key from extra.foehn', function () {
        $config = InstallerConfig::fromArray([
            'web-dir' => 'public',
            'wp-dir' => 'wordpress',
            'theme-dir' => 'src/theme',
            'theme-name' => 'starter-theme',
            'mu-plugins-dir' => 'plugins/mu',
            'config-dir' => 'conf',
        ], $this->root);

        expect($config->webDir)->toBe('public');
        expect($config->wpDir)->toBe('wordpress');
        expect($config->themeDir)->toBe('src/theme');
        expect($config->themeName)->toBe('starter-theme');
        expect($config->muPluginsDir)->toBe('plugins/mu');
        expect($config->configDir)->toBe('conf');
    });
});
