<?php

declare(strict_types=1);

namespace Demo\Hooks;

use Studiometa\Foehn\Assets\ViteManifest;
use Studiometa\Foehn\Attributes\AsAction;

/**
 * Put the Vite build on the page.
 *
 * `ViteManifest` reads whichever of the two things the vite plugin wrote: the
 * `hot` file while `npm run dev` runs, or `dist/.vite/manifest.json` after a build.
 * Nothing here has to know which.
 *
 * The entry names are the paths given to the plugin's `input` in vite.config.js,
 * which is what the manifest is keyed by.
 */
final class AssetHooks
{
    #[AsAction('wp_enqueue_scripts')]
    public function enqueue(): void
    {
        ViteManifest::fromTheme()
            ->enqueue('theme/assets/css/app.css', handle: 'demo-styles')
            ->enqueue('theme/assets/js/app.js', handle: 'demo-app', inFooter: true);
    }
}
