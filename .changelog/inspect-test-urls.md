### Added

- `wp foehn inspect templates` gives each template a `url` to test it with: the newest published post of its type, the post type archive, the term with the most posts, the front page, the posts page, a search, a missing page, or the author or month of the newest post. A template that no such URL renders has `url: null` and a `noUrl` reason, such as `Its sample URLs render 'single-*' instead.` or, for a pattern like `tax-*`, `Føhn gives no request a template name like this.`. See [Test URLs](https://studiometa.github.io/foehn-framework/guide/cli-commands#test-urls)
