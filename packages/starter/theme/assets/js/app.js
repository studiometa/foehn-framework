import { defineManifest, fromMetaGlob, registerManifests } from '@studiometa/js-toolkit';
import '@studiometa/ui/autoload';

/**
 * Components are discovered, not wired.
 *
 * Drop a class into `components/` and mark an element with
 * `data-component="TheClassName"` — the loader finds it and fetches the module only
 * for pages that use it. Nothing here has to be edited to add one.
 *
 * `@studiometa/ui/autoload` does the same for @studiometa/ui's components, as a
 * side effect of the import, so `data-component="Modal"` works with no import.
 */
const manifest = defineManifest({
  packageName: 'starter-theme',
  modules: fromMetaGlob(import.meta.glob('./components/*.js')),
});

registerManifests(manifest);
