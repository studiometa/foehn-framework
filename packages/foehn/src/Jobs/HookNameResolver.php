<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Jobs;

/**
 * Resolves hook names from class names.
 *
 * Converts FQCN to a WordPress-friendly hook name:
 * `App\Jobs\ProcessImport` → `foehn/app/jobs/process_import`
 */
final class HookNameResolver
{
    /**
     * Resolve a hook name for a cron class.
     */
    public static function forCron(string $className, ?string $customHook = null): string
    {
        return $customHook ?? self::classToHook($className);
    }

    /**
     * Resolve a hook name for a job DTO class.
     */
    public static function forJob(string $dtoClassName, ?string $customHook = null): string
    {
        return $customHook ?? self::classToHook($dtoClassName);
    }

    /**
     * Convert a class name to a hook name.
     *
     * `App\Jobs\ProcessImport` → `foehn/app/jobs/process_import`
     */
    private static function classToHook(string $className): string
    {
        // Remove leading backslash
        $className = ltrim($className, '\\');

        // Convert namespace separators to forward slashes
        $path = str_replace('\\', '/', $className);

        // Convert CamelCase to snake_case for each segment
        $segments = explode('/', $path);
        $segments = array_map(self::camelToSnake(...), $segments);

        return 'foehn/' . implode('/', $segments);
    }

    /**
     * Convert a CamelCase string to snake_case.
     */
    private static function camelToSnake(string $value): string
    {
        $result = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $value);

        return strtolower($result ?? $value);
    }
}
