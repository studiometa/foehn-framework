<?php

declare(strict_types=1);

use Composer\IO\BufferIO;

/**
 * A project directory to generate into, cleaned up by the caller.
 *
 * The installer works on real files — it symlinks, copies and writes a web root —
 * so the tests give it a real directory rather than a mocked filesystem.
 */
function makeProjectRoot(string ...$directories): string
{
    $root = sys_get_temp_dir() . '/foehn-installer-tests/' . uniqid('project-', true);

    mkdir($root, 0o777, true);

    foreach ($directories as $directory) {
        mkdir($root . '/' . $directory, 0o777, true);
    }

    return $root;
}

/**
 * Delete a directory and everything below it, symlinks included.
 */
function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($entries as $entry) {
        // A symlink to a directory reports as a directory; unlink it rather than
        // recursing into the theme it points at.
        if ($entry->isLink() || !$entry->isDir()) {
            @unlink($entry->getPathname());

            continue;
        }

        @rmdir($entry->getPathname());
    }

    @rmdir($directory);
}

/**
 * An IO that records what the installer reported, so tests can assert on the
 * warnings a silent skip would otherwise hide.
 */
function recordingIo(): BufferIO
{
    return new BufferIO();
}
