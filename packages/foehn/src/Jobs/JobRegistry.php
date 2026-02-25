<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Jobs;

/**
 * Registry of discovered job handlers.
 *
 * Maps DTO class names to their hook names and groups,
 * allowing the dispatcher to know where to send jobs.
 */
final class JobRegistry
{
    /**
     * @var array<class-string, array{hook: string, group: string, handlerClass: class-string}>
     */
    private array $handlers = [];

    /**
     * Register a job handler for a DTO class.
     *
     * @param class-string $dtoClass The job DTO class
     * @param class-string $handlerClass The handler class
     */
    public function register(string $dtoClass, string $handlerClass, string $hook, string $group): void
    {
        $this->handlers[$dtoClass] = [
            'hook' => $hook,
            'group' => $group,
            'handlerClass' => $handlerClass,
        ];
    }

    /**
     * Get the registration for a DTO class.
     *
     * @param class-string $dtoClass
     * @return array{hook: string, group: string, handlerClass: class-string}|null
     */
    public function getForDto(string $dtoClass): ?array
    {
        return $this->handlers[$dtoClass] ?? null;
    }

    /**
     * Get all registered handlers.
     *
     * @return array<class-string, array{hook: string, group: string, handlerClass: class-string}>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * Check if a handler is registered for a DTO class.
     *
     * @param class-string $dtoClass
     */
    public function has(string $dtoClass): bool
    {
        return array_key_exists($dtoClass, $this->handlers);
    }
}
