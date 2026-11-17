<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Settings\Setting;

/**
 * What a settings page stores.
 *
 * Implemented alongside #[AsSettingsPage]. Each Setting also says which field
 * edits it, and Føhn draws the form from them.
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
}
