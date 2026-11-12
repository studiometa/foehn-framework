<?php

declare(strict_types=1);

namespace Studiometa\FoehnInstaller;

use Composer\IO\IOInterface;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copies the project's public directory into the generated web root.
 *
 * `web/` is rebuilt on every `composer install`, so a file a project needs at the
 * document root — `.user.ini`, `robots.txt`, `.well-known/` — has to come from a
 * directory the project versions. That directory is `public/` by default
 * (`extra.foehn.public-dir`), and its content is copied, never symlinked: the web root
 * must stay self-contained for a runtime image or an `rsync` deployment, and a webserver
 * should not follow links out of it.
 *
 * The installer's own paths win. `index.php`, `wp-config.php`, WordPress core and
 * `wp-content/` are never written from the public directory, so a project cannot break
 * the boot by accident.
 *
 * The files copied are listed in {@see self::MANIFEST}. The next run reads that list and
 * deletes each file the public directory no longer holds, so a file removed from
 * `public/` disappears from the web root on the next install.
 */
final class PublicFiles
{
    /**
     * The list of copied files, relative to the web root.
     *
     * A PHP file and not JSON, because it sits inside the document root: a request for
     * it runs it and prints nothing.
     */
    public const MANIFEST = 'wp-content/foehn/public-files.php';

    /**
     * Files the installer generates at the top of the web root.
     *
     * @var list<string>
     */
    private const GENERATED = ['index.php', 'wp-config.php'];

    public function __construct(
        private readonly IOInterface $io,
        private readonly string $projectRoot,
        private readonly InstallerConfig $config,
    ) {}

    /**
     * Copy the public directory into the web root and remove what it no longer holds.
     */
    public function sync(): void
    {
        $webRoot = realpath($this->projectRoot . '/' . $this->config->webDir);

        if ($webRoot === false) {
            return;
        }

        $previous = $this->readManifest($webRoot);
        $source = $this->source($webRoot);
        $copied = $source === null ? [] : $this->copy($source, $webRoot);

        $this->removeStale($webRoot, array_values(array_diff($previous, $copied)));
        $this->writeManifest($webRoot, $copied);
    }

    /**
     * The real path of the public directory, or null when there is none to copy.
     *
     * Refused, with a warning, when it is outside the project — a `public-dir` such as
     * `../..` would publish whatever it reaches — or when it overlaps the web root, which
     * would copy the web root into itself.
     */
    private function source(string $webRoot): ?string
    {
        $name = $this->config->publicDir;
        $path = realpath($this->projectRoot . '/' . $name);

        if ($path === false || !is_dir($path)) {
            return null;
        }

        $projectRoot = realpath($this->projectRoot);

        if ($projectRoot === false || !self::isInside($path, $projectRoot)) {
            $this->io->write(
                "  <warning>Skipped public files:</warning> public-dir \"{$name}\" is not inside the project",
            );

            return null;
        }

        if ($path === $webRoot || self::isInside($path, $webRoot) || self::isInside($webRoot, $path)) {
            $this->io->write(
                "  <warning>Skipped public files:</warning> public-dir \"{$name}\" overlaps the web root "
                . "\"{$this->config->webDir}\" — set extra.foehn.public-dir to another directory",
            );

            return null;
        }

        return $path;
    }

    /**
     * Copy every file of the public directory, and return the relative paths copied.
     *
     * @return list<string>
     */
    private function copy(string $source, string $webRoot): array
    {
        $copied = [];

        foreach ($this->files($source) as $relative => $file) {
            $label = $this->config->publicDir . '/' . $relative;

            if ($file->isLink()) {
                $this->io->write(
                    "  <warning>Skipped public file:</warning> {$label} is a symlink — copy the file instead",
                );

                continue;
            }

            if ($this->isReserved($relative)) {
                $this->io->write(
                    "  <warning>Skipped public file:</warning> {$label} — the installer owns "
                    . "{$this->config->webDir}/{$relative}",
                );

                continue;
            }

            $target = $webRoot . '/' . $relative;
            $directory = dirname($target);

            // The closest directory that exists must resolve inside the web root before
            // anything is created below it: a symlink on the way would create the
            // directories somewhere else.
            $existing = $directory;

            while (!file_exists($existing)) {
                $existing = dirname($existing);
            }

            $realDirectory = realpath($existing);
            $inWebRoot =
                $realDirectory !== false && ($realDirectory === $webRoot || self::isInside($realDirectory, $webRoot));

            if ($inWebRoot && is_dir($existing) && !is_dir($directory)) {
                mkdir($directory, 0o755, true);
            }

            if (!$inWebRoot || !is_dir($directory) || is_link($target) || is_dir($target)) {
                $this->io->write(
                    "  <warning>Skipped public file:</warning> {$label} — "
                    . "a directory, a file or a symlink is in the way of {$this->config->webDir}/{$relative}",
                );

                continue;
            }

            copy($file->getPathname(), $target);
            $copied[] = $relative;

            $this->io->write("  <comment>Copied:</comment> {$label} → {$this->config->webDir}/{$relative}");
        }

        return $copied;
    }

    /**
     * Every entry of the public directory that is not a directory, keyed by its path
     * relative to it, in a stable order.
     *
     * Dotfiles are included. A symlinked directory is not entered, and is returned as
     * an entry of its own so that the caller can report it.
     *
     * @return array<string, SplFileInfo>
     */
    private function files(string $source): array
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $files = [];

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                continue;
            }

            $files[substr($entry->getPathname(), strlen($source) + 1)] = $entry;
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * Whether a path of the web root belongs to the installer or to WordPress.
     */
    private function isReserved(string $relative): bool
    {
        if (in_array($relative, self::GENERATED, true)) {
            return true;
        }

        foreach (['wp-content', $this->config->wpDir] as $directory) {
            if ($relative === $directory || str_starts_with($relative, $directory . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete the files an earlier run copied and the public directory no longer holds,
     * then the directories this leaves empty.
     *
     * @param list<string> $stale
     */
    private function removeStale(string $webRoot, array $stale): void
    {
        foreach ($stale as $relative) {
            // The list is read back from the web root: a path that could leave it, or
            // that names a file of the installer, is not one this class wrote.
            if (
                $relative === ''
                || str_starts_with($relative, '/')
                || in_array('..', explode('/', $relative), true)
                || $this->isReserved($relative)
            ) {
                continue;
            }

            $path = $webRoot . '/' . $relative;
            $directory = realpath(dirname($path));

            // The installer copies regular files only. A symlink in their place, or a
            // directory on the way that is now a symlink, belongs to someone else, and
            // the file it reaches may be outside the web root.
            if (
                $directory === false
                || $directory !== $webRoot && !self::isInside($directory, $webRoot)
                || is_link($path)
                || !is_file($path)
            ) {
                continue;
            }

            unlink($path);
            $this->io->write("  <comment>Removed:</comment> {$this->config->webDir}/{$relative}");

            $this->removeEmptyDirectories($webRoot, dirname($relative));
        }
    }

    private function removeEmptyDirectories(string $webRoot, string $relative): void
    {
        while ($relative !== '.' && $relative !== '') {
            $path = $webRoot . '/' . $relative;

            if (is_link($path) || !is_dir($path) || (scandir($path) ?: []) !== ['.', '..']) {
                return;
            }

            rmdir($path);
            $relative = dirname($relative);
        }
    }

    /**
     * @return list<string>
     */
    private function readManifest(string $webRoot): array
    {
        $path = $webRoot . '/' . self::MANIFEST;

        if (!is_file($path)) {
            return [];
        }

        $files = (static fn(string $file): mixed => include $file)($path);

        if (!is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, is_string(...)));
    }

    /**
     * @param list<string> $files
     */
    private function writeManifest(string $webRoot, array $files): void
    {
        $path = $webRoot . '/' . self::MANIFEST;

        if ($files === []) {
            if (is_file($path)) {
                unlink($path);
            }

            return;
        }

        $content =
            "<?php\n\n"
            . "/**\n"
            . " * Files copied from the public directory — auto-generated by foehn-installer.\n"
            . " * DO NOT EDIT — the next `composer install` reads this list to remove the files\n"
            . " * the public directory no longer holds.\n"
            . " */\n\n"
            . 'return '
            . var_export($files, true)
            . ";\n";

        file_put_contents($path, $content);
    }

    /**
     * Whether a path is strictly below a directory.
     */
    private static function isInside(string $path, string $directory): bool
    {
        return str_starts_with($path, rtrim($directory, '/') . '/');
    }
}
