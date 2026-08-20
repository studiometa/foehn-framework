<?php

declare(strict_types=1);

use App\Hooks\AssetHooks;
use Studiometa\Foehn\Attributes\AsAction;

describe('AssetHooks', function () {
    it('enqueues on wp_enqueue_scripts', function () {
        $ref = new ReflectionMethod(AssetHooks::class, 'enqueue');
        $attrs = $ref->getAttributes(AsAction::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->hook)->toBe('wp_enqueue_scripts');
    });

    it('asks for the entries vite.config.js declares as inputs', function () {
        // The manifest is keyed by the input paths, so these two strings have to
        // match vite.config.js exactly. Nothing errors when they do not — the page
        // simply loads with no stylesheet and no script, which is how this theme
        // shipped before ViteManifest existed.
        $source = file_get_contents(__DIR__ . '/../../../../theme/app/Hooks/AssetHooks.php');
        $config = file_get_contents(__DIR__ . '/../../../../vite.config.js');

        foreach (['theme/assets/css/app.css', 'theme/assets/js/app.js'] as $entry) {
            expect($source)->toContain($entry);
            expect($config)->toContain($entry);
        }
    });

    it('builds into the theme, which is the only directory that gets served', function () {
        $config = file_get_contents(__DIR__ . '/../../../../vite.config.js');

        // web/wp-content/themes/<name> is a symlink to theme/. A build written to the
        // package root is never reachable from a browser.
        expect($config)->toContain("outDir: 'theme/dist'");
    });
});
