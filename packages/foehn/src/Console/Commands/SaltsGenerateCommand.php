<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Security\Salts;

#[AsCliCommand(name: 'salts:generate', description: 'Generate the WordPress security keys', longDescription: <<<'DOC'
    ## DESCRIPTION

    Writes a fresh set of the eight WordPress security keys to
    config/wordpress-salts.config.php, which the generated wp-config.php reads.

    These keys sign authentication cookies and nonces. A site running on guessable
    keys is a site whose login cookies can be forged, so wp-config.php refuses to
    serve a production request when they are missing.

    The installer generates them on a first install. Run this to rotate them, or on a
    project whose keys were never generated — after a deploy that provisioned .env by
    hand, for instance.

    Rotating logs every user out: the cookies they hold were signed with the keys
    being replaced.

    ## OPTIONS

    [--path=<path>]
    : Write to this file instead of config/wordpress-salts.config.php.

    [--force]
    : Overwrite keys that already exist.

    [--yes]
    : Skip the confirmation prompt.

    ## EXAMPLES

        # Generate keys for a project that has none
        wp foehn salts:generate

        # Rotate the keys, logging everyone out
        wp foehn salts:generate --force

        # Write somewhere else
        wp foehn salts:generate --path=/srv/secrets/salts.php
    DOC)]
final readonly class SaltsGenerateCommand implements CliCommandInterface
{
    public function __construct(
        private WpCli $cli,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $path = $this->resolvePath($assocArgs);

        if ($path === null) {
            $this->cli->error('Could not work out where to write the keys. Pass --path=<path>.');

            return;
        }

        if (file_exists($path) && ($assocArgs['force'] ?? null) === null) {
            $this->cli->error(sprintf(
                'Keys already exist at %s. Pass --force to replace them, which logs every user out.',
                $path,
            ));

            return;
        }

        if (
            file_exists($path)
            && !$this->cli->confirm('Replace the existing keys? Every user will be logged out.', $assocArgs)
        ) {
            return;
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0o755, true)) {
            $this->cli->error("Could not create {$directory}.");

            return;
        }

        if (file_put_contents($path, Salts::generate()->toPhpFile()) === false) {
            $this->cli->error("Could not write {$path}.");

            return;
        }

        // The file holds secrets, so it is readable by its owner alone. wp-config.php
        // runs as the web server user, which is why generation belongs to the same
        // account that serves the site.
        chmod($path, 0o600);

        $this->cli->success(sprintf('Wrote %d security keys to %s.', count(Salts::NAMES), $path));
        $this->cli->log('Keep this file out of version control.');
    }

    /**
     * Where to write, defaulting to the file the generated wp-config.php reads.
     *
     * @param array<string, string> $assocArgs
     */
    private function resolvePath(array $assocArgs): ?string
    {
        // An empty --path is a caller that meant to pass one and did not; it falls
        // back rather than trying to write to the current directory.
        if (($assocArgs['path'] ?? '') !== '') {
            return $assocArgs['path'];
        }

        if (!defined('WP_CONTENT_DIR')) {
            return null;
        }

        // web/wp-content → the project root two levels up, where the installer puts
        // the config directory.
        return dirname((string) constant('WP_CONTENT_DIR'), 2) . '/config/wordpress-salts.config.php';
    }
}
