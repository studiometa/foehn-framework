<?php

declare(strict_types=1);

namespace App\Hooks;

use Studiometa\Foehn\Assets\ViteManifest;
use Studiometa\Foehn\Attributes\AsAction;

/**
 * Put the Vite build on the page.
 *
 * `ViteManifest` reads whichever of the two things the vite plugin wrote: the
 * `hot` file while `npm run dev` runs, or `theme/dist/.vite/manifest.json` after
 * `npm run build`. Nothing here has to know which, and a project with neither
 * serves pages without assets rather than failing.
 *
 * The entry names are the paths given to `input` in vite.config.js, because those
 * are the keys Vite writes into the manifest — relative to the project root, not
 * to the theme.
 */
final class AssetHooks
{
    #[AsAction('wp_enqueue_scripts')]
    public function enqueue(): void
    {
        ViteManifest::fromTheme()
            ->enqueue('theme/assets/css/app.css', handle: 'starter-styles')
            ->enqueue('theme/assets/js/app.js', handle: 'starter-app', inFooter: true);
    }

    /**
     * Put the theme styles and scripts in the block editor canvas, so blocks look
     * and behave as they do on the site. WordPress loads what this action enqueues
     * inside the editor iframe, so the assets do not reach the admin interface
     * around it. The components mount again by themselves when a block renders again.
     */
    #[AsAction('enqueue_block_assets')]
    public function enqueueEditor(): void
    {
        if (!is_admin()) {
            return;
        }

        ViteManifest::fromTheme()
            ->enqueue('theme/assets/css/app.css', handle: 'starter-styles')
            ->enqueue('theme/assets/js/app.js', handle: 'starter-app', inFooter: true);
    }
}
