<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Errors;

use Composer\InstalledVersions;

/**
 * Which files are the theme's own code, and how to name a file in an error entry.
 *
 * The theme's own code is every file under the theme directory, except its `vendor`
 * and `node_modules` directories. A warning raised anywhere else is a warning of
 * WordPress, a plugin or a library: the project cannot fix it in its own code.
 */
final class SourcePaths
{
    /** @var list<string>|null Resolved once: the constants it reads cannot change in a process. */
    private ?array $bases = null;

    /**
     * @param string $root The theme directory
     */
    public function __construct(
        private readonly string $root,
    ) {}

    /**
     * Whether a file is part of the theme's own code.
     */
    public function isOwn(string $file): bool
    {
        $root = rtrim($this->root, '/') . '/';

        if ($root === '/' || !str_starts_with($file, $root)) {
            return false;
        }

        $inside = '/' . substr($file, strlen($root));

        return !str_contains($inside, '/vendor/') && !str_contains($inside, '/node_modules/');
    }

    /**
     * A file relative to the Composer project or to WordPress, whichever is closer.
     *
     * A file outside both keeps its absolute path: the log stays on the machine that
     * wrote it, and the path is what finds the file there.
     */
    public function relative(string $file): string
    {
        foreach ($this->bases() as $base) {
            if (str_starts_with($file, $base)) {
                return substr($file, strlen($base));
            }
        }

        return $file;
    }

    /**
     * The directories a path can be relative to, longest first.
     *
     * @return list<string>
     */
    private function bases(): array
    {
        if ($this->bases !== null) {
            return $this->bases;
        }

        $candidates = [dirname($this->root)];

        if (defined('ABSPATH')) {
            $candidates[] = (string) constant('ABSPATH');
        }

        if (class_exists(InstalledVersions::class)) {
            $candidates[] = (string) realpath(InstalledVersions::getRootPackage()['install_path']);
        }

        $bases = [];

        foreach ($candidates as $candidate) {
            $base = rtrim($candidate, '/');

            // A base with one segment, such as /var, would make any path look like a
            // path of the project.
            if ($base === '' || substr_count($base, '/') < 2) {
                continue;
            }

            $bases[] = $base . '/';
        }

        $bases = array_values(array_unique($bases));
        usort($bases, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return $this->bases = $bases;
    }
}
