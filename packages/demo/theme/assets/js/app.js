import { defineManifest, fromMetaGlob, registerManifests } from '@studiometa/js-toolkit';
import '@studiometa/ui/autoload';

/**
 * Components are discovered, not wired.
 *
 * `import.meta.glob` hands Vite a lazy importer per file, `fromMetaGlob` normalises
 * that into the shape the loader wants, and `registerManifests` schedules the start.
 * The loader then mounts whatever `[data-component]` it finds in the markup and
 * fetches only those modules — so a component nobody uses on a page costs nothing.
 *
 * `@studiometa/ui/autoload` registers that package's own manifest the same way, as
 * a side effect of the import, which is why it takes no arguments and returns
 * nothing. Its components are then available by name with no import here.
 */
const manifest = defineManifest({
  packageName: 'demo-theme',
  modules: fromMetaGlob(import.meta.glob('./components/*.js')),
});

registerManifests(manifest);
