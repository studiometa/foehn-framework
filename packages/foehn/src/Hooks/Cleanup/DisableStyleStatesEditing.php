<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Cleanup;

use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Turn off the responsive and state style editing of the block editor.
 *
 * WordPress 7.1 lets editors set block styles per viewport and per state
 * (`:hover`, `:focus`, ...). This class sets two editor settings to `false`:
 *
 * - `responsiveEditingEnabled`: removes the responsive styles toggle of the
 *   View menu and the Viewport group of the Global Styles States dropdown
 * - `blockStatesEditingEnabled`: removes the state dropdown of the block
 *   inspector and the pseudo-state options of Global Styles
 *
 * Styles that are already saved still render. Before WordPress 7.1 the
 * settings have no effect.
 */
final class DisableStyleStatesEditing
{
    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    #[AsFilter('block_editor_settings_all')]
    public function disableStyleStatesEditing(array $settings): array
    {
        $settings['responsiveEditingEnabled'] = false;
        $settings['blockStatesEditingEnabled'] = false;

        return $settings;
    }
}
