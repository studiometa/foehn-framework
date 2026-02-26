<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Mark a class as an async job handler.
 *
 * The class must implement `__invoke()` with a single typed parameter (the job DTO).
 * Jobs are dispatched manually via `dispatch()` or `JobDispatcher::dispatch()`.
 *
 * Usage:
 *
 *     // The job DTO (payload)
 *     final readonly class ProcessImport
 *     {
 *         public function __construct(public int $importId, public string $source) {}
 *     }
 *
 *     // The handler
 *     #[AsJob]
 *     final class ProcessImportHandler
 *     {
 *         public function __invoke(ProcessImport $job): void { ... }
 *     }
 *
 *     // Dispatch
 *     dispatch(new ProcessImport(42, 'csv'));
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsJob
{
    /**
     * @param string $group Action Scheduler group for admin UI filtering
     * @param string|null $hook Custom hook name (defaults to DTO class-derived name)
     */
    public function __construct(
        public string $group = 'foehn',
        public ?string $hook = null,
    ) {}
}
