<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Settings\Setting;

/**
 * A settings page: what it stores, and what its form looks like.
 *
 * Implemented alongside #[AsSettingsPage].
 */
interface SettingsPageInterface
{
    /**
     * What this page stores, keyed by option name.
     *
     * Each key becomes a WordPress option of that exact name — the Settings API
     * has no namespacing, so name them as you would name an option.
     *
     * @return array<string, Setting>
     */
    public static function settings(): array;

    /**
     * The body of the form.
     *
     * Called inside the page shell, between `do_settings_sections()` and the
     * submit button, so it prints fields and nothing else. Read the current
     * values with `Settings::get()`.
     */
    public function render(): void;
}
