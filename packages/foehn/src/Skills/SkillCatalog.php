<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Skills;

use Studiometa\Foehn\Discovery\DiscoveryLocations;

/**
 * The Agent Skills of the packages the project has installed.
 *
 * A skill describes the release it ships with, so it is read from the installed
 * package and not from a copy that can fall behind. Sources:
 *
 * - every Composer package in `vendor/composer/installed.json` with a
 *   `skills/<name>/SKILL.md`. No package is named here, so a third-party package
 *   that ships a skill is listed too;
 * - `node_modules/@studiometa/foehn-vite-plugin/skills`, when the plugin is
 *   installed.
 *
 * Only files are read. Nothing here needs WordPress, so a later adapter can list the
 * skills of a project whose site does not boot.
 */
final readonly class SkillCatalog
{
    public const string VITE_PLUGIN = '@studiometa/foehn-vite-plugin';

    public function __construct(
        private DiscoveryLocations $locations,
    ) {}

    /**
     * Every skill, Composer packages first, each source in package name order.
     *
     * Two packages can ship a skill with the same name. Both are listed; find()
     * returns the first.
     *
     * @return list<Skill>
     */
    public function all(): array
    {
        $root = $this->locations->resolveRootPath();

        if ($root === null) {
            return [];
        }

        $skills = [];

        foreach (self::composerPackages($root) as $package) {
            array_push($skills, ...self::skillsIn($root, $package['path'], $package['name'], $package['version']));
        }

        $plugin = 'node_modules/' . self::VITE_PLUGIN;

        if (is_dir($root . '/' . $plugin)) {
            array_push($skills, ...self::skillsIn(
                $root,
                $plugin,
                self::VITE_PLUGIN,
                self::npmVersion($root . '/' . $plugin),
            ));
        }

        return $skills;
    }

    public function find(string $name): ?Skill
    {
        foreach ($this->all() as $skill) {
            if ($skill->name === $name) {
                return $skill;
            }
        }

        return null;
    }

    /**
     * Installed Composer packages, with their install path relative to the root.
     *
     * @return list<array{name: string, version: ?string, path: string}>
     */
    private static function composerPackages(string $root): array
    {
        $installed = self::readJson($root . '/vendor/composer/installed.json');
        $packages = $installed['packages'] ?? [];
        $rows = [];

        foreach (is_array($packages) ? $packages : [] as $package) {
            $name = is_array($package) ? $package['name'] ?? null : null;
            $installPath = is_array($package) ? $package['install-path'] ?? null : null;

            if (!is_string($name) || !is_string($installPath)) {
                continue;
            }

            $version = $package['version'] ?? null;

            $rows[] = [
                'name' => $name,
                'version' => is_string($version) ? $version : null,
                // install-path is relative to vendor/composer.
                'path' => self::normalize('vendor/composer/' . $installPath),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * @return list<Skill>
     */
    private static function skillsIn(string $root, string $packagePath, string $package, ?string $version): array
    {
        $files = glob($root . '/' . $packagePath . '/skills/*/SKILL.md') ?: [];
        sort($files);
        $skills = [];

        foreach ($files as $file) {
            $directory = basename(dirname($file));
            $fields = Frontmatter::parse((string) file_get_contents($file));
            $name = $fields['name'] ?? '';

            $skills[] = new Skill(
                name: $name !== '' ? $name : $directory,
                package: $package,
                version: $version,
                description: $fields['description'] ?? '',
                root: $root,
                path: $packagePath . '/skills/' . $directory,
            );
        }

        return $skills;
    }

    private static function npmVersion(string $directory): ?string
    {
        $version = self::readJson($directory . '/package.json')['version'] ?? null;

        return is_string($version) ? $version : null;
    }

    /**
     * A JSON file decoded to an array; empty when it is missing or not an object.
     *
     * @return array<array-key, mixed>
     */
    private static function readJson(string $file): array
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : [];
    }

    /**
     * Resolve `.` and `..` without touching the filesystem.
     *
     * realpath() would follow a symlinked path repository to its target, and the
     * path an agent should read is the one under vendor/.
     */
    private static function normalize(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            // A path repository can sit above the project root: keep the `..`
            // that leaves it.
            if ($segment === '..' && $segments !== [] && end($segments) !== '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
