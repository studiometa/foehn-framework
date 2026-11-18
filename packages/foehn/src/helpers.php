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

if (!function_exists('Studiometa\Foehn\multilingual')) {
    /**
     * The adapter of the multilingual plugin of the site, or null on a site with one
     * language.
     *
     * Polylang now. The WPML adapter takes its place here when WPML is active.
     */
    function multilingual(): ?Contracts\Multilingual
    {
        return Integrations\Polylang\Polylang::isActive() ? new Integrations\Polylang\Polylang() : null;
    }
}

if (!function_exists('Studiometa\Foehn\languages')) {
    /**
     * The language switcher of the current request, for the theme to write its own markup.
     *
     * One item for each language: its `code`, its `name`, the `url` of the translation
     * of the current page, whether it is the `current` language and whether the page is
     * `translated` in it. A language without a translation links to its home page. Empty
     * on a site with one language.
     *
     * @return list<array{code: string, name: string, url: string, current: bool, translated: bool}>
     */
    function languages(): array
    {
        $multilingual = multilingual();

        if ($multilingual === null) {
            return [];
        }

        $object = get_queried_object();
        $object = $object instanceof \WP_Post || $object instanceof \WP_Term ? $object : null;
        $translations = $object === null ? [] : $multilingual->translations($object);
        $current = $multilingual->currentLanguage();
        $items = [];

        foreach ($multilingual->languages() as $code => $name) {
            // Without an object, only the front page is the same page in every language.
            $translated = $object === null ? is_front_page() : array_key_exists($code, $translations);

            $items[] = [
                'code' => $code,
                'name' => $name,
                'url' => $multilingual->url($code, $translated ? $object : null),
                'current' => $code === $current,
                'translated' => $translated,
            ];
        }

        return $items;
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
