import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { Base, registerComponent } from '@studiometa/js-toolkit';
import { Action, Fetch } from '@studiometa/ui';
import {
  MAPBOX_MAP_CONNECTED,
  MapboxClusterItem,
  StoreLocator,
  provideMapboxGl,
} from '@studiometa/ui-mapbox';

/**
 * The venue locator in a browser, driven by the real `Fetch`, `Action`, `StoreLocator`
 * and `MapboxClusterItem` of the `next` release line.
 *
 * The markup is the demo venue page as the PHP suite renders it from the shipped
 * templates: `fixtures/*.html` are written by the "Browser fixtures" test of
 * packages/places/tests/Unit/TemplatesTest.php, which fails when a template change was
 * not rendered again. Only the map, the cluster and the `mapbox-gl` module are stand-ins,
 * because a real map needs WebGL, a token and the network.
 */

/** @type {Record<string, string>} */
const FIXTURES = Object.fromEntries(
  Object.entries(
    import.meta.glob('./fixtures/*.html', { query: '?raw', import: 'default', eager: true }),
  ).map(([path, html]) => [path.replace(/^.*\/(.+)\.html$/, '$1'), html]),
);

const SECTIONS = 'place-filters,place-summary,place-results,place-pagination';
const ARCHIVE = new URL('/venues/', window.location.href).href;

/** Every MapboxMap the page built, so a test can say the map was never rebuilt. */
const maps = [];

/** Every MapboxCluster the page built. */
const clusters = [];

/** Every popup StoreLocator opened. */
const popups = [];

class FakeMapboxMap extends Base {
  static config = { name: 'MapboxMap' };

  isLoaded = true;

  map = {
    fitBounds: vi.fn(),
    flyTo: vi.fn(),
    on: () => {},
    off: () => {},
    getCenter: () => ({ distanceTo: (lngLat) => Math.hypot(lngLat.lng - 4.8, lngLat.lat - 45.7) }),
    getBounds: () => ({
      getWest: () => 4.7,
      getSouth: () => 45.6,
      getEast: () => 5,
      getNorth: () => 45.9,
      contains: () => true,
    }),
  };

  mounted() {
    maps.push(this);
    document.dispatchEvent(new CustomEvent(MAPBOX_MAP_CONNECTED, { detail: this }));
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

/** The registry contract of MapboxCluster, which MapboxClusterItem and StoreLocator use. */
class FakeMapboxCluster extends Base {
  static config = { name: 'MapboxCluster' };

  registry = [];

  get items() {
    return [...this.registry];
  }

  mounted() {
    clusters.push(this);
  }

  register(item) {
    if (!this.registry.includes(item)) {
      this.registry.push(item);
      this.scheduleUpdate();
    }
  }

  unregister(item) {
    this.registry = this.registry.filter((candidate) => candidate !== item);
    this.scheduleUpdate();
  }

  /** One `map-update` for a batch of registrations, 100 ms after the last, as MapboxCluster does. */
  scheduleUpdate() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.$emit('map-update', { items: this.items }), 100);
  }

  /** A click on the marker of an item, as MapboxCluster reports it. */
  clickMarker(id) {
    this.$emit('map-item-click', { item: this.registry.find((item) => item.id === String(id)) });
  }
}

class FakePopup {
  setLngLat(lngLat) {
    this.lngLat = lngLat;
    return this;
  }

  setHTML(html) {
    this.html = html;
    return this;
  }

  addTo() {
    popups.push(this);
    return this;
  }

  remove() {}
}

class FakeLngLat {
  constructor(lng, lat) {
    this.lng = lng;
    this.lat = lat;
  }
}

/** Answer each request with the fixture a function of its URL names, and keep the URLs. */
function serve(answer) {
  const requests = [];

  vi.spyOn(window, 'fetch').mockImplementation(async (input) => {
    const url = new URL(String(input), window.location.href);
    requests.push(url);

    return new Response(FIXTURES[answer(url)], {
      status: 200,
      headers: { 'content-type': 'text/html' },
    });
  });

  return requests;
}

/** Load a fixture at a URL, as a first page load does. */
async function load(fixture, url = ARCHIVE) {
  window.history.replaceState(null, '', url);
  document.body.innerHTML = FIXTURES[fixture];
  // The registry mounts on the next frames.
  await new Promise((resolve) => setTimeout(resolve, 100));

  // On the map page, StoreLocator has wired the map and the cluster once it has marked
  // the items in view.
  if (
    document.querySelector('[data-component="StoreLocator"] [data-component="MapboxClusterItem"]')
  ) {
    await vi.waitFor(() => expect(document.querySelector('[data-in-bounds]')).not.toBeNull());
  }
}

function form() {
  return document.getElementById('place-search');
}

/** The ids of the places the results section lists, in document order. */
function listed() {
  return [...document.querySelectorAll('#foehn-section-place-results li[id^="place-"]')].map(
    (element) => element.id.replace('place-', ''),
  );
}

/** The ids the cluster stand-in holds, read from the item instances it registered. */
function registered() {
  return clusters.flatMap((cluster) => cluster.items.map((item) => item.id)).sort();
}

beforeAll(() => {
  provideMapboxGl({ Popup: FakePopup, LngLat: FakeLngLat });

  for (const component of [
    Fetch,
    Action,
    StoreLocator,
    MapboxClusterItem,
    FakeMapboxMap,
    FakeMapboxCluster,
  ]) {
    registerComponent(component);
  }
});

beforeEach(() => {
  maps.length = 0;
  clusters.length = 0;
  popups.length = 0;
});

afterEach(async () => {
  vi.restoreAllMocks();
  document.body.innerHTML = '';
  await new Promise((resolve) => setTimeout(resolve, 20));
});

describe('Fetch on the place sections', () => {
  it('updates the four sections in one request and keeps a normal history URL', async () => {
    const requests = serve(() => 'list-lyon');
    await load('list-page-1');
    const geocoder = document.querySelector('.places-geocoder__control');

    form().elements.q.value = 'lyon';
    form().requestSubmit();

    await vi.waitFor(() => expect(listed()).toEqual(['2']));

    expect(requests).toHaveLength(1);
    expect(requests[0].pathname).toBe('/venues/');
    expect(requests[0].searchParams.get('foehn_sections')).toBe(SECTIONS);
    expect(requests[0].searchParams.get('q')).toBe('lyon');
    expect(window.location.href).toBe(`${ARCHIVE}?q=lyon`);
    expect(document.querySelector('.places-summary__total').textContent.trim()).toBe('1 place');
    // Nothing outside the sections is touched.
    expect(document.querySelector('.places-geocoder__control')).toBe(geocoder);
  });

  it('folds every value of a repeated field onto the section request', async () => {
    const requests = serve(() => 'list-lyon');
    await load('list-page-1');

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
    serve(() => 'list-lyon');
    await load('list-page-1');
    const before = form();

    before.elements.q.value = 'lyon';
    before.requestSubmit();

    await vi.waitFor(() => expect(listed()).toEqual(['2']));
    expect(form()).toBe(before);
  });
});

describe('Pagination and history', () => {
  const byPage = (url) => (url.searchParams.get('paged') === '2' ? 'list-page-2' : 'list-page-1');

  it('requests the page of the link, then the restored page on back and forward', async () => {
    const requests = serve(byPage);
    await load('list-page-1');

    expect(listed()).toEqual(['1', '2']);
    document.querySelector('.places-pagination a[href="/venues/?paged=2"]').click();

    await vi.waitFor(() => expect(listed()).toEqual(['3']));
    expect(requests[0].pathname).toBe('/venues/');
    expect(requests[0].searchParams.get('paged')).toBe('2');
    expect(requests[0].searchParams.get('foehn_sections')).toBe(SECTIONS);
    expect(window.location.href).toBe(`${ARCHIVE}?paged=2`);

    window.history.back();

    await vi.waitFor(() => expect(listed()).toEqual(['1', '2']));
    expect(window.location.href).toBe(ARCHIVE);
    expect(requests[1].pathname).toBe('/venues/');
    expect(requests[1].searchParams.has('paged')).toBe(false);
    expect(requests[1].searchParams.get('foehn_sections')).toBe(SECTIONS);

    window.history.forward();

    await vi.waitFor(() => expect(listed()).toEqual(['3']));
    expect(requests[2].searchParams.get('paged')).toBe('2');
  });

  it('restores a page that was loaded directly, after a search from it', async () => {
    const requests = serve((url) => (url.searchParams.has('q') ? 'list-lyon' : byPage(url)));
    await load('list-page-2', `${ARCHIVE}?paged=2`);

    form().elements.q.value = 'lyon';
    form().requestSubmit();
    await vi.waitFor(() => expect(listed()).toEqual(['2']));

    window.history.back();

    await vi.waitFor(() => expect(listed()).toEqual(['3']));
    expect(window.location.href).toBe(`${ARCHIVE}?paged=2`);
    expect(requests[1].searchParams.get('paged')).toBe('2');
    expect(requests[1].searchParams.get('foehn_sections')).toBe(SECTIONS);
  });
});

describe('The map across updates', () => {
  it('is never rebuilt by a Fetch update, and frames the new bounds', async () => {
    serve(() => 'map-south');
    await load('map', `${ARCHIVE}?view=map`);
    const mapElement = document.getElementById('place-map');
    const container = mapElement.querySelector('[data-ref="container"]');

    expect(maps).toHaveLength(1);
    form().elements.q.value = 'south';
    form().requestSubmit();

    await vi.waitFor(() =>
      expect(maps[0].map.fitBounds).toHaveBeenCalledWith(
        [
          [4.6278, 43.6767],
          [4.8357, 45.764],
        ],
        { padding: 60, maxZoom: 12 },
      ),
    );
    expect(document.getElementById('place-map')).toBe(mapElement);
    expect(mapElement.querySelector('[data-ref="container"]')).toBe(container);
    expect(maps).toHaveLength(1);
  });

  it('is never rebuilt by a back and forward navigation, which updates the sections', async () => {
    const requests = serve((url) => (url.searchParams.get('q') === 'south' ? 'map-south' : 'map'));
    await load('map', `${ARCHIVE}?view=map`);
    const mapElement = document.getElementById('place-map');

    form().elements.q.value = 'south';
    form().requestSubmit();
    await vi.waitFor(() => expect(listed().sort()).toEqual(['2', '3']));
    expect(window.location.href).toBe(`${ARCHIVE}?q=south&view=map`);

    window.history.back();
    await vi.waitFor(() => expect(listed().sort()).toEqual(['1', '2']));
    expect(window.location.href).toBe(`${ARCHIVE}?view=map`);

    window.history.forward();
    await vi.waitFor(() => expect(listed().sort()).toEqual(['2', '3']));

    expect(requests).toHaveLength(3);
    expect(requests.every((url) => url.searchParams.get('foehn_sections') === SECTIONS)).toBe(true);
    expect(document.getElementById('place-map')).toBe(mapElement);
    expect(maps).toHaveLength(1);
  });
});

describe('Selection after a morph', () => {
  it('selects kept and added places from the list and from the map', async () => {
    serve(() => 'map-south');
    await load('map', `${ARCHIVE}?view=map`);
    const locator = document.querySelector('[data-component="StoreLocator"]');
    const selected = [];
    locator.addEventListener('map-select', (event) => selected.push(event.detail.item.id));
    const row = (id) => document.getElementById(`place-${id}`);
    const active = () =>
      [...document.querySelectorAll('[data-component="MapboxClusterItem"][data-active]')].map(
        (element) => element.id,
      );

    await vi.waitFor(() => expect(registered()).toEqual(['1', '2']));

    row(2).querySelector('.places-item__title').click();
    await vi.waitFor(() => expect(selected).toEqual(['2']));
    expect(row(2).getAttribute('aria-current')).toBe('true');
    expect(maps[0].map.flyTo).toHaveBeenLastCalledWith({ center: [4.8357, 45.764], zoom: 14 });
    expect(popups.at(-1).html).toContain('Lyon');

    const kept = row(2);
    form().elements.q.value = 'south';
    form().requestSubmit();

    // Paris is removed, Lyon is kept, Arles is added.
    await vi.waitFor(() => expect(registered()).toEqual(['2', '3']));
    expect(row(1)).toBeNull();
    expect(row(2)).toBe(kept);

    row(3).querySelector('.places-item__title').click();
    await vi.waitFor(() => expect(selected).toEqual(['2', '3']));
    expect(active()).toEqual(['place-3']);
    expect(row(3).getAttribute('aria-current')).toBe('true');
    expect(row(2).hasAttribute('aria-current')).toBe(false);
    expect(popups.at(-1).html).toContain('Arles');

    row(2).querySelector('.places-item__title').click();
    await vi.waitFor(() => expect(selected).toEqual(['2', '3', '2']));
    expect(active()).toEqual(['place-2']);

    clusters[0].clickMarker(3);
    await vi.waitFor(() => expect(selected).toEqual(['2', '3', '2', '3']));
    expect(active()).toEqual(['place-3']);
    expect(row(3).getAttribute('aria-current')).toBe('true');
  });
});

describe('Position changes', () => {
  it('turns a geocoder result into a radius search', async () => {
    const requests = serve(() => 'list-lyon');
    await load('list-page-1');

    document
      .querySelector('.places-geocoder__control')
      .dispatchEvent(
        new CustomEvent('map-result', { detail: { result: { center: [4.8357, 45.764] } } }),
      );

    await vi.waitFor(() => expect(requests).toHaveLength(1));
    expect(requests[0].searchParams.get('near')).toBe('45.764,4.8357');
    expect(requests[0].searchParams.has('bbox')).toBe(false);
    await vi.waitFor(() =>
      expect(new URL(window.location.href).searchParams.get('near')).toBe('45.764,4.8357'),
    );
    expect(window.location.href).not.toContain('foehn_sections');
  });

  it('shows Search this area after a move by the visitor only', async () => {
    serve(() => 'map');
    await load('map', `${ARCHIVE}?view=map`);
    const button = document.getElementById('place-search-area');

    maps[0].move(false);
    expect(button.hidden).toBe(true);

    maps[0].move(true);
    await vi.waitFor(() => expect(button.hidden).toBe(false));
  });

  it('submits the visible area as bbox and turns near off', async () => {
    const requests = serve(() => 'map');
    await load('map', `${ARCHIVE}?view=map`);
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
