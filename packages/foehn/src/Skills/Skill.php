<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Skills;

/**
 * One Agent Skill shipped by an installed package: a `skills/<name>/SKILL.md` file and
 * the files under its `references/` directory.
 *
 * `path` is relative to the project root, as an agent reads it from the project
 * directory: `vendor/studiometa/foehn/skills/foehn`. Inside a container the absolute
 * path would name a directory the host does not have.
 */
final readonly class Skill
{
    public function __construct(
        public string $name,
        public string $package,
        public ?string $version,
        public string $description,
        private string $root,
        public string $path,
    ) {}

    /**
     * The skill directory, absolute.
     */
    public function directory(): string
    {
        return $this->root . '/' . $this->path;
    }

    /**
     * The text of SKILL.md, frontmatter included.
     */
    public function contents(): string
    {
        return (string) file_get_contents($this->directory() . '/SKILL.md');
    }

    /**
     * The reference files, relative to the `references/` directory, sorted.
     *
     * @return list<string>
     */
    public function references(): array
    {
        $root = $this->directory() . '/references';

        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $root,
            \FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            if (!($file instanceof \SplFileInfo && $file->isFile())) {
                continue;
            }

            $files[] = substr($file->getPathname(), strlen($root) + 1);
        }

        sort($files);

        return $files;
    }

    /**
     * The text of one reference file, or null when the skill has no such file.
     *
     * The name is matched against the files that exist, never joined to a path, so
     * `../` cannot read outside the skill. `blocks`, `blocks.md` and
     * `references/blocks.md` all name `references/blocks.md`.
     */
    public function reference(string $name): ?string
    {
        $name = str_starts_with($name, 'references/') ? substr($name, strlen('references/')) : $name;

        foreach ($this->references() as $file) {
            if ($file === $name || $file === $name . '.md') {
                return (string) file_get_contents($this->directory() . '/references/' . $file);
            }
        }

        return null;
    }

    /**
     * @return array{name: string, package: string, version: ?string, description: string, path: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'package' => $this->package,
            'version' => $this->version,
            'description' => $this->description,
            'path' => $this->path . '/SKILL.md',
        ];
    }
}
