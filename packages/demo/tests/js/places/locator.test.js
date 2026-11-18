import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { Base, registerComponent } from '@studiometa/js-toolkit';
import { Action, Fetch } from '@studiometa/ui';
import { MapboxClusterItem } from '@studiometa/ui-mapbox';

/**
 * The venue locator in a browser: the markup of the studiometa/foehn-places templates,
 * driven by the real `Fetch`, `Action` and `MapboxClusterItem` of the `next` release
 * line. The map and the cluster are stand-ins with the same names, because a real map
 * needs WebGL, a token and the network. What is under test is the integration the
 * package owns: the attributes, the section wrappers, the effects and the ids.
 */

const SECTIONS = 'place-filters,place-summary,place-results,place-pagination';
const ARCHIVE = new URL('/venues/', window.location.href).href;

/** Every MapboxMap the page built, so a test can say the map was never rebuilt. */
const maps = [];

/** Every MapboxCluster the page built. */
const clusters = [];

class FakeMapboxMap extends Base {
  static config = { name: 'MapboxMap' };

  map = {
    fitBounds: vi.fn(),
    getBounds: () => ({
      getWest: () => 4.7,
      getSouth: () => 45.6,
      getEast: () => 5,
      getNorth: () => 45.9,
    }),
  };

  mounted() {
    maps.push(this);
  }

  /** A move, as Mapbox reports it: `originalEvent` only when the visitor moved the map. */
  move(byVisitor) {
    this.$el.dispatchEvent(
      new CustomEvent('map-moveend', {
        detail: { event: byVisitor ? { originalEvent: new MouseEvent('mouseup') } : {} },
      }),
    );
  }
}

/** The cluster's registration contract, which MapboxClusterItem calls. */
class FakeMapboxCluster extends Base {
  static config = { name: 'MapboxCluster' };

  items = new Set();

  mounted() {
    clusters.push(this);
  }

  register(item) {
    this.items.add(item);
  }

  unregister(item) {
    this.items.delete(item);
  }

  /** What a click on a marker or a list row resolves to: the registered item of the id. */
  itemById(id) {
    return [...this.items].find((item) => item.id === String(id));
  }
}

function item(id, title, view) {
  const lngLat = JSON.stringify([4.8 + id / 100, 45.7]);
  const map = view === 'map' ? ` data-component="MapboxClusterItem" data-option-id="${id}" data-option-lng-lat="${lngLat}"` : '';

  return `<li id="place-${id}" class="places-list__item"${map}><article class="places-item"><h3><a href="/venues/v-${id}/">${title}</a></h3></article></li>`;
}

/** The four section wrappers, as a section request answers them. */
function sections({ places, view = 'list', query = '', bounds = null }) {
  const form = `<form id="place-search" action="${ARCHIVE}" method="get"
      data-component="Action Fetch" data-option-history data-option-mode="morph"
      data-option-selector="[data-foehn-section]"
      data-option-src="${ARCHIVE}?foehn_sections=${SECTIONS}"
      data-on:change="() => $el.requestSubmit()"
      data-on:fetch-update-after="MapboxMap(#place-map) -> () => {
        const bounds = document.querySelector('#foehn-section-place-results [data-bounds]')?.dataset.bounds;
        if (bounds) target.map.fitBounds(JSON.parse(bounds), { padding: 60, maxZoom: 12 });
      }">
      <input id="place-search-q" type="search" name="q" value="${query}">
      ${view === 'map' ? '<input type="hidden" name="view" value="map">' : ''}
      <input type="hidden" name="near" value="" disabled>
      <input type="hidden" name="bbox" value="" disabled>
      <label><input type="checkbox" name="place_type[]" value="gallery"> Gallery</label>
      <label><input type="checkbox" name="place_type[]" value="bookshop"> Bookshop</label>
      <button type="submit">Search</button>
    </form>`;
  const list = `<ul class="places-map-items">${places.map(([id, title]) => item(id, title, view)).join('')}</ul>`;
  const boundsAttribute = bounds ? ` data-bounds='${JSON.stringify(bounds)}'` : '';

  return [
    `<div id="foehn-section-place-filters" data-foehn-section="place-filters">${form}</div>`,
    `<div id="foehn-section-place-summary" data-foehn-section="place-summary"><p class="places-summary__total">${places.length} places</p></div>`,
    `<div id="foehn-section-place-results" data-foehn-section="place-results"><div class="places-results"${boundsAttribute}>${list}</div></div>`,
    `<div id="foehn-section-place-pagination" data-foehn-section="place-pagination"></div>`,
  ];
}

/** The full page: the sections, and in the map view the map shell around the results. */
function page(state) {
  const [filters, summary, results, pagination] = sections(state);
  const body =
    state.view === 'map'
      ? `<div id="place-map" data-component="MapboxMap Action" data-on:map-moveend="() => {
            if (event.detail.event.originalEvent) document.getElementById('place-search-area').hidden = false;
          }">
          <div data-component="MapboxCluster">${results}</div>
          <div data-ref="container"><canvas class="mapboxgl-canvas"></canvas></div>
          <button id="place-search-area" type="button" form="place-search" hidden data-component="Action"
            data-on:click="MapboxMap(#place-map) -> () => {
              const { form } = action.$el;
              const { near, radius, bbox } = form.elements;
              const bounds = target.map.getBounds();
              bbox.value = [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()].join(',');
              bbox.disabled = false;
              if (near) { near.value = ''; near.disabled = true; }
              if (radius) radius.disabled = true;
              action.$el.hidden = true;
              form.requestSubmit();
            }">Search this area</button>
        </div>`
      : results;

  return `<header id="site-header">Header</header>
    <div id="place-geocoder" data-component="Action" data-on:map-result="Fetch(#place-search) -> () => {
      const [lng, lat] = event.detail.result.center;
      const { near, bbox } = target.$el.elements;
      near.value = \`\${lat},\${lng}\`;
      near.disabled = false;
      if (bbox) { bbox.value = ''; bbox.disabled = true; }
      target.$el.requestSubmit();
    }"></div>
    ${summary}${filters}${body}${pagination}`;
}

/** Answer each request with a function of its URL, and keep the URLs. */
function serve(answer) {
  const requests = [];

  vi.spyOn(window, 'fetch').mockImplementation(async (input) => {
    const url = new URL(String(input), window.location.href);
    requests.push(url);

    return new Response(answer(url), { status: 200, headers: { 'content-type': 'text/html' } });
  });

  return requests;
}

async function mount(state) {
  document.body.innerHTML = page(state);
  await vi.waitFor(() => {
    expect(document.querySelector('#place-search').__base__ ?? document.querySelector('#place-search')).toBeTruthy();
  });
  // The registry mounts on the next frames.
  await new Promise((resolve) => setTimeout(resolve, 50));
}

function form() {
  return document.getElementById('place-search');
}

beforeAll(() => {
  for (const component of [Fetch, Action, MapboxClusterItem, FakeMapboxMap, FakeMapboxCluster]) {
    registerComponent(component);
  }
});

beforeEach(() => {
  maps.length = 0;
  clusters.length = 0;
  window.history.replaceState(null, '', ARCHIVE);
});

afterEach(async () => {
  vi.restoreAllMocks();
  document.body.innerHTML = '';
  await new Promise((resolve) => setTimeout(resolve, 20));
});

describe('Fetch on the place sections', () => {
  it('updates the four sections in one request and keeps a normal history URL', async () => {
    const requests = serve((url) => sections({ places: [[2, 'Lyon']], query: url.searchParams.get('q') ?? '' }).join(''));
    await mount({ places: [[1, 'Paris'], [2, 'Lyon']] });
    const header = document.getElementById('site-header');

    form().elements.q.value = 'lyon';
    form().requestSubmit();

    await vi.waitFor(() => expect(document.querySelector('.places-summary__total').textContent).toBe('1 places'));

    expect(requests).toHaveLength(1);
    expect(requests[0].searchParams.get('foehn_sections')).toBe(SECTIONS);
    expect(requests[0].searchParams.get('q')).toBe('lyon');
    expect(window.location.href).toBe(`${ARCHIVE}?q=lyon`);
    expect(document.querySelectorAll('#foehn-section-place-results li')).toHaveLength(1);
    // Nothing outside the sections is touched.
    expect(document.getElementById('site-header')).toBe(header);
  });

  it('folds every value of a repeated field onto the section URL', async () => {
    const requests = serve(() => sections({ places: [] }).join(''));
    await mount({ places: [] });

    for (const box of form().querySelectorAll('[name="place_type[]"]')) box.checked = true;
    form().requestSubmit();

    await vi.waitFor(() => expect(requests).toHaveLength(1));
    expect(requests[0].searchParams.getAll('place_type[]')).toEqual(['gallery', 'bookshop']);
    expect(requests[0].searchParams.get('foehn_sections')).toBe(SECTIONS);
    // An empty position control is disabled, so it is not submitted.
    expect(requests[0].searchParams.has('near')).toBe(false);
    expect(requests[0].searchParams.has('bbox')).toBe(false);
  });

  it('keeps the form node and the Fetch instance through a morph', async () => {
    serve(() => sections({ places: [[3, 'Arles']], query: 'arles' }).join(''));
    await mount({ places: [] });
    const before = form();

    before.elements.q.value = 'arles';
    before.requestSubmit();

    await vi.waitFor(() => expect(document.querySelectorAll('#foehn-section-place-results li')).toHaveLength(1));
    expect(form()).toBe(before);
  });
});

describe('The map across updates', () => {
  it('is never rebuilt by a Fetch update, and frames the new bounds', async () => {
    serve(() => sections({ view: 'map', places: [[2, 'Lyon']], bounds: [[4.8, 45.7], [4.9, 45.8]] }).join(''));
    await mount({ view: 'map', places: [[1, 'Paris'], [2, 'Lyon']] });
    const mapElement = document.getElementById('place-map');
    const canvas = mapElement.querySelector('canvas');

    expect(maps).toHaveLength(1);
    form().elements.q.value = 'lyon';
    form().requestSubmit();

    await vi.waitFor(() => expect(maps[0].map.fitBounds).toHaveBeenCalled());
    expect(maps[0].map.fitBounds).toHaveBeenCalledWith([[4.8, 45.7], [4.9, 45.8]], { padding: 60, maxZoom: 12 });
    expect(document.getElementById('place-map')).toBe(mapElement);
    expect(mapElement.querySelector('canvas')).toBe(canvas);
    expect(maps).toHaveLength(1);
  });

  it('is never rebuilt by a back and forward navigation, which updates the sections', async () => {
    const requests = serve((url) => {
      const lyon = url.searchParams.get('q') === 'lyon';
      const state = { view: 'map', places: lyon ? [[2, 'Lyon']] : [[1, 'Paris'], [2, 'Lyon']], query: lyon ? 'lyon' : '' };

      // A restore asks for the page URL: it answers the full page, as WordPress does.
      return url.searchParams.has('foehn_sections') ? sections(state).join('') : page(state);
    });
    window.history.replaceState(null, '', `${ARCHIVE}?view=map`);
    await mount({ view: 'map', places: [[1, 'Paris'], [2, 'Lyon']] });
    const mapElement = document.getElementById('place-map');
    const canvas = mapElement.querySelector('canvas');

    form().elements.q.value = 'lyon';
    form().requestSubmit();
    await vi.waitFor(() => expect(document.querySelectorAll('#foehn-section-place-results li')).toHaveLength(1));
    expect(window.location.href).toBe(`${ARCHIVE}?q=lyon&view=map`);

    window.history.back();
    await vi.waitFor(() => expect(document.querySelectorAll('#foehn-section-place-results li')).toHaveLength(2));
    expect(window.location.href).toBe(`${ARCHIVE}?view=map`);

    window.history.forward();
    await vi.waitFor(() => expect(document.querySelectorAll('#foehn-section-place-results li')).toHaveLength(1));

    expect(requests.length).toBeGreaterThanOrEqual(3);
    expect(document.getElementById('place-map')).toBe(mapElement);
    expect(mapElement.querySelector('canvas')).toBe(canvas);
    expect(maps).toHaveLength(1);
  });
});

describe('Selection after a morph', () => {
  it('keeps the node of a kept place, mounts an added one and drops a removed one', async () => {
    serve(() => sections({ view: 'map', places: [[2, 'Lyon'], [3, 'Arles']] }).join(''));
    await mount({ view: 'map', places: [[1, 'Paris'], [2, 'Lyon']] });
    const cluster = () => document.querySelector('[data-component="MapboxCluster"]');
    const kept = document.getElementById('place-2');

    await vi.waitFor(() => expect(clusterItems()).toEqual(['1', '2']));

    form().requestSubmit();

    await vi.waitFor(() => expect(clusterItems()).toEqual(['2', '3']));
    expect(document.getElementById('place-2')).toBe(kept);
    expect(document.getElementById('place-1')).toBeNull();
    expect(cluster()).not.toBeNull();

    function clusterItems() {
      return [...document.querySelectorAll('[data-component="MapboxClusterItem"]')]
        .filter((element) => registeredIds().includes(element.dataset.optionId))
        .map((element) => element.dataset.optionId)
        .sort();
    }
  });
});

/** The ids the cluster stand-in holds, read from the item instances it registered. */
function registeredIds() {
  return clusters.flatMap((cluster) => [...cluster.items].map((instance) => instance.id));
}

describe('Position changes', () => {
  it('turns a geocoder result into a radius search', async () => {
    const requests = serve(() => sections({ places: [[2, 'Lyon']] }).join(''));
    await mount({ places: [[1, 'Paris'], [2, 'Lyon']] });

    document.getElementById('place-geocoder').dispatchEvent(
      new CustomEvent('map-result', { detail: { result: { center: [4.8357, 45.764] } } }),
    );

    await vi.waitFor(() => expect(requests).toHaveLength(1));
    expect(requests[0].searchParams.get('near')).toBe('45.764,4.8357');
    expect(requests[0].searchParams.has('bbox')).toBe(false);
    await vi.waitFor(() => expect(new URL(window.location.href).searchParams.get('near')).toBe('45.764,4.8357'));
    expect(window.location.href).not.toContain('foehn_sections');
  });

  it('shows Search this area after a move by the visitor only', async () => {
    serve(() => sections({ view: 'map', places: [] }).join(''));
    await mount({ view: 'map', places: [] });
    const button = document.getElementById('place-search-area');

    maps[0].move(false);
    expect(button.hidden).toBe(true);

    maps[0].move(true);
    await vi.waitFor(() => expect(button.hidden).toBe(false));
  });

  it('submits the visible area as bbox and turns near off', async () => {
    const requests = serve(() => sections({ view: 'map', places: [] }).join(''));
    await mount({ view: 'map', places: [] });
    const button = document.getElementById('place-search-area');
    form().elements.near.value = '45.764,4.8357';
    form().elements.near.disabled = false;

    maps[0].move(true);
    await vi.waitFor(() => expect(button.hidden).toBe(false));
    button.click();

    await vi.waitFor(() => expect(requests).toHaveLength(1));
    expect(requests[0].searchParams.get('bbox')).toBe('4.7,45.6,5,45.9');
    expect(requests[0].searchParams.has('near')).toBe(false);
    expect(button.hidden).toBe(true);
  });
});
