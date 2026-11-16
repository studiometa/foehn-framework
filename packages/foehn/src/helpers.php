<?php

declare(strict_types=1);

namespace Studiometa\Foehn;

if (!function_exists('Studiometa\Foehn\app')) {
    /**
     * Get the kernel instance or a service from the container.
     *
     * @template T of object
     * @param class-string<T>|null $class
     * @return ($class is null ? Kernel : T)
     */
    function app(?string $class = null): object
    {
        if ($class === null) {
            return Kernel::getInstance();
        }

        return Kernel::get($class);
    }
}

if (!function_exists('Studiometa\Foehn\dispatch')) {
    /**
     * Dispatch a job DTO for async processing.
     *
     * The job will be serialized and processed in the background
     * by its corresponding `#[AsJob]` handler via Action Scheduler.
     *
     * @param object $job The job DTO to dispatch
     * @param int|null $delay Optional delay in seconds before the job runs
     */
    function dispatch(object $job, ?int $delay = null): void
    {
        app(Contracts\JobDispatcher::class)->dispatch($job, $delay);
    }
}

if (!function_exists('Studiometa\Foehn\breadcrumbs')) {
    /**
     * The breadcrumb trail of the current request, for the theme to write its own markup.
     *
     * Each item has a plain-text `label` and a `url`. The current page, the last item,
     * has no URL. The items come from Rank Math, and the list is empty when Rank Math or
     * its breadcrumbs are off.
     *
     * @return list<array{label: string, url: string|null}>
     */
    function breadcrumbs(): array
    {
        return Integrations\RankMath\RankMath::breadcrumbs();
    }
}
