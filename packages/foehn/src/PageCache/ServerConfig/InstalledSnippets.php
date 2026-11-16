<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache\ServerConfig;

/**
 * The nginx page-cache snippets a project keeps, whatever their file name.
 *
 * `wp foehn cache:config --server=nginx --write` writes `config/nginx/foehn-page-cache.conf`,
 * but a project can commit the snippet under another name, or in `.ddev/nginx/` for ddev.
 * A snippet found only by its name would go stale without a word after an upgrade that
 * changes the policy hash. So each `*.conf` file of these directories is read, and a file
 * is a Føhn snippet when it holds the comment the generator writes.
 */
final readonly class InstalledSnippets
{
    /**
     * The directories searched, relative to the project root.
     *
     * `config/nginx/` is where the Docker image includes project rules from, and
     * `.ddev/nginx/` is where ddev includes them from.
     *
     * @var list<string>
     */
    public const DIRECTORIES = ['config/nginx', '.ddev/nginx'];

    /** The first line of the comment the generator writes. */
    private const MARKER = '# Foehn page cache';

    /**
     * Every Føhn nginx snippet under the project, and whether it carries the policy hash.
     *
     * @return array<string, bool> Whether each snippet is current, by path relative to the project root.
     */
    public static function nginx(string $projectRoot, string $hash): array
    {
        $snippets = [];

        foreach (self::DIRECTORIES as $directory) {
            $files = glob(rtrim($projectRoot, '/') . '/' . $directory . '/*.conf');

            foreach ($files === false ? [] : $files as $file) {
                $contents = is_file($file) ? (string) file_get_contents($file) : '';

                if (!str_contains($contents, self::MARKER)) {
                    continue;
                }

                $snippets[$directory . '/' . basename($file)] =
                    preg_match('/^\s*# policy: ' . preg_quote($hash, '/') . '\s*$/m', $contents) === 1;
            }
        }

        return $snippets;
    }
}
