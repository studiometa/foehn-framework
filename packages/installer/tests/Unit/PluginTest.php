<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\HttpDownloader;
use Composer\Util\Loop;
use Studiometa\FoehnInstaller\Plugin;

beforeEach(function () {
    $this->io = recordingIo();
    $this->root = makeProjectRoot('vendor');

    /**
     * A real Composer, assembled from its own objects rather than mocked: the plugin
     * asks it where the vendor directory is, what `extra` holds, and where
     * studiometa/foehn was installed, and those answers are the whole of what it does
     * before handing over to the generator.
     *
     * @param array<string, mixed> $extra
     */
    $this->composer = function (array $extra, ?string $foehnPath = null): Composer {
        $composer = new Composer();

        $config = new Config(false, $this->root);
        $config->merge(['config' => ['vendor-dir' => $this->root . '/vendor']]);
        $composer->setConfig($config);

        $package = new RootPackage('studiometa/test-project', '1.0.0.0', '1.0.0');
        $package->setExtra($extra);
        $composer->setPackage($package);

        $httpDownloader = new HttpDownloader($this->io, $config);
        $repositoryManager = new RepositoryManager($this->io, $config, $httpDownloader);
        $repository = new InstalledArrayRepository();

        if ($foehnPath !== null) {
            $repository->addPackage(new Package('studiometa/foehn', '1.0.0.0', '1.0.0'));
        }

        $repositoryManager->setLocalRepository($repository);
        $composer->setRepositoryManager($repositoryManager);

        $installationManager = new class(new Loop($httpDownloader), $this->io) extends InstallationManager {
            public ?string $path = null;

            public function getInstallPath(PackageInterface $package): ?string
            {
                return $this->path;
            }
        };
        $installationManager->path = $foehnPath;
        $composer->setInstallationManager($installationManager);

        return $composer;
    };

    $this->run = function (Composer $composer): void {
        $plugin = new Plugin();
        $plugin->activate($composer, $this->io);
        $plugin->onPostInstall(new Event(ScriptEvents::POST_INSTALL_CMD, $composer, $this->io));
    };
});

afterEach(fn() => removeDirectory($this->root));

describe('Plugin', function () {
    it('subscribes to the events that follow an install', function () {
        expect(Plugin::getSubscribedEvents())->toBe([
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstall',
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdate',
        ]);
    });

    it('generates nothing in a project that is not a Foehn project', function () {
        // Composer runs this plugin in every project that happens to require it, and
        // a stray web/ directory in one of those is not a small surprise.
        ($this->run)(($this->composer)([]));

        expect(is_dir($this->root . '/web'))->toBeFalse();
        expect($this->io->getOutput())->toBe('');
    });

    it('generates the web root for a project that configures it', function () {
        ($this->run)(($this->composer)(['foehn' => ['theme-name' => 'starter-theme']]));

        expect($this->root . '/web/index.php')->toBeFile();
        expect($this->root . '/web/wp-config.php')->toBeFile();
        expect($this->io->getOutput())->toContain('Web root generated successfully');
    });

    it('generates the web root for a project that has a theme directory', function () {
        mkdir($this->root . '/theme');

        ($this->run)(($this->composer)([]));

        expect(is_link($this->root . '/web/wp-content/themes/theme'))->toBeTrue();
    });

    it('honours the directories the project named', function () {
        mkdir($this->root . '/sources');

        ($this->run)(($this->composer)([
            'foehn' => ['web-dir' => 'public', 'theme-dir' => 'sources', 'theme-name' => 'mine'],
        ]));

        expect($this->root . '/public/index.php')->toBeFile();
        expect(is_link($this->root . '/public/wp-content/themes/mine'))->toBeTrue();
    });

    it('copies the editor registrar from wherever Composer installed the framework', function () {
        // Resolved through Composer's own APIs so that a path repository or a symlink
        // reports the place the files actually are.
        $package = makeProjectRoot('resources/js');
        file_put_contents($package . '/resources/js/editor.js', "console.log('registrar');\n");

        try {
            ($this->run)(($this->composer)(['foehn' => ['theme-name' => 'x']], $package));

            expect(file_get_contents($this->root . '/web/wp-content/foehn/editor.js'))
                ->toContain("console.log('registrar');");
        } finally {
            removeDirectory($package);
        }
    });

    it('reports a framework package it cannot locate', function () {
        ($this->run)(($this->composer)(['foehn' => ['theme-name' => 'x']]));

        expect($this->io->getOutput())->toContain('Skipped editor script');
    });

    it('runs again after an update', function () {
        $composer = ($this->composer)(['foehn' => ['theme-name' => 'x']]);

        $plugin = new Plugin();
        $plugin->activate($composer, $this->io);
        $plugin->onPostUpdate(new Event(ScriptEvents::POST_UPDATE_CMD, $composer, $this->io));

        expect($this->root . '/web/index.php')->toBeFile();
    });

    it('has nothing to undo', function () {
        $composer = ($this->composer)([]);
        $plugin = new Plugin();

        $plugin->activate($composer, $this->io);
        $plugin->deactivate($composer, $this->io);
        $plugin->uninstall($composer, $this->io);

        expect($this->io->getOutput())->toBe('');
    });
});
