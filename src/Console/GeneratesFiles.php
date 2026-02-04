<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console;

use Studiometa\WPTempest\Kernel;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Generation\ClassManipulator;
use Tempest\Support\Filesystem;

use function Tempest\Support\str;

/**
 * Trait for generating files in WP-CLI commands.
 */
trait GeneratesFiles
{
    private readonly WpCli $cli;

    /**
     * Get the target path for a generated file.
     *
     * @param string $subdirectory Subdirectory within the app path (e.g., 'PostTypes', 'Blocks')
     * @param string $className Class name without extension
     */
    protected function getTargetPath(string $subdirectory, string $className): string
    {
        $appPath = $this->getAppPath();

        return rtrim($appPath, '/') . '/' . $subdirectory . '/' . $className . '.php';
    }

    /**
     * Check if we should generate the file.
     */
    protected function shouldGenerate(string $targetPath, bool $force): bool
    {
        if (Filesystem\is_file($targetPath) && !$force) {
            $this->cli->error(
                "File already exists: {$this->cli->getRelativePath($targetPath)}\n" . 'Use --force to overwrite.',
                exit: false,
            );

            return false;
        }

        return true;
    }

    /**
     * Generate a class file from a stub.
     *
     * @param class-string $stubClass Stub class to use as template
     * @param string $targetPath Target file path
     * @param array<string, string> $replacements Key-value pairs to replace in the stub
     */
    protected function generateClassFile(string $stubClass, string $targetPath, array $replacements = []): void
    {
        // Ensure directory exists
        $directory = dirname($targetPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        // Get namespace from target path
        $namespace = $this->resolveNamespace($targetPath);
        $className = pathinfo($targetPath, PATHINFO_FILENAME);

        // Manipulate the stub class
        $manipulator = new ClassManipulator($stubClass);
        $manipulator->setNamespace($namespace)->setClassName($className)->removeClassAttribute(SkipDiscovery::class);

        // Get the generated content
        $content = $manipulator->print();

        // Apply replacements
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        // Write the file
        Filesystem\write_file($targetPath, $content);
    }

    /**
     * Generate a raw file from a template.
     *
     * @param string $templatePath Path to template file
     * @param string $targetPath Target file path
     * @param array<string, string> $replacements Key-value pairs to replace
     */
    protected function generateRawFile(string $templatePath, string $targetPath, array $replacements = []): void
    {
        // Ensure directory exists
        $directory = dirname($targetPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $content = Filesystem\read_file($templatePath);

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, (string) $content);
        }

        Filesystem\write_file($targetPath, $content);
    }

    /**
     * Get the app path from the kernel.
     */
    protected function getAppPath(): string
    {
        return Kernel::getInstance()->getAppPath();
    }

    /**
     * Resolve namespace from file path.
     */
    protected function resolveNamespace(string $filePath): string
    {
        $appPath = $this->getAppPath();
        $relativePath = str_replace($appPath, '', dirname($filePath));
        $relativePath = trim($relativePath, '/');

        // Try to find the base namespace from composer.json
        $baseNamespace = $this->findBaseNamespace($appPath);

        if ($relativePath === '') {
            return $baseNamespace;
        }

        $namespaceSegments = array_map(
            static fn(string $segment) => str($segment)->studly()->toString(),
            explode('/', $relativePath),
        );

        return $baseNamespace . '\\' . implode('\\', $namespaceSegments);
    }

    /**
     * Find the base namespace from composer.json.
     */
    protected function findBaseNamespace(string $appPath): string
    {
        // Check for composer.json in the theme/plugin
        $composerPath = $this->findComposerJson($appPath);

        if ($composerPath !== null && Filesystem\is_file($composerPath)) {
            $composer = json_decode((string) Filesystem\read_file($composerPath), true);
            $psr4 = $composer['autoload']['psr-4'] ?? [];

            // Find the namespace that points to our app path
            foreach ($psr4 as $namespace => $path) {
                $fullPath = dirname($composerPath) . '/' . rtrim((string) $path, '/');
                if (realpath($fullPath) === realpath($appPath)) {
                    return rtrim($namespace, '\\');
                }
            }

            // Return first namespace as fallback
            if (count($psr4) > 0) {
                return rtrim((string) array_key_first($psr4), '\\');
            }
        }

        // Default fallback
        return 'App';
    }

    /**
     * Find composer.json by walking up the directory tree.
     */
    protected function findComposerJson(string $startPath): ?string
    {
        $current = $startPath;
        $root = dirname($current, 10); // Safety limit

        while ($current !== $root && $current !== '/') {
            $composerPath = $current . '/composer.json';
            if (Filesystem\is_file($composerPath)) {
                return $composerPath;
            }
            $current = dirname($current);
        }

        return null;
    }
}
