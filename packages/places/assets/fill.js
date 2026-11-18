/**
 * The values a Mapbox geocoder result gives the place fields.
 *
 * Coordinates come from `center` (`[lng, lat]`), rounded to six decimal places. The
 * street is the house number and the street name of an address. The postal code, the
 * city and the country come from the `postcode`, `place` and `country` entries of the
 * result context, or from the result itself when it is one of them. A field the result
 * does not provide is absent, so it keeps its current value.
 *
 * @param {object} result A Mapbox geocoding feature.
 * @returns {Record<string, string>}
 */
export function placeValues(result) {
  const values = {};
  const [lng, lat] = Array.isArray(result?.center) ? result.center : [];

  if (Number.isFinite(lat) && Number.isFinite(lng)) {
    values.latitude = String(Math.round(lat * 1e6) / 1e6);
    values.longitude = String(Math.round(lng * 1e6) / 1e6);
  }

  const types = Array.isArray(result?.place_type) ? result.place_type : [];

  if (types.includes("address") && result.text) {
    values.street = [result.address, result.text].filter(Boolean).join(" ");
  }

  const entries = [result, ...(Array.isArray(result?.context) ? result.context : [])];
  const fields = { postcode: "postal_code", place: "city", country: "country" };

  for (const [type, field] of Object.entries(fields)) {
    const entry = entries.find(
      (item) =>
        typeof item?.id === "string" && item.id.startsWith(`${type}.`) && typeof item.text === "string",
    );

    if (entry) {
      values[field] = entry.text;
    }
  }

  return values;
}

/**
 * Write values into the `foehn_places[key]` fields of a form, and tell the editor.
 *
 * @param {ParentNode} root The element that holds the fields.
 * @param {Record<string, string>} values
 */
export function fillPlaceFields(root, values) {
  for (const [key, value] of Object.entries(values)) {
    const field = root.querySelector(`[name="foehn_places[${key}]"]`);

    if (field) {
      field.value = value;
      field.dispatchEvent(new Event("input", { bubbles: true }));
      field.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }
}
