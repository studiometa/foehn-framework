import { test } from "node:test";
import assert from "node:assert/strict";
import { placeValues } from "../../assets/fill.js";

test("an address fills the coordinates, the street and the context, in the right fields", () => {
  const values = placeValues({
    center: [4.83571234, 45.76401234],
    place_type: ["address"],
    address: "1",
    text: "Rue de la République",
    context: [
      { id: "postcode.1", text: "69001" },
      { id: "place.2", text: "Lyon" },
      { id: "country.3", text: "France" },
    ],
  });

  assert.deepEqual(values, {
    latitude: "45.764012",
    longitude: "4.835712",
    street: "1 Rue de la République",
    postal_code: "69001",
    city: "Lyon",
    country: "France",
  });
});

test("a field the result does not give is absent, so it keeps its value", () => {
  const values = placeValues({ center: [4.8, 45.7], place_type: ["place"], id: "place.2", text: "Lyon" });

  assert.deepEqual(values, { latitude: "45.7", longitude: "4.8", city: "Lyon" });
});

test("no center gives no coordinates", () => {
  assert.deepEqual(placeValues({ place_type: ["country"], id: "country.1", text: "France" }), { country: "France" });
});
