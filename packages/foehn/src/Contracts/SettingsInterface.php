<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Settings\Setting;

/**
 * What a settings page or a Customizer section stores.
 *
 * Implemented alongside #[AsSettingsPage] or #[AsCustomizerSection]. Each
 * Setting also says which field edits it, and Føhn draws the fields from them.
 */
interface SettingsInterface
{
    /**
     * What this page or section stores, keyed by option name.
     *
     * Each key becomes a WordPress option of that exact name — the Settings API
     * has no namespacing, so name them as you would name an option.
     *
     * @return array<string, Setting>
     */
    public static function settings(): array;
}
