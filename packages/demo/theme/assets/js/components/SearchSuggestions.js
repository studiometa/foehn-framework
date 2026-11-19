import { Base } from '@studiometa/js-toolkit';

/**
 * Suggestions under a search field, while a visitor types.
 *
 * The suggestions endpoint of studiometa/foehn-search gives the best matches of the
 * index, typos included, as `{ id, title, type, url }`. Each one is a link to its post.
 * The form still goes to the search page with Enter.
 *
 * The endpoint is an option, from `rest_url()` in `components/search-form.twig`, so a
 * site in a sub-directory asks the right URL.
 */
export class SearchSuggestions extends Base {
  static config = {
    name: 'SearchSuggestions',
    refs: ['input', 'list'],
    options: {
      endpoint: String,
      // Fewer characters give too many matches to be of use.
      minLength: { type: Number, default: 2 },
      delay: { type: Number, default: 150 },
    },
  };

  timer = null;

  controller = null;

  onInputInput() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.suggest(this.$refs.input.value.trim()), this.$options.delay);
  }

  onInputKeydown({ event }) {
    if (event.key === 'Escape') {
      this.render([]);
    }
  }

  async suggest(query) {
    // A new request cancels the one before it, so a slow answer never replaces the
    // suggestions of what the visitor typed since.
    this.controller?.abort();

    if (query.length < this.$options.minLength) {
      this.render([]);
      return;
    }

    this.controller = new AbortController();
    const url = new URL(this.$options.endpoint, window.location.href);
    url.searchParams.set('q', query);

    try {
      const response = await fetch(url, { signal: this.controller.signal });
      this.render(response.ok ? await response.json() : []);
    } catch {
      // Aborted, or offline: the field still searches with Enter.
    }
  }

  render(suggestions) {
    const { list } = this.$refs;

    list.replaceChildren(
      ...suggestions.map(({ title, type, url }) => {
        const item = document.createElement('li');
        const link = document.createElement('a');
        const label = document.createElement('span');

        link.className = 'search-form__suggestion';
        link.href = url;
        link.append(title);
        label.className = 'label';
        label.textContent = type;
        link.append(label);
        item.append(link);

        return item;
      }),
    );
    list.hidden = suggestions.length === 0;
  }

  destroyed() {
    clearTimeout(this.timer);
    this.controller?.abort();
  }
}
