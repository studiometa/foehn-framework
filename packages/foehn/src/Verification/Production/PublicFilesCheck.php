<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Production;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Studiometa\Foehn\Verification\VerificationResult;

/**
 * Every file of the project's public directory is in the web root, unchanged.
 *
 * `studiometa/foehn-installer` copies `extra.foehn.public-dir` (default `public`) into
 * the web root on `composer install`. A deploy that skipped the install, or a runtime
 * image built without the directory, serves a site without its `.user.ini`, its
 * `robots.txt` or its `.well-known/` files, and nothing else reports it.
 *
 * A file the installer refused to copy — `public/index.php` against the generated
 * front controller — fails too: the project ships a file the site never serves.
 *
 * No path reaches the report except paths relative to the public directory.
 */
final readonly class PublicFilesCheck implements Check
{
    public const NAME = 'public-files';

    public function __construct(
        /** The directory as the project names it, for the report. */
        private string $directory,
        /** Its absolute path, or null when the project has none the installer copies. */
        private ?string $source,
        /** The absolute path of the web root. */
        private string $webRoot,
    ) {}

    public function run(): array
    {
        if ($this->source === null || !is_dir($this->source)) {
            return [VerificationResult::pass(
                self::NAME,
                "The project has no {$this->directory}/ directory for the installer to copy.",
                ['directory' => $this->directory, 'files' => 0],
            )];
        }

        $files = $this->files();
        $missing = [];
        $different = [];

        foreach ($files as $relative) {
            $source = $this->source . '/' . $relative;
            $target = $this->webRoot . '/' . $relative;

            if (!is_file($target)) {
                $missing[] = $relative;

                continue;
            }

            if (hash_file('xxh128', $source) !== hash_file('xxh128', $target)) {
                $different[] = $relative;
            }
        }

        $details = [
            'directory' => $this->directory,
            'files' => count($files),
            'missing' => $missing,
            'different' => $different,
        ];

        if ($missing === [] && $different === []) {
            return [VerificationResult::pass(
                self::NAME,
                sprintf(
                    'The web root holds the %d file%s of %s/.',
                    count($files),
                    count($files) === 1 ? '' : 's',
                    $this->directory,
                ),
                $details,
            )];
        }

        $failed = count($missing) + count($different);

        return [VerificationResult::fail(
            self::NAME,
            sprintf(
                '%d file%s of %s/ %s missing from the web root or different from it. Run `composer install`.',
                $failed,
                $failed === 1 ? '' : 's',
                $this->directory,
                $failed === 1 ? 'is' : 'are',
            ),
            $details,
        )];
    }

    /**
     * The regular files of the public directory, relative to it, sorted.
     *
     * Symlinks are left out, as the installer leaves them out.
     *
     * @return list<string>
     */
    private function files(): array
    {
        $source = (string) $this->source;
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $source,
            FilesystemIterator::SKIP_DOTS,
        ));

        $files = [];

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            if ($entry->isLink() || !$entry->isFile()) {
                continue;
            }

            $files[] = substr($entry->getPathname(), strlen($source) + 1);
        }

        sort($files, SORT_STRING);

        return $files;
    }
}
