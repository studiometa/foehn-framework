<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Security\Salts;

#[AsCliCommand(name: 'salts:generate', description: 'Generate the WordPress security keys', longDescription: <<<'DOC'
    ## DESCRIPTION

    Writes a fresh set of the eight WordPress security keys to the project's .env,
    which is where wp-config.php reads them from.

    These keys sign authentication cookies and nonces. A site running on guessable
    keys is a site whose login cookies can be forged, so wp-config.php refuses to
    serve a production request when they are missing.

    The installer generates them on a first install. Run this to rotate them, or on a
    project whose keys were never generated.

    Rotating logs every user out: the cookies they hold were signed with the keys
    being replaced.

    Keys already set in the environment — container variables, a vault — are what
    WordPress uses, whatever .env holds. This command writes .env, so a project that
    keeps its secrets elsewhere should put them there instead.

    ## OPTIONS

    [--path=<path>]
    : Write to this file instead of .env. A path ending in .php is written as a PHP
    file of define() calls, the shape wp-config.php requires.

    [--force]
    : Replace keys that are already set.

    [--yes]
    : Skip the confirmation prompt.

    ## EXAMPLES

        # Generate keys for a project that has none
        wp foehn salts:generate

        # Rotate the keys, logging everyone out
        wp foehn salts:generate --force

        # Write a PHP file instead
        wp foehn salts:generate --path=config/wordpress-salts.config.php
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
        $root = $this->projectRoot();
        $path = $this->resolvePath($assocArgs, $root);

        if ($path === null) {
            $this->cli->error('Could not work out where to write the keys. Pass --path=<path>.');

            return;
        }

        $force = ($assocArgs['force'] ?? null) !== null;
        $salts = Salts::generate();

        if (str_ends_with($path, '.php')) {
            $this->writePhpFile($path, $salts, $force, $assocArgs);

            return;
        }

        $this->writeEnvFile($path, $salts, $force, $assocArgs, $root);
    }

    /**
     * Add the keys to a dotenv file, leaving everything else in it alone.
     *
     * @param array<string, string> $assocArgs
     */
    private function writeEnvFile(string $path, Salts $salts, bool $force, array $assocArgs, ?string $root): void
    {
        $contents = is_file($path) ? (string) file_get_contents($path) : '';
        $present = array_values(array_filter(
            Salts::NAMES,
            static fn(string $name): bool => preg_match('/^[ \t]*' . $name . '[ \t]*=[ \t]*\S/m', $contents) === 1,
        ));

        if ($present !== [] && !$force) {
            $this->cli->error(sprintf(
                '%s already sets %s. Pass --force to replace them, which logs every user out.',
                $path,
                implode(', ', $present),
            ));

            return;
        }

        if ($present !== []) {
            // WP-CLI ends the process when the answer is no, so there is no branch to
            // take here: reaching the next line means it was yes.
            $this->cli->confirm('Replace the existing keys? Every user will be logged out.', $assocArgs);
        }

        foreach ($salts->values as $name => $value) {
            $line = sprintf('%s="%s"', $name, $value);
            $pattern = '/^[ \t]*' . $name . '[ \t]*=.*$/m';

            // A name may be present but empty — .env.example lists them that way — so
            // an existing line is replaced whether or not it held anything.
            $contents = preg_match($pattern, $contents) === 1
                ? (string) preg_replace($pattern, $line, $contents, 1)
                : $this->append($contents, $line);
        }

        if (file_put_contents($path, $contents) === false) {
            $this->cli->error("Could not write {$path}.");

            return;
        }

        $this->cli->success(sprintf('Wrote %d security keys to %s.', count(Salts::NAMES), $path));
        $this->warnAboutPhpFile($root);
        $this->warnIfReadableByOthers($path);
    }

    /**
     * Append a line under a heading, adding the heading the first time.
     */
    private function append(string $contents, string $line): string
    {
        $heading = '# WordPress security keys — secret, and specific to this install';

        if (!str_contains($contents, $heading)) {
            $contents = rtrim($contents, "\n") . "\n\n" . $heading . "\n";
        }

        return rtrim($contents, "\n") . "\n" . $line . "\n";
    }

    /**
     * Write the PHP file wp-config.php requires.
     *
     * @param array<string, string> $assocArgs
     */
    private function writePhpFile(string $path, Salts $salts, bool $force, array $assocArgs): void
    {
        if (is_file($path) && !$force) {
            $this->cli->error(sprintf(
                'Keys already exist at %s. Pass --force to replace them, which logs every user out.',
                $path,
            ));

            return;
        }

        if (is_file($path)) {
            $this->cli->confirm('Replace the existing keys? Every user will be logged out.', $assocArgs);
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0o755, true)) {
            $this->cli->error("Could not create {$directory}.");

            return;
        }

        if (file_put_contents($path, $salts->toPhpFile()) === false) {
            $this->cli->error("Could not write {$path}.");

            return;
        }

        chmod($path, 0o600);

        $this->cli->success(sprintf('Wrote %d security keys to %s.', count(Salts::NAMES), $path));
        $this->cli->log('Keep this file out of version control.');
    }

    /**
     * Say so when a PHP salts file would win over the keys just written.
     *
     * wp-config.php requires that file before it reads the environment, so rotating
     * .env while one exists changes nothing WordPress will see.
     */
    private function warnAboutPhpFile(?string $root): void
    {
        if ($root === null || !is_file($root . '/config/wordpress-salts.config.php')) {
            return;
        }

        $this->cli->warning(
            'config/wordpress-salts.config.php exists and is read first, so these keys will not be used. '
            . 'Delete it, or rotate it with --path=config/wordpress-salts.config.php.',
        );
    }

    /**
     * A file of secrets that anyone on the host can read is worth mentioning.
     */
    private function warnIfReadableByOthers(string $path): void
    {
        $mode = fileperms($path);

        if ($mode === false || ($mode & 0o077) === 0) {
            return;
        }

        $this->cli->warning(sprintf('%s is readable by other users (%s).', $path, decoct($mode & 0o777)));
    }

    /**
     * Where to write, defaulting to the project's .env.
     *
     * @param array<string, string> $assocArgs
     */
    private function resolvePath(array $assocArgs, ?string $root): ?string
    {
        // An empty --path is a caller that meant to pass one and did not; it falls
        // back rather than writing to the current directory.
        $path = $assocArgs['path'] ?? '';

        if ($path !== '') {
            // A relative path is relative to the project, not to wherever wp ran.
            return str_starts_with($path, '/') || $root === null ? $path : $root . '/' . $path;
        }

        return $root === null ? null : $root . '/.env';
    }

    /**
     * The directory holding the project's .env, worked out from the web root.
     */
    private function projectRoot(): ?string
    {
        if (!defined('WP_CONTENT_DIR')) {
            return null;
        }

        // web/wp-content → the project root two levels up.
        return dirname((string) constant('WP_CONTENT_DIR'), 2);
    }
}
