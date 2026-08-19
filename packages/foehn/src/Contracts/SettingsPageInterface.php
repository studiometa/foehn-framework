<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use Studiometa\Foehn\Settings\Setting;

/**
 * What a settings page stores.
 *
 * Implemented alongside #[AsSettingsPage]. How the form looks is a separate
 * question, answered either by a Twig template named on the attribute or by
 * SettingsFormInterface.
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
