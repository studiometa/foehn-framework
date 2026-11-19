import { defineManifest, fromMetaGlob, registerManifest } from '@studiometa/js-toolkit';
import '@studiometa/ui/autoload';
import '@studiometa/ui-mapbox/autoload';
import 'mapbox-gl/dist/mapbox-gl.css';
import '@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css';

/**
 * Components are discovered, not wired.
 *
 * `import.meta.glob` hands Vite a lazy importer per file, `fromMetaGlob` normalises
 * that into the shape the loader wants, and `registerManifest` schedules the start.
 * The loader then mounts whatever `[data-component]` it finds in the markup and
 * fetches only those modules — so a component nobody uses on a page costs nothing.
 *
 * `@studiometa/ui/autoload` and `@studiometa/ui-mapbox/autoload` register their
 * packages' own manifests the same way, as a side effect of the import. Their
 * components are then available by name with no import here: `Fetch` and `Action`
 * on every archive, and the Mapbox components on the venue map, where `mapbox-gl` is
 * loaded only when a map mounts. The two stylesheets are small and static.
 */
const manifest = defineManifest({
  modules: fromMetaGlob(import.meta.glob('./components/*.js')),
});

registerManifest(manifest);
