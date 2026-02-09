<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use ReflectionException;

/**
 * Scans PHP classes from an app directory using Composer's PSR-4 autoload map.
 *
 * Extracted from DiscoveryRunner to reduce complexity and separate concerns.
 */
final class ClassScanner
{
    /**
     * Build a DiscoveryLocation for the given app path.
     */
    public static function buildLocation(string $appPath): ?DiscoveryLocation
    {
        $resolved = realpath($appPath);

        if ($resolved === false) {
            return null;
        }

        $namespace = self::resolveNamespace($resolved);

        return DiscoveryLocation::app($namespace, $resolved);
    }

    /**
     * Scan all concrete classes in the given location.
     *
     * @return array<ReflectionClass<object>>
     */
    public static function scan(DiscoveryLocation $location): array
    {
        $appPath = $location->path;

        if (!is_dir($appPath)) {
            return [];
        }

        $classes = [];
        $classMap = self::buildClassMap($appPath);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $appPath,
            \RecursiveDirectoryIterator::SKIP_DOTS,
        ));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            $className = $classMap[$filePath] ?? self::extractClassName($filePath);

            if ($className === null || !class_exists($className)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);

                if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                    continue;
                }

                $classes[] = $reflection;
            } catch (ReflectionException) {
                continue;
            }
        }

        return $classes;
    }

    /**
     * Resolve the PSR-4 namespace for the app path from Composer's autoload.
     */
    private static function resolveNamespace(string $appPath): string
    {
        $loader = self::getComposerLoader($appPath);

        if ($loader === null) {
            return 'App\\';
        }

        foreach ($loader->getPrefixesPsr4() as $prefix => $dirs) {
            foreach ($dirs as $dir) {
                $dir = realpath($dir);

                if ($dir === false) {
                    continue;
                }

                if ($dir === $appPath || str_starts_with($appPath, $dir)) {
                    return $prefix;
                }
            }
        }

        return 'App\\';
    }

    /**
     * Build a file path to class name map from Composer's PSR-4 autoload.
     *
     * @return array<string, string>
     */
    private static function buildClassMap(string $appPath): array
    {
        $map = [];
        $loader = self::getComposerLoader($appPath);

        if ($loader === null) {
            return $map;
        }

        foreach ($loader->getPrefixesPsr4() as $prefix => $dirs) {
            foreach ($dirs as $dir) {
                $dir = realpath($dir);

                if ($dir === false || !is_dir($dir)) {
                    continue;
                }

                if (!str_starts_with($appPath, $dir) && !str_starts_with($dir, $appPath)) {
                    continue;
                }

                $dirIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                    $dir,
                    \RecursiveDirectoryIterator::SKIP_DOTS,
                ));

                /** @var \SplFileInfo $file */
                foreach ($dirIterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    $filePath = $file->getRealPath();

                    if ($filePath === false) {
                        continue;
                    }

                    $relativePath = substr($filePath, strlen($dir) + 1);
                    $map[$filePath] = $prefix . str_replace(['/', '.php'], ['\\', ''], $relativePath);
                }
            }
        }

        return $map;
    }

    /**
     * Get the Composer class loader.
     */
    private static function getComposerLoader(string $appPath): ?\Composer\Autoload\ClassLoader
    {
        $autoloadFiles = [
            dirname($appPath) . '/vendor/autoload.php',
            $appPath . '/../../vendor/autoload.php',
            $appPath . '/../../../vendor/autoload.php',
        ];

        foreach ($autoloadFiles as $autoloadFile) {
            $resolved = realpath($autoloadFile);

            if ($resolved === false || !file_exists($resolved)) {
                continue;
            }

            $loader = require $resolved;

            if ($loader instanceof \Composer\Autoload\ClassLoader) {
                return $loader;
            }
        }

        return null;
    }

    /**
     * Extract class name from a PHP file by parsing namespace and class declarations.
     */
    private static function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        $namespace = null;
        $class = null;
        $matches = null;

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (preg_match('/(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $class : $class;
    }
}
