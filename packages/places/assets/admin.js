/**
 * The address search of the place meta box.
 *
 * A standalone `MapboxGeocoder` (no map) renders in its root element. Its result fills
 * the location and the address fields of the meta box. Nothing is saved: the post form
 * saves the fields, with every rule of the meta box.
 */
import "@mapbox/mapbox-gl-geocoder/dist/mapbox-gl-geocoder.css";
import { registerComponent } from "@studiometa/js-toolkit";
import { MapboxGeocoder } from "@studiometa/ui-mapbox/MapboxGeocoder";
import { fillPlaceFields, placeValues } from "./fill.js";

// `map-result` is dispatched on the geocoder element: listen in the capture phase,
// which reaches it whether the event bubbles or not.
document.addEventListener(
  "map-result",
  (event) => {
    const geocoder = event.target instanceof Element ? event.target.closest("[data-foehn-places-geocoder]") : null;

    if (geocoder) {
      fillPlaceFields(geocoder.closest(".postbox") ?? document, placeValues(event.detail?.result));
    }
  },
  true,
);

registerComponent(MapboxGeocoder);
