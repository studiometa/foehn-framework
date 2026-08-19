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
 * Give a generated project a `vendor/autoload.php` that loads this monorepo's.
 *
 * The generated `wp-config.php` is only worth executing when the classes it
 * requires are loadable — `Dotenv\Dotenv` above all, since the order in which
 * it runs is the thing under test.
 */
function linkRealAutoloader(string $root): void
{
    $loader = dirname(new ReflectionClass(Composer\Autoload\ClassLoader::class)->getFileName(), 2) . '/autoload.php';

    if (!is_dir($root . '/vendor')) {
        mkdir($root . '/vendor', 0o777, true);
    }

    file_put_contents($root . '/vendor/autoload.php', '<?php require ' . var_export($loader, true) . ';');
}

/**
 * Stand a probe where WordPress would be, and run everything before it.
 *
 * The generated `wp-config.php` ends on `require_once ABSPATH . 'wp-settings.php'`,
 * so a file at that path runs with every constant already defined — which is the
 * only way to assert on the values rather than on the strings that define them.
 */
function stubWordPress(string $root, string $probe): void
{
    mkdir($root . '/web/wp', 0o777, true);
    file_put_contents($root . '/web/wp/wp-settings.php', "<?php\n" . $probe);
}

/**
 * Run a generated `wp-config.php` and return everything it printed.
 *
 * It always ends by failing on WordPress being absent; what comes before that is
 * the interesting part.
 */
function runWpConfig(string $root, array $environment = []): string
{
    $prefix = '';

    foreach ($environment as $key => $value) {
        $prefix .= $key . '=' . escapeshellarg((string) $value) . ' ';
    }

    $output = [];
    exec($prefix . 'php ' . escapeshellarg($root . '/web/wp-config.php') . ' 2>&1', $output);

    return implode("\n", $output);
}

/**
 * An IO that records what the installer reported, so tests can assert on the
 * warnings a silent skip would otherwise hide.
 */
function recordingIo(): BufferIO
{
    return new BufferIO();
}
