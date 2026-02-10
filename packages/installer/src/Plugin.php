<?php

declare(strict_types=1);

namespace Studiometa\FoehnInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin that generates the WordPress web root structure.
 *
 * On `post-install-cmd` and `post-update-cmd`, this plugin:
 * 1. Creates `web/` directory structure
 * 2. Symlinks the theme directory
 * 3. Symlinks custom mu-plugins
 * 4. Generates `wp-config.php`
 * 5. Generates `index.php`
 * 6. Generates the mu-plugin loader
 */
final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;

    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Nothing to do
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Nothing to do
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstall',
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdate',
        ];
    }

    public function onPostInstall(Event $event): void
    {
        $this->generateWebRoot();
    }

    public function onPostUpdate(Event $event): void
    {
        $this->generateWebRoot();
    }

    private function generateWebRoot(): void
    {
        $projectRoot = $this->getProjectRoot();
        $config = $this->loadInstallerConfig($projectRoot);

        // Only run if this is a Foehn project (has theme/ directory or foehn-installer config)
        if (!$config->enabled) {
            return;
        }

        $generator = new WebRootGenerator($this->io, $projectRoot, $config);
        $generator->generate();
    }

    private function getProjectRoot(): string
    {
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');

        return dirname((string) $vendorDir);
    }

    private function loadInstallerConfig(string $projectRoot): InstallerConfig
    {
        $extra = $this->composer->getPackage()->getExtra();
        $foehnConfig = $extra['foehn'] ?? [];

        return InstallerConfig::fromArray($foehnConfig, $projectRoot);
    }
}
