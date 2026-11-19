import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SearchSuggestions } from '../../../theme/assets/js/components/SearchSuggestions.js';

function createSearchForm() {
  const el = document.createElement('form');
  el.setAttribute('data-component', 'SearchSuggestions');
  el.setAttribute('data-option-endpoint', 'https://foehn.test/wp-json/foehn-search/v1/suggestions');
  el.setAttribute('data-option-delay', '0');
  el.innerHTML = '<input data-ref="input" type="search" name="s" /><ul data-ref="list" hidden></ul>';
  document.body.appendChild(el);
  return el;
}

function type(el, value) {
  const input = el.querySelector('input');
  input.value = value;
  input.dispatchEvent(new Event('input', { bubbles: true }));
}

function respond(suggestions) {
  return vi.spyOn(window, 'fetch').mockResolvedValue(new Response(JSON.stringify(suggestions)));
}

describe('SearchSuggestions', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('lists the suggestions of the endpoint as links', async () => {
    const fetch = respond([
      { id: 1, title: 'Corridors', type: 'project', url: 'https://foehn.test/projects/corridors/' },
      { id: 2, title: 'Notes on Corridors', type: 'post', url: 'https://foehn.test/notes-on-corridors/' },
    ]);
    const el = createSearchForm();
    const component = new SearchSuggestions(el);
    await component.$mount();

    type(el, 'coridors');
    const list = el.querySelector('ul');
    await vi.waitFor(() => expect(list.hidden).toBe(false));

    expect(String(fetch.mock.calls[0][0])).toBe(
      'https://foehn.test/wp-json/foehn-search/v1/suggestions?q=coridors',
    );
    expect([...list.querySelectorAll('a')].map((link) => link.getAttribute('href'))).toEqual([
      'https://foehn.test/projects/corridors/',
      'https://foehn.test/notes-on-corridors/',
    ]);
    expect(list.querySelector('a').textContent).toBe('Corridorsproject');

    component.$unmount();
  });

  it('asks nothing for a query shorter than two characters', async () => {
    const fetch = respond([]);
    const el = createSearchForm();
    const component = new SearchSuggestions(el);
    await component.$mount();

    type(el, 'c');
    await new Promise((resolve) => setTimeout(resolve, 20));

    expect(fetch).not.toHaveBeenCalled();
    expect(el.querySelector('ul').hidden).toBe(true);

    component.$unmount();
  });

  it('prints a title as text, never as markup', async () => {
    respond([{ id: 1, title: '<img src=x onerror=alert(1)>', type: 'post', url: 'https://foehn.test/x/' }]);
    const el = createSearchForm();
    const component = new SearchSuggestions(el);
    await component.$mount();

    type(el, 'img');
    await vi.waitFor(() => expect(el.querySelector('ul').hidden).toBe(false));

    expect(el.querySelector('ul img')).toBeNull();

    component.$unmount();
  });

  it('hides the suggestions with Escape', async () => {
    respond([{ id: 1, title: 'Corridors', type: 'project', url: 'https://foehn.test/projects/corridors/' }]);
    const el = createSearchForm();
    const component = new SearchSuggestions(el);
    await component.$mount();

    type(el, 'corridors');
    const list = el.querySelector('ul');
    await vi.waitFor(() => expect(list.hidden).toBe(false));

    el.querySelector('input').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

    expect(list.hidden).toBe(true);

    component.$unmount();
  });
});
