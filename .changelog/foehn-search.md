### Added

- A new package, `studiometa/foehn-search`: a native search with typo tolerance and relevance, from Loupe and SQLite, with no licence and no external service. It needs the `pdo_sqlite` (SQLite 3.35 or later) and `intl` extensions, which the runtime image has
- `#[AsSearchDocument(postType: '…')]` makes the published posts of a post type searchable, through a class that turns a post into a document. `Document::fromPost()` gives the title, the excerpt, the content as plain text, the term names of the public taxonomies and the date
- The index follows each save, trash, delete and term change, through an `#[AsJob]` job, and `wp foehn-search reindex` rebuilds it. It lives in `storage/search` in the project root, outside the web root
- The search page gets every match from the index as `post__in`, in order of relevance, so taxonomy filters, pages and `Facets` counts work as on any archive. An empty search gives no result
- `SearchGroups` gives the results grouped by post type, with a "see all" URL for each
- `GET /wp-json/foehn-search/v1/suggestions?q=` gives the best five matches in the language of the page, with a short `Cache-Control`
- Each language has its own index, from the `Multilingual` contract

### Fixed

- `Facets` counts a `category` or `post_tag` facet with its own filter lifted: WordPress keeps the selected category in `cat` (and a tag in `tag_id`), which left every other category of the facet at 0
